<?php

namespace Tests\Feature\Actions\Workspace;

use App\Actions\Workspace\ListWorkspaceMembers;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ListWorkspaceMembersTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_members_and_marks_the_owner(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->assignWorkspaceRole($workspace, $owner, 'owner');
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');

        $result = (new ListWorkspaceMembers)($owner, $workspace);

        $this->assertCount(2, $result['members']);
        $ownerRow = collect($result['members'])->firstWhere('id', $owner->id);
        $this->assertTrue($ownerRow['is_owner']);
        $memberRow = collect($result['members'])->firstWhere('id', $member->id);
        $this->assertFalse($memberRow['is_owner']);
        $this->assertSame('member', $memberRow['role']);
    }

    public function test_it_lists_pending_invitations(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->assignWorkspaceRole($workspace, $owner, 'owner');
        WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => 'pending@example.com',
            'role' => 'member',
            'invited_by' => $owner->id,
            'expires_at' => now()->addDays(7),
        ]);

        $result = (new ListWorkspaceMembers)($owner, $workspace);

        $this->assertCount(1, $result['invitations']);
        $this->assertSame('pending@example.com', $result['invitations'][0]['email']);
    }

    public function test_a_plain_member_can_view_the_list(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->assignWorkspaceRole($workspace, $owner, 'owner');
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');

        $result = (new ListWorkspaceMembers)($member, $workspace);

        $this->assertCount(2, $result['members']);
    }

    public function test_a_non_member_is_rejected(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->assignWorkspaceRole($workspace, $owner, 'owner');
        $outsider = User::factory()->create();

        try {
            (new ListWorkspaceMembers)($outsider, $workspace);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
