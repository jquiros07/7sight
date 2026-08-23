<?php

namespace App\Actions\Camera;

use App\Actions\Camera\Concerns\PresentsCameraStatus;
use App\Actions\Camera\Concerns\ResolvesCameraPathName;
use App\Models\Camera;
use App\Support\MediaMtxClient;

class ListCameras
{
    use PresentsCameraStatus;
    use ResolvesCameraPathName;

    public function __construct(
        private readonly MediaMtxClient $mediaMtx,
    ) {}

    /**
     * List every registered camera, annotated with its live status and HLS playback URL.
     *
     * @return array<int, array<string, mixed>>
     */
    public function __invoke(): array
    {
        $activePaths = $this->activePaths($this->mediaMtx);

        return Camera::with('creator:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Camera $camera) => $this->present($camera, $activePaths))
            ->all();
    }
}
