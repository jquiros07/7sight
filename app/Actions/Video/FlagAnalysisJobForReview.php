<?php

namespace App\Actions\Video;

use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Models\AnalysisJob;
use App\Models\User;
use App\Models\Video;

class FlagAnalysisJobForReview
{
    use AuthorizesWorkspaceAccess;

    /**
     * Mark an analysis job's results as disputed by a human reviewer. Any
     * workspace member can flag - this is about catching AI mistakes, not
     * an admin-gated action.
     */
    public function __invoke(User $user, Video $video, AnalysisJob $analysisJob, ?string $note): AnalysisJob
    {
        abort_if($analysisJob->video_id !== $video->id, 404);

        $this->authorizeMembership($user, $video->workspace);

        $analysisJob->update([
            'flagged_for_review_at' => now(),
            'flagged_by' => $user->id,
            'flagged_review_note' => $note,
        ]);

        return $analysisJob->fresh(['results', 'flaggedByUser']);
    }
}
