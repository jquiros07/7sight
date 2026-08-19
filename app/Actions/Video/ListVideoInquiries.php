<?php

namespace App\Actions\Video;

use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoInquiry;
use Illuminate\Support\Collection;

class ListVideoInquiries
{
    use AuthorizesWorkspaceAccess;

    /**
     * List a video's past questions and answers, most recent first. Requires
     * workspace membership.
     *
     * @return Collection<int, VideoInquiry>
     */
    public function __invoke(User $user, Video $video): Collection
    {
        $this->authorizeMembership($user, $video->workspace);

        return $video->inquiries()->latest()->get();
    }
}
