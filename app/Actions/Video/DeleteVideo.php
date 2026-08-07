<?php

namespace App\Actions\Video;

use App\Actions\Video\Concerns\AuthorizesVideoAccess;
use App\Models\User;
use App\Models\Video;

class DeleteVideo
{
    use AuthorizesVideoAccess;

    /**
     * Delete a video. Requires being the uploader or a workspace owner/admin.
     */
    public function __invoke(User $user, Video $video): void
    {
        $this->authorizeVideoManagement($user, $video);

        $video->delete();
    }
}
