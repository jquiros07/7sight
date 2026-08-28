<?php

namespace App\Actions\Workspace\Concerns;

use App\Models\User;
use App\Models\Workspace;
use Spatie\Permission\PermissionRegistrar;

trait AttachesWorkspaceMember
{
    /**
     * Add a user to a workspace with the given role - both the legacy pivot
     * column (still read directly by the frontend for display) and the
     * spatie role assignment that actually gates access.
     */
    private function attachMember(Workspace $workspace, User $user, string $role): void
    {
        $workspace->users()->attach($user->id, ['role' => $role]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($workspace->id);
        $user->assignRole($role);
    }
}
