<?php

namespace Tests\Feature\Actions\Camera;

use App\Actions\Camera\StreamCameraRecording;
use App\Enums\CameraRecordingStatus;
use App\Models\Camera;
use App\Models\CameraRecording;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StreamCameraRecordingTest extends TestCase
{
    use RefreshDatabase;

    private function memberOf(Camera $camera): User
    {
        $user = User::factory()->create();
        $this->assignWorkspaceRole($camera->workspace, $user, 'member');

        return $user;
    }

    public function test_a_workspace_member_can_stream_a_completed_recording(): void
    {
        Storage::fake('local');
        $camera = Camera::factory()->create();
        $user = $this->memberOf($camera);
        $recording = CameraRecording::factory()->create([
            'camera_id' => $camera->id,
            'status' => CameraRecordingStatus::Completed,
            'disk' => 'local',
            'path' => "camera-recordings/{$camera->id}/recording.mp4",
        ]);
        Storage::disk('local')->put($recording->path, 'fake-mp4-bytes');

        $response = (app(StreamCameraRecording::class))($user, $camera, $recording);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame('video/mp4', $response->headers->get('Content-Type'));
    }

    public function test_an_outsider_cannot_stream_a_recording(): void
    {
        Storage::fake('local');
        $camera = Camera::factory()->create();
        $outsider = User::factory()->create();
        $recording = CameraRecording::factory()->create([
            'camera_id' => $camera->id,
            'status' => CameraRecordingStatus::Completed,
            'disk' => 'local',
            'path' => "camera-recordings/{$camera->id}/recording.mp4",
        ]);
        Storage::disk('local')->put($recording->path, 'fake-mp4-bytes');

        try {
            (app(StreamCameraRecording::class))($outsider, $camera, $recording);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
