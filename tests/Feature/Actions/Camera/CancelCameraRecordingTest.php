<?php

namespace Tests\Feature\Actions\Camera;

use App\Actions\Camera\CancelCameraRecording;
use App\Enums\CameraRecordingStatus;
use App\Models\Camera;
use App\Models\CameraRecording;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CancelCameraRecordingTest extends TestCase
{
    use RefreshDatabase;

    private function memberOf(Camera $camera): User
    {
        $user = User::factory()->create();
        $camera->workspace->users()->attach($user->id, ['role' => 'member']);

        return $user;
    }

    public function test_it_flags_an_in_progress_recording_for_cancellation(): void
    {
        $camera = Camera::factory()->create();
        $user = $this->memberOf($camera);
        $recording = CameraRecording::factory()->create(['camera_id' => $camera->id, 'status' => CameraRecordingStatus::Recording]);

        $result = (app(CancelCameraRecording::class))($user, $camera, $recording);

        $this->assertTrue($result->cancel_requested);
    }

    public function test_it_rejects_cancelling_a_recording_that_is_not_in_progress(): void
    {
        $camera = Camera::factory()->create();
        $user = $this->memberOf($camera);
        $recording = CameraRecording::factory()->create(['camera_id' => $camera->id, 'status' => CameraRecordingStatus::Completed]);

        try {
            (app(CancelCameraRecording::class))($user, $camera, $recording);
            $this->fail('Expected a 422 exception.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_an_outsider_cannot_cancel_a_recording(): void
    {
        $camera = Camera::factory()->create();
        $outsider = User::factory()->create();
        $recording = CameraRecording::factory()->create(['camera_id' => $camera->id, 'status' => CameraRecordingStatus::Recording]);

        try {
            (app(CancelCameraRecording::class))($outsider, $camera, $recording);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertFalse($recording->fresh()->cancel_requested);
    }
}
