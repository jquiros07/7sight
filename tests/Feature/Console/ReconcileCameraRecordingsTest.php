<?php

namespace Tests\Feature\Console;

use App\Enums\CameraRecordingStatus;
use App\Models\CameraRecording;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconcileCameraRecordingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_an_orphaned_recording_as_failed(): void
    {
        $recording = CameraRecording::factory()->create([
            'status' => CameraRecordingStatus::Recording,
            'ends_at' => now()->subMinutes(15),
        ]);

        $this->artisan('recordings:reconcile')->assertExitCode(0);

        $recording->refresh();
        $this->assertSame(CameraRecordingStatus::Failed, $recording->status);
        $this->assertNotNull($recording->error_message);
        $this->assertNotNull($recording->completed_at);
    }

    public function test_it_leaves_a_recording_still_within_its_expected_window_alone(): void
    {
        $recording = CameraRecording::factory()->create([
            'status' => CameraRecordingStatus::Recording,
            'ends_at' => now()->addHours(2),
        ]);

        $this->artisan('recordings:reconcile')->assertExitCode(0);

        $this->assertSame(CameraRecordingStatus::Recording, $recording->fresh()->status);
    }

    public function test_it_leaves_already_finished_recordings_alone(): void
    {
        $recording = CameraRecording::factory()->create([
            'status' => CameraRecordingStatus::Completed,
            'ends_at' => now()->subMinutes(15),
        ]);

        $this->artisan('recordings:reconcile')->assertExitCode(0);

        $this->assertSame(CameraRecordingStatus::Completed, $recording->fresh()->status);
    }
}
