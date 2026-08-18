import json
import logging
import time
import urllib.error
import urllib.request

import sentry_sdk

import config
import db
import queue_stream
from aggregate import aggregate_detections
from providers import get_provider

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
logger = logging.getLogger("analysis-worker")

if config.SENTRY_DSN:
    sentry_sdk.init(dsn=config.SENTRY_DSN)


def normalize_label(name: str) -> str:
    return name.strip().lower().replace(" ", "_").replace("/", "_")


def filter_to_selected_objects(detections, analysis_config):
    """When the user chose 'specific objects' for object detection, drop
    everything Rekognition found that isn't one of their selected objects."""
    object_detection_config = (analysis_config or {}).get("object_detection") or {}
    if object_detection_config.get("mode") != "specific":
        return detections

    selected = set(object_detection_config.get("objects") or [])
    if not selected:
        return detections

    return [d for d in detections if normalize_label(d.label) in selected]


def filter_to_threat_labels(detections):
    """Physical weapon/hazard objects from the label-detection call, filtered
    to config.THREAT_LABELS."""
    return [d for d in detections if normalize_label(d.label) in config.THREAT_LABELS]


def filter_to_threat_moderation_labels(detections):
    """Violence categories from the content-moderation call, filtered to
    config.THREAT_MODERATION_LABELS - catches unarmed violence (e.g. a
    physical fight) that label detection alone can't see."""
    return [d for d in detections if normalize_label(d.label) in config.THREAT_MODERATION_LABELS]


def run_analysis(provider, job):
    video_path = f"{config.STORAGE_ROOT}/{job['video_path']}"

    if job["type"] == "object_detection":
        detections = provider.detect_objects(video_path)
        analysis_config = json.loads(job["video_analysis_config"]) if job.get("video_analysis_config") else {}
        return filter_to_selected_objects(detections, analysis_config)

    if job["type"] == "threat_detection":
        weapon_detections = filter_to_threat_labels(provider.detect_objects(video_path))
        violence_detections = filter_to_threat_moderation_labels(provider.moderate_content(video_path))
        return weapon_detections + violence_detections

    if job["type"] == "content_moderation":
        return provider.moderate_content(video_path)

    raise ValueError(f"Unsupported analysis type: {job['type']}")


def notify_insights_ready(video_id):
    """Ask Laravel to generate AI insights now that every analysis type for
    this video has completed. Best-effort: the analysis job itself already
    succeeded, so a failure here is just logged/reported, not retried."""
    url = f"{config.LARAVEL_INTERNAL_URL}/api/internal/videos/{video_id}/insights"
    request = urllib.request.Request(
        url, method="POST", headers={"X-Internal-Token": config.INTERNAL_API_TOKEN}
    )

    try:
        with urllib.request.urlopen(request, timeout=60) as response:
            response.read()
    except Exception as error:
        logger.exception("video %s: failed to trigger insight generation", video_id)
        sentry_sdk.capture_exception(error)


def process(conn, provider, job_id):
    job = db.get_job(conn, job_id)
    if job is None:
        logger.warning("job %s no longer exists, skipping", job_id)
        return

    if job["status"] in ("completed", "failed"):
        logger.info("job %s already terminal (%s), skipping stale message", job_id, job["status"])
        return

    attempts = job["attempts"]
    while attempts < config.MAX_ATTEMPTS:
        attempts += 1
        db.mark_processing(conn, job_id, attempts)
        logger.info("job %s: attempt %s/%s (%s)", job_id, attempts, config.MAX_ATTEMPTS, job["type"])

        try:
            detections = run_analysis(provider, job)
            rows = aggregate_detections(detections)
            db.save_results(conn, job_id, rows)
            db.mark_completed(conn, job_id)
            if db.recompute_video_status(conn, job["video_id"]):
                notify_insights_ready(job["video_id"])
            logger.info("job %s: completed with %s labels", job_id, len(rows))
            return
        except Exception as error:
            logger.exception("job %s: attempt %s failed", job_id, attempts)
            sentry_sdk.capture_exception(error)
            db.mark_error(conn, job_id, str(error))

            if attempts >= config.MAX_ATTEMPTS:
                db.mark_failed(conn, job_id)
                db.recompute_video_status(conn, job["video_id"])
                logger.error("job %s: exhausted retries, marked failed", job_id)
                return

            time.sleep(config.RETRY_BACKOFF_SECONDS[attempts - 1])


def main():
    redis_client = queue_stream.connect()
    queue_stream.ensure_group(redis_client)
    conn = db.connect()
    provider = get_provider(config.ANALYSIS_PROVIDER)

    logger.info(
        "analysis worker started (provider=%s, consumer=%s)",
        config.ANALYSIS_PROVIDER,
        config.CONSUMER_NAME,
    )

    while True:
        for message_id, fields in queue_stream.reclaim_stale(redis_client):
            process(conn, provider, int(fields["job_id"]))
            queue_stream.ack(redis_client, message_id)

        message = queue_stream.read_one(redis_client)
        if message:
            message_id, fields = message
            process(conn, provider, int(fields["job_id"]))
            queue_stream.ack(redis_client, message_id)


if __name__ == "__main__":
    main()
