<?php

namespace App\Actions\Workspace\Concerns;

use App\Models\User;
use App\Models\Workspace;
use Spatie\Permission\PermissionRegistrar;

trait AuthorizesWorkspaceAccess
{
    /**
     * Any role in the workspace passes. The owner always passes without a
     * permission-table round-trip - same fast-path the old owner_id check had.
     */
    private function authorizeMembership(User $user, Workspace $workspace): void
    {
        if ($workspace->owner_id === $user->id) {
            return;
        }

        $this->setWorkspaceTeamContext($workspace);

        abort_if($user->roles()->doesntExist(), 403);
    }

    private function authorizePermission(User $user, Workspace $workspace, string $permission): void
    {
        if ($workspace->owner_id === $user->id) {
            return;
        }

        $this->setWorkspaceTeamContext($workspace);

        abort_if(! $user->hasPermissionTo($permission), 403);
    }

    /**
     * Spatie's teams feature scopes every role/permission check to this id -
     * every action authorizes through here, so this is the only place that
     * needs to set it.
     */
    private function setWorkspaceTeamContext(Workspace $workspace): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($workspace->id);
    }
}
