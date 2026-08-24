<?php

namespace App\Actions\Video\Concerns;

trait ValidatesVideoMetadata
{
    private const MAX_DURATION_SECONDS = 15 * 60;

    private const MAX_LONG_EDGE = 1920;

    private const MAX_SHORT_EDGE = 1080;

    private const MAX_FILE_SIZE_BYTES = 500 * 1024 * 1024;

    /**
     * @param  array{duration: float|null, width: int|null, height: int|null}  $metadata
     * @return list<string>
     */
    private function metadataValidationErrors(array $metadata, ?int $sizeBytes = null): array
    {
        if ($metadata['duration'] === null || $metadata['width'] === null || $metadata['height'] === null) {
            return ['That file could not be read as a video. Please choose a different file.'];
        }

        $errors = [];

        if ($metadata['duration'] > self::MAX_DURATION_SECONDS) {
            $errors[] = 'Videos must be 15 minutes or shorter.';
        }

        $longEdge = max($metadata['width'], $metadata['height']);
        $shortEdge = min($metadata['width'], $metadata['height']);

        if ($longEdge > self::MAX_LONG_EDGE || $shortEdge > self::MAX_SHORT_EDGE) {
            $errors[] = 'Videos must be 1080p or lower.';
        }

        if ($sizeBytes !== null && $sizeBytes > self::MAX_FILE_SIZE_BYTES) {
            $errors[] = 'Videos must be 500MB or smaller.';
        }

        return $errors;
    }
}
