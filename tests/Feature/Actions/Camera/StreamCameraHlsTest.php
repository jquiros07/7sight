<?php

namespace Tests\Feature\Actions\Camera;

use App\Actions\Camera\StreamCameraHls;
use App\Models\Camera;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StreamCameraHlsTest extends TestCase
{
    use RefreshDatabase;

    private function memberOf(Camera $camera): User
    {
        $user = User::factory()->create();
        $this->assignWorkspaceRole($camera->workspace, $user, 'member');

        return $user;
    }

    public function test_a_workspace_member_can_fetch_the_playlist(): void
    {
        $camera = Camera::factory()->create();
        $user = $this->memberOf($camera);

        Http::fake([
            "http://mediamtx:8888/camera-{$camera->id}/index.m3u8" => Http::response(
                '#EXTM3U',
                200,
                ['Content-Type' => 'application/vnd.apple.mpegurl']
            ),
        ]);

        $response = (app(StreamCameraHls::class))($user, $camera, 'index.m3u8');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('#EXTM3U', $response->getContent());
        $this->assertSame('application/vnd.apple.mpegurl', $response->headers->get('Content-Type'));
    }

    public function test_it_passes_through_mediamtxs_status_when_the_camera_is_offline(): void
    {
        $camera = Camera::factory()->create();
        $user = $this->memberOf($camera);

        Http::fake([
            "http://mediamtx:8888/camera-{$camera->id}/index.m3u8" => Http::response(status: 404),
        ]);

        $response = (app(StreamCameraHls::class))($user, $camera, 'index.m3u8');

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_it_returns_a_bad_gateway_when_mediamtx_is_unreachable(): void
    {
        $camera = Camera::factory()->create();
        $user = $this->memberOf($camera);

        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });

        try {
            (app(StreamCameraHls::class))($user, $camera, 'index.m3u8');
            $this->fail('Expected a 502 exception.');
        } catch (HttpException $e) {
            $this->assertSame(502, $e->getStatusCode());
        }
    }

    public function test_an_outsider_cannot_fetch_the_playlist(): void
    {
        $camera = Camera::factory()->create();
        $outsider = User::factory()->create();

        Http::fake();

        try {
            (app(StreamCameraHls::class))($outsider, $camera, 'index.m3u8');
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        Http::assertNothingSent();
    }
}
