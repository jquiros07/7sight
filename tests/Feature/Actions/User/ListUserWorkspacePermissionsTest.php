<?php

namespace Tests\Feature\Actions\User;

use App\Actions\User\ListUserWorkspacePermissions;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ListUserWorkspacePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_gets_the_member_permission_set_for_their_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');

        $permissions = (app(ListUserWorkspacePermissions::class))($member);

        $this->assertContains('videos.upload', $permissions[$workspace->id]);
        $this->assertNotContains('videos.analyze', $permissions[$workspace->id]);
        $this->assertNotContains('workspace.delete', $permissions[$workspace->id]);
    }

    public function test_an_admin_gets_the_admin_permission_set(): void
    {
        $workspace = Workspace::factory()->create();
        $admin = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $admin, 'admin');

        $permissions = (app(ListUserWorkspacePermissions::class))($admin);

        $this->assertContains('videos.analyze', $permissions[$workspace->id]);
        $this->assertNotContains('workspace.delete', $permissions[$workspace->id]);
    }

    public function test_the_owner_gets_every_permission_without_an_explicit_role(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $permissions = (app(ListUserWorkspacePermissions::class))($owner);

        $this->assertEqualsCanonicalizing(Permission::pluck('name')->all(), $permissions[$workspace->id]);
    }

    /**
     * The riskiest way this action could break: forgetting to switch spatie's
     * team context between workspaces and leaking one workspace's role into
     * another's entry, or into a workspace the user isn't even part of.
     */
    public function test_permissions_are_scoped_independently_per_workspace(): void
    {
        $workspaceA = Workspace::factory()->create();
        $workspaceB = Workspace::factory()->create();
        $unrelated = Workspace::factory()->create();

        $user = User::factory()->create();
        $this->assignWorkspaceRole($workspaceA, $user, 'member');
        $this->assignWorkspaceRole($workspaceB, $user, 'admin');

        $permissions = (app(ListUserWorkspacePermissions::class))($user);

        $this->assertNotContains('videos.analyze', $permissions[$workspaceA->id]);
        $this->assertContains('videos.analyze', $permissions[$workspaceB->id]);
        $this->assertArrayNotHasKey($unrelated->id, $permissions);
    }
}
