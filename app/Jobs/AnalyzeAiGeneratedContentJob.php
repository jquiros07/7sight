<?php

namespace App\Jobs;

use App\Actions\Video\RecordAiContentInsight;
use App\Ai\Agents\AiGeneratedContentAgent;
use App\Models\AiContentAnalysis;
use App\Support\FfmpegVideoProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Files\LocalVideo;
use Throwable;

class AnalyzeAiGeneratedContentJob implements ShouldQueue
{
    use Queueable;

    /**
     * Clips wider or taller than this (pixels) are downscaled before being
     * sent to Gemini, since a 90-second clip at original resolution risks
     * exceeding the provider's inline attachment size limit.
     */
    private const MAX_DIMENSION = 854;

    public function __construct(
        public AiContentAnalysis $analysis,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [20, 60];
    }

    public function handle(FfmpegVideoProcessor $processor, RecordAiContentInsight $recordAiContentInsight): void
    {
        Log::info('AnalyzeAiGeneratedContentJob started', ['ai_content_analysis_id' => $this->analysis->id]);

        $this->analysis->update(['status' => 'processing', 'started_at' => now()]);

        $tempFiles = [];

        try {
            $video = $this->analysis->video;
            $disk = Storage::disk($video->disk);

            $clipPath = tempnam(sys_get_temp_dir(), 'ai-content-').'.mp4';
            $tempFiles[] = $clipPath;

            $processor->trim(
                $disk->path($video->path),
                $clipPath,
                (float) $this->analysis->start_seconds,
                (float) ($this->analysis->end_seconds - $this->analysis->start_seconds),
            );

            $dimensions = $this->downscaledDimensions($video->width, $video->height);

            if ($dimensions !== null) {
                $resizedPath = tempnam(sys_get_temp_dir(), 'ai-content-').'.mp4';
                $tempFiles[] = $resizedPath;

                $processor->resize($clipPath, $resizedPath, $dimensions[0], $dimensions[1]);
                $clipPath = $resizedPath;
            }

            $result = $this->promptAgent($clipPath);

            $this->analysis->update([
                'status' => 'completed',
                'result' => $result,
                'completed_at' => now(),
            ]);

            // A failure here shouldn't undo the analysis result already saved
            // above, so it's caught and logged rather than left to the outer
            // catch (which would re-mark this completed analysis as failed).
            try {
                $recordAiContentInsight($video, $this->analysis->user, [
                    ...$result,
                    'start_seconds' => $this->analysis->start_seconds,
                    'end_seconds' => $this->analysis->end_seconds,
                ]);
            } catch (Throwable $e) {
                Log::error($e->getMessage(), ['exception' => $e, 'ai_content_analysis_id' => $this->analysis->id]);
            }

            Log::info('AnalyzeAiGeneratedContentJob finished', ['ai_content_analysis_id' => $this->analysis->id]);
        } catch (Throwable $e) {
            Log::error($e->getMessage(), ['exception' => $e, 'ai_content_analysis_id' => $this->analysis->id]);

            throw $e;
        } finally {
            foreach ($tempFiles as $path) {
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function promptAgent(string $clipPath): array
    {
        return retry(
            times: 3,
            callback: fn () => (new AiGeneratedContentAgent)->prompt(
                'Assess this video clip for signs it is AI-generated or synthetic.',
                [new LocalVideo($clipPath)],
            )->toArray(),
            sleepMilliseconds: fn (int $attempt) => $attempt * 2000,
            when: fn (Throwable $e) => $e instanceof RateLimitedException || $e instanceof ProviderOverloadedException,
        );
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function downscaledDimensions(?int $width, ?int $height): ?array
    {
        if (! $width || ! $height) {
            return null;
        }

        $largest = max($width, $height);

        if ($largest <= self::MAX_DIMENSION) {
            return null;
        }

        $scale = self::MAX_DIMENSION / $largest;

        return [(int) round($width * $scale), (int) round($height * $scale)];
    }

    /**
     * Called once this job has exhausted every retry attempt.
     */
    public function failed(?Throwable $exception): void
    {
        $this->analysis->forceFill([
            'status' => 'failed',
            'error_message' => $exception?->getMessage(),
            'completed_at' => now(),
        ])->save();
    }
}
