<?php

namespace Tests\Feature\Actions\Camera;

use App\Actions\Camera\ShowCamera;
use App\Models\Camera;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ShowCameraTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_a_camera_with_its_live_status_and_hls_url(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $user, 'member');
        $camera = Camera::factory()->create(['workspace_id' => $workspace->id]);

        Http::fake(['*/v3/paths/list' => Http::response([
            'items' => [
                ['name' => "camera-{$camera->id}", 'ready' => true],
            ],
        ])]);

        $result = (app(ShowCamera::class))($user, $camera);

        $this->assertSame($camera->id, $result['id']);
        $this->assertTrue($result['is_live']);
        $this->assertSame("http://localhost:8888/camera-{$camera->id}/index.m3u8", $result['hls_url']);
    }

    public function test_an_outsider_cannot_view_the_camera(): void
    {
        Http::fake();
        $camera = Camera::factory()->create();
        $outsider = User::factory()->create();

        try {
            (app(ShowCamera::class))($outsider, $camera);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
