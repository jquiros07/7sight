<?php

namespace App\Jobs;

use App\Actions\Video\GenerateVideoInsights;
use App\Models\Video;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateVideoInsightsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Video $video,
    ) {}

    public function handle(GenerateVideoInsights $generateVideoInsights): void
    {
        try {
            $generateVideoInsights($this->video->uploader, $this->video);
        } catch (Throwable $e) {
            Log::error($e->getMessage(), ['exception' => $e, 'video_id' => $this->video->id]);

            throw $e;
        }
    }
}
