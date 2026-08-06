<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['video_id', 'type', 'status', 'error_message', 'raw_output_path', 'started_at', 'completed_at'])]
class AnalysisJob extends Model
{
    /** @use HasFactory<\Database\Factories\AnalysisJobFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(AnalysisResult::class);
    }
}
