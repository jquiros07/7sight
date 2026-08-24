<?php

namespace App\Console\Commands;

use App\Enums\CameraRecordingStatus;
use App\Models\CameraRecording;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileCameraRecordings extends Command
{
    protected $signature = 'recordings:reconcile';

    protected $description = "Mark orphaned camera recordings as failed. A recording job reports its own completion, but if it's killed outright (container restart, crash) its row is left stuck at 'recording' forever - this finds rows well past their expected end time and closes them out.";

    public function handle(): int
    {
        $orphaned = CameraRecording::query()
            ->where('status', CameraRecordingStatus::Recording)
            ->where('ends_at', '<', now()->subMinutes(10))
            ->get();

        foreach ($orphaned as $recording) {
            Log::error("Camera recording {$recording->id} orphaned - marking failed.", ['recording_id' => $recording->id]);

            $recording->update([
                'status' => CameraRecordingStatus::Failed,
                'error_message' => 'Recording did not complete as expected (the process may have been interrupted).',
                'completed_at' => now(),
            ]);
        }

        return self::SUCCESS;
    }
}
