<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Adds the permission gating access to a camera's raw RTSP stream_url
     * (which commonly embeds credentials). Granted to admin/owner, withheld
     * from member - same split as the other ADMIN_ONLY permissions in
     * RolesAndPermissionsSeeder. A separate migration (not just updating the
     * seeder) because the original seeding migration already ran on any
     * existing database.
     */
    public function up(): void
    {
        $permission = Permission::findOrCreate('cameras.view-credentials');

        Role::whereIn('name', ['admin', 'owner'])->get()->each(
            fn (Role $role) => $role->givePermissionTo($permission)
        );
    }

    public function down(): void
    {
        Permission::where('name', 'cameras.view-credentials')->first()?->delete();
    }
};
