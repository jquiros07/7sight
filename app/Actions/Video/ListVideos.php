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
        $search = trim((string) ($params['search'] ?? ''));
        $dateFrom = $params['date_from'] ?? null;
        $dateTo = $params['date_to'] ?? null;
        $durationMin = is_numeric($params['duration_min'] ?? null) ? (int) $params['duration_min'] : null;
        $durationMax = is_numeric($params['duration_max'] ?? null) ? (int) $params['duration_max'] : null;
        $sizeMin = is_numeric($params['size_min'] ?? null) ? (int) $params['size_min'] : null;
        $sizeMax = is_numeric($params['size_max'] ?? null) ? (int) $params['size_max'] : null;

        $workspaceIds = $user->workspaces()->pluck('workspaces.id');

        return Video::with('workspace:id,name')
            ->withExists(['analysisJobs as has_completed_analysis' => fn ($query) => $query->where('status', 'completed')])
            ->whereIn('workspace_id', $workspaceIds)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('workspace', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($dateFrom, fn ($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('created_at', '<=', $dateTo))
            ->when($durationMin !== null, fn ($query) => $query->where('duration_seconds', '>=', $durationMin))
            ->when($durationMax !== null, fn ($query) => $query->where('duration_seconds', '<=', $durationMax))
            ->when($sizeMin !== null, fn ($query) => $query->where('size', '>=', $sizeMin))
            ->when($sizeMax !== null, fn ($query) => $query->where('size', '<=', $sizeMax))
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction)
            ->paginate($perPage)
            ->withQueryString();
    }
}
