<?php

namespace App\Actions\Video;

use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Ai\Agents\ContentModerationAgent;
use App\Ai\Agents\ObjectDetectionAgent;
use App\Ai\Agents\ThreatDetectionAgent;
use App\Enums\AnalysisType;
use App\Models\AnalysisJob;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoInsight;
use Illuminate\Support\Collection;

class GenerateVideoInsights
{
    use AuthorizesWorkspaceAccess;

    /**
     * Ask the AI to summarize a video's analysis results. Requires workspace
     * membership. When $type is given, only that analysis type's insight is
     * generated and the others are stored as null; otherwise all three are
     * generated together.
     *
     * @return array<string, mixed>
     */
    public function __invoke(User $user, Video $video, ?AnalysisType $type = null): array
    {
        $this->authorizeMembership($user, $video->workspace);

        $video->loadMissing('analysisJobs.results');

        $jobs = $this->latestCompletedJobsByType($video);

        abort_if($jobs->isEmpty(), 422, 'This video has no completed analysis to generate insights from.');

        $threatJob = $jobs->first(fn (AnalysisJob $job) => $job->type === AnalysisType::ThreatDetection);
        $moderationJob = $jobs->first(fn (AnalysisJob $job) => $job->type === AnalysisType::ContentModeration);
        $objectDetectionJobs = $jobs->reject(
            fn (AnalysisJob $job) => in_array($job->type, [AnalysisType::ThreatDetection, AnalysisType::ContentModeration], true)
        );

        $wantsObjectDetection = in_array($type, [null, AnalysisType::ObjectDetection], true);
        $wantsThreatAssessment = in_array($type, [null, AnalysisType::ThreatDetection], true);
        $wantsModeration = in_array($type, [null, AnalysisType::ContentModeration], true);

        match ($type) {
            AnalysisType::ObjectDetection => abort_if($objectDetectionJobs->isEmpty(), 422, 'This video has no completed object detection analysis to generate insights from.'),
            AnalysisType::ThreatDetection => abort_if($threatJob === null, 422, 'This video has no completed threat detection analysis to generate insights from.'),
            AnalysisType::ContentModeration => abort_if($moderationJob === null, 422, 'This video has no completed content moderation analysis to generate insights from.'),
            default => null,
        };

        $result = [
            'object_detection' => (! $wantsObjectDetection || $objectDetectionJobs->isEmpty()) ? null : (new ObjectDetectionAgent)->prompt(
                json_encode($this->buildPromptData($video, $objectDetectionJobs), JSON_PRETTY_PRINT)
            )->toArray(),

            // The threat assessment gets every job's data, not just the threat-detection
            // one, so it can correlate e.g. an object-detection "Person" label with a
            // threat-detection "Knife" label the same way a human analyst would - even
            // when threat detection is the only type explicitly requested.
            'threat_assessment' => (! $wantsThreatAssessment || $threatJob === null) ? null : (new ThreatDetectionAgent)->prompt(
                json_encode($this->buildPromptData($video, $jobs), JSON_PRETTY_PRINT)
            )->toArray(),

            // Content moderation is a self-contained assessment (Rekognition's own
            // moderation API), so unlike threat detection it only needs its own results.
            'moderation' => (! $wantsModeration || $moderationJob === null) ? null : (new ContentModerationAgent)->prompt(
                json_encode($this->buildPromptData($video, collect([$moderationJob])), JSON_PRETTY_PRINT)
            )->toArray(),
        ];

        VideoInsight::create([
            'video_id' => $video->id,
            'user_id' => $user->id,
            ...$result,
        ]);

        return $result;
    }

    /**
     * The most recent completed job per analysis type — an older, superseded
     * attempt from a retry is ignored, same rule the video's status uses.
     *
     * @return Collection<int, AnalysisJob>
     */
    private function latestCompletedJobsByType(Video $video): Collection
    {
        return $video->analysisJobs
            ->where('status', 'completed')
            ->groupBy(fn ($job) => $job->type->value)
            ->map(fn (Collection $jobs) => $jobs->sortByDesc('created_at')->first())
            ->values();
    }

    /**
     * @param  Collection<int, AnalysisJob>  $jobs
     * @return array<string, mixed>
     */
    private function buildPromptData(Video $video, Collection $jobs): array
    {
        return [
            'duration_seconds' => $video->duration_seconds,
            'analyses' => $jobs->map(fn ($job) => [
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
