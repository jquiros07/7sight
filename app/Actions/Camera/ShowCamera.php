<?php

namespace App\Actions\Camera;

use App\Actions\Camera\Concerns\AuthorizesCameraAccess;
use App\Actions\Camera\Concerns\PresentsCameraStatus;
use App\Actions\Camera\Concerns\ResolvesCameraPathName;
use App\Models\Camera;
use App\Models\User;
use App\Support\MediaMtxClient;

class ShowCamera
{
    use AuthorizesCameraAccess;
    use PresentsCameraStatus;
    use ResolvesCameraPathName;

    public function __construct(
        private readonly MediaMtxClient $mediaMtx,
    ) {}

    /**
     * Requires workspace membership.
     *
     * @return array<string, mixed>
     */
    public function __invoke(User $user, Camera $camera): array
    {
        $this->authorizeCameraAccess($user, $camera);

        $camera->loadMissing([
            'creator:id,name',
            'workspace:id,name',
            'activeRecording:camera_recordings.id,camera_recordings.camera_id,camera_recordings.ends_at',
        ]);

        return $this->present($camera, $this->activePaths($this->mediaMtx));
    }
}
