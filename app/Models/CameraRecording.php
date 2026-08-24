<?php

namespace App\Models;

use App\Enums\CameraRecordingStatus;
use Database\Factories\CameraRecordingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'camera_id',
    'requested_by',
    'status',
    'duration_minutes',
    'started_at',
    'ends_at',
    'completed_at',
    'cancel_requested',
    'disk',
    'path',
    'size',
    'duration_seconds',
    'process_pid',
    'error_message',
])]
class CameraRecording extends Model
{
    /** @use HasFactory<CameraRecordingFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CameraRecordingStatus::class,
            'duration_minutes' => 'integer',
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancel_requested' => 'boolean',
            'size' => 'integer',
            'duration_seconds' => 'integer',
            'process_pid' => 'integer',
        ];
    }

    public function camera(): BelongsTo
    {
        return $this->belongsTo(Camera::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
