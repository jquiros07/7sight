<?php

namespace Tests;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // PermissionRegistrar is a singleton that can carry stale cached
        // permission data and a stale team id across tests running in the
        // same PHP process (RefreshDatabase reuses the process).
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Attach a user to a workspace with the given role, both on the legacy
     * pivot column (still read by resources/js/pages/Workspaces.tsx) and as
     * a real spatie role scoped to that workspace - mirrors what production
     * code does in CreateWorkspace/tinker-based member assignment.
     */
    protected function assignWorkspaceRole(Workspace $workspace, User $user, string $role): void
    {
        $workspace->users()->attach($user->id, ['role' => $role]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($workspace->id);
        $user->assignRole($role);
    }
}
