<?php

namespace App\Actions\Camera;

use App\Actions\Camera\Concerns\AuthorizesCameraAccess;
use App\Enums\CameraRecordingStatus;
use App\Jobs\RecordCameraFeedJob;
use App\Models\Camera;
use App\Models\CameraRecording;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StartCameraRecording
{
    use AuthorizesCameraAccess;

    private const ALLOWED_DURATION_MINUTES = [3, 5, 15, 30, 60, 180, 300, 480];

    /**
     * Start recording a camera's feed for a fixed duration. Requires
     * workspace membership. Only one active recording per camera at a time.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function __invoke(User $user, Camera $camera, array $input): CameraRecording
    {
        $this->authorizePermission($user, $camera->workspace, 'recordings.start');

        $validated = Validator::make($input, [
            'duration_minutes' => ['required', 'integer', Rule::in(self::ALLOWED_DURATION_MINUTES)],
        ])->validate();

        return DB::transaction(function () use ($user, $camera, $validated) {
            Camera::whereKey($camera->id)->lockForUpdate()->first();

            abort_if(
                $camera->recordings()->where('status', CameraRecordingStatus::Recording)->exists(),
                422,
                'This camera already has an active recording.'
            );

            $startedAt = now();

            $recording = CameraRecording::create([
                'camera_id' => $camera->id,
                'requested_by' => $user->id,
                'status' => CameraRecordingStatus::Recording,
                'duration_minutes' => $validated['duration_minutes'],
                'started_at' => $startedAt,
                'ends_at' => $startedAt->clone()->addMinutes($validated['duration_minutes']),
            ]);

            RecordCameraFeedJob::dispatch($recording)->onConnection('recordings')->onQueue('recordings');

            return $recording;
        });
    }
}
