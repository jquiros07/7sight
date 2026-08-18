import json
from datetime import datetime, timezone

import pymysql
import pymysql.cursors

import config


def connect():
    return pymysql.connect(
        host=config.DB_HOST,
        port=config.DB_PORT,
        database=config.DB_DATABASE,
        user=config.DB_USERNAME,
        password=config.DB_PASSWORD,
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=True,
    )


def now():
    return datetime.now(timezone.utc)


def get_job(conn, job_id):
    """Load a job together with the fields we need from its video."""
    with conn.cursor() as cursor:
        cursor.execute(
            """
            SELECT aj.*,
                   v.disk AS video_disk,
                   v.path AS video_path,
                   v.workspace_id AS video_workspace_id,
                   v.user_id AS video_user_id,
                   v.analysis_config AS video_analysis_config
            FROM analysis_jobs aj
            JOIN videos v ON v.id = aj.video_id
            WHERE aj.id = %s
            """,
            (job_id,),
        )
        return cursor.fetchone()


def mark_processing(conn, job_id, attempts):
    with conn.cursor() as cursor:
        cursor.execute(
            """
            UPDATE analysis_jobs
            SET status = 'processing', attempts = %s, started_at = COALESCE(started_at, %s), updated_at = %s
            WHERE id = %s
            """,
            (attempts, now(), now(), job_id),
        )


def mark_error(conn, job_id, message):
    with conn.cursor() as cursor:
        cursor.execute(
            "UPDATE analysis_jobs SET error_message = %s, updated_at = %s WHERE id = %s",
            (message[:65535], now(), job_id),
        )


def mark_completed(conn, job_id, external_job_id=None, raw_output_path=None):
    with conn.cursor() as cursor:
        cursor.execute(
            """
            UPDATE analysis_jobs
            SET status = 'completed', completed_at = %s, external_job_id = %s, raw_output_path = %s, updated_at = %s
            WHERE id = %s
            """,
            (now(), external_job_id, raw_output_path, now(), job_id),
        )


def mark_failed(conn, job_id):
    with conn.cursor() as cursor:
        cursor.execute(
            "UPDATE analysis_jobs SET status = 'failed', completed_at = %s, updated_at = %s WHERE id = %s",
            (now(), now(), job_id),
        )


def save_results(conn, job_id, rows):
    """rows: aggregate.aggregate_detections() output."""
    with conn.cursor() as cursor:
        for row in rows:
            cursor.execute(
                """
                INSERT INTO analysis_results
                    (analysis_job_id, label, occurrences, avg_confidence, min_confidence,
                     max_confidence, first_seen_at, last_seen_at, data, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                """,
                (
                    job_id,
                    row["label"],
                    row["occurrences"],
                    row["avg_confidence"],
                    row["min_confidence"],
                    row["max_confidence"],
                    row["first_seen_at"],
                    row["last_seen_at"],
                    json.dumps(row["data"]),
                    now(),
                    now(),
                ),
            )


def recompute_video_status(conn, video_id):
    """Video is 'processing' while any job is pending/processing, 'failed' if the
    most recent job for any analysis type failed, otherwise 'ready' once every
    type's most recent job has completed. A retry's older, superseded attempt of
    the same type is ignored so a later success can clear an earlier failure.

    Returns True if this call is what just moved the video into 'ready' (i.e.
    it wasn't already ready), so the caller can fire a one-time notification."""
    with conn.cursor() as cursor:
        cursor.execute("SELECT status FROM videos WHERE id = %s", (video_id,))
        previous_status = cursor.fetchone()["status"]

        cursor.execute(
            """
            SELECT aj.status
            FROM analysis_jobs aj
            INNER JOIN (
                SELECT type, MAX(id) AS latest_id
                FROM analysis_jobs
                WHERE video_id = %s
                GROUP BY type
            ) latest ON latest.type = aj.type AND latest.latest_id = aj.id
            """,
            (video_id,),
        )
        statuses = {row["status"] for row in cursor.fetchall()}

        if "pending" in statuses or "processing" in statuses:
            status = "processing"
        elif "failed" in statuses:
            status = "failed"
        else:
            status = "ready"

        cursor.execute(
            "UPDATE videos SET status = %s, updated_at = %s WHERE id = %s",
            (status, now(), video_id),
        )

        return status == "ready" and previous_status != "ready"
