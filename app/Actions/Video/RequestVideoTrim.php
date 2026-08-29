<?php

namespace App\Actions\Video;

use App\Actions\Video\Concerns\AuthorizesVideoAccess;
use App\Enums\VideoToolType;
use App\Jobs\TrimVideoJob;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoToolJob;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RequestVideoTrim
{
    use AuthorizesVideoAccess;

    /**
     * Queue a trim of the video between two timestamps. Requires being the
     * uploader or having 'videos.trim' in the video's workspace.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function __invoke(User $user, Video $video, array $input): VideoToolJob
    {
        $this->authorizeVideoManagement($user, $video, 'videos.trim');

        $validated = Validator::make($input, [
            'start_seconds' => ['required', 'numeric', 'min:0', 'lt:end_seconds'],
            'end_seconds' => ['required', 'numeric', 'max:'.$video->duration_seconds],
        ])->validate();

        $job = VideoToolJob::create([
            'video_id' => $video->id,
            'user_id' => $user->id,
            'type' => VideoToolType::Trim,
            'status' => 'pending',
            'params' => $validated,
        ]);

        TrimVideoJob::dispatch($job);

        return $job;
    }
}
