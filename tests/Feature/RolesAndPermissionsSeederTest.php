<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Guards the seeded role -> permission matrix itself, independent of any
 * Action - if this drifts, every action-level authorization test could stay
 * green while the actual role definitions silently changed underneath them.
 */
class RolesAndPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_exactly_three_roles(): void
    {
        $this->assertEqualsCanonicalizing(['owner', 'admin', 'member'], Role::pluck('name')->all());
    }

    public function test_member_lacks_admin_only_permissions(): void
    {
        $member = Role::findByName('member');

        $this->assertFalse($member->hasPermissionTo('workspace.update'));
        $this->assertFalse($member->hasPermissionTo('workspace.delete'));
        $this->assertFalse($member->hasPermissionTo('videos.analyze'));
        $this->assertFalse($member->hasPermissionTo('videos.generate-insights'));
        $this->assertFalse($member->hasPermissionTo('videos.inquire'));
        $this->assertFalse($member->hasPermissionTo('videos.update'));
        $this->assertFalse($member->hasPermissionTo('videos.delete'));
        $this->assertTrue($member->hasPermissionTo('videos.view'));
        $this->assertTrue($member->hasPermissionTo('videos.upload'));
    }

    public function test_admin_has_everything_except_workspace_delete(): void
    {
        $admin = Role::findByName('admin');

        $this->assertFalse($admin->hasPermissionTo('workspace.delete'));
        $this->assertTrue($admin->hasPermissionTo('workspace.update'));
        $this->assertTrue($admin->hasPermissionTo('videos.analyze'));
        $this->assertTrue($admin->hasPermissionTo('videos.update'));
    }

    public function test_owner_has_every_permission(): void
    {
        $owner = Role::findByName('owner');

        $this->assertTrue($owner->hasPermissionTo('workspace.delete'));
        $this->assertSame(Permission::count(), $owner->permissions()->count());
    }
}
