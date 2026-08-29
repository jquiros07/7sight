<?php

namespace App\Actions\Video;

use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Models\AiContentAnalysis;
use App\Models\User;
use App\Models\Video;

class ShowAiContentAnalysis
{
    use AuthorizesWorkspaceAccess;

    /**
     * Return an AI-generated-content analysis' status/result. Requires
     * workspace membership.
     */
    public function __invoke(User $user, Video $video, AiContentAnalysis $aiContentAnalysis): AiContentAnalysis
    {
        $this->authorizeMembership($user, $video->workspace);

        abort_unless($aiContentAnalysis->video_id === $video->id, 404);

        return $aiContentAnalysis;
    }
}
