<?php

namespace App\Models;

use Database\Factories\AiContentAnalysisFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'video_id',
    'user_id',
    'status',
    'start_seconds',
    'end_seconds',
    'result',
    'error_message',
    'started_at',
    'completed_at',
])]
class AiContentAnalysis extends Model
{
    /** @use HasFactory<AiContentAnalysisFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_seconds' => 'integer',
            'end_seconds' => 'integer',
            'result' => 'array',
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
