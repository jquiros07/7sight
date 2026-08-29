<?php

namespace App\Actions\Video;

use App\Actions\Video\Concerns\AuthorizesVideoAccess;
use App\Models\User;
use App\Models\Video;
use App\Support\FfmpegVideoProcessor;
use Illuminate\Support\Facades\Storage;

class GenerateVideoThumbnail
{
    use AuthorizesVideoAccess;

    public function __construct(
        private readonly FfmpegVideoProcessor $processor,
    ) {}

    /**
     * Extract a frame near the start of the video and store it as the
     * video's thumbnail. Requires being the uploader or having
     * 'videos.generate-thumbnail' in the video's workspace.
     */
    public function __invoke(User $user, Video $video): Video
    {
        $this->authorizeVideoManagement($user, $video, 'videos.generate-thumbnail');

        $path = "videos/{$video->workspace_id}/{$video->user_id}/derived/{$video->id}/thumbnail.jpg";

        $disk = Storage::disk($video->disk);
        $disk->makeDirectory(dirname($path));

        $atSecond = $video->duration_seconds > 1 ? 1.0 : 0.0;

        $this->processor->thumbnail($disk->path($video->path), $disk->path($path), $atSecond);

        $video->update(['thumbnail_path' => $path]);

        return $video;
    }
}
