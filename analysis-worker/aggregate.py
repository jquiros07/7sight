from collections import defaultdict


def aggregate_detections(detections):
    """Group raw per-timestamp detections by label into the rows analysis_results
    expects: an occurrence count, confidence stats, and the first/last time the
    label was seen in the video (in seconds).
    """
    # Grouped case-insensitively to match analysis_results' utf8mb4_unicode_ci
    # unique index on (analysis_job_id, label) - otherwise e.g. "AT" and "at"
    # from OCR text detection group into separate rows here but collide as
    # the same key on insert.
    by_label = defaultdict(list)
    first_seen_label = {}
    for detection in detections:
        key = detection.label.strip().lower()
        by_label[key].append(detection)
        first_seen_label.setdefault(key, detection.label)

    rows = []
    for key, items in by_label.items():
        confidences = [item.confidence for item in items]
        timestamps = sorted(item.timestamp_seconds for item in items)

        rows.append(
            {
                "label": first_seen_label[key],
                "occurrences": len(items),
                "avg_confidence": sum(confidences) / len(confidences),
                "min_confidence": min(confidences),
                "max_confidence": max(confidences),
                "first_seen_at": timestamps[0],
                "last_seen_at": timestamps[-1],
                "data": {"timestamps": timestamps},
            }
        )

    return rows
