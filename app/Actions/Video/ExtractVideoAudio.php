<?php

namespace App\Actions\Video;

use App\Actions\Video\Concerns\AuthorizesVideoAccess;
use App\Enums\VideoToolType;
use App\Jobs\ExtractAudioJob;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoToolJob;

class ExtractVideoAudio
{
    use AuthorizesVideoAccess;

    /**
     * Queue an audio track extraction for the video. Requires being the
     * uploader or having 'videos.extract-audio' in the video's workspace.
     */
    public function __invoke(User $user, Video $video): VideoToolJob
    {
        $this->authorizeVideoManagement($user, $video, 'videos.extract-audio');

        $job = VideoToolJob::create([
            'video_id' => $video->id,
            'user_id' => $user->id,
            'type' => VideoToolType::AudioExtraction,
            'status' => 'pending',
        ]);

        ExtractAudioJob::dispatch($job);

        return $job;
    }
}
