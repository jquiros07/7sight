<?php

namespace App\Actions\Video;

use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Models\User;
use App\Models\Video;

class ShowVideo
{
    use AuthorizesWorkspaceAccess;

    /**
     * Return a single video. Requires workspace membership.
     */
    public function __invoke(User $user, Video $video): Video
    {
        $this->authorizeMembership($user, $video->workspace);

        return $video->load('analysisJobs.results');
    }
}
