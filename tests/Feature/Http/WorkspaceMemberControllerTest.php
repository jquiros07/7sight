<?php

namespace Tests\Feature\Http;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WorkspaceMemberControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_invite_a_member_over_http(): void
    {
        Mail::fake();

        $workspace = Workspace::factory()->create();
        $admin = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $admin, 'admin');

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/workspaces/{$workspace->id}/members", ['email' => 'new@example.com', 'role' => 'member']);

        $response->assertCreated();
        $response->assertJson(['status' => 'invited', 'email' => 'new@example.com']);
    }

    public function test_a_plain_member_is_forbidden_from_inviting_over_http(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');

        $response = $this->actingAs($member, 'sanctum')
            ->postJson("/api/workspaces/{$workspace->id}/members", ['email' => 'new@example.com', 'role' => 'member']);

        $response->assertForbidden();
    }

    public function test_it_lists_members_over_http(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->assignWorkspaceRole($workspace, $owner, 'owner');

        $response = $this->actingAs($owner, 'sanctum')->getJson("/api/workspaces/{$workspace->id}/members");

        $response->assertOk();
        $response->assertJsonCount(1, 'members');
    }

    public function test_it_updates_a_role_over_http(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->assignWorkspaceRole($workspace, $owner, 'owner');
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');

        $response = $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/workspaces/{$workspace->id}/members/{$member->id}", ['role' => 'admin']);

        $response->assertOk();
        $response->assertJson(['id' => $member->id, 'role' => 'admin']);
    }

    public function test_it_removes_a_member_over_http(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->assignWorkspaceRole($workspace, $owner, 'owner');
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');

        $response = $this->actingAs($owner, 'sanctum')->deleteJson("/api/workspaces/{$workspace->id}/members/{$member->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('workspace_user', ['workspace_id' => $workspace->id, 'user_id' => $member->id]);
    }

    public function test_it_cancels_an_invitation_over_http(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->assignWorkspaceRole($workspace, $owner, 'owner');
        $invitation = WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => 'pending@example.com',
            'role' => 'member',
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/workspaces/{$workspace->id}/invitations/{$invitation->id}");

        $response->assertNoContent();
        $this->assertModelMissing($invitation);
    }
}
