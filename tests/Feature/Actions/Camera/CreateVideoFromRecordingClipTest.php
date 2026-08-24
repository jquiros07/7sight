<?php

namespace Tests\Feature\Actions\Camera;

use App\Actions\Camera\CreateVideoFromRecordingClip;
use App\Enums\CameraRecordingStatus;
use App\Models\Camera;
use App\Models\CameraRecording;
use App\Models\User;
use App\Support\VideoMetadataInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CreateVideoFromRecordingClipTest extends TestCase
{
    use RefreshDatabase;

    private function memberOf(Camera $camera): User
    {
        $user = User::factory()->create();
        $camera->workspace->users()->attach($user->id, ['role' => 'member']);

        return $user;
    }

    private function completedRecording(Camera $camera, int $durationSeconds = 1800): CameraRecording
    {
        return CameraRecording::factory()->create([
            'camera_id' => $camera->id,
            'status' => CameraRecordingStatus::Completed,
            'path' => "camera-recordings/{$camera->id}/source.mp4",
            'duration_seconds' => $durationSeconds,
        ]);
    }

    /**
     * ffmpeg is faked (never actually runs), so nothing writes the clip file
     * it's normally responsible for - this fake writes a stand-in file at
     * whatever output path the action passed it, so the action's own
     * Storage::size()/inspector calls afterward have something to read.
     */
    private function fakeFfmpegWritesOutputFile(): void
    {
        Process::fake(function (PendingProcess $process) {
            $outputPath = end($process->command);
            file_put_contents($outputPath, 'fake-mp4-bytes');

            return Process::result(exitCode: 0);
        });
    }

    public function test_it_creates_a_video_from_a_clip_of_a_completed_recording(): void
    {
        Storage::fake('local');
        $this->fakeFfmpegWritesOutputFile();
        $this->mock(VideoMetadataInspector::class, function ($mock) {
            $mock->shouldReceive('inspect')->andReturn(['duration' => 60.0, 'width' => 1280, 'height' => 720]);
        });

        $camera = Camera::factory()->create();
        $user = $this->memberOf($camera);
        $recording = $this->completedRecording($camera);

        $video = (app(CreateVideoFromRecordingClip::class))($user, $camera, $recording, [
            'title' => 'Loading dock clip',
            'start_seconds' => 10,
            'end_seconds' => 70,
            'analysis_types' => ['threat_detection'],
        ]);

        $this->assertSame($camera->workspace_id, $video->workspace_id);
        $this->assertSame($recording->id, $video->camera_recording_id);
        $this->assertSame(10, $video->clip_start_seconds);
        $this->assertSame(70, $video->clip_end_seconds);
        $this->assertSame(60, $video->duration_seconds);
    }

    public function test_it_rejects_clipping_a_recording_that_is_not_completed(): void
    {
        $camera = Camera::factory()->create();
        $user = $this->memberOf($camera);
        $recording = CameraRecording::factory()->create(['camera_id' => $camera->id, 'status' => CameraRecordingStatus::Recording]);

        try {
            (app(CreateVideoFromRecordingClip::class))($user, $camera, $recording, [
                'title' => 'Clip',
                'start_seconds' => 0,
                'end_seconds' => 60,
                'analysis_types' => ['threat_detection'],
            ]);
            $this->fail('Expected a 422 exception.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_it_rejects_a_clip_larger_than_500mb_and_deletes_the_file(): void
    {
        Storage::fake('local');
        Process::fake(function (PendingProcess $process) {
            $outputPath = end($process->command);
            // A sparse file large enough to trip the 500MB cap without
            // actually writing that many bytes to disk.
            $handle = fopen($outputPath, 'w');
            fseek($handle, 500 * 1024 * 1024 + 1);
            fwrite($handle, 'x');
            fclose($handle);

            return Process::result(exitCode: 0);
        });
        $this->mock(VideoMetadataInspector::class, function ($mock) {
            $mock->shouldReceive('inspect')->andReturn(['duration' => 60.0, 'width' => 1280, 'height' => 720]);
        });

        $camera = Camera::factory()->create();
        $user = $this->memberOf($camera);
        $recording = $this->completedRecording($camera);

        try {
            (app(CreateVideoFromRecordingClip::class))($user, $camera, $recording, [
                'title' => 'Too big',
                'start_seconds' => 0,
                'end_seconds' => 60,
                'analysis_types' => ['threat_detection'],
            ]);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('500MB', $e->getMessage());
        }

        $this->assertDatabaseCount('videos', 0);
        $this->assertEmpty(Storage::disk('local')->allFiles("videos/{$camera->workspace_id}/{$user->id}"));
    }

    public function test_it_rejects_a_clip_longer_than_fifteen_minutes(): void
    {
        $camera = Camera::factory()->create();
        $user = $this->memberOf($camera);
        $recording = $this->completedRecording($camera, 7200);

        $this->expectException(ValidationException::class);

        (app(CreateVideoFromRecordingClip::class))($user, $camera, $recording, [
            'title' => 'Too long',
            'start_seconds' => 0,
            'end_seconds' => 901,
            'analysis_types' => ['threat_detection'],
        ]);
    }

    public function test_it_starts_analysis_when_auto_start_analysis_is_true(): void
    {
        Storage::fake('local');
        $this->fakeFfmpegWritesOutputFile();
        $this->mock(VideoMetadataInspector::class, function ($mock) {
            $mock->shouldReceive('inspect')->andReturn(['duration' => 60.0, 'width' => 1280, 'height' => 720]);
        });
        Redis::shouldReceive('connection')->with('analysis_queue')->andReturnSelf();
        Redis::shouldReceive('xadd')->once();

        $camera = Camera::factory()->create();
        $user = $this->memberOf($camera);
        $recording = $this->completedRecording($camera);

        $video = (app(CreateVideoFromRecordingClip::class))($user, $camera, $recording, [
            'title' => 'Auto-analyzed clip',
            'start_seconds' => 0,
            'end_seconds' => 60,
            'analysis_types' => ['object_detection'],
            'auto_start_analysis' => true,
            'analysis_config' => ['object_detection' => ['mode' => 'all']],
        ]);

        $this->assertDatabaseHas('analysis_jobs', ['video_id' => $video->id, 'type' => 'object_detection']);
    }

    public function test_an_outsider_cannot_create_a_clip(): void
    {
        $camera = Camera::factory()->create();
        $outsider = User::factory()->create();
        $recording = $this->completedRecording($camera);

        try {
            (app(CreateVideoFromRecordingClip::class))($outsider, $camera, $recording, [
                'title' => 'Clip',
                'start_seconds' => 0,
                'end_seconds' => 60,
                'analysis_types' => ['threat_detection'],
            ]);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertDatabaseCount('videos', 0);
    }
}
