<?php

namespace App\Jobs;

use App\Models\VideoToolJob;
use App\Support\FfmpegVideoProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ResizeVideoJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public VideoToolJob $videoToolJob,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [20, 60];
    }

    public function handle(FfmpegVideoProcessor $processor): void
    {
        Log::info('ResizeVideoJob started', ['video_tool_job_id' => $this->videoToolJob->id]);

        $this->videoToolJob->update(['status' => 'processing', 'started_at' => now()]);

        try {
            $video = $this->videoToolJob->video;
            $disk = Storage::disk($video->disk);

            $outputPath = "videos/{$video->workspace_id}/{$video->user_id}/tool-jobs/{$this->videoToolJob->id}/output.mp4";
            $disk->makeDirectory(dirname($outputPath));

            $processor->resize(
                $disk->path($video->path),
                $disk->path($outputPath),
                (int) $this->videoToolJob->params['width'],
                (int) $this->videoToolJob->params['height'],
            );

            $this->videoToolJob->update([
                'status' => 'completed',
                'output_disk' => $video->disk,
                'output_path' => $outputPath,
                'completed_at' => now(),
            ]);

            Log::info('ResizeVideoJob finished', ['video_tool_job_id' => $this->videoToolJob->id]);
        } catch (Throwable $e) {
            Log::error($e->getMessage(), ['exception' => $e, 'video_tool_job_id' => $this->videoToolJob->id]);

            throw $e;
        }
    }

    /**
     * Called once this job has exhausted every retry attempt.
     */
    public function failed(?Throwable $exception): void
    {
        $this->videoToolJob->forceFill([
            'status' => 'failed',
            'error_message' => $exception?->getMessage(),
            'completed_at' => now(),
        ])->save();
    }
}
