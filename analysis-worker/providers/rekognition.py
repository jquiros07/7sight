import logging
import os
import time
import uuid

import boto3

import config
from providers.base import AnalysisProvider, Detection

logger = logging.getLogger("analysis-worker")

POLL_INTERVAL_SECONDS = 5


class RekognitionProvider(AnalysisProvider):
    """Uses Amazon Rekognition Video's async label-detection and
    content-moderation APIs, which read the video directly from S3 and
    sample frames internally — no local frame extraction needed.
    """

    def __init__(self):
        self._s3 = boto3.client("s3", region_name=config.AWS_DEFAULT_REGION)
        self._rekognition = boto3.client("rekognition", region_name=config.AWS_DEFAULT_REGION)

    def detect_objects(self, video_path: str) -> list[Detection]:
        return self._run(
            video_path,
            self._rekognition.start_label_detection,
            self._collect_labels,
            MinConfidence=config.REKOGNITION_MIN_CONFIDENCE,
        )

    def moderate_content(self, video_path: str) -> list[Detection]:
        return self._run(video_path, self._rekognition.start_content_moderation, self._collect_moderation_labels)

    def detect_text(self, video_path: str) -> list[Detection]:
        return self._run(
            video_path,
            self._rekognition.start_text_detection,
            self._collect_text,
            Filters={"WordFilter": {"MinConfidence": config.REKOGNITION_MIN_CONFIDENCE}},
        )

    def _run(self, video_path, start_fn, collect_fn, **start_kwargs) -> list[Detection]:
        s3_key = self._upload_scratch_copy(video_path)
        try:
            logger.info("rekognition: starting %s (s3_key=%s)", start_fn.__name__, s3_key)
            job_id = start_fn(
                Video={"S3Object": {"Bucket": config.AWS_BUCKET, "Name": s3_key}}, **start_kwargs
            )["JobId"]
            logger.info("rekognition: %s job started (job_id=%s)", start_fn.__name__, job_id)
            return collect_fn(job_id)
        finally:
            logger.info("rekognition: deleting scratch copy (s3_key=%s)", s3_key)
            self._s3.delete_object(Bucket=config.AWS_BUCKET, Key=s3_key)

    def _upload_scratch_copy(self, video_path: str) -> str:
        key = f"analysis-tmp/{uuid.uuid4()}/{os.path.basename(video_path)}"
        logger.info("rekognition: uploading scratch copy to s3 (video_path=%s, key=%s)", video_path, key)
        self._s3.upload_file(video_path, config.AWS_BUCKET, key)
        logger.info("rekognition: scratch copy uploaded (key=%s)", key)
        return key

    def _collect_labels(self, job_id: str) -> list[Detection]:
        items = self._poll(self._rekognition.get_label_detection, job_id, "Labels")
        return [
            Detection(
                label=item["Label"]["Name"],
                confidence=item["Label"]["Confidence"],
                timestamp_seconds=item["Timestamp"] / 1000,
            )
            for item in items
        ]

    def _collect_moderation_labels(self, job_id: str) -> list[Detection]:
        items = self._poll(self._rekognition.get_content_moderation, job_id, "ModerationLabels")
        return [
            Detection(
                label=item["ModerationLabel"]["Name"],
                confidence=item["ModerationLabel"]["Confidence"],
                timestamp_seconds=item["Timestamp"] / 1000,
            )
            for item in items
        ]

    def _collect_text(self, job_id: str) -> list[Detection]:
        # Rekognition returns both LINE and WORD-level detections for the same
        # text - WORD is just a LINE broken into its individual words, so
        # keeping both would double-count everything. LINE alone is the
        # readable, deduplicated result.
        items = self._poll(self._rekognition.get_text_detection, job_id, "TextDetections")
        return [
            Detection(
                label=item["TextDetection"]["DetectedText"],
                confidence=item["TextDetection"]["Confidence"],
                timestamp_seconds=item["Timestamp"] / 1000,
            )
            for item in items
            if item["TextDetection"]["Type"] == "LINE"
        ]

    def _poll(self, get_fn, job_id: str, result_key: str) -> list[dict]:
        """Poll a Rekognition Video Get* endpoint until it finishes, then walk
        every page of results."""
        last_status = None
        polls = 0
        while True:
            response = get_fn(JobId=job_id)
            polls += 1
            if response["JobStatus"] != last_status:
                logger.info("rekognition: job %s status=%s (poll #%s)", job_id, response["JobStatus"], polls)
                last_status = response["JobStatus"]
            if response["JobStatus"] == "SUCCEEDED":
                break
            if response["JobStatus"] == "FAILED":
                raise RuntimeError(response.get("StatusMessage", "Rekognition job failed"))
            time.sleep(POLL_INTERVAL_SECONDS)

        items = list(response[result_key])
        next_token = response.get("NextToken")
        pages = 1
        while next_token:
            response = get_fn(JobId=job_id, NextToken=next_token)
            items.extend(response[result_key])
            next_token = response.get("NextToken")
            pages += 1

        logger.info("rekognition: job %s collected %s items across %s page(s)", job_id, len(items), pages)

        return items
