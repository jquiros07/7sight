<?php

namespace App\Actions\Camera;

use App\Actions\Camera\Concerns\AuthorizesCameraAccess;
use App\Enums\CameraRecordingStatus;
use App\Models\Camera;
use App\Models\CameraRecording;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StreamCameraRecording
{
    use AuthorizesCameraAccess;

    /**
     * Stream a completed recording's file to the browser inline (not as an
     * attachment), so the browser can issue Range requests for seeking/scrubbing
     * in a <video> element. Requires workspace membership.
     */
    public function __invoke(User $user, Camera $camera, CameraRecording $recording): BinaryFileResponse
    {
        $this->authorizeCameraAccess($user, $camera);

        abort_if($recording->camera_id !== $camera->id, 404);
        abort_if($recording->status !== CameraRecordingStatus::Completed, 422, 'This recording is not ready to preview yet.');

        return response()->file(Storage::disk($recording->disk)->path($recording->path), [
            'Content-Type' => 'video/mp4',
        ]);
    }
}
