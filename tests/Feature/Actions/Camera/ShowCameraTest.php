<?php

namespace Tests\Feature\Actions\Camera;

use App\Actions\Camera\ShowCamera;
use App\Models\Camera;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShowCameraTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_a_camera_with_its_live_status_and_hls_url(): void
    {
        $camera = Camera::factory()->create();

        Http::fake(['*/v3/paths/list' => Http::response([
            'items' => [
                ['name' => "camera-{$camera->id}", 'ready' => true],
            ],
        ])]);

        $result = (app(ShowCamera::class))($camera);

        $this->assertSame($camera->id, $result['id']);
        $this->assertTrue($result['is_live']);
        $this->assertSame("http://localhost:8888/camera-{$camera->id}/index.m3u8", $result['hls_url']);
    }
}
