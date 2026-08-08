from collections import defaultdict


def aggregate_detections(detections):
    """Group raw per-timestamp detections by label into the rows analysis_results
    expects: an occurrence count, confidence stats, and the first/last time the
    label was seen in the video (in seconds).
    """
    by_label = defaultdict(list)
    for detection in detections:
        by_label[detection.label].append(detection)

    rows = []
    for label, items in by_label.items():
        confidences = [item.confidence for item in items]
        timestamps = sorted(item.timestamp_seconds for item in items)

        rows.append(
            {
                "label": label,
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
