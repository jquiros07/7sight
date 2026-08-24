<?php

namespace Tests\Feature\Actions\Camera;

use App\Actions\Camera\StartCameraRecording;
use App\Enums\CameraRecordingStatus;
use App\Jobs\RecordCameraFeedJob;
use App\Models\Camera;
use App\Models\CameraRecording;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StartCameraRecordingTest extends TestCase
{
    use RefreshDatabase;

    private function memberOf(Camera $camera): User
    {
        $user = User::factory()->create();
        $camera->workspace->users()->attach($user->id, ['role' => 'member']);

        return $user;
    }

    public function test_it_starts_a_recording_and_dispatches_the_job(): void
    {
        Bus::fake();
        $camera = Camera::factory()->create();
        $user = $this->memberOf($camera);

        $recording = (app(StartCameraRecording::class))($user, $camera, ['duration_minutes' => 180]);

        $this->assertSame(CameraRecordingStatus::Recording, $recording->status);
        $this->assertSame(180, $recording->duration_minutes);
        $this->assertSame($user->id, $recording->requested_by);
        Bus::assertDispatched(RecordCameraFeedJob::class, fn ($job) => $job->recording->id === $recording->id);
    }

    public function test_it_rejects_a_duration_outside_the_allowed_presets(): void
    {
        $camera = Camera::factory()->create();
        $user = $this->memberOf($camera);

        $this->expectException(ValidationException::class);

        (app(StartCameraRecording::class))($user, $camera, ['duration_minutes' => 10]);
    }

    public function test_it_rejects_starting_a_second_recording_while_one_is_active(): void
    {
        Bus::fake();
        $camera = Camera::factory()->create();
        $user = $this->memberOf($camera);
        CameraRecording::factory()->create(['camera_id' => $camera->id, 'status' => CameraRecordingStatus::Recording]);

        try {
            (app(StartCameraRecording::class))($user, $camera, ['duration_minutes' => 3]);
            $this->fail('Expected a 422 exception.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertDatabaseCount('camera_recordings', 1);
    }

    public function test_an_outsider_cannot_start_a_recording(): void
    {
        Bus::fake();
        $camera = Camera::factory()->create();
        $outsider = User::factory()->create();

        try {
            (app(StartCameraRecording::class))($outsider, $camera, ['duration_minutes' => 3]);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        Bus::assertNotDispatched(RecordCameraFeedJob::class);
    }
}
