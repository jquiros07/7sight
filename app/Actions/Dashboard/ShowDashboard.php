<?php

namespace App\Actions\Dashboard;

use App\Enums\VideoStatus;
use App\Models\AnalysisJob;
use App\Models\AnalysisResult;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoToolJob;
use App\Models\Workspace;
use Illuminate\Support\Collection;

class ShowDashboard
{
    private const UPLOADS_WINDOW_DAYS = 14;

    private const SPOTLIGHT_LIMIT = 5;

    private const ACTIVITY_LIMIT = 8;

    private const NEEDS_REVIEW_LIMIT = 5;

    private const TOP_LABELS_LIMIT = 8;

    /**
     * A video sitting in "processing" without any of its jobs completing (or
     * failing) for this long is treated as stuck - recompute_video_status()
     * touches the video's updated_at every time any of its jobs finishes, so
     * a stale updated_at means no progress has happened, not just a long
     * video still legitimately being analyzed.
     */
    private const STUCK_PROCESSING_MINUTES = 30;

    private const RISK_RANK = ['CRITICAL' => 4, 'HIGH' => 3, 'MEDIUM' => 2, 'LOW' => 1];

    private const MODERATION_RANK = ['HIGH' => 3, 'MEDIUM' => 2, 'LOW' => 1, 'NONE' => 0];

    /**
     * AI-generated-content findings only carry a confidence score, not a
     * risk-level label like threat/moderation do - bucketed here onto the
     * same HIGH/MEDIUM/LOW scale so it sorts fairly against those signals.
     */
    private const AI_CONTENT_CONFIDENCE_SEVERITY = [
        [80, 'HIGH', 3],
        [50, 'MEDIUM', 2],
        [0, 'LOW', 1],
    ];

    /**
     * Cross-workspace overview for the given user: account-wide totals, a
     * safety spotlight surfacing the videos most worth a human's attention,
     * a workspace leaderboard, and a recent cross-workspace activity feed.
     * Unlike the per-workspace dashboard, this only looks at data that spans
     * or compares across workspaces.
     *
     * @return array<string, mixed>
     */
    public function __invoke(User $user): array
    {
        $workspaces = $user->workspaces()->get();
        $workspaceNames = $workspaces->pluck('name', 'id');
        $workspaceIds = $workspaces->pluck('id');

        $videos = Video::query()
            ->select(['id', 'workspace_id', 'title', 'status', 'size', 'created_at', 'updated_at'])
            ->whereIn('workspace_id', $workspaceIds)
            ->withCount('inquiries')
            ->with([
                'insights:id,video_id,threat_assessment,moderation,ai_content_assessment,created_at',
                'analysisJobs:id,video_id,type,flagged_for_review_at,flagged_by,flagged_review_note',
                'analysisJobs.flaggedByUser:id,name',
            ])
            ->get();

        $signals = $this->flaggedSignals($videos, $workspaceNames);
        $flaggedJobs = $videos->flatMap->analysisJobs->whereNotNull('flagged_for_review_at');

        $stats = [
            'total_workspaces' => $workspaces->count(),
            'total_videos' => $videos->count(),
            'total_storage_bytes' => (int) $videos->sum('size'),
            'failed_videos' => $videos->where('status', VideoStatus::Failed)->count(),
            'processing_videos' => $videos->where('status', VideoStatus::Processing)->count(),
            'stuck_processing_videos' => $this->stuckProcessingVideos($videos),
            'total_inquiries' => (int) $videos->sum('inquiries_count'),
            'flagged_for_review' => $flaggedJobs->count(),
            'tool_jobs_run' => VideoToolJob::whereIn('video_id', $videos->pluck('id'))->count(),
        ];

        $uploadsOverTime = $this->uploadsOverTime($videos);

        $safetySpotlight = [
            'threats_detected' => $signals->where('type', 'threat')->count(),
            'flagged_moderation' => $signals->where('type', 'moderation')->count(),
            'ai_content_flagged' => $signals->where('type', 'ai_content')->count(),
            'items' => $this->topSignals($signals),
        ];

        return [
            'stats' => $stats,
            'uploads_over_time' => $uploadsOverTime,
            'safety_spotlight' => $safetySpotlight,
            'workspace_leaderboard' => $this->workspaceLeaderboard($workspaces, $videos, $signals),
            'recent_activity' => $this->recentActivity($videos, $workspaceNames),
            'top_labels' => $this->topLabels($workspaceIds),
            'needs_review' => $this->needsReviewItems($flaggedJobs, $videos, $workspaceNames),
            // Computed once here rather than duplicated in the React page and the
            // PDF report - both render this exact same list, so they can't drift
            // apart the way the hand-maintained copies just did.
            'suggestions' => $this->buildSuggestions($stats, $safetySpotlight, $uploadsOverTime),
        ];
    }

