<?php

namespace Tests\Feature\Jobs;

use App\Enums\CameraRecordingStatus;
use App\Jobs\RecordCameraFeedJob;
use App\Models\CameraRecording;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecordCameraFeedJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_the_recording_completed_when_ffmpeg_succeeds(): void
    {
        Storage::fake('local');
        Process::fake(function (PendingProcess $process) {
            $outputPath = end($process->command);
            file_put_contents($outputPath, 'fake-mp4-bytes');

            return Process::result(exitCode: 0);
        });

        $recording = CameraRecording::factory()->create([
            'status' => CameraRecordingStatus::Recording,
            'duration_minutes' => 180,
            'started_at' => now()->subMinutes(3),
        ]);

        (new RecordCameraFeedJob($recording))->handle();

        $recording->refresh();
        $this->assertSame(CameraRecordingStatus::Completed, $recording->status);
        $this->assertSame(180, $recording->duration_seconds);
        $this->assertNotNull($recording->path);
        $this->assertNotNull($recording->size);
    }

    public function test_it_marks_the_recording_failed_when_ffmpeg_fails(): void
    {
        Storage::fake('local');
        Process::fake(fn () => Process::result(exitCode: 1, errorOutput: 'connection refused'));

        $recording = CameraRecording::factory()->create(['status' => CameraRecordingStatus::Recording, 'duration_minutes' => 180]);

        (new RecordCameraFeedJob($recording))->handle();

        $recording->refresh();
        $this->assertSame(CameraRecordingStatus::Failed, $recording->status);
        $this->assertNotNull($recording->error_message);
    }
}
