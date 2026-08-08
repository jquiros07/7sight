from providers.base import AnalysisProvider
from providers.fake import FakeProvider
from providers.rekognition import RekognitionProvider

_PROVIDERS = {
    "rekognition": RekognitionProvider,
    "fake": FakeProvider,
}


def get_provider(name: str) -> AnalysisProvider:
    try:
        provider_class = _PROVIDERS[name]
    except KeyError:
        raise ValueError(f"Unknown analysis provider: {name}")

    return provider_class()
