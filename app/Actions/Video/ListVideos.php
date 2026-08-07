<?php

namespace App\Actions\Video;

use App\Models\User;
use App\Models\Video;
use Illuminate\Pagination\LengthAwarePaginator;

class ListVideos
{
    /**
     * List the videos across the workspaces the given user belongs to.
     *
     * @param  array<string, mixed>  $params
     */
    public function __invoke(User $user, array $params = []): LengthAwarePaginator
    {
        $sort = in_array($params['sort'] ?? null, ['title', 'created_at', 'size', 'duration_seconds'], true)
            ? $params['sort']
            : 'created_at';
        $direction = ($params['direction'] ?? null) === 'asc' ? 'asc' : 'desc';
        $perPage = min((int) ($params['per_page'] ?? 15), 100) ?: 15;

        $workspaceIds = $user->workspaces()->pluck('workspaces.id');

        return Video::with('workspace:id,name')
            ->whereIn('workspace_id', $workspaceIds)
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction)
            ->paginate($perPage)
            ->withQueryString();
    }
}
