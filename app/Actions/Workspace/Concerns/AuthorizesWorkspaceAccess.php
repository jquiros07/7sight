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
        abort_if(! $this->userHasPermission($user, $workspace, $permission), 403);
    }

    /**
     * Same rule as authorizePermission(), without aborting - for callers that
     * need to branch on the result (e.g. masking a field) rather than reject
     * the whole request.
     */
    private function userHasPermission(User $user, Workspace $workspace, string $permission): bool
    {
        if ($workspace->owner_id === $user->id) {
            return true;
        }

        $this->setWorkspaceTeamContext($workspace);

        return $user->hasPermissionTo($permission);
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
