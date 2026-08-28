<?php

namespace App\Actions\Workspace;

use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Models\User;
use App\Models\Workspace;

class ListWorkspaceMembers
{
    use AuthorizesWorkspaceAccess;

    /**
     * List a workspace's current members and pending invitations. Any
     * member can view this - only mutating it (invite/remove/change role)
     * requires workspace.update.
     *
     * @return array<string, mixed>
     */
    public function __invoke(User $user, Workspace $workspace): array
    {
        $this->authorizeMembership($user, $workspace);

        $members = $workspace->users()
            ->get(['users.id', 'users.name', 'users.email'])
            ->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->pivot->role,
                'is_owner' => $member->id === $workspace->owner_id,
            ]);

        $invitations = $workspace->invitations()
            ->get(['id', 'email', 'role', 'expires_at', 'created_at'])
            ->map(fn ($invitation) => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'expires_at' => $invitation->expires_at,
                'invited_at' => $invitation->created_at,
            ]);

        return ['members' => $members, 'invitations' => $invitations];
    }
}
