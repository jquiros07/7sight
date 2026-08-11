<?php

namespace App\Actions\Workspace;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class ListWorkspaces
{
    /**
     * List the workspaces the given user belongs to.
     *
     * @param  array<string, mixed>  $params
     */
    public function __invoke(User $user, array $params = []): LengthAwarePaginator
    {
        $sort = in_array($params['sort'] ?? null, ['name', 'created_at', 'updated_at'], true)
            ? $params['sort']
            : 'created_at';
        $direction = ($params['direction'] ?? null) === 'asc' ? 'asc' : 'desc';
        $perPage = min((int) ($params['per_page'] ?? 15), 100) ?: 15;
        $search = trim((string) ($params['search'] ?? ''));
        $dateFrom = $params['date_from'] ?? null;
        $dateTo = $params['date_to'] ?? null;

        return $user->workspaces()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('workspaces.name', 'like', "%{$search}%")
                        ->orWhere('workspaces.description', 'like', "%{$search}%");
                });
            })
            ->when($dateFrom, fn ($query) => $query->whereDate('workspaces.created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('workspaces.created_at', '<=', $dateTo))
            ->orderBy("workspaces.$sort", $direction)
            ->orderBy('workspaces.id', $direction)
            ->paginate($perPage)
            ->withQueryString();
    }
}
