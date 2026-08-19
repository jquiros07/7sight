<?php

namespace App\Actions\Video\Concerns;

use App\Models\AnalysisJob;
use App\Models\Video;
use Illuminate\Support\Collection;

trait BuildsAnalysisPromptData
{
    /**
     * The most recent completed job per analysis type - an older, superseded
     * attempt from a retry is ignored, same rule the video's status uses.
     *
     * @return Collection<int, AnalysisJob>
     */
    private function latestCompletedJobsByType(Video $video): Collection
    {
        return $video->analysisJobs
            ->where('status', 'completed')
            ->groupBy(fn (AnalysisJob $job) => $job->type->value)
            ->map(fn (Collection $jobs) => $jobs->sortByDesc('created_at')->first())
            ->values();
    }

    /**
     * @param  Collection<int, AnalysisJob>  $jobs
     * @return array<string, mixed>
     */
    private function buildAnalysisPromptData(Video $video, Collection $jobs): array
    {
        return [
            'duration_seconds' => $video->duration_seconds,
            'analyses' => $jobs->map(fn (AnalysisJob $job) => [
                'type' => $job->type->value,
                'results' => $job->results->map(fn ($result) => [
                    'label' => $result->label,
                    'occurrences' => $result->occurrences,
                    'avg_confidence' => $result->avg_confidence,
                    'min_confidence' => $result->min_confidence,
                    'max_confidence' => $result->max_confidence,
                    'first_seen_seconds' => (float) $result->first_seen_at,
                    'last_seen_seconds' => (float) $result->last_seen_at,
                    'detection_timestamps_seconds' => $result->data['timestamps'] ?? [],
                ])->all(),
            ])->all(),
        ];
    }
}
