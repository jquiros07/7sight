<?php

namespace App\Actions\Video\Concerns;

use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Models\User;
use App\Models\Video;

trait AuthorizesVideoAccess
{
    use AuthorizesWorkspaceAccess;

    /**
     * Requires being the video's uploader or a workspace owner/admin.
     */
    private function authorizeVideoManagement(User $user, Video $video): void
    {
        if ($video->user_id === $user->id) {
            return;
        }

        $this->authorizeRole($user, $video->workspace, ['owner', 'admin']);
    }
}