    /**
     * Small deterministic tips derived from stats already computed above - no
     * extra AI call. Shared verbatim by the Dashboard page and the PDF report.
     *
     * @param  array<string, mixed>  $stats
     * @param  array<string, mixed>  $safetySpotlight
     * @param  array<int, array{date: string, count: int}>  $uploadsOverTime
     * @return array<int, array{type: string, tone: string, text: string}>
     */
    private function buildSuggestions(array $stats, array $safetySpotlight, array $uploadsOverTime): array
    {
        if ($stats['total_videos'] === 0) {
            return [];
        }

        $suggestions = [];

        if ($stats['failed_videos'] > 0) {
            $n = $stats['failed_videos'];
            $suggestions[] = [
                'type' => 'failed_videos',
                'tone' => 'warning',
                'text' => "{$n} video".($n === 1 ? '' : 's').' failed analysis — review and retry '.($n === 1 ? 'it' : 'them').'.',
            ];
        }

        if ($stats['stuck_processing_videos'] > 0) {
            $n = $stats['stuck_processing_videos'];
            $suggestions[] = [
                'type' => 'stuck_processing',
                'tone' => 'warning',
                'text' => "{$n} video".($n === 1 ? '' : 's').' stuck processing for over 30 minutes — may need a retry.',
            ];
        }

        $flagged = $safetySpotlight['threats_detected'] + $safetySpotlight['flagged_moderation'] + $safetySpotlight['ai_content_flagged'];

        if ($flagged > 0) {
            $suggestions[] = [
                'type' => 'safety_flagged',
                'tone' => 'warning',
                'text' => "{$flagged} video".($flagged === 1 ? '' : 's').' flagged for safety — see the spotlight.',
            ];
        } else {
            $suggestions[] = [
                'type' => 'safety_clear',
                'tone' => 'success',
                'text' => 'No safety flags across your workspaces — all clear.',
            ];
        }

        if ($stats['flagged_for_review'] > 0) {
            $n = $stats['flagged_for_review'];
            $suggestions[] = [
                'type' => 'needs_review',
                'tone' => 'warning',
                'text' => "{$n} analysis result".($n === 1 ? '' : 's').' flagged for human review — see Needs review.',
            ];
        }

        if ($stats['processing_videos'] > 0) {
            $n = $stats['processing_videos'];
            $suggestions[] = [
                'type' => 'processing',
                'tone' => 'info',
                'text' => "{$n} video".($n === 1 ? '' : 's').' currently being analyzed.',
            ];
        }

        $recentUploads = array_sum(array_column($uploadsOverTime, 'count'));

        if ($recentUploads === 0) {
            $suggestions[] = [
                'type' => 'no_uploads',
                'tone' => 'info',
                'text' => 'No uploads in the past 14 days.',
            ];
        }

        return $suggestions;
    }

