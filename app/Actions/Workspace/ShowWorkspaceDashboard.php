<?php

namespace App\Actions\Workspace;

use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Enums\AnalysisType;
use App\Enums\VideoStatus;
use App\Models\AnalysisJob;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoToolJob;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
     * Every number here comes from a targeted SQL query (COUNT/SUM/GROUP BY,
     * and a ROW_NUMBER() window query for "latest insight per video") rather
     * than loading every video/job/result/insight row into PHP and
     * aggregating with collection methods. That previous approach scaled
     * with total historical row count - which grows unbounded as videos get
     * reprocessed and re-analyzed - instead of with the workspace's actual
     * video count.
     *
     * @return array<string, mixed>
     */
    public function __invoke(User $user, Workspace $workspace): array
    {
        $this->authorizeMembership($user, $workspace);

        $videoIds = Video::where('workspace_id', $workspace->id)->pluck('id');
        $insightSummary = $workspace->latestInsightSummary;

        $videoStats = Video::where('workspace_id', $workspace->id)
            ->selectRaw('COUNT(*) as total_videos, COALESCE(SUM(size), 0) as total_storage_bytes')
            ->first();

        $jobStats = AnalysisJob::whereIn('video_id', $videoIds)
            ->selectRaw("
                SUM(status = 'completed') as completed_analyses,
                SUM(status IN ('pending', 'processing')) as processing_now,
                SUM(status = 'failed') as failed_jobs,
                SUM(flagged_for_review_at IS NOT NULL) as flagged_for_review,
                AVG(CASE WHEN status = 'completed' AND started_at IS NOT NULL AND completed_at IS NOT NULL
                    THEN {$this->secondsBetween('started_at', 'completed_at')} END) as avg_processing_seconds
            ")
            ->first();

        // Each fetched once and reused below - risk/moderation breakdowns
        // would otherwise re-run the same "latest per video" query insight_flags
        // already made.
        $threatRows = $this->latestNonNullInsightPerVideo($videoIds, 'threat_assessment');
        $moderationRows = $this->latestNonNullInsightPerVideo($videoIds, 'moderation');
        $aiContentRows = $this->latestNonNullInsightPerVideo($videoIds, 'ai_content_assessment');

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
                'total_videos' => (int) $videoStats->total_videos,
                'total_storage_bytes' => (int) $videoStats->total_storage_bytes,
                'completed_analyses' => (int) $jobStats->completed_analyses,
                'processing_now' => (int) $jobStats->processing_now,
                'failed_jobs' => (int) $jobStats->failed_jobs,
                'flagged_for_review' => (int) $jobStats->flagged_for_review,
                'avg_processing_seconds' => $jobStats->avg_processing_seconds === null
                    ? null
                    : (int) round($jobStats->avg_processing_seconds),
                'tool_jobs_run' => VideoToolJob::whereIn('video_id', $videoIds)->count(),
            ],
            'uploads_over_time' => $this->uploadsOverTime($workspace),
            'videos_by_status' => $this->videosByStatus($workspace),
            'jobs_by_type' => $this->jobsByType($videoIds),
            'top_labels' => $this->topLabels($videoIds),
            'insight_flags' => $this->insightFlags($threatRows, $moderationRows, $aiContentRows),
            'risk_level_breakdown' => $this->riskLevelBreakdown($threatRows),
            'moderation_severity_breakdown' => $this->moderationSeverityBreakdown($moderationRows),
            'needs_review' => $this->needsReviewItems($videoIds),
        ];
    }

    /**
     * SQL fragment computing the number of seconds between two datetime
     * columns. MySQL (dev/prod) and SQLite (the test suite's DB_CONNECTION,
     * see phpunit.xml) don't share a date-diff function, so this is the one
     * spot in this action that needs to branch by driver.
     */
    private function secondsBetween(string $start, string $end): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "(strftime('%s', {$end}) - strftime('%s', {$start}))",
            default => "TIMESTAMPDIFF(SECOND, {$start}, {$end})",
        };
    }

    /**
     * @return array<int, array{date: string, count: int}>
     */
    private function uploadsOverTime(Workspace $workspace): array
    {
        $windowStart = now()->subDays(self::UPLOADS_WINDOW_DAYS - 1)->startOfDay();

        $byDate = Video::where('workspace_id', $workspace->id)
            ->where('created_at', '>=', $windowStart)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        return collect(range(self::UPLOADS_WINDOW_DAYS - 1, 0))
            ->map(function (int $daysAgo) use ($byDate) {
                $date = now()->subDays($daysAgo)->toDateString();

                return ['date' => $date, 'count' => (int) $byDate->get($date, 0)];
            })
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function videosByStatus(Workspace $workspace): array
    {
        $counts = Video::where('workspace_id', $workspace->id)
            ->select('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return $this->countsByValues($counts, array_column(VideoStatus::cases(), 'value'));
    }

    /**
     * @param  Collection<int, int>  $videoIds
     * @return array<string, int>
     */
    private function jobsByType(Collection $videoIds): array
    {
        $counts = AnalysisJob::whereIn('video_id', $videoIds)
            ->select('type')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type');

        return $this->countsByValues($counts, [
            AnalysisType::ObjectDetection->value,
            AnalysisType::ThreatDetection->value,
            AnalysisType::ContentModeration->value,
            AnalysisType::TextDetection->value,
        ]);
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
        return collect($keys)->mapWithKeys(fn (string $key) => [$key => (int) $counts->get($key, 0)])->all();
    }

    /**
     * @param  Collection<int, int>  $videoIds
     * @return array<int, array{label: string, occurrences: int}>
     */
    private function topLabels(Collection $videoIds): array
    {
        if ($videoIds->isEmpty()) {
            return [];
        }

        return DB::table('analysis_results')
            ->join('analysis_jobs', 'analysis_jobs.id', '=', 'analysis_results.analysis_job_id')
            ->whereIn('analysis_jobs.video_id', $videoIds)
            ->select('analysis_results.label')
            ->selectRaw('SUM(analysis_results.occurrences) as occurrences')
            ->groupBy('analysis_results.label')
            ->orderByDesc('occurrences')
            ->limit(self::TOP_LABELS_LIMIT)
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'occurrences' => (int) $row->occurrences])
            ->all();
    }

    /**
     * The most recently flagged analysis jobs in this workspace - results a
     * human disputed as not matching what they saw in the video.
     *
     * @param  Collection<int, int>  $videoIds
     * @return array<int, array<string, mixed>>
     */
    private function needsReviewItems(Collection $videoIds): array
    {
        if ($videoIds->isEmpty()) {
            return [];
        }

        return AnalysisJob::whereIn('video_id', $videoIds)
            ->whereNotNull('flagged_for_review_at')
            ->with(['video:id,title', 'flaggedByUser:id,name'])
            ->orderByDesc('flagged_for_review_at')
            ->limit(self::NEEDS_REVIEW_LIMIT)
            ->get()
            ->map(fn (AnalysisJob $job) => [
                'video_id' => $job->video_id,
                'video_title' => $job->video->title ?? '',
                'type' => $job->type->value,
                'note' => $job->flagged_review_note,
                'flagged_by' => $job->flaggedByUser?->name,
                'flagged_at' => $job->flagged_for_review_at,
            ])
            ->all();
    }

    /**
     * One row per video (among $videoIds) holding that video's most recent
     * video_insights.$column value where the column isn't null, decoded from
     * JSON. Uses ROW_NUMBER() so only one row per video round-trips from the
     * database no matter how many insight rows that video has accumulated
     * over time (repeated regenerations, retries, etc).
     *
     * @param  Collection<int, int>  $videoIds
     * @return Collection<int, object{video_id: int, value: array<string, mixed>}>
     */
    private function latestNonNullInsightPerVideo(Collection $videoIds, string $column): Collection
    {
        if ($videoIds->isEmpty()) {
            return collect();
        }

        $ranked = DB::table('video_insights')
            ->select('video_id')
            ->selectRaw("{$column} as value")
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY video_id ORDER BY created_at DESC) as rn')
            ->whereIn('video_id', $videoIds)
            ->whereNotNull($column);

        return DB::query()
            ->fromSub($ranked, 'ranked')
            ->where('rn', 1)
            ->get()
            ->map(fn ($row) => (object) [
                'video_id' => $row->video_id,
                'value' => json_decode($row->value, true),
            ]);
    }

    /**
     * Counts videos whose most recently generated insight of each kind flags
     * a threat or moderation issue. Scoped insight generation can leave one
     * kind null on the latest row, so each kind looks at its own latest
     * non-null value rather than only the single latest row.
     *
     * @param  Collection<int, object{video_id: int, value: array<string, mixed>}>  $threatRows
     * @param  Collection<int, object{video_id: int, value: array<string, mixed>}>  $moderationRows
     * @param  Collection<int, object{video_id: int, value: array<string, mixed>}>  $aiContentRows
     * @return array{threats_detected: int, flagged_moderation: int, ai_generated_content_flagged: int}
     */
    private function insightFlags(Collection $threatRows, Collection $moderationRows, Collection $aiContentRows): array
    {
        return [
            'threats_detected' => $threatRows->filter(fn ($row) => $row->value['threat_detected'] ?? false)->count(),
            'flagged_moderation' => $moderationRows->filter(fn ($row) => ($row->value['status'] ?? 'SAFE') !== 'SAFE')->count(),
            'ai_generated_content_flagged' => $aiContentRows->filter(fn ($row) => ($row->value['verdict'] ?? null) === 'AI_GENERATED')->count(),
        ];
    }

    /**
     * Each video's latest threat assessment, grouped by risk level. Videos
     * with no threat assessment generated yet are excluded entirely, same
     * "latest non-null value" rule as insightFlags().
     *
     * @param  Collection<int, object{video_id: int, value: array<string, mixed>}>  $threatRows
     * @return array<string, int>
     */
    private function riskLevelBreakdown(Collection $threatRows): array
    {
        $counts = $threatRows->countBy(fn ($row) => $row->value['risk_level'] ?? null);

        return $this->countsByValues($counts, ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL']);
    }

    /**
     * Each video's latest moderation assessment, grouped by severity. Videos
     * with no moderation assessment generated yet are excluded entirely.
     *
     * @param  Collection<int, object{video_id: int, value: array<string, mixed>}>  $moderationRows
     * @return array<string, int>
     */
    private function moderationSeverityBreakdown(Collection $moderationRows): array
    {
        $counts = $moderationRows->countBy(fn ($row) => $row->value['severity'] ?? null);

        return $this->countsByValues($counts, ['NONE', 'LOW', 'MEDIUM', 'HIGH']);
    }
}
