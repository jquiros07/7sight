import os

DB_HOST = os.environ.get("DB_HOST", "mysql")
DB_PORT = int(os.environ.get("DB_PORT", "3306"))
DB_DATABASE = os.environ.get("DB_DATABASE", "video_intelligence_platform")
DB_USERNAME = os.environ.get("DB_USERNAME", "laravel")
DB_PASSWORD = os.environ.get("DB_PASSWORD", "")

REDIS_HOST = os.environ.get("REDIS_HOST", "redis")
REDIS_PORT = int(os.environ.get("REDIS_PORT", "6379"))

AWS_ACCESS_KEY_ID = os.environ.get("AWS_ACCESS_KEY_ID")
AWS_SECRET_ACCESS_KEY = os.environ.get("AWS_SECRET_ACCESS_KEY")
AWS_DEFAULT_REGION = os.environ.get("AWS_DEFAULT_REGION", "us-east-1")
AWS_BUCKET = os.environ.get("AWS_BUCKET")

ANALYSIS_PROVIDER = os.environ.get("ANALYSIS_PROVIDER", "rekognition")
SENTRY_DSN = os.environ.get("SENTRY_DSN")

# Rekognition's label detection defaults to returning anything with >= 50%
# confidence if MinConfidence isn't set, which surfaces a lot of speculative,
# low-confidence guesses (e.g. swimwear straps or reflections misread as a
# "Blade" at ~51%). Raise the floor to cut that noise for both object and
# threat detection, which both read from this same label-detection call.
REKOGNITION_MIN_CONFIDENCE = float(os.environ.get("REKOGNITION_MIN_CONFIDENCE", "75"))

# Called once a video's analysis jobs all complete, to trigger insight
# generation on the Laravel side (see routes/api.php's internal group).
LARAVEL_INTERNAL_URL = os.environ.get("LARAVEL_INTERNAL_URL", "http://app")
INTERNAL_API_TOKEN = os.environ.get("INTERNAL_API_TOKEN", "")

# Root of the shared volume holding uploaded videos (matches Laravel's
# 'local' disk root, storage/app/private, mounted read-write into this
# container so we can also write raw provider responses back for debugging).
STORAGE_ROOT = os.environ.get("STORAGE_ROOT", "/videos")

STREAM_NAME = "analysis_jobs"
CONSUMER_GROUP = "analysis_workers"
CONSUMER_NAME = os.environ.get("HOSTNAME", "worker-1")

MAX_ATTEMPTS = 3
RETRY_BACKOFF_SECONDS = [5, 30, 120]
STALE_IDLE_MS = 5 * 60 * 1000  # reclaim messages a crashed worker never acked

# Threat detection has no dedicated Rekognition API - it reuses the same
# label-detection call as object detection and filters down to these
# (normalized: lowercase, spaces/slashes -> underscores). Edit freely if
# Rekognition surfaces other threat-relevant labels worth tracking.
THREAT_LABELS = {
    "weapon",
    "gun",
    "handgun",
    "rifle",
    "firearm",
    "knife",
    "blade",
    "sword",
    "explosive",
    "fire",
    "smoke",
}