    /**
     * The most recently flagged analysis jobs across every workspace the
     * user belongs to - results a human disputed as not matching what they
     * saw in the video.
     *
     * @param  Collection<int, AnalysisJob>  $flaggedJobs
     * @param  Collection<int, Video>  $videos
     * @param  Collection<int, string>  $workspaceNames
     * @return array<int, array<string, mixed>>
     */
    private function needsReviewItems(Collection $flaggedJobs, Collection $videos, Collection $workspaceNames): array
    {
        $videoTitles = $videos->pluck('title', 'id');
        $videoWorkspaceIds = $videos->pluck('workspace_id', 'id');

        return $flaggedJobs
            ->sortByDesc('flagged_for_review_at')
            ->take(self::NEEDS_REVIEW_LIMIT)
            ->map(fn (AnalysisJob $job) => [
                'video_id' => $job->video_id,
                'video_title' => $videoTitles[$job->video_id] ?? '',
                'workspace_name' => $workspaceNames[$videoWorkspaceIds[$job->video_id] ?? null] ?? '',
                'type' => $job->type->value,
                'note' => $job->flagged_review_note,
                'flagged_by' => $job->flaggedByUser?->name,
                'flagged_at' => $job->flagged_for_review_at,
            ])
            ->values()
            ->all();
    }

