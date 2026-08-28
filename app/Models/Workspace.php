<?php

namespace App\Models;

use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'description', 'owner_id'])]
class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * Soft-delete the workspace's videos alongside it, since the FK's cascadeOnDelete
     * only fires on a hard delete.
     */
    protected static function booted(): void
    {
        static::deleting(function (Workspace $workspace) {
            $workspace->videos()->delete();
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')
            ->using(WorkspaceUser::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    public function insightSummaries(): HasMany
    {
        return $this->hasMany(WorkspaceInsightSummary::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }

    public function latestInsightSummary(): HasOne
    {
        return $this->hasOne(WorkspaceInsightSummary::class)->latestOfMany();
    }
}
