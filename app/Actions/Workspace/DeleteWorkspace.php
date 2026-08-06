<?php

namespace App\Actions\Workspace;

use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Models\User;
use App\Models\Workspace;

class DeleteWorkspace
{
    use AuthorizesWorkspaceAccess;

    /**
     * Delete a workspace. Requires the owner role.
     */
    public function __invoke(User $user, Workspace $workspace): void
    {
        $this->authorizeRole($user, $workspace, ['owner']);

        $workspace->delete();
    }
}