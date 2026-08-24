<?php

namespace App\Actions\Camera;

use App\Actions\Camera\Concerns\AuthorizesCameraAccess;
use App\Models\Camera;
use App\Models\CameraRecording;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ListCameraRecordings
{
    use AuthorizesCameraAccess;

    /**
     * List a camera's recordings, newest first. Requires workspace membership.
     *
     * @return Collection<int, CameraRecording>
     */
    public function __invoke(User $user, Camera $camera): Collection
    {
        $this->authorizeCameraAccess($user, $camera);

        return $camera->recordings()->orderByDesc('created_at')->get();
    }
}
