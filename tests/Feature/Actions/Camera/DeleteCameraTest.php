<?php

namespace Tests\Feature\Actions\Camera;

use App\Actions\Camera\DeleteCamera;
use App\Models\Camera;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeleteCameraTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_the_camera_and_removes_its_mediamtx_path(): void
    {
        Http::fake(['*/v3/config/paths/delete/*' => Http::response(status: 200)]);
        $camera = Camera::factory()->create();

        (app(DeleteCamera::class))($camera);

        $this->assertSoftDeleted($camera);
        Http::assertSent(fn ($request) => $request->url() === "http://mediamtx:9997/v3/config/paths/delete/camera-{$camera->id}"
            && $request->method() === 'DELETE');
    }

    public function test_it_still_deletes_the_camera_when_mediamtx_is_unreachable(): void
    {
        Http::fake(['*' => Http::response(status: 500)]);
        $camera = Camera::factory()->create();

        (app(DeleteCamera::class))($camera);

        $this->assertSoftDeleted($camera);
    }
}
