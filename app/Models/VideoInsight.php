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
