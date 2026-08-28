<?php

namespace Tests\Feature\Actions\Workspace;

use App\Actions\Workspace\UpdateMemberRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class UpdateMemberRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_a_members_role(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->assignWorkspaceRole($workspace, $owner, 'owner');
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');

        $result = (new UpdateMemberRole)($owner, $workspace, $member, ['role' => 'admin']);

        $this->assertSame(['id' => $member->id, 'role' => 'admin'], $result);
        $this->assertDatabaseHas('workspace_user', ['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => 'admin']);
        $this->assertTrue($member->hasRole('admin'));
        $this->assertFalse($member->hasRole('member'));
    }

    public function test_the_owners_role_cannot_be_changed(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->assignWorkspaceRole($workspace, $owner, 'owner');

        try {
            (new UpdateMemberRole)($owner, $workspace, $owner, ['role' => 'member']);
            $this->fail('Expected a 422 exception.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_a_plain_member_cannot_change_roles(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->assignWorkspaceRole($workspace, $owner, 'owner');
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');
        $otherMember = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $otherMember, 'member');

        try {
            (new UpdateMemberRole)($member, $workspace, $otherMember, ['role' => 'admin']);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_it_rejects_a_user_who_is_not_a_member_of_the_workspace(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->assignWorkspaceRole($workspace, $owner, 'owner');
        $outsider = User::factory()->create();

        try {
            (new UpdateMemberRole)($owner, $workspace, $outsider, ['role' => 'admin']);
            $this->fail('Expected a 404 exception.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }
}
