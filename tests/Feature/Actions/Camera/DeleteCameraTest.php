<?php

namespace Tests\Feature\Actions\Camera;

use App\Actions\Camera\DeleteCamera;
use App\Models\Camera;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DeleteCameraTest extends TestCase
{
    use RefreshDatabase;

    private function memberOf(Camera $camera): User
    {
        $user = User::factory()->create();
        $camera->workspace->users()->attach($user->id, ['role' => 'member']);

        return $user;
    }

    public function test_it_deletes_the_camera_and_removes_its_mediamtx_path(): void
    {
        Http::fake(['*/v3/config/paths/delete/*' => Http::response(status: 200)]);
        $camera = Camera::factory()->create();
        $user = $this->memberOf($camera);

        (app(DeleteCamera::class))($user, $camera);

        $this->assertSoftDeleted($camera);
        Http::assertSent(fn ($request) => $request->url() === "http://mediamtx:9997/v3/config/paths/delete/camera-{$camera->id}"
            && $request->method() === 'DELETE');
    }

    public function test_it_still_deletes_the_camera_when_mediamtx_is_unreachable(): void
    {
        Http::fake(['*' => Http::response(status: 500)]);
        $camera = Camera::factory()->create();
        $user = $this->memberOf($camera);

        (app(DeleteCamera::class))($user, $camera);

        $this->assertSoftDeleted($camera);
    }

    public function test_an_outsider_cannot_delete_the_camera(): void
    {
        Http::fake();
        $camera = Camera::factory()->create();
        $outsider = User::factory()->create();

        try {
            (app(DeleteCamera::class))($outsider, $camera);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertDatabaseHas('cameras', ['id' => $camera->id, 'deleted_at' => null]);
        Http::assertNothingSent();
    }
}
