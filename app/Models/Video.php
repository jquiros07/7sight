<?php

namespace App\Models;

use App\Enums\VideoStatus;
use Database\Factories\VideoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
}
