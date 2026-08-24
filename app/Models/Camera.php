<?php

namespace App\Models;

use App\Enums\CameraRecordingStatus;
use Database\Factories\CameraFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['workspace_id', 'name', 'location', 'stream_url', 'created_by'])]
class Camera extends Model
{
    /** @use HasFactory<CameraFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stream_url' => 'encrypted',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recordings(): HasMany
    {
        return $this->hasMany(CameraRecording::class);
    }

    public function activeRecording(): HasOne
    {
        return $this->hasOne(CameraRecording::class)
            ->where('status', CameraRecordingStatus::Recording)
            ->latestOfMany();
    }
}
