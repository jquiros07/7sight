<?php

namespace App\Actions\Workspace;

use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Enums\AnalysisType;
use App\Enums\VideoStatus;
use App\Models\AnalysisJob;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoInsight;
use App\Models\VideoToolJob;
use App\Models\Workspace;
use Illuminate\Support\Collection;

class ShowWorkspaceDashboard
{
    use AuthorizesWorkspaceAccess;

    private const UPLOADS_WINDOW_DAYS = 14;

    private const TOP_LABELS_LIMIT = 8;

    private const NEEDS_REVIEW_LIMIT = 5;

    /**
     * Aggregate stats for a workspace's videos, analysis jobs, and generated
     * AI insights. Requires workspace membership.
     *
     * @return array<string, mixed>
     */
    public function __invoke(User $user, Workspace $workspace): array
    {
        $this->authorizeMembership($user, $workspace);

        $videos = $workspace->videos()
            ->select(['id', 'workspace_id', 'title', 'status', 'size', 'created_at'])
            ->with([
                'analysisJobs:id,video_id,type,status,started_at,completed_at,flagged_for_review_at,flagged_by,flagged_review_note',
                'analysisJobs.results:id,analysis_job_id,label,occurrences',
                'analysisJobs.flaggedByUser:id,name',
                'insights:id,video_id,threat_assessment,moderation,ai_content_assessment,created_at',
            ])
            ->get();
        $jobs = $videos->flatMap->analysisJobs;
        $insightSummary = $workspace->latestInsightSummary;

        return [
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
            ],
            'insight_summary' => $insightSummary === null ? null : [
                'summary' => $insightSummary->summary,
                'highlights' => $insightSummary->highlights,
                'generated_at' => $insightSummary->created_at,
            ],
            'stats' => [
                'total_videos' => $videos->count(),
                'total_storage_bytes' => (int) $videos->sum('size'),
                'completed_analyses' => $jobs->where('status', 'completed')->count(),
                'processing_now' => $jobs->whereIn('status', ['pending', 'processing'])->count(),
                'failed_jobs' => $jobs->where('status', 'failed')->count(),
                'flagged_for_review' => $jobs->whereNotNull('flagged_for_review_at')->count(),
                'avg_processing_seconds' => $this->avgProcessingSeconds($jobs),
                'tool_jobs_run' => VideoToolJob::whereIn('video_id', $videos->pluck('id'))->count(),
            ],
            'uploads_over_time' => $this->uploadsOverTime($videos),
            'videos_by_status' => $this->countsByValues(
                $videos->countBy(fn (Video $video) => $video->status->value),
                array_column(VideoStatus::cases(), 'value'),
            ),
            'jobs_by_type' => $this->countsByValues(
                $jobs->countBy(fn (AnalysisJob $job) => $job->type->value),
                [
                    AnalysisType::ObjectDetection->value,
                    AnalysisType::ThreatDetection->value,
                    AnalysisType::ContentModeration->value,
                    AnalysisType::TextDetection->value,
                ],
            ),
            'top_labels' => $this->topLabels($jobs),
            'insight_flags' => $this->insightFlags($videos),
            'risk_level_breakdown' => $this->riskLevelBreakdown($videos),
            'moderation_severity_breakdown' => $this->moderationSeverityBreakdown($videos),
            'needs_review' => $this->needsReviewItems($videos, $jobs),
        ];
    }

    /**
     * The most recently flagged analysis jobs in this workspace - results a
     * human disputed as not matching what they saw in the video.
     *
     * @param  Collection<int, Video>  $videos
     * @param  Collection<int, AnalysisJob>  $jobs
     * @return array<int, array<string, mixed>>
     */
    private function needsReviewItems(Collection $videos, Collection $jobs): array
    {
        $videoTitles = $videos->pluck('title', 'id');

        return $jobs
            ->whereNotNull('flagged_for_review_at')
            ->sortByDesc('flagged_for_review_at')
            ->take(self::NEEDS_REVIEW_LIMIT)
            ->map(fn (AnalysisJob $job) => [
                'video_id' => $job->video_id,
                'video_title' => $videoTitles[$job->video_id] ?? '',
                'type' => $job->type->value,
                'note' => $job->flagged_review_note,
                'flagged_by' => $job->flaggedByUser?->name,
                'flagged_at' => $job->flagged_for_review_at,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Video>  $videos
     * @return array<int, array{date: string, count: int}>
     */
    private function uploadsOverTime(Collection $videos): array
    {
        $byDate = $videos->countBy(fn (Video $video) => $video->created_at->toDateString());

        return collect(range(self::UPLOADS_WINDOW_DAYS - 1, 0))
            ->map(function (int $daysAgo) use ($byDate) {
                $date = now()->subDays($daysAgo)->toDateString();

                return ['date' => $date, 'count' => $byDate->get($date, 0)];
            })
            ->all();
    }

    /**
     * Fills in zero counts for any key present in $keys but missing from $counts.
     *
     * @param  Collection<string, int>  $counts
     * @param  array<int, string>  $keys
     * @return array<string, int>
     */
    private function countsByValues(Collection $counts, array $keys): array
    {
        return collect($keys)->mapWithKeys(fn (string $key) => [$key => $counts->get($key, 0)])->all();
    }

    /**
     * @param  Collection<int, AnalysisJob>  $jobs
     * @return array<int, array{label: string, occurrences: int}>
     */
    private function topLabels(Collection $jobs): array
    {
        return $jobs->flatMap->results
            ->groupBy('label')
            ->map(fn (Collection $results, string $label) => ['label' => $label, 'occurrences' => $results->sum('occurrences')])
            ->sortByDesc('occurrences')
            ->take(self::TOP_LABELS_LIMIT)
            ->values()
            ->all();
    }

    /**
     * Counts videos whose most recently generated insight of each kind flags
     * a threat or moderation issue. Scoped insight generation can leave one
     * kind null on the latest row, so each kind looks at its own latest
     * non-null value rather than only the single latest row.
     *
     * @param  Collection<int, Video>  $videos
     * @return array{threats_detected: int, flagged_moderation: int, ai_generated_content_flagged: int}
     */
    private function insightFlags(Collection $videos): array
    {
        $threatsDetected = 0;
        $flaggedModeration = 0;
        $aiGeneratedContentFlagged = 0;

        foreach ($videos as $video) {
            $latestThreat = $video->insights->whereNotNull('threat_assessment')->sortByDesc('created_at')->first();
            $latestModeration = $video->insights->whereNotNull('moderation')->sortByDesc('created_at')->first();
            $latestAiContent = $video->insights->whereNotNull('ai_content_assessment')->sortByDesc('created_at')->first();

            if ($latestThreat && ($latestThreat->threat_assessment['threat_detected'] ?? false)) {
                $threatsDetected++;
            }

            if ($latestModeration && ($latestModeration->moderation['status'] ?? 'SAFE') !== 'SAFE') {
                $flaggedModeration++;
            }

            if ($latestAiContent && ($latestAiContent->ai_content_assessment['verdict'] ?? null) === 'AI_GENERATED') {
                $aiGeneratedContentFlagged++;
            }
        }

        return [
            'threats_detected' => $threatsDetected,
            'flagged_moderation' => $flaggedModeration,
            'ai_generated_content_flagged' => $aiGeneratedContentFlagged,
        ];
    }

    /**
     * Each video's latest threat assessment, grouped by risk level. Videos
     * with no threat assessment generated yet are excluded entirely, same
     * "latest non-null value" rule as insightFlags().
     *
     * @param  Collection<int, Video>  $videos
     * @return array<string, int>
     */
    private function riskLevelBreakdown(Collection $videos): array
    {
        $counts = $videos
            ->map(fn (Video $video) => $video->insights->whereNotNull('threat_assessment')->sortByDesc('created_at')->first())
            ->filter()
            ->countBy(fn (VideoInsight $insight) => $insight->threat_assessment['risk_level'] ?? null);

        return $this->countsByValues($counts, ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL']);
    }

    /**
     * Each video's latest moderation assessment, grouped by severity. Videos
     * with no moderation assessment generated yet are excluded entirely.
     *
     * @param  Collection<int, Video>  $videos
     * @return array<string, int>
     */
    private function moderationSeverityBreakdown(Collection $videos): array
    {
        $counts = $videos
            ->map(fn (Video $video) => $video->insights->whereNotNull('moderation')->sortByDesc('created_at')->first())
            ->filter()
            ->countBy(fn (VideoInsight $insight) => $insight->moderation['severity'] ?? null);

        return $this->countsByValues($counts, ['NONE', 'LOW', 'MEDIUM', 'HIGH']);
    }

    /**
     * Average wall-clock time between a job starting and completing, across
     * completed jobs that have both timestamps. Null when there's no data yet.
     *
     * @param  Collection<int, AnalysisJob>  $jobs
     */
    private function avgProcessingSeconds(Collection $jobs): ?int
    {
        $durations = $jobs
            ->where('status', 'completed')
            ->filter(fn (AnalysisJob $job) => $job->started_at && $job->completed_at)
            ->map(fn (AnalysisJob $job) => $job->started_at->diffInSeconds($job->completed_at));

        return $durations->isEmpty() ? null : (int) round($durations->avg());
    }
}
