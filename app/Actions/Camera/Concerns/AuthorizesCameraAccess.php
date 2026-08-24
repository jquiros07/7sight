<?php

namespace App\Actions\Camera\Concerns;

use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Models\Camera;
use App\Models\User;

trait AuthorizesCameraAccess
{
    use AuthorizesWorkspaceAccess;

    /**
     * Requires membership in the camera's workspace.
     */
    private function authorizeCameraAccess(User $user, Camera $camera): void
    {
        $this->authorizeMembership($user, $camera->workspace);
    }
}
