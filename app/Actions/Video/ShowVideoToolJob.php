<?php

namespace App\Actions\Video;

use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoToolJob;

class ShowVideoToolJob
{
    use AuthorizesWorkspaceAccess;

    /**
     * Return a video tool job's status. Requires workspace membership.
     */
    public function __invoke(User $user, Video $video, VideoToolJob $videoToolJob): VideoToolJob
    {
        $this->authorizeMembership($user, $video->workspace);

        abort_unless($videoToolJob->video_id === $video->id, 404);

        return $videoToolJob;
    }
}
