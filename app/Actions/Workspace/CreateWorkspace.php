<?php

namespace App\Actions\Workspace;

use App\Actions\Workspace\Concerns\GeneratesUniqueSlug;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class CreateWorkspace
{
    use GeneratesUniqueSlug;

    /**
     * Validate and create a new workspace. The given user becomes its owner.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function __invoke(User $user, array $input): Workspace
    {
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ])->validate();

        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name']),
            'description' => $validated['description'] ?? null,
        ]);

        // Kept even though the spatie role assignment below is what actually
        // gates access now - resources/js/pages/Workspaces.tsx still reads
        // this pivot's role directly for its own display.
        $workspace->users()->attach($user->id, ['role' => 'owner']);

        app(PermissionRegistrar::class)->setPermissionsTeamId($workspace->id);
        $user->assignRole('owner');

        return $workspace;
    }
}
