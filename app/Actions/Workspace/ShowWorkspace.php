<?php

namespace App\Actions\Workspace;

use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Models\User;
use App\Models\Workspace;

class ShowWorkspace
{
    use AuthorizesWorkspaceAccess;

    /**
     * Return a single workspace. Requires membership.
     */
    public function __invoke(User $user, Workspace $workspace): Workspace
    {
        $this->authorizeMembership($user, $workspace);

        return $workspace;
    }
}