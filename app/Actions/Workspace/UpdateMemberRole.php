<?php

namespace App\Actions\Workspace;

use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class UpdateMemberRole
{
    use AuthorizesWorkspaceAccess;

    /**
     * Change an existing member's role. The owner's role is fixed - ownership
     * isn't transferable through this action.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function __invoke(User $actor, Workspace $workspace, User $member, array $input): array
    {
        $this->authorizePermission($actor, $workspace, 'workspace.update');

        abort_if($member->id === $workspace->owner_id, 422, "The workspace owner's role cannot be changed.");
        abort_unless($workspace->users()->where('users.id', $member->id)->exists(), 404);

        $validated = Validator::make($input, [
            'role' => ['required', 'string', Rule::in(['admin', 'member'])],
        ])->validate();

        $workspace->users()->updateExistingPivot($member->id, ['role' => $validated['role']]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($workspace->id);
        $member->syncRoles([$validated['role']]);

        return ['id' => $member->id, 'role' => $validated['role']];
    }
}
