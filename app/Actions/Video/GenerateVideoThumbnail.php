<?php

namespace App\Actions\Video;

use App\Actions\Video\Concerns\AuthorizesVideoAccess;
use App\Enums\VideoToolType;
use App\Jobs\GenerateThumbnailJob;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoToolJob;

class GenerateVideoThumbnail
{
    use AuthorizesVideoAccess;

    /**
     * Queue a thumbnail extraction for the video. Requires being the
     * uploader or having 'videos.generate-thumbnail' in the video's
     * workspace.
     */
    public function __invoke(User $user, Video $video): VideoToolJob
    {
        $this->authorizeVideoManagement($user, $video, 'videos.generate-thumbnail');

        $job = VideoToolJob::create([
            'video_id' => $video->id,
            'user_id' => $user->id,
            'type' => VideoToolType::Thumbnail,
            'status' => 'pending',
        ]);

        GenerateThumbnailJob::dispatch($job);

        return $job;
    }
}
