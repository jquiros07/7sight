import json
import logging
import time
import urllib.error
import urllib.request
from concurrent.futures import ThreadPoolExecutor

import sentry_sdk

import config
import db
import queue_stream
from aggregate import aggregate_detections
from providers import get_provider

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
logger = logging.getLogger("analysis-worker")

if config.SENTRY_DSN:
    sentry_sdk.init(dsn=config.SENTRY_DSN, traces_sample_rate=config.SENTRY_TRACES_SAMPLE_RATE)


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
        # The two Rekognition calls are independent async jobs - run them
        # concurrently instead of waiting for one to finish before starting
        # the other, since each one's own poll loop is mostly idle waiting.
        with ThreadPoolExecutor(max_workers=2) as executor:
            labels_future = executor.submit(provider.detect_objects, video_path)
            moderation_future = executor.submit(provider.moderate_content, video_path)
            weapon_detections = filter_to_threat_labels(labels_future.result())
            violence_detections = filter_to_threat_moderation_labels(moderation_future.result())
        return weapon_detections + violence_detections

    if job["type"] == "content_moderation":
        return provider.moderate_content(video_path)

    if job["type"] == "text_detection":
        return provider.detect_text(video_path)

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


def receive_latency_ms(message_id):
    """Milliseconds since this message was published, using the timestamp
    Redis embeds in every Stream entry ID ("<ms>-<seq>") - no extra field
    needed on the message itself."""
    try:
        published_ms = int(message_id.split("-")[0])
        return max(0, int(time.time() * 1000) - published_ms)
    except (ValueError, IndexError):
        return None


def process(conn, provider, job_id, message_id, fields):
    job = db.get_job(conn, job_id)
    if job is None:
        logger.warning("job %s no longer exists, skipping", job_id)
        return

    if job["status"] in ("completed", "failed"):
        logger.info("job %s already terminal (%s), skipping stale message", job_id, job["status"])
        return

    # This is a plain script, not a web framework, so nothing creates a
    # transaction automatically. continue_trace() picks up the sentry-trace/
    # baggage headers AnalyzeVideo.php attached to the message, so this
    # shows up as one distributed trace with the request that queued the
    # job, rather than an unrelated trace.
    headers = {"sentry-trace": fields.get("sentry_trace", ""), "baggage": fields.get("baggage", "")}
    transaction = sentry_sdk.continue_trace(headers, op="analysis-worker.process_job", name=job["type"])

    with sentry_sdk.start_transaction(transaction):
        attempts = job["attempts"]
        while attempts < config.MAX_ATTEMPTS:
            attempts += 1
            db.mark_processing(conn, job_id, attempts)
            logger.info("job %s: attempt %s/%s (%s)", job_id, attempts, config.MAX_ATTEMPTS, job["type"])

            try:
                # op="queue.process" with these messaging.* attributes is what
                # Sentry's Queues dashboard (not just the trace explorer)
                # requires to aggregate this stream's throughput/latency.
                with sentry_sdk.start_span(op="queue.process", name=job["type"]) as span:
                    span.set_data("messaging.message.id", str(job_id))
                    span.set_data("messaging.destination.name", config.STREAM_NAME)
                    span.set_data("messaging.message.retry.count", attempts - 1)
                    latency = receive_latency_ms(message_id)
                    if latency is not None:
                        span.set_data("messaging.message.receive.latency", latency)

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


def process_and_ack(redis_client, provider, message_id, fields):
    """Run one job to completion on its own DB connection, then ack the
    stream message. Each concurrent job needs its own connection since a
    pymysql connection isn't safe to share across threads. Wrapped in a
    catch-all because, unlike process()'s internal retry loop, an exception
    here would otherwise be silently lost inside the thread pool."""
    job_id = int(fields["job_id"])
    try:
        conn = db.connect()
        try:
            process(conn, provider, job_id, message_id, fields)
        finally:
            conn.close()
        queue_stream.ack(redis_client, message_id)
    except Exception as error:
        logger.exception("job %s: unhandled error outside process()", job_id)
        sentry_sdk.capture_exception(error)


def main():
    redis_client = queue_stream.connect()
    queue_stream.ensure_group(redis_client)
    provider = get_provider(config.ANALYSIS_PROVIDER)

    logger.info(
        "analysis worker started (provider=%s, consumer=%s, concurrency=%s)",
        config.ANALYSIS_PROVIDER,
        config.CONSUMER_NAME,
        config.WORKER_CONCURRENCY,
    )

    with ThreadPoolExecutor(max_workers=config.WORKER_CONCURRENCY) as executor:
        while True:
            for message_id, fields in queue_stream.reclaim_stale(redis_client):
                executor.submit(process_and_ack, redis_client, provider, message_id, fields)

            message = queue_stream.read_one(redis_client)
            if message:
                message_id, fields = message
                executor.submit(process_and_ack, redis_client, provider, message_id, fields)


if __name__ == "__main__":
    main()
