<?php

namespace App\Actions\Camera;

use App\Actions\Camera\Concerns\AuthorizesCameraAccess;
use App\Enums\CameraRecordingStatus;
use App\Models\Camera;
use App\Models\CameraRecording;
use App\Models\User;

class CancelCameraRecording
{
    use AuthorizesCameraAccess;

    /**
     * Request cancellation of an in-progress recording. Requires workspace
     * membership. The recording job itself polls `cancel_requested` and
     * stops ffmpeg - this only flags the request.
     */
    public function __invoke(User $user, Camera $camera, CameraRecording $recording): CameraRecording
    {
        $this->authorizeCameraAccess($user, $camera);

        abort_if($recording->status !== CameraRecordingStatus::Recording, 422, 'This recording is not in progress.');

        $recording->update(['cancel_requested' => true]);

        return $recording;
    }
}
