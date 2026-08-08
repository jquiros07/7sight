from providers.base import AnalysisProvider, Detection


class FakeProvider(AnalysisProvider):
    """Returns canned detections without calling any external service.

    Useful for exercising the queue/retry/DB-write pipeline without needing
    real AWS credentials, and as a template for a future non-Rekognition provider.
    """

    def detect_objects(self, video_path: str) -> list[Detection]:
        return [
            Detection(label="Person", confidence=98.5, timestamp_seconds=0.0),
            Detection(label="Car", confidence=91.2, timestamp_seconds=1.0),
            Detection(label="Person", confidence=95.0, timestamp_seconds=2.0),
        ]

    def moderate_content(self, video_path: str) -> list[Detection]:
        return [Detection(label="Safe", confidence=99.9, timestamp_seconds=0.0)]
