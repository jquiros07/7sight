<?php

namespace App\Actions\Video;

use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Models\AnalysisJob;
use App\Models\User;
use App\Models\Video;

class UnflagAnalysisJobForReview
{
    use AuthorizesWorkspaceAccess;

    /**
     * Clear a previous human-review flag. Any workspace member can unflag,
     * same reasoning as flagging - it's not an admin-gated action.
     */
    public function __invoke(User $user, Video $video, AnalysisJob $analysisJob): AnalysisJob
    {
        abort_if($analysisJob->video_id !== $video->id, 404);

        $this->authorizeMembership($user, $video->workspace);

        $analysisJob->update([
            'flagged_for_review_at' => null,
            'flagged_by' => null,
            'flagged_review_note' => null,
        ]);

        return $analysisJob->fresh('results');
    }
}
