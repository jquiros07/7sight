<?php

namespace Tests\Feature\Actions\Camera;

use App\Actions\Camera\ListCameraRecordings;
use App\Models\Camera;
use App\Models\CameraRecording;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ListCameraRecordingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_workspace_member_can_list_a_cameras_recordings(): void
    {
        $camera = Camera::factory()->create();
        $user = User::factory()->create();
        $this->assignWorkspaceRole($camera->workspace, $user, 'member');
        CameraRecording::factory()->count(2)->create(['camera_id' => $camera->id]);

        $results = (app(ListCameraRecordings::class))($user, $camera);

        $this->assertCount(2, $results);
    }

    public function test_an_outsider_cannot_list_a_cameras_recordings(): void
    {
        $camera = Camera::factory()->create();
        $outsider = User::factory()->create();
        CameraRecording::factory()->create(['camera_id' => $camera->id]);

        try {
            (app(ListCameraRecordings::class))($outsider, $camera);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
