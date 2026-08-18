<?php

namespace App\Actions\Dashboard;

use App\Enums\VideoStatus;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use Illuminate\Support\Collection;

class ShowDashboard
{
    private const UPLOADS_WINDOW_DAYS = 14;

    private const SPOTLIGHT_LIMIT = 5;

    private const ACTIVITY_LIMIT = 8;

    private const RISK_RANK = ['CRITICAL' => 4, 'HIGH' => 3, 'MEDIUM' => 2, 'LOW' => 1];

    private const MODERATION_RANK = ['HIGH' => 3, 'MEDIUM' => 2, 'LOW' => 1, 'NONE' => 0];

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

        $videos = Video::query()
            ->whereIn('workspace_id', $workspaces->pluck('id'))
            ->with('insights')
            ->get();

        $signals = $this->flaggedSignals($videos, $workspaceNames);

        return [
            'stats' => [
                'total_workspaces' => $workspaces->count(),
                'total_videos' => $videos->count(),
                'total_storage_bytes' => (int) $videos->sum('size'),
                'failed_videos' => $videos->where('status', VideoStatus::Failed)->count(),
                'processing_videos' => $videos->where('status', VideoStatus::Processing)->count(),
            ],
            'uploads_over_time' => $this->uploadsOverTime($videos),
            'safety_spotlight' => [
                'threats_detected' => $signals->where('type', 'threat')->count(),
                'flagged_moderation' => $signals->where('type', 'moderation')->count(),
                'items' => $this->topSignals($signals),
            ],
            'workspace_leaderboard' => $this->workspaceLeaderboard($workspaces, $videos, $signals),
            'recent_activity' => $this->recentActivity($videos, $workspaceNames),
        ];
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
        }

        return $signals;
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
