<?php

namespace App\Actions\Camera;

use App\Actions\Camera\Concerns\ResolvesCameraPathName;
use App\Models\Camera;
use App\Support\MediaMtxClient;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteCamera
{
    use ResolvesCameraPathName;

    public function __construct(
        private readonly MediaMtxClient $mediaMtx,
    ) {}

    /**
     * Delete a camera. Removing its MediaMTX path is best-effort - the camera
     * disappearing from the app matters more than a stale MediaMTX path
     * lingering if MediaMTX happens to be unreachable.
     */
    public function __invoke(Camera $camera): void
    {
        $camera->delete();

        try {
            $this->mediaMtx->removePath($this->pathName($camera));
        } catch (Throwable $e) {
            Log::error('Failed to remove camera path from MediaMTX', ['exception' => $e]);
        }
    }
}
