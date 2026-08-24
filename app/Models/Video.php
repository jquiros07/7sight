<?php

namespace App\Models;

use App\Enums\VideoStatus;
use Database\Factories\VideoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'workspace_id',
    'user_id',
    'title',
    'description',
    'status',
    'disk',
    'path',
    'original_filename',
    'mime_type',
    'size',
    'duration_seconds',
    'width',
    'height',
    'thumbnail_path',
    'analysis_types',
    'auto_start_analysis',
    'analysis_config',
    'camera_recording_id',
    'clip_start_seconds',
    'clip_end_seconds',
])]
class Video extends Model
{
    /** @use HasFactory<VideoFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => VideoStatus::class,
            'size' => 'integer',
            'duration_seconds' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'analysis_types' => 'array',
            'auto_start_analysis' => 'boolean',
            'analysis_config' => 'array',
            'insights_failed_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function analysisJobs(): HasMany
    {
        return $this->hasMany(AnalysisJob::class);
    }

    public function insights(): HasMany
    {
        return $this->hasMany(VideoInsight::class);
    }

    public function latestInsight(): HasOne
    {
        return $this->hasOne(VideoInsight::class)->latestOfMany();
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(VideoInquiry::class);
    }

    public function cameraRecording(): BelongsTo
    {
        return $this->belongsTo(CameraRecording::class);
    }
}
