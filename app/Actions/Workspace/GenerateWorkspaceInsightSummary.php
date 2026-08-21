<?php

namespace App\Actions\Workspace;

use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Ai\Agents\WorkspaceInsightSummaryAgent;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use App\Models\WorkspaceInsightSummary;
use Illuminate\Support\Collection;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Throwable;

class GenerateWorkspaceInsightSummary
{
    use AuthorizesWorkspaceAccess;

    private const MAX_VIDEOS = 200;

    /**
     * Generate a short AI narrative summarizing every analyzed video in a
     * workspace, grounded in each video's already-generated AI insights
     * rather than raw detection data. Requires workspace membership.
     * Manually triggered (not run automatically) so it doesn't burn an AI
     * call on every dashboard page view.
     */
    public function __invoke(User $user, Workspace $workspace): WorkspaceInsightSummary
    {
        $this->authorizeMembership($user, $workspace);

        $videos = $workspace->videos()
            ->whereHas('insights')
            ->with('latestInsight')
            ->latest()
            ->limit(self::MAX_VIDEOS)
            ->get();

        abort_if($videos->isEmpty(), 422, 'This workspace has no analyzed videos to summarize yet.');

        $result = $this->promptAgent($videos);

        return WorkspaceInsightSummary::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'summary' => $result['summary'],
            'highlights' => $result['highlights'],
        ]);
    }

    /**
     * Prompt the summary agent, retrying with backoff if the AI provider
     * rate limits the request or is temporarily overloaded - same policy as
     * the other insight-generating actions.
     *
     * @param  Collection<int, Video>  $videos
     * @return array<string, mixed>
     */
    private function promptAgent(Collection $videos): array
    {
        $data = [
            'video_count' => $videos->count(),
            'videos' => $videos->map(fn (Video $video) => [
                'object_detection' => $video->latestInsight?->object_detection,
                'threat_assessment' => $video->latestInsight?->threat_assessment,
                'moderation' => $video->latestInsight?->moderation,
                'text_detection' => $video->latestInsight?->text_detection,
            ])->all(),
        ];

        return retry(
            times: 3,
            callback: fn () => (new WorkspaceInsightSummaryAgent)->prompt(json_encode($data, JSON_PRETTY_PRINT))->toArray(),
            sleepMilliseconds: fn (int $attempt) => $attempt * 500,
            when: fn (Throwable $e) => $e instanceof RateLimitedException || $e instanceof ProviderOverloadedException,
        );
    }
}