    /**
     * The most frequent detection labels across every video in every
     * workspace the user belongs to. A DB-level aggregate rather than
     * loading every AnalysisResult row into PHP - unlike $videos above,
     * nothing else in this action needs the raw result rows, so there's no
     * reason to pull them into memory just to sum them here.
     *
     * @param  Collection<int, int>  $workspaceIds
     * @return array<int, array{label: string, occurrences: int}>
     */
    private function topLabels(Collection $workspaceIds): array
    {
        return AnalysisResult::query()
            ->join('analysis_jobs', 'analysis_jobs.id', '=', 'analysis_results.analysis_job_id')
            ->join('videos', 'videos.id', '=', 'analysis_jobs.video_id')
            ->whereIn('videos.workspace_id', $workspaceIds)
            ->whereNull('videos.deleted_at')
            ->selectRaw('analysis_results.label as label, SUM(analysis_results.occurrences) as occurrences')
            ->groupBy('analysis_results.label')
            ->orderByDesc('occurrences')
            ->limit(self::TOP_LABELS_LIMIT)
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'occurrences' => (int) $row->occurrences])
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
     * @param  Collection<int, Video>  $videos
     */
    private function stuckProcessingVideos(Collection $videos): int
    {
        $staleSince = now()->subMinutes(self::STUCK_PROCESSING_MINUTES);

        return $videos
            ->where('status', VideoStatus::Processing)
            ->filter(fn (Video $video) => $video->updated_at->lt($staleSince))
            ->count();
    }

    /**
     * Every video's latest threat/moderation flag, each as its own "signal" so
     * a video can surface for both reasons. Feeds the spotlight counts, the
     * spotlight list, and the leaderboard's flagged count off one pass.
     *
     * @param  Collection<int, Video>  $videos
     * @param  Collection<int, string>  $workspaceNames
     * @return Collection<int, array<string, mixed>>
     */
    private function flaggedSignals(Collection $videos, Collection $workspaceNames): Collection
    {
        $signals = collect();

        foreach ($videos as $video) {
            $latestThreat = $video->insights->whereNotNull('threat_assessment')->sortByDesc('created_at')->first();
            $latestModeration = $video->insights->whereNotNull('moderation')->sortByDesc('created_at')->first();
            $latestAiContent = $video->insights->whereNotNull('ai_content_assessment')->sortByDesc('created_at')->first();

            if ($latestThreat && ($latestThreat->threat_assessment['threat_detected'] ?? false)) {
                $riskLevel = $latestThreat->threat_assessment['risk_level'] ?? 'LOW';

                $signals->push([
                    'type' => 'threat',
                    'video_id' => $video->id,
                    'video_title' => $video->title,
                    'workspace_id' => $video->workspace_id,
                    'workspace_name' => $workspaceNames[$video->workspace_id] ?? '',
                    'severity' => $riskLevel,
                    'rank' => self::RISK_RANK[$riskLevel] ?? 0,
                    'detected_at' => $latestThreat->created_at,
                ]);
            }

            if ($latestModeration && ($latestModeration->moderation['status'] ?? 'SAFE') !== 'SAFE') {
                $severity = $latestModeration->moderation['severity'] ?? 'NONE';

                $signals->push([
                    'type' => 'moderation',
                    'video_id' => $video->id,
                    'video_title' => $video->title,
                    'workspace_id' => $video->workspace_id,
                    'workspace_name' => $workspaceNames[$video->workspace_id] ?? '',
                    'severity' => $severity,
                    'rank' => self::MODERATION_RANK[$severity] ?? 0,
                    'detected_at' => $latestModeration->created_at,
                ]);
            }

            if ($latestAiContent && ($latestAiContent->ai_content_assessment['verdict'] ?? null) === 'AI_GENERATED') {
                [$severity, $rank] = $this->aiContentSeverity($latestAiContent->ai_content_assessment['confidence'] ?? 0);

                $signals->push([
                    'type' => 'ai_content',
                    'video_id' => $video->id,
                    'video_title' => $video->title,
                    'workspace_id' => $video->workspace_id,
                    'workspace_name' => $workspaceNames[$video->workspace_id] ?? '',
                    'severity' => $severity,
                    'rank' => $rank,
                    'detected_at' => $latestAiContent->created_at,
                ]);
            }
        }

        return $signals;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function aiContentSeverity(int $confidence): array
    {
        foreach (self::AI_CONTENT_CONFIDENCE_SEVERITY as [$threshold, $severity, $rank]) {
            if ($confidence >= $threshold) {
                return [$severity, $rank];
            }
        }

        return ['LOW', 1];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $signals
     * @return array<int, array<string, mixed>>
     */
    private function topSignals(Collection $signals): array
    {
        return $signals
            ->sortBy([['rank', 'desc'], ['detected_at', 'desc']])
            ->take(self::SPOTLIGHT_LIMIT)
            ->map(fn (array $signal) => [
                'video_id' => $signal['video_id'],
                'video_title' => $signal['video_title'],
                'workspace_name' => $signal['workspace_name'],
                'type' => $signal['type'],
                'severity' => $signal['severity'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Workspace>  $workspaces
     * @param  Collection<int, Video>  $videos
     * @param  Collection<int, array<string, mixed>>  $signals
     * @return array<int, array<string, mixed>>
     */
    private function workspaceLeaderboard(Collection $workspaces, Collection $videos, Collection $signals): array
    {
        return $workspaces
            ->map(function (Workspace $workspace) use ($videos, $signals) {
                $workspaceVideos = $videos->where('workspace_id', $workspace->id);

                return [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'total_videos' => $workspaceVideos->count(),
                    'failed_videos' => $workspaceVideos->where('status', VideoStatus::Failed)->count(),
                    'flagged_count' => $signals->where('workspace_id', $workspace->id)->count(),
                    'last_activity_at' => $workspaceVideos->sortByDesc('created_at')->first()?->created_at,
                ];
            })
            ->sortByDesc('total_videos')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Video>  $videos
     * @param  Collection<int, string>  $workspaceNames
     * @return array<int, array<string, mixed>>
     */
    private function recentActivity(Collection $videos, Collection $workspaceNames): array
    {
        return $videos
            ->sortByDesc('created_at')
            ->take(self::ACTIVITY_LIMIT)
            ->map(fn (Video $video) => [
                'id' => $video->id,
                'title' => $video->title,
                'workspace_name' => $workspaceNames[$video->workspace_id] ?? '',
                'status' => $video->status->value,
                'created_at' => $video->created_at,
            ])
            ->values()
            ->all();
    }
}
