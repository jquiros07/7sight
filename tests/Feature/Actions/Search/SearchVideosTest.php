<?php

namespace Tests\Feature\Actions\Search;

use App\Actions\Search\SearchVideos;
use App\Ai\Agents\VideoSearchAgent;
use App\Enums\VideoStatus;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoInsight;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Embeddings;
use Tests\TestCase;

class SearchVideosTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_requires_a_query_of_at_least_three_characters(): void
    {
        $user = User::factory()->create();

        $this->expectException(ValidationException::class);

        (new SearchVideos)($user, ['query' => 'ab']);
    }

    public function test_it_returns_no_matches_when_the_user_has_no_analyzed_videos(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $this->assignWorkspaceRole($workspace, $user, 'owner');
        Video::factory()->create(['workspace_id' => $workspace->id]);

        $result = (new SearchVideos)($user, ['query' => 'forklift']);

        $this->assertSame(0, $result['candidates_searched']);
        $this->assertSame([], $result['matches']);
    }

    public function test_it_only_searches_videos_in_the_users_workspaces(): void
    {
        VideoSearchAgent::fake([['matches' => []]]);

        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $this->assignWorkspaceRole($workspace, $user, 'owner');
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        VideoInsight::create(['video_id' => $video->id, 'object_detection' => ['summary' => 'a forklift']]);

        $otherWorkspace = Workspace::factory()->create();
        $otherVideo = Video::factory()->create(['workspace_id' => $otherWorkspace->id]);
        VideoInsight::create(['video_id' => $otherVideo->id, 'object_detection' => ['summary' => 'a forklift']]);

        $result = (new SearchVideos)($user, ['query' => 'forklift']);

        $this->assertSame(1, $result['candidates_searched']);
    }

    public function test_it_maps_agent_matches_back_to_known_video_data(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['name' => 'Warehouse']);
        $this->assignWorkspaceRole($workspace, $user, 'owner');
        $video = Video::factory()->create([
            'workspace_id' => $workspace->id,
            'title' => 'Loading Dock',
            'status' => VideoStatus::Ready,
        ]);
        VideoInsight::create(['video_id' => $video->id, 'object_detection' => ['summary' => 'a forklift']]);

        VideoSearchAgent::fake([[
            'matches' => [
                ['video_id' => $video->id, 'relevance' => 'HIGH', 'reason' => 'Shows a forklift.'],
            ],
        ]]);

        $result = (new SearchVideos)($user, ['query' => 'forklift']);

        $this->assertSame([[
            'video_id' => $video->id,
            'title' => 'Loading Dock',
            'workspace_name' => 'Warehouse',
            'status' => 'ready',
            'relevance' => 'HIGH',
            'reason' => 'Shows a forklift.',
        ]], $result['matches']);
    }

    public function test_it_drops_matches_for_video_ids_that_are_not_real_candidates(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $this->assignWorkspaceRole($workspace, $user, 'owner');
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        VideoInsight::create(['video_id' => $video->id, 'object_detection' => ['summary' => 'a forklift']]);

        VideoSearchAgent::fake([[
            'matches' => [
                ['video_id' => $video->id + 999, 'relevance' => 'HIGH', 'reason' => 'Hallucinated.'],
            ],
        ]]);

        $result = (new SearchVideos)($user, ['query' => 'forklift']);

        $this->assertSame([], $result['matches']);
    }

    public function test_it_sends_the_query_to_the_search_agent(): void
    {
        VideoSearchAgent::fake([['matches' => []]]);

        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $this->assignWorkspaceRole($workspace, $user, 'owner');
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        VideoInsight::create(['video_id' => $video->id, 'object_detection' => ['summary' => 'a forklift']]);

        (new SearchVideos)($user, ['query' => 'forklift near the dock']);

        VideoSearchAgent::assertPrompted(
            fn ($prompt) => str_contains($prompt->prompt, 'forklift near the dock')
        );
    }

    public function test_it_only_ranks_candidates_that_have_an_embedding(): void
    {
        Embeddings::fake([[[0.5, 0.5]]]);
        VideoSearchAgent::fake([['matches' => []]]);

        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $this->assignWorkspaceRole($workspace, $user, 'owner');

        $embeddedVideo = Video::factory()->create(['workspace_id' => $workspace->id]);
        VideoInsight::create([
            'video_id' => $embeddedVideo->id,
            'object_detection' => ['summary' => 'a forklift'],
            'embedding' => [0.5, 0.5],
        ]);

        $unembeddedVideo = Video::factory()->create(['workspace_id' => $workspace->id]);
        VideoInsight::create([
            'video_id' => $unembeddedVideo->id,
            'object_detection' => ['summary' => 'a forklift'],
        ]);

        (new SearchVideos)($user, ['query' => 'forklift']);

        VideoSearchAgent::assertPrompted(function ($prompt) use ($embeddedVideo, $unembeddedVideo) {
            $videoIds = array_column(json_decode($prompt->prompt, true)['videos'], 'video_id');

            return in_array($embeddedVideo->id, $videoIds, true)
                && ! in_array($unembeddedVideo->id, $videoIds, true);
        });
    }

    public function test_it_caps_ranked_candidates_to_the_top_k_most_similar(): void
    {
        $farEmbedding = [1.0, 0.0];
        $nearEmbedding = [0.0, 1.0];

        Embeddings::fake([[$nearEmbedding]]);
        VideoSearchAgent::fake([['matches' => []]]);

        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $this->assignWorkspaceRole($workspace, $user, 'owner');

        Video::factory()->count(16)->create(['workspace_id' => $workspace->id])
            ->each(fn (Video $video) => VideoInsight::create([
                'video_id' => $video->id,
                'object_detection' => ['summary' => 'noise'],
                'embedding' => $farEmbedding,
            ]));

        $bestMatch = Video::factory()->create(['workspace_id' => $workspace->id]);
        VideoInsight::create([
            'video_id' => $bestMatch->id,
            'object_detection' => ['summary' => 'forklift'],
            'embedding' => $nearEmbedding,
        ]);

        (new SearchVideos)($user, ['query' => 'forklift']);

        VideoSearchAgent::assertPrompted(function ($prompt) use ($bestMatch) {
            $videoIds = array_column(json_decode($prompt->prompt, true)['videos'], 'video_id');

            return count($videoIds) === 15 && in_array($bestMatch->id, $videoIds, true);
        });
    }
}
