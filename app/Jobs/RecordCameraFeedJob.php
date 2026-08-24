<?php

namespace App\Jobs;

use App\Enums\CameraRecordingStatus;
use App\Models\CameraRecording;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class RecordCameraFeedJob implements ShouldQueue
{
    use Queueable;

    /**
     * A multi-hour recording must not be auto-retried - that would double
     * up the recording (and the file it already wrote).
     */
    public int $tries = 1;

    /**
     * This job is intentionally long-running (up to 24h); the queue worker
     * running it is started with --timeout=0 to match.
     */
    public int $timeout = 0;

    public function __construct(
        public CameraRecording $recording,
    ) {}

    public function handle(): void
    {
        $recording = $this->recording;
        $context = ['recording_id' => $recording->id, 'camera_id' => $recording->camera_id];

        Log::info('Camera recording job started', [...$context, 'duration_minutes' => $recording->duration_minutes]);

        try {
            $outputPath = "camera-recordings/{$recording->camera_id}/{$recording->id}.mp4";
            Storage::disk($recording->disk)->makeDirectory(dirname($outputPath));
            $absolutePath = Storage::disk($recording->disk)->path($outputPath);

            Log::info('Starting ffmpeg', [...$context, 'output_path' => $outputPath]);

            $process = Process::forever()->start([
                'ffmpeg', '-y',
                '-rtsp_transport', 'tcp',
                '-i', $recording->camera->stream_url,
                '-t', (string) ($recording->duration_minutes * 60),
                '-c', 'copy',
                '-movflags', '+frag_keyframe+empty_moov',
                $absolutePath,
            ]);

            $recording->update(['process_pid' => $process->id()]);
            Log::info('ffmpeg started', [...$context, 'pid' => $process->id()]);

            while ($process->running()) {
                if ($recording->refresh()->cancel_requested) {
                    Log::info('Cancellation requested, signalling ffmpeg', [...$context, 'pid' => $process->id()]);
                    $process->signal(SIGTERM);
                    break;
                }

                sleep(5);
            }

            $result = $process->wait();
            Log::info('ffmpeg exited', [...$context, 'successful' => $result->successful(), 'exit_code' => $result->exitCode()]);

            if (! $result->successful() && ! $recording->cancel_requested) {
                throw new RuntimeException("ffmpeg failed: {$result->errorOutput()}");
            }

            // Not read from the file itself: getID3 can't parse duration out of
            // this fragmented/empty-moov mp4 (the format chosen specifically so
            // a killed process still leaves a playable file) - it reliably
            // returns 0. Wall-clock elapsed time is accurate for both a full
            // and a cancelled-early recording, and needs no format parsing at all.
            $recording->update([
                'status' => $recording->cancel_requested ? CameraRecordingStatus::Cancelled : CameraRecordingStatus::Completed,
                'completed_at' => now(),
                'path' => $outputPath,
                'size' => Storage::disk($recording->disk)->size($outputPath),
                'duration_seconds' => abs(now()->diffInSeconds($recording->started_at)),
            ]);

            Log::info('Camera recording job finished', [...$context, 'status' => $recording->status->value]);
        } catch (Throwable $e) {
            Log::error('Camera recording failed', [...$context, 'exception' => $e]);

            $recording->update([
                'status' => CameraRecordingStatus::Failed,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }
}
