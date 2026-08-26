<?php

namespace App\Models;

use App\Enums\AnalysisType;
use Database\Factories\AnalysisJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'video_id',
    'type',
    'status',
    'attempts',
    'external_job_id',
    'error_message',
    'raw_output_path',
    'started_at',
    'completed_at',
    'flagged_for_review_at',
    'flagged_by',
    'flagged_review_note',
])]
class AnalysisJob extends Model
{
    /** @use HasFactory<AnalysisJobFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AnalysisType::class,
            'attempts' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'flagged_for_review_at' => 'datetime',
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

    /**
     * Named flaggedByUser (not flaggedBy) so its serialized key doesn't
     * collide with the raw flagged_by foreign key column - Eloquent snake
     * cases relation names, so flaggedBy() would serialize to the same
     * "flagged_by" key as the column and silently mask it whenever loaded.
     */
    public function flaggedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'flagged_by');
    }
}
