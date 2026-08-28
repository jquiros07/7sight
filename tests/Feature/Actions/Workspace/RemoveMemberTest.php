<?php

namespace Tests\Feature\Actions\Workspace;

use App\Actions\Workspace\RemoveMember;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RemoveMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_removes_a_member(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->assignWorkspaceRole($workspace, $owner, 'owner');
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');

        (new RemoveMember)($owner, $workspace, $member);

        $this->assertDatabaseMissing('workspace_user', ['workspace_id' => $workspace->id, 'user_id' => $member->id]);
        $this->assertFalse($member->hasRole('member'));
    }

    public function test_the_owner_cannot_be_removed(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->assignWorkspaceRole($workspace, $owner, 'owner');

        try {
            (new RemoveMember)($owner, $workspace, $owner);
            $this->fail('Expected a 422 exception.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertDatabaseHas('workspace_user', ['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    }

    public function test_a_plain_member_cannot_remove_anyone(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->assignWorkspaceRole($workspace, $owner, 'owner');
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');
        $otherMember = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $otherMember, 'member');

        try {
            (new RemoveMember)($member, $workspace, $otherMember);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
