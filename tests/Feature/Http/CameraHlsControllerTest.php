<?php

namespace Tests\Feature\Http;

use App\Models\Camera;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Exercises the real HTTP route/middleware stack for HLS playback, rather
 * than calling StreamCameraHls directly - Sanctum's stateful-request
 * detection and session-guard auth only actually run at this layer.
 */
class CameraHlsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_workspace_member_can_fetch_the_playlist_over_http(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $user, 'member');
        $camera = Camera::factory()->create(['workspace_id' => $workspace->id]);

        Http::fake([
            "http://mediamtx:8888/camera-{$camera->id}/index.m3u8" => Http::response('#EXTM3U', 200, [
                'Content-Type' => 'application/vnd.apple.mpegurl',
            ]),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->get("/api/cameras/{$camera->id}/hls/index.m3u8");

        $response->assertOk();
        $this->assertSame('#EXTM3U', $response->getContent());
    }

    /**
     * Regression test: {path} route parameters never include the URL's query
     * string, but MediaMTX's sub-playlist/segment URLs carry their session id
     * as one (e.g. main_stream.m3u8?session=...). Losing it here means every
     * such request reaches MediaMTX with no session at all and gets rejected.
     */
    public function test_a_query_string_on_the_requested_path_is_forwarded_to_mediamtx(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $user, 'member');
        $camera = Camera::factory()->create(['workspace_id' => $workspace->id]);

        Http::fake([
            "http://mediamtx:8888/camera-{$camera->id}/main_stream.m3u8?session=abc-123" => Http::response('#EXTM3U', 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->get("/api/cameras/{$camera->id}/hls/main_stream.m3u8?session=abc-123");

        $response->assertOk();
        Http::assertSent(fn ($request) => $request->url() === "http://mediamtx:8888/camera-{$camera->id}/main_stream.m3u8?session=abc-123");
    }

    public function test_an_unauthenticated_request_is_rejected(): void
    {
        $camera = Camera::factory()->create();

        Http::fake();

        $response = $this->get("/api/cameras/{$camera->id}/hls/index.m3u8");

        $response->assertUnauthorized();
    }
}
