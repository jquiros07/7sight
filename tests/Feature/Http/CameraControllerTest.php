<?php

namespace Tests\Feature\Http;

use App\Models\Camera;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Exercises the real HTTP route/controller layer rather than calling the
 * Action directly - the other Camera tests all do the latter, which
 * wouldn't have caught a controller-level mistake building the explicit
 * field array (CameraController stopped using $request->all() in favor of
 * naming each field, per CLAUDE.md's "never pass request->all() through"
 * rule).
 */
class CameraControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_partial_update_over_http_does_not_wipe_omitted_fields(): void
    {
        Http::fake();
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $user, 'member');
        $camera = Camera::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Old Name',
            'stream_url' => 'rtsp://192.168.1.10:554/stream1',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/cameras/{$camera->id}", ['name' => 'New Name']);

        $response->assertOk();
        $this->assertSame('New Name', $camera->fresh()->name);
        $this->assertSame('rtsp://192.168.1.10:554/stream1', $camera->fresh()->stream_url);
    }

    public function test_creating_a_camera_over_http_reads_the_expected_fields(): void
    {
        Http::fake(['*/v3/config/paths/add/*' => Http::response(status: 200)]);
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $user, 'member');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/cameras', [
            'workspace_id' => $workspace->id,
            'name' => 'Loading Dock',
            'location' => 'Warehouse A',
            'stream_url' => 'rtsp://192.168.1.10:554/stream1',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('cameras', [
            'workspace_id' => $workspace->id,
            'name' => 'Loading Dock',
            'location' => 'Warehouse A',
        ]);
    }
}
