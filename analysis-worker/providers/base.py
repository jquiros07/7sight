from abc import ABC, abstractmethod
from dataclasses import dataclass


@dataclass
class Detection:
    label: str
    confidence: float
    timestamp_seconds: float


class AnalysisProvider(ABC):
    """A pluggable analysis backend.

    Add a new ML provider by implementing this interface and registering it
    in providers/__init__.py — the worker loop, retry logic, and DB writes
    only ever talk to this interface, so nothing else needs to change.
    """

    @abstractmethod
    def detect_objects(self, video_path: str) -> list[Detection]:
        """Return every object/label detected in the video."""

    @abstractmethod
    def moderate_content(self, video_path: str) -> list[Detection]:
        """Return every content-moderation label detected in the video."""

    @abstractmethod
    def detect_text(self, video_path: str) -> list[Detection]:
        """Return every piece of on-screen text (OCR) detected in the video."""
