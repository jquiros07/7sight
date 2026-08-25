<?php

namespace Tests\Feature\Actions\Video;

use App\Actions\Video\ListVideos;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListVideosTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_only_lists_videos_in_the_users_workspaces(): void
    {
        $user = User::factory()->create();
        $ownWorkspace = Workspace::factory()->create();
        $this->assignWorkspaceRole($ownWorkspace, $user, 'owner');
        $ownVideo = Video::factory()->create(['workspace_id' => $ownWorkspace->id]);
        Video::factory()->create();

        $results = (new ListVideos)($user);

        $this->assertCount(1, $results);
        $this->assertSame($ownVideo->id, $results->first()->id);
    }

    public function test_it_filters_by_search_term_matching_the_title(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $this->assignWorkspaceRole($workspace, $user, 'owner');
        $matching = Video::factory()->create(['workspace_id' => $workspace->id, 'title' => 'Loading Dock Camera']);
        Video::factory()->create(['workspace_id' => $workspace->id, 'title' => 'Front Entrance']);

        $results = (new ListVideos)($user, ['search' => 'Loading Dock']);

        $this->assertCount(1, $results);
        $this->assertSame($matching->id, $results->first()->id);
    }

    public function test_it_filters_by_duration_range(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $this->assignWorkspaceRole($workspace, $user, 'owner');
        $short = Video::factory()->create(['workspace_id' => $workspace->id, 'duration_seconds' => 30]);
        Video::factory()->create(['workspace_id' => $workspace->id, 'duration_seconds' => 600]);

        $results = (new ListVideos)($user, ['duration_min' => 10, 'duration_max' => 60]);

        $this->assertCount(1, $results);
        $this->assertSame($short->id, $results->first()->id);
    }

    public function test_it_sorts_by_the_requested_column_and_direction(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $this->assignWorkspaceRole($workspace, $user, 'owner');
        Video::factory()->create(['workspace_id' => $workspace->id, 'title' => 'B Video']);
        Video::factory()->create(['workspace_id' => $workspace->id, 'title' => 'A Video']);

        $results = (new ListVideos)($user, ['sort' => 'title', 'direction' => 'asc']);

        $this->assertSame(['A Video', 'B Video'], $results->pluck('title')->all());
    }

    public function test_it_caps_the_per_page_at_100(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $this->assignWorkspaceRole($workspace, $user, 'owner');
        Video::factory()->count(3)->create(['workspace_id' => $workspace->id]);

        $results = (new ListVideos)($user, ['per_page' => 500]);

        $this->assertSame(100, $results->perPage());
    }
}
