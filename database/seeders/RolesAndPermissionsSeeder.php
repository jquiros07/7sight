<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    private const PERMISSIONS = [
        'workspace.view', 'workspace.update', 'workspace.delete',
        'cameras.view', 'cameras.create', 'cameras.update', 'cameras.delete',
        'cameras.download-recording', 'cameras.stream-recording',
        'recordings.list', 'recordings.start', 'recordings.cancel',
        'recordings.create-video-clip',
        'videos.upload', 'videos.view', 'videos.stream',
        'videos.generate-report', 'videos.list-inquiries',
        'videos.update', 'videos.delete',
        'videos.analyze', 'videos.generate-insights', 'videos.inquire',
    ];

    /**
     * Withheld from 'member' relative to 'admin'. videos.update/.delete are
     * here even though a member CAN update/delete their own video - that
     * path is a separate ownership bypass in AuthorizesVideoAccess, checked
     * before this permission ever matters. This permission only governs
     * managing someone else's video, which member should never be able to do.
     */
    private const ADMIN_ONLY = [
        'workspace.update',
        'recordings.start', 'videos.analyze', 'videos.generate-insights', 'videos.inquire',
        'videos.update', 'videos.delete',
    ];

    /** Withheld from 'admin' relative to 'owner'. */
    private const OWNER_ONLY = ['workspace.delete'];

    /**
     * Seed the global role/permission catalog. Roles and permissions are
     * NOT per-workspace - spatie's teams feature scopes only the assignment
     * pivot tables (by workspace_id), so this only ever needs to run once.
     */
    public function run(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name);
        }

        Role::findOrCreate('member')->syncPermissions(
            array_diff(self::PERMISSIONS, self::ADMIN_ONLY, self::OWNER_ONLY)
        );
        Role::findOrCreate('admin')->syncPermissions(
            array_diff(self::PERMISSIONS, self::OWNER_ONLY)
        );
        Role::findOrCreate('owner')->syncPermissions(self::PERMISSIONS);
    }
}
