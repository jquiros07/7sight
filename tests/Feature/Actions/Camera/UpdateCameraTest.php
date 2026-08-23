<?php

namespace Tests\Feature\Actions\Camera;

use App\Actions\Camera\UpdateCamera;
use App\Models\Camera;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class UpdateCameraTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_fields_without_calling_mediamtx_when_the_stream_url_is_unchanged(): void
    {
        Http::fake();
        $camera = Camera::factory()->create(['name' => 'Old Name', 'stream_url' => 'rtsp://192.168.1.10:554/stream1']);

        $updated = (app(UpdateCamera::class))($camera, [
            'name' => 'New Name',
            'stream_url' => 'rtsp://192.168.1.10:554/stream1',
        ]);

        $this->assertSame('New Name', $updated->name);
        Http::assertNothingSent();
    }

    public function test_it_updates_the_mediamtx_path_when_the_stream_url_changes(): void
    {
        Http::fake(['*/v3/config/paths/patch/*' => Http::response(status: 200)]);
        $camera = Camera::factory()->create(['stream_url' => 'rtsp://192.168.1.10:554/stream1']);

        (app(UpdateCamera::class))($camera, ['stream_url' => 'rtsp://192.168.1.20:554/stream1']);

        Http::assertSent(function ($request) use ($camera) {
            return $request->url() === "http://mediamtx:9997/v3/config/paths/patch/camera-{$camera->id}"
                && $request['source'] === 'rtsp://192.168.1.20:554/stream1';
        });
    }

    public function test_it_rejects_a_non_rtsp_stream_url(): void
    {
        $camera = Camera::factory()->create();

        $this->expectException(ValidationException::class);

        (app(UpdateCamera::class))($camera, ['stream_url' => 'https://example.com']);
    }

    public function test_it_rolls_back_when_mediamtx_update_fails(): void
    {
        Http::fake(['*' => Http::response(status: 500)]);
        $camera = Camera::factory()->create(['name' => 'Original', 'stream_url' => 'rtsp://192.168.1.10:554/stream1']);

        try {
            (app(UpdateCamera::class))($camera, ['name' => 'Changed', 'stream_url' => 'rtsp://192.168.1.20:554/stream1']);
            $this->fail('Expected a 503 exception.');
        } catch (HttpException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }

        $this->assertSame('Original', $camera->fresh()->name);
        $this->assertSame('rtsp://192.168.1.10:554/stream1', $camera->fresh()->stream_url);
    }
}
