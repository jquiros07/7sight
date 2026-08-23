<?php

namespace App\Actions\Camera;

use App\Actions\Camera\Concerns\PresentsCameraStatus;
use App\Actions\Camera\Concerns\ResolvesCameraPathName;
use App\Models\Camera;
use App\Support\MediaMtxClient;

class ShowCamera
{
    use PresentsCameraStatus;
    use ResolvesCameraPathName;

    public function __construct(
        private readonly MediaMtxClient $mediaMtx,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(Camera $camera): array
    {
        return $this->present($camera, $this->activePaths($this->mediaMtx));
    }
}
