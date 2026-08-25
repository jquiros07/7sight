<?php

use App\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Backfills spatie role assignments for every existing workspace from
     * the owner_id column and workspace_user pivot role. Idempotent
     * (assignRole() no-ops on a duplicate) and a no-op on a fresh database
     * with no workspaces yet (e.g. every test run).
     */
    public function up(): void
    {
        $registrar = app(PermissionRegistrar::class);

        Workspace::withTrashed()->with(['owner', 'users'])->cursor()->each(function (Workspace $workspace) use ($registrar) {
            $registrar->setPermissionsTeamId($workspace->id);

            $workspace->owner?->assignRole('owner');

            foreach ($workspace->users as $member) {
                if ($member->id === $workspace->owner_id) {
                    continue;
                }

                $member->assignRole($member->pivot->role);
            }
        });

        $registrar->setPermissionsTeamId(null);
    }

    /**
     * Data backfill, not reversible schema - intentionally a no-op.
     */
    public function down(): void {}
};
