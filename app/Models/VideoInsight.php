<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'video_id',
    'user_id',
    'object_detection',
    'threat_assessment',
    'moderation',
    'text_detection',
    'embedding',
])]
class VideoInsight extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'object_detection' => 'array',
            'threat_assessment' => 'array',
            'moderation' => 'array',
            'text_detection' => 'array',
            'embedding' => 'array',
        ];
    }

    /**
     * A plain-text summary of this insight's populated fields, used as the
     * input for generating a search embedding.
     */
    public function searchableText(): string
    {
        return collect([
            'object_detection' => $this->object_detection,
            'threat_assessment' => $this->threat_assessment,
            'moderation' => $this->moderation,
            'text_detection' => $this->text_detection,
        ])
            ->filter()
            ->map(fn (array $value, string $field) => "{$field}: ".json_encode($value))
            ->implode("\n\n");
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
