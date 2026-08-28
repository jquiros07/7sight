<?php

namespace App\Actions\Workspace;

use App\Actions\Workspace\Concerns\AttachesWorkspaceMember;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Support\Facades\Log;
use Throwable;

class ConsumePendingInvitations
{
    use AttachesWorkspaceMember;

    /**
     * Attach a newly-verified user to every workspace they hold a pending
     * invitation for. Run on email verification, not registration - at
     * registration time the email hasn't been proven to belong to the
     * registrant yet, so attaching then would let anyone claim workspace
     * access for an email address they don't actually own.
     */
    public function __invoke(User $user): void
    {
        $invitations = WorkspaceInvitation::where('email', $user->email)
            ->where('expires_at', '>', now())
            ->get();

        foreach ($invitations as $invitation) {
            try {
                $this->attachMember($invitation->workspace, $user, $invitation->role);
                $invitation->delete();
            } catch (Throwable $e) {
                Log::error($e->getMessage(), ['exception' => $e, 'workspace_invitation_id' => $invitation->id]);
            }
        }

        WorkspaceInvitation::where('email', $user->email)->where('expires_at', '<=', now())->delete();
    }
}
