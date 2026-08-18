<?php

namespace App\Actions\Workspace\Concerns;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceUser;

trait AuthorizesWorkspaceAccess
{
    /**
     * Reads the role directly off the pivot table rather than through
     * $workspace->users() - that relation would pull a full users.* row
     * (including the password hash) via the join just to read one string,
     * and this runs on nearly every authorized request in the app.
     */
    private function roleFor(User $user, Workspace $workspace): ?string
    {
        if ($workspace->owner_id === $user->id) {
            return 'owner';
        }

        return WorkspaceUser::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->value('role');
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
