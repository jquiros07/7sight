<?php

namespace App\Actions\Video;

use App\Actions\Video\Concerns\AuthorizesVideoAccess;
use App\Enums\VideoToolType;
use App\Jobs\ResizeVideoJob;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoToolJob;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RequestVideoResize
{
    use AuthorizesVideoAccess;

    private const MAX_WIDTH = 3840;

    private const MAX_HEIGHT = 2160;

    /**
     * Queue a resize/transcode of the video to a target resolution.
     * Requires being the uploader or having 'videos.resize' in the video's
     * workspace.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function __invoke(User $user, Video $video, array $input): VideoToolJob
    {
        $this->authorizeVideoManagement($user, $video, 'videos.resize');

        $validated = Validator::make($input, [
            'width' => ['required', 'integer', 'min:1', 'max:'.self::MAX_WIDTH],
            'height' => ['required', 'integer', 'min:1', 'max:'.self::MAX_HEIGHT],
        ])->validate();

        $job = VideoToolJob::create([
            'video_id' => $video->id,
            'user_id' => $user->id,
            'type' => VideoToolType::Resize,
            'status' => 'pending',
            'params' => $validated,
        ]);

        ResizeVideoJob::dispatch($job);

        return $job;
    }
}
