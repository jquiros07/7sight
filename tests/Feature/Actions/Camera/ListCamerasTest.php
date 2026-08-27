<?php

namespace Tests\Feature\Actions\Camera;

use App\Actions\Camera\ListCameras;
use App\Models\Camera;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ListCamerasTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_cameras_with_live_status_and_hls_url(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $user, 'member');

        $live = Camera::factory()->create(['workspace_id' => $workspace->id, 'name' => 'B Camera']);
        $offline = Camera::factory()->create(['workspace_id' => $workspace->id, 'name' => 'A Camera']);

        Http::fake(['*/v3/paths/list' => Http::response([
            'items' => [
                ['name' => "camera-{$live->id}", 'ready' => true],
            ],
        ])]);

        $result = (app(ListCameras::class))($user);

        $byId = collect($result)->keyBy('id');

        $this->assertTrue($byId[$live->id]['is_live']);
        $this->assertFalse($byId[$offline->id]['is_live']);
        $this->assertSame(
            "http://localhost:8000/api/cameras/{$live->id}/hls/index.m3u8",
            $byId[$live->id]['hls_url']
        );
    }

    public function test_it_orders_cameras_by_name(): void
    {
        Http::fake();
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $user, 'member');
        Camera::factory()->create(['workspace_id' => $workspace->id, 'name' => 'B Camera']);
        Camera::factory()->create(['workspace_id' => $workspace->id, 'name' => 'A Camera']);

        $result = (app(ListCameras::class))($user);

        $this->assertSame(['A Camera', 'B Camera'], array_column($result, 'name'));
    }

    public function test_it_only_lists_cameras_in_the_users_workspaces(): void
    {
        Http::fake();
        $ownWorkspace = Workspace::factory()->create();
        $otherWorkspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $this->assignWorkspaceRole($ownWorkspace, $user, 'member');

        $own = Camera::factory()->create(['workspace_id' => $ownWorkspace->id]);
        Camera::factory()->create(['workspace_id' => $otherWorkspace->id]);

        $result = (app(ListCameras::class))($user);

        $this->assertSame([$own->id], array_column($result, 'id'));
    }

    public function test_it_can_be_filtered_to_a_single_workspace(): void
    {
        Http::fake();
        $workspaceA = Workspace::factory()->create();
        $workspaceB = Workspace::factory()->create();
        $user = User::factory()->create();
        $this->assignWorkspaceRole($workspaceA, $user, 'member');
        $this->assignWorkspaceRole($workspaceB, $user, 'member');

        $inA = Camera::factory()->create(['workspace_id' => $workspaceA->id]);
        Camera::factory()->create(['workspace_id' => $workspaceB->id]);

        $result = (app(ListCameras::class))($user, $workspaceA->id);

        $this->assertSame([$inA->id], array_column($result, 'id'));
    }

    public function test_filtering_to_a_workspace_the_user_does_not_belong_to_returns_nothing(): void
    {
        Http::fake();
        $ownWorkspace = Workspace::factory()->create();
        $otherWorkspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $this->assignWorkspaceRole($ownWorkspace, $user, 'member');
        Camera::factory()->create(['workspace_id' => $otherWorkspace->id]);

        $result = (app(ListCameras::class))($user, $otherWorkspace->id);

        $this->assertSame([], $result);
    }

    public function test_it_defaults_to_offline_when_mediamtx_is_unreachable(): void
    {
        Http::fake(['*' => Http::response(status: 500)]);
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $user, 'member');
        Camera::factory()->create(['workspace_id' => $workspace->id]);

        $result = (app(ListCameras::class))($user);

        $this->assertFalse($result[0]['is_live']);
    }
}
