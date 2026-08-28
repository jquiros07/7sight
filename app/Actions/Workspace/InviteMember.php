<?php

namespace App\Actions\Workspace;

use App\Actions\Workspace\Concerns\AttachesWorkspaceMember;
use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Mail\WorkspaceInvitationMail;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class InviteMember
{
    use AttachesWorkspaceMember;
    use AuthorizesWorkspaceAccess;

    private const INVITATION_LIFETIME_DAYS = 7;

    /**
     * Invite a user to a workspace by email. An existing user is attached
     * directly; an email with no matching account gets a pending invitation
     * (consumed once that email is verified on registration, see
     * ConsumePendingInvitations) and an invite email.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function __invoke(User $inviter, Workspace $workspace, array $input): array
    {
        $this->authorizePermission($inviter, $workspace, 'workspace.update');

        $validated = Validator::make($input, [
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::in(['admin', 'member'])],
        ])->validate();

        $email = $validated['email'];
        $role = $validated['role'];

        $existingUser = User::where('email', $email)->first();

        if ($existingUser !== null) {
            abort_if(
                $workspace->users()->where('users.id', $existingUser->id)->exists(),
                422,
                'This user is already a member of this workspace.'
            );

            $this->attachMember($workspace, $existingUser, $role);

            return ['status' => 'added', 'email' => $email];
        }

        $invitation = WorkspaceInvitation::updateOrCreate(
            ['workspace_id' => $workspace->id, 'email' => $email],
            [
                'role' => $role,
                'invited_by' => $inviter->id,
                'expires_at' => now()->addDays(self::INVITATION_LIFETIME_DAYS),
            ],
        );

        try {
            Mail::to($email)->send(new WorkspaceInvitationMail($workspace, $inviter, $role, $email));
        } catch (Throwable $e) {
            Log::error($e->getMessage(), ['exception' => $e, 'workspace_invitation_id' => $invitation->id]);
        }

        return ['status' => 'invited', 'email' => $email];
    }
}
