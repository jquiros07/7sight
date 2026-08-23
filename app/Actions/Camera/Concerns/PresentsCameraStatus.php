<?php

namespace App\Actions\Camera\Concerns;

use App\Models\Camera;
use App\Support\MediaMtxClient;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Requires ResolvesCameraPathName and a MediaMtxClient $this->mediaMtx property.
 */
trait PresentsCameraStatus
{
    /**
     * @return array<string, bool>
     */
    private function activePaths(MediaMtxClient $mediaMtx): array
    {
        try {
            return $mediaMtx->listActivePaths();
        } catch (Throwable $e) {
            Log::error('Failed to fetch MediaMTX path statuses', ['exception' => $e]);

            return [];
        }
    }

    /**
     * @param  array<string, bool>  $activePaths
     * @return array<string, mixed>
     */
    private function present(Camera $camera, array $activePaths): array
    {
        return [
            'id' => $camera->id,
            'name' => $camera->name,
            'location' => $camera->location,
            'stream_url' => $camera->stream_url,
            'created_by' => $camera->creator?->name,
            'created_at' => $camera->created_at,
            'is_live' => $activePaths[$this->pathName($camera)] ?? false,
            'hls_url' => rtrim(config('services.mediamtx.public_hls_url'), '/')."/{$this->pathName($camera)}/index.m3u8",
        ];
    }
}
