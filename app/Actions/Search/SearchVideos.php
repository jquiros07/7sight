<?php

namespace App\Actions\Search;

use App\Ai\Agents\VideoSearchAgent;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Throwable;

class SearchVideos
{
    private const MAX_CANDIDATES = 200;

    /**
     * How many embedding-ranked candidates get sent to the search agent for
     * relevance/reason ranking. Keeps that single Gemini call's prompt small
     * regardless of how many analyzed videos exist.
     */
    private const TOP_K = 15;

    /**
     * Search the user's analyzed videos with a free-text query. Uses a single
     * AI call over each video's already-generated insights - no new
     * Rekognition or per-video AI calls - so only videos with a generated
     * insight are searchable, across every workspace the user belongs to.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function __invoke(User $user, array $input): array
    {
        $validated = Validator::make($input, [
            'query' => ['required', 'string', 'min:3', 'max:500'],
        ])->validate();

        $query = $validated['query'];

        $workspaceIds = $user->workspaces()->pluck('workspaces.id');

        $videos = Video::query()
            ->whereIn('workspace_id', $workspaceIds)
            ->whereHas('insights')
            ->with(['workspace:id,name', 'latestInsight'])
            ->latest()
            ->limit(self::MAX_CANDIDATES)
            ->get();

        if ($videos->isEmpty()) {
            return ['query' => $query, 'candidates_searched' => 0, 'matches' => []];
        }

        $topCandidates = $this->rankByEmbeddingSimilarity($query, $videos);

        $result = $this->promptForMatches($query, $topCandidates);

        return [
            'query' => $query,
            'candidates_searched' => $videos->count(),
            'matches' => $this->resolveMatches($result['matches'] ?? [], $videos),
        ];
    }

    /**
     * Narrow the full candidate pool down to the TOP_K most semantically
     * similar to the query, using each video's precomputed insight
     * embedding - so the search agent only has to rank/explain a handful of
     * videos instead of every analyzed video in the workspace. Candidates
     * without an embedding yet (not yet backfilled, or embedding generation
     * failed) are excluded from ranking; if none have one, falls back to the
     * most recent TOP_K so search still returns something.
     *
     * @param  Collection<int, Video>  $videos
     * @return Collection<int, Video>
     */
    private function rankByEmbeddingSimilarity(string $query, Collection $videos): Collection
    {
        $embedded = $videos->filter(fn (Video $video) => $video->latestInsight?->embedding !== null);

        if ($embedded->isEmpty()) {
            return $videos->take(self::TOP_K);
        }

        $queryEmbedding = Embeddings::for([$query])->generate(Lab::Gemini)->first();

        return $embedded
            ->sortByDesc(fn (Video $video) => $this->cosineSimilarity($queryEmbedding, $video->latestInsight->embedding))
            ->take(self::TOP_K)
            ->values();
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $value) {
            $dot += $value * $b[$i];
            $normA += $value ** 2;
            $normB += $b[$i] ** 2;
        }

        return $dot / ((sqrt($normA) * sqrt($normB)) ?: 1);
    }

    /**
     * Prompt the search agent, retrying with backoff if the AI provider rate
     * limits the request or is temporarily overloaded - same policy as
     * GenerateVideoInsights.
     *
     * @param  Collection<int, Video>  $videos
     * @return array<string, mixed>
     */
    private function promptForMatches(string $query, Collection $videos): array
    {
        $data = [
            'query' => $query,
            'videos' => $videos->map(fn (Video $video) => [
                'video_id' => $video->id,
                'object_detection' => $video->latestInsight?->object_detection,
                'threat_assessment' => $video->latestInsight?->threat_assessment,
                'moderation' => $video->latestInsight?->moderation,
            ])->all(),
        ];

        return retry(
            times: 3,
            callback: fn () => (new VideoSearchAgent)->prompt(json_encode($data, JSON_PRETTY_PRINT))->toArray(),
            sleepMilliseconds: fn (int $attempt) => $attempt * 2000,
            when: fn (Throwable $e) => $e instanceof RateLimitedException || $e instanceof ProviderOverloadedException,
        );
    }

    /**
     * Maps the agent's matches back to our own video/workspace data rather
     * than trusting it to echo details correctly, and silently drops any
     * video_id that doesn't correspond to a real candidate.
     *
     * @param  array<int, array<string, mixed>>  $matches
     * @param  Collection<int, Video>  $videos
     * @return array<int, array<string, mixed>>
     */
    private function resolveMatches(array $matches, Collection $videos): array
    {
        $videosById = $videos->keyBy('id');

        return collect($matches)
            ->filter(fn (array $match) => isset($match['video_id']) && $videosById->has($match['video_id']))
            ->map(function (array $match) use ($videosById) {
                $video = $videosById[$match['video_id']];

                return [
                    'video_id' => $video->id,
                    'title' => $video->title,
                    'workspace_name' => $video->workspace->name,
                    'status' => $video->status->value,
                    'relevance' => $match['relevance'] ?? 'LOW',
                    'reason' => $match['reason'] ?? '',
                ];
            })
            ->values()
            ->all();
    }
}
