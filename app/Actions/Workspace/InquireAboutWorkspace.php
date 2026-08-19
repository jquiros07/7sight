<?php

namespace App\Actions\Workspace;

use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Ai\Agents\WorkspaceInquiryAgent;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use App\Models\WorkspaceInquiry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Throwable;

class InquireAboutWorkspace
{
    use AuthorizesWorkspaceAccess;

    private const MAX_CANDIDATES = 200;

    /**
     * Ask a free-text question spanning every analyzed video in a workspace,
     * grounded in each video's already-generated AI insights rather than raw
     * detection data - there's too much of that to fit in one prompt across
     * many videos, unlike single-video Inquire. Requires workspace
     * membership.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function __invoke(User $user, Workspace $workspace, array $input): WorkspaceInquiry
    {
        $this->authorizeMembership($user, $workspace);

        $validated = Validator::make($input, [
            'question' => ['required', 'string', 'min:3', 'max:500'],
        ])->validate();

        $videos = $workspace->videos()
            ->whereHas('insights')
            ->with('latestInsight')
            ->latest()
            ->limit(self::MAX_CANDIDATES)
            ->get();

        abort_if($videos->isEmpty(), 422, 'This workspace has no analyzed videos to investigate yet.');

        return WorkspaceInquiry::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'question' => $validated['question'],
            'answer' => $this->promptAgent($validated['question'], $videos),
        ]);
    }

    /**
     * Prompt the workspace inquiry agent, retrying with backoff if the AI
     * provider rate limits the request or is temporarily overloaded - same
     * policy as InquireAboutVideo.
     *
     * @param  Collection<int, Video>  $videos
     * @return array<string, mixed>
     */
    private function promptAgent(string $question, Collection $videos): array
    {
        $data = [
            'question' => $question,
            'videos' => $videos->map(fn (Video $video) => [
                'video_id' => $video->id,
                'object_detection' => $video->latestInsight?->object_detection,
                'threat_assessment' => $video->latestInsight?->threat_assessment,
                'moderation' => $video->latestInsight?->moderation,
            ])->all(),
        ];

        $result = retry(
            times: 3,
            callback: fn () => (new WorkspaceInquiryAgent)->prompt(json_encode($data, JSON_PRETTY_PRINT))->toArray(),
            sleepMilliseconds: fn (int $attempt) => $attempt * 500,
            when: fn (Throwable $e) => $e instanceof RateLimitedException || $e instanceof ProviderOverloadedException,
        );

        return [
            ...$result,
            'video_citations' => $this->resolveCitations($result['video_citations'] ?? [], $videos),
        ];
    }

    /**
     * Maps the agent's citations back to our own video data rather than
     * trusting it to echo details correctly, and silently drops any
     * video_id that doesn't correspond to a real candidate - same rule
     * SearchVideos uses.
     *
     * @param  array<int, array<string, mixed>>  $citations
     * @param  Collection<int, Video>  $videos
     * @return array<int, array<string, mixed>>
     */
    private function resolveCitations(array $citations, Collection $videos): array
    {
        $videosById = $videos->keyBy('id');

        return collect($citations)
            ->filter(fn (array $citation) => isset($citation['video_id']) && $videosById->has($citation['video_id']))
            ->map(function (array $citation) use ($videosById) {
                $video = $videosById[$citation['video_id']];

                return [
                    'video_id' => $video->id,
                    'video_title' => $video->title,
                    'note' => $citation['note'] ?? '',
                ];
            })
            ->values()
            ->all();
    }
}
