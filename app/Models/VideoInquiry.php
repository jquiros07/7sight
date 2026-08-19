<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'video_id',
    'user_id',
    'question',
    'answer',
])]
class VideoInquiry extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answer' => 'array',
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
