<?php

namespace App\Actions\Workspace;

use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Models\User;
use App\Models\Workspace;
use Spatie\Permission\PermissionRegistrar;

class RemoveMember
{
    use AuthorizesWorkspaceAccess;

    /**
     * Remove a member from a workspace. The owner can't be removed this way.
     */
    public function __invoke(User $actor, Workspace $workspace, User $member): void
    {
        $this->authorizePermission($actor, $workspace, 'workspace.update');

        abort_if($member->id === $workspace->owner_id, 422, 'The workspace owner cannot be removed.');
        abort_unless($workspace->users()->where('users.id', $member->id)->exists(), 404);

        $workspace->users()->detach($member->id);

        app(PermissionRegistrar::class)->setPermissionsTeamId($workspace->id);
        $member->syncRoles([]);
    }
}
