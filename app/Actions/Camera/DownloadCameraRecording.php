<?php

namespace App\Actions\Camera;

use App\Actions\Camera\Concerns\AuthorizesCameraAccess;
use App\Enums\CameraRecordingStatus;
use App\Models\Camera;
use App\Models\CameraRecording;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadCameraRecording
{
    use AuthorizesCameraAccess;

    /**
     * Send a completed recording's file to the browser as an attachment
     * (forces a save rather than playing inline). Requires workspace membership.
     */
    public function __invoke(User $user, Camera $camera, CameraRecording $recording): BinaryFileResponse
    {
        $this->authorizeCameraAccess($user, $camera);

        abort_if($recording->camera_id !== $camera->id, 404);
        abort_if($recording->status !== CameraRecordingStatus::Completed, 422, 'This recording is not ready to download yet.');

        return response()->download(
            Storage::disk($recording->disk)->path($recording->path),
            "camera-{$camera->id}-recording-{$recording->id}.mp4",
            ['Content-Type' => 'video/mp4'],
        );
    }
}
