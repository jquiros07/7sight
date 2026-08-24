<?php

namespace App\Support;

use getID3;

/**
 * Thin wrapper around getID3, used wherever a video file on disk needs its
 * duration/dimensions read back (upload validation, camera recording clips).
 */
class VideoMetadataInspector
{
    /**
     * @return array{duration: float|null, width: int|null, height: int|null}
     */
    public function inspect(string $absolutePath): array
    {
        $getID3 = new getID3;
        $info = $getID3->analyze($absolutePath);

        return [
            'duration' => $info['playtime_seconds'] ?? null,
            'width' => $info['video']['resolution_x'] ?? null,
            'height' => $info['video']['resolution_y'] ?? null,
        ];
    }
}
