<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'analysis_job_id',
    'label',
    'occurrences',
    'avg_confidence',
    'min_confidence',
    'max_confidence',
    'first_seen_at',
    'last_seen_at',
    'data',
])]
class AnalysisResult extends Model
{
    /** @use HasFactory<\Database\Factories\AnalysisResultFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurrences' => 'integer',
            'avg_confidence' => 'float',
            'min_confidence' => 'float',
            'max_confidence' => 'float',
            'first_seen_at' => 'decimal:3',
            'last_seen_at' => 'decimal:3',
            'data' => 'array',
        ];
    }

    public function analysisJob(): BelongsTo
    {
        return $this->belongsTo(AnalysisJob::class);
    }
}
