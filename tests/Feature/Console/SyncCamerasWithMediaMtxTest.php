<?php

namespace Tests\Feature\Console;

use App\Models\Camera;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncCamerasWithMediaMtxTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_cameras_mediamtx_has_no_record_of(): void
    {
        $camera = Camera::factory()->create(['stream_url' => 'rtsp://192.168.1.10:554/stream1']);

        Http::fake([
            '*/v3/config/paths/list' => Http::response(['items' => []]),
            '*/v3/config/paths/add/*' => Http::response(status: 200),
        ]);

        $this->artisan('cameras:sync')->assertExitCode(0);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), "/v3/config/paths/add/camera-{$camera->id}")
            && $request['source'] === 'rtsp://192.168.1.10:554/stream1');
    }

    public function test_it_updates_cameras_mediamtx_already_has_a_record_of(): void
    {
        $camera = Camera::factory()->create(['stream_url' => 'rtsp://192.168.1.10:554/stream1']);

        Http::fake([
            '*/v3/config/paths/list' => Http::response(['items' => [['name' => "camera-{$camera->id}"]]]),
            '*/v3/config/paths/patch/*' => Http::response(status: 200),
        ]);

        $this->artisan('cameras:sync')->assertExitCode(0);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), "/v3/config/paths/patch/camera-{$camera->id}"));
    }

    public function test_it_does_not_fail_when_mediamtx_is_unreachable(): void
    {
        Camera::factory()->create();
        Http::fake(['*' => Http::response(status: 500)]);

        $this->artisan('cameras:sync')->assertExitCode(0);
    }

    public function test_it_isolates_failures_to_the_camera_that_failed(): void
    {
        $good = Camera::factory()->create(['stream_url' => 'rtsp://192.168.1.10:554/stream1']);
        $bad = Camera::factory()->create(['stream_url' => 'rtsp://192.168.1.20:554/stream1']);

        Http::fake([
            '*/v3/config/paths/list' => Http::response(['items' => []]),
            "*/v3/config/paths/add/camera-{$bad->id}" => Http::response(status: 500),
            '*/v3/config/paths/add/*' => Http::response(status: 200),
        ]);

        $this->artisan('cameras:sync')->assertExitCode(0);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), "/v3/config/paths/add/camera-{$good->id}"));
    }
}
