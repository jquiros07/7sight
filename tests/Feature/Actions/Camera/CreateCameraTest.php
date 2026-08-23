<?php

namespace Tests\Feature\Actions\Camera;

use App\Actions\Camera\CreateCamera;
use App\Models\Camera;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CreateCameraTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_registers_a_camera_and_starts_restreaming_it_via_mediamtx(): void
    {
        Http::fake(['*/v3/config/paths/add/*' => Http::response(status: 200)]);
        $user = User::factory()->create();

        $camera = (app(CreateCamera::class))($user, [
            'name' => 'Loading Dock',
            'location' => 'Warehouse A',
            'stream_url' => 'rtsp://user:pass@192.168.1.10:554/stream1',
        ]);

        $this->assertDatabaseHas('cameras', [
            'id' => $camera->id,
            'name' => 'Loading Dock',
            'location' => 'Warehouse A',
            'created_by' => $user->id,
        ]);

        Http::assertSent(function ($request) use ($camera) {
            return $request->url() === "http://mediamtx:9997/v3/config/paths/add/camera-{$camera->id}"
                && $request['source'] === 'rtsp://user:pass@192.168.1.10:554/stream1';
        });
    }

    public function test_it_enforces_the_five_camera_cap(): void
    {
        Camera::factory()->count(5)->create();
        Http::fake();
        $user = User::factory()->create();

        try {
            (app(CreateCamera::class))($user, ['name' => 'Sixth Camera', 'stream_url' => 'rtsp://192.168.1.15:554/stream1']);
            $this->fail('Expected a 422 exception.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertDatabaseCount('cameras', 5);
        Http::assertNothingSent();
    }

    public function test_it_requires_a_name_and_stream_url(): void
    {
        $user = User::factory()->create();

        $this->expectException(ValidationException::class);

        (app(CreateCamera::class))($user, []);
    }

    public function test_it_requires_the_stream_url_to_be_rtsp(): void
    {
        $user = User::factory()->create();

        $this->expectException(ValidationException::class);

        (app(CreateCamera::class))($user, ['name' => 'Bad URL', 'stream_url' => 'https://example.com/stream']);
    }

    public function test_it_rolls_back_the_camera_when_mediamtx_registration_fails(): void
    {
        Http::fake(['*' => Http::response(status: 500)]);
        $user = User::factory()->create();

        try {
            (app(CreateCamera::class))($user, ['name' => 'Loading Dock', 'stream_url' => 'rtsp://192.168.1.10:554/stream1']);
            $this->fail('Expected a 503 exception.');
        } catch (HttpException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }

        $this->assertDatabaseCount('cameras', 0);
    }
}
