<?php

namespace App\Actions\User;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class ListUserWorkspacePermissions
{
    /**
     * Map every workspace the user owns or belongs to, to the permission
     * names they hold there. Owners implicitly hold every permission - same
     * fast-path AuthorizesWorkspaceAccess uses - since they have no explicit
     * role assignment to look up.
     *
     * @return array<int, array<int, string>>
     */
    public function __invoke(User $user): array
    {
        $registrar = app(PermissionRegistrar::class);
        $allPermissionNames = null;
        $permissions = [];

        foreach ($user->ownedWorkspaces()->pluck('id') as $workspaceId) {
            $permissions[$workspaceId] = $allPermissionNames ??= Permission::pluck('name')->all();
        }

        foreach ($user->workspaces()->get(['workspaces.id']) as $workspace) {
            if (isset($permissions[$workspace->id])) {
                continue;
            }

            $registrar->setPermissionsTeamId($workspace->id);

            // getAllPermissions() caches the roles/permissions relations on
            // $user - without unsetting them here, every workspace after the
            // first would silently reuse the previous workspace's cached
            // roles instead of re-querying under the new team context.
            $user->unsetRelation('roles')->unsetRelation('permissions');
            $permissions[$workspace->id] = $user->getAllPermissions()->pluck('name')->all();
        }

        return $permissions;
    }
}
