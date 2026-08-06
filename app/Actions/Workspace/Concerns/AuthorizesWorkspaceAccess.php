<?php

namespace App\Actions\Workspace\Concerns;

use App\Models\User;
use App\Models\Workspace;

trait AuthorizesWorkspaceAccess
{
    private function roleFor(User $user, Workspace $workspace): ?string
    {
        if ($workspace->owner_id === $user->id) {
            return 'owner';
        }

        return $workspace->users()->where('user_id', $user->id)->first()?->pivot->role;
    }

    private function authorizeMembership(User $user, Workspace $workspace): void
    {
        abort_if($this->roleFor($user, $workspace) === null, 403);
    }

    /**
     * @param  array<int, string>  $roles
     */
    private function authorizeRole(User $user, Workspace $workspace, array $roles): void
    {
        abort_if(! in_array($this->roleFor($user, $workspace), $roles, true), 403);
    }
}