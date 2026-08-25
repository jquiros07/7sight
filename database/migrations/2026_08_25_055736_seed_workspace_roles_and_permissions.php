<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Seeds the global role/permission catalog via a migration (not just a
     * seeder) so it's guaranteed to exist in every environment that runs
     * `migrate` - including the test suite, which uses RefreshDatabase and
     * never calls $this->seed().
     */
    public function up(): void
    {
        (new RolesAndPermissionsSeeder)->run();
    }

    public function down(): void
    {
        // Cascades to role_has_permissions/model_has_roles/model_has_permissions.
        Role::whereIn('name', ['owner', 'admin', 'member'])->delete();
        Permission::query()->delete();
    }
};
