<?php

namespace App\Actions\Workspace;

use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;

class CancelInvitation
{
    use AuthorizesWorkspaceAccess;

    public function __invoke(User $actor, Workspace $workspace, WorkspaceInvitation $invitation): void
    {
        $this->authorizePermission($actor, $workspace, 'workspace.update');

        abort_unless($invitation->workspace_id === $workspace->id, 404);

        $invitation->delete();
    }
}
