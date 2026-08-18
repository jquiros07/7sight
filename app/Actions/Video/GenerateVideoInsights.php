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
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Throwable;

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

        $video->loadMissing('analysisJobs.results', 'latestInsight');

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

        abort_if(
            $this->alreadyUpToDate($video, $jobs, $wantsObjectDetection, $wantsThreatAssessment, $wantsModeration),
            422,
            'Insights for this video are already up to date.'
        );

        $result = [
            'object_detection' => (! $wantsObjectDetection || $objectDetectionJobs->isEmpty()) ? null : $this->promptForInsights(
                new ObjectDetectionAgent, $this->buildPromptData($video, $objectDetectionJobs)
            ),

            // The threat assessment gets every job's data, not just the threat-detection
            // one, so it can correlate e.g. an object-detection "Person" label with a
            // threat-detection "Knife" label the same way a human analyst would - even
            // when threat detection is the only type explicitly requested.
            'threat_assessment' => (! $wantsThreatAssessment || $threatJob === null) ? null : $this->promptForInsights(
                new ThreatDetectionAgent, $this->buildPromptData($video, $jobs)
            ),

            // Content moderation is a self-contained assessment (Rekognition's own
            // moderation API), so unlike threat detection it only needs its own results.
            'moderation' => (! $wantsModeration || $moderationJob === null) ? null : $this->promptForInsights(
                new ContentModerationAgent, $this->buildPromptData($video, collect([$moderationJob]))
            ),
        ];

        VideoInsight::create([
            'video_id' => $video->id,
            'user_id' => $user->id,
            ...$result,
        ]);

        return $result;
    }

    /**
     * Prompt an insights agent, retrying with backoff if the AI provider rate
     * limits the request or is temporarily overloaded - both transient and
     * worth retrying, unlike e.g. an insufficient-credits failure.
     *
     * @return array<string, mixed>
     */
    private function promptForInsights(Agent $agent, array $data): array
    {
        return retry(
            times: 3,
            callback: fn () => $agent->prompt(json_encode($data, JSON_PRETTY_PRINT))->toArray(),
            sleepMilliseconds: fn (int $attempt) => $attempt * 500,
            when: fn (Throwable $e) => $e instanceof RateLimitedException || $e instanceof ProviderOverloadedException,
        );
    }

    /**
     * True if the video's latest insight already has every field this request
     * wants, generated after the most recent completed analysis job - i.e.
     * re-prompting the AI now would just repeat the same result. A field the
     * last insight left null (never generated, or a type added since) still
     * allows the request through.
     *
     * @param  Collection<int, AnalysisJob>  $jobs
     */
    private function alreadyUpToDate(
        Video $video,
        Collection $jobs,
        bool $wantsObjectDetection,
        bool $wantsThreatAssessment,
        bool $wantsModeration,
    ): bool {
        $latestInsight = $video->latestInsight;

        if ($latestInsight === null) {
            return false;
        }

        $latestJobCompletedAt = $jobs->max('completed_at');

        if ($latestJobCompletedAt !== null && $latestInsight->created_at->lt($latestJobCompletedAt)) {
            return false;
        }

        return (! $wantsObjectDetection || $latestInsight->object_detection !== null)
            && (! $wantsThreatAssessment || $latestInsight->threat_assessment !== null)
            && (! $wantsModeration || $latestInsight->moderation !== null);
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
