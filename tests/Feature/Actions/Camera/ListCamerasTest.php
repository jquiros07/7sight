<?php

namespace Tests\Feature\Actions\Camera;

use App\Actions\Camera\ListCameras;
use App\Models\Camera;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ListCamerasTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_cameras_with_live_status_and_hls_url(): void
    {
        $live = Camera::factory()->create(['name' => 'B Camera']);
        $offline = Camera::factory()->create(['name' => 'A Camera']);

        Http::fake(['*/v3/paths/list' => Http::response([
            'items' => [
                ['name' => "camera-{$live->id}", 'ready' => true],
            ],
        ])]);

        $result = (app(ListCameras::class))();

        $byId = collect($result)->keyBy('id');

        $this->assertTrue($byId[$live->id]['is_live']);
        $this->assertFalse($byId[$offline->id]['is_live']);
        $this->assertSame(
            "http://localhost:8888/camera-{$live->id}/index.m3u8",
            $byId[$live->id]['hls_url']
        );
    }

    public function test_it_orders_cameras_by_name(): void
    {
        Http::fake();
        Camera::factory()->create(['name' => 'B Camera']);
        Camera::factory()->create(['name' => 'A Camera']);

        $result = (app(ListCameras::class))();

        $this->assertSame(['A Camera', 'B Camera'], array_column($result, 'name'));
    }

    public function test_it_defaults_to_offline_when_mediamtx_is_unreachable(): void
    {
        Http::fake(['*' => Http::response(status: 500)]);
        Camera::factory()->create();

        $result = (app(ListCameras::class))();

        $this->assertFalse($result[0]['is_live']);
    }
}
