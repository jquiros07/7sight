<?php

namespace Tests\Feature\Actions\Camera;

use App\Actions\Camera\CreateCamera;
use App\Models\Camera;
use App\Models\User;
use App\Models\Workspace;
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
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $user, 'member');

        $camera = (app(CreateCamera::class))($user, [
            'workspace_id' => $workspace->id,
            'name' => 'Loading Dock',
            'location' => 'Warehouse A',
            'stream_url' => 'rtsp://user:pass@192.168.1.10:554/stream1',
        ]);

        $this->assertDatabaseHas('cameras', [
            'id' => $camera->id,
            'workspace_id' => $workspace->id,
            'name' => 'Loading Dock',
            'location' => 'Warehouse A',
            'created_by' => $user->id,
        ]);

        Http::assertSent(function ($request) use ($camera) {
            return $request->url() === "http://mediamtx:9997/v3/config/paths/add/camera-{$camera->id}"
                && $request['source'] === 'rtsp://user:pass@192.168.1.10:554/stream1';
        });
    }

    public function test_it_rejects_a_user_who_is_not_a_member_of_the_workspace(): void
    {
        Http::fake();
        $workspace = Workspace::factory()->create();
        $outsider = User::factory()->create();

        try {
            (app(CreateCamera::class))($outsider, [
                'workspace_id' => $workspace->id,
                'name' => 'Loading Dock',
                'stream_url' => 'rtsp://192.168.1.10:554/stream1',
            ]);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertDatabaseCount('cameras', 0);
        Http::assertNothingSent();
    }

    public function test_it_enforces_the_five_camera_cap_per_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        Camera::factory()->count(5)->create(['workspace_id' => $workspace->id]);
        Http::fake();
        $user = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $user, 'member');

        try {
            (app(CreateCamera::class))($user, [
                'workspace_id' => $workspace->id,
                'name' => 'Sixth Camera',
                'stream_url' => 'rtsp://192.168.1.15:554/stream1',
            ]);
            $this->fail('Expected a 422 exception.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertDatabaseCount('cameras', 5);
        Http::assertNothingSent();
    }

    public function test_the_cap_does_not_count_cameras_in_other_workspaces(): void
    {
        Camera::factory()->count(5)->create();
        Http::fake(['*/v3/config/paths/add/*' => Http::response(status: 200)]);
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $user, 'member');

        $camera = (app(CreateCamera::class))($user, [
            'workspace_id' => $workspace->id,
            'name' => 'First Camera Here',
            'stream_url' => 'rtsp://192.168.1.15:554/stream1',
        ]);

        $this->assertSame($workspace->id, $camera->workspace_id);
    }

    public function test_it_requires_a_workspace_name_and_stream_url(): void
    {
        $user = User::factory()->create();

        $this->expectException(ValidationException::class);

        (app(CreateCamera::class))($user, []);
    }

    public function test_it_requires_the_stream_url_to_be_rtsp(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $user, 'member');

        $this->expectException(ValidationException::class);

        (app(CreateCamera::class))($user, [
            'workspace_id' => $workspace->id,
            'name' => 'Bad URL',
            'stream_url' => 'https://example.com/stream',
        ]);
    }

    public function test_it_rejects_a_stream_url_pointing_to_an_internal_host(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $user, 'member');

        $this->expectException(ValidationException::class);

        (app(CreateCamera::class))($user, [
            'workspace_id' => $workspace->id,
            'name' => 'Suspicious',
            'stream_url' => 'rtsp://169.254.169.254:554/stream1',
        ]);
    }

    public function test_it_rolls_back_the_camera_when_mediamtx_registration_fails(): void
    {
        Http::fake(['*' => Http::response(status: 500)]);
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $user, 'member');

        try {
            (app(CreateCamera::class))($user, [
                'workspace_id' => $workspace->id,
                'name' => 'Loading Dock',
                'stream_url' => 'rtsp://192.168.1.10:554/stream1',
            ]);
            $this->fail('Expected a 503 exception.');
        } catch (HttpException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }

        $this->assertDatabaseCount('cameras', 0);
    }
}
