<?php

namespace App\Actions\Video\Concerns;

use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Models\User;
use App\Models\Video;

trait AuthorizesVideoAccess
{
    use AuthorizesWorkspaceAccess;

    /**
     * Requires being the video's uploader, or having $permission in the
     * video's workspace.
     */
    private function authorizeVideoManagement(User $user, Video $video, string $permission): void
    {
        if ($video->user_id === $user->id) {
            return;
        }

        $this->authorizePermission($user, $video->workspace, $permission);
    }
}
