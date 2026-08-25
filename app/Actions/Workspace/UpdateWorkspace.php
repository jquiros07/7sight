<?php

namespace App\Actions\Workspace;

use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Actions\Workspace\Concerns\GeneratesUniqueSlug;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateWorkspace
{
    use AuthorizesWorkspaceAccess;
    use GeneratesUniqueSlug;

    /**
     * Update a workspace. Requires the owner or admin role.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function __invoke(User $user, Workspace $workspace, array $input): Workspace
    {
        $this->authorizePermission($user, $workspace, 'workspace.update');

        $validated = Validator::make($input, [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ])->validate();

        if (isset($validated['name']) && $validated['name'] !== $workspace->name) {
            $validated['slug'] = $this->uniqueSlug($validated['name'], $workspace->id);
        }

        $workspace->update($validated);

        return $workspace;
    }
}
