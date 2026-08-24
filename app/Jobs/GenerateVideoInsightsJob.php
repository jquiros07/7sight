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
        Log::info('GenerateVideoInsightsJob started', ['video_id' => $this->video->id]);

        try {
            $generateVideoInsights($this->video->uploader, $this->video);
            Log::info('GenerateVideoInsightsJob finished', ['video_id' => $this->video->id]);
        } catch (Throwable $e) {
            Log::error($e->getMessage(), ['exception' => $e, 'video_id' => $this->video->id]);

            throw $e;
        }
    }

    /**
     * Called once this job has exhausted every retry attempt. Records the
     * failure on the video so the results page can show an error instead of
     * "insights aren't ready yet" forever.
     */
    public function failed(?Throwable $exception): void
    {
        $this->video->forceFill(['insights_failed_at' => now()])->save();
    }
}
