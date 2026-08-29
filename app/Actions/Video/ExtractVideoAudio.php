<?php

namespace App\Actions\Video;

use App\Actions\Video\Concerns\AuthorizesVideoAccess;
use App\Models\User;
use App\Models\Video;
use App\Support\FfmpegVideoProcessor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExtractVideoAudio
{
    use AuthorizesVideoAccess;

    public function __construct(
        private readonly FfmpegVideoProcessor $processor,
    ) {}

    /**
     * Extract the video's audio track and return it as a download. Nothing
     * is persisted - the output is a temp file deleted once sent. Requires
     * being the uploader or having 'videos.extract-audio' in the video's
     * workspace.
     */
    public function __invoke(User $user, Video $video): BinaryFileResponse
    {
        $this->authorizeVideoManagement($user, $video, 'videos.extract-audio');

        $inputPath = Storage::disk($video->disk)->path($video->path);
        $outputPath = tempnam(sys_get_temp_dir(), 'video-audio-').'.mp3';

        $this->processor->extractAudio($inputPath, $outputPath);

        $fileName = Str::slug($video->title ?: 'video').'.mp3';

        return response()->download($outputPath, $fileName)->deleteFileAfterSend();
    }
}
