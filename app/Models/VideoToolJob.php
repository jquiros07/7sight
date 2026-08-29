<?php

namespace App\Models;

use App\Enums\VideoToolType;
use Database\Factories\VideoToolJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'video_id',
    'user_id',
    'type',
    'status',
    'params',
    'output_disk',
    'output_path',
    'error_message',
    'started_at',
    'completed_at',
])]
class VideoToolJob extends Model
{
    /** @use HasFactory<VideoToolJobFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => VideoToolType::class,
            'params' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
