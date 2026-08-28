<?php

namespace Tests\Feature\Actions\Workspace;

use App\Actions\Workspace\ConsumePendingInvitations;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsumePendingInvitationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_attaches_the_user_to_every_workspace_with_a_matching_invitation(): void
    {
        $workspace = Workspace::factory()->create();
        $inviter = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $inviter, 'owner');
        WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => 'invitee@example.com',
            'role' => 'admin',
            'invited_by' => $inviter->id,
            'expires_at' => now()->addDays(7),
        ]);
        $newUser = User::factory()->create(['email' => 'invitee@example.com']);

        (new ConsumePendingInvitations)($newUser);

        $this->assertDatabaseHas('workspace_user', ['workspace_id' => $workspace->id, 'user_id' => $newUser->id, 'role' => 'admin']);
        $this->assertDatabaseCount('workspace_invitations', 0);
    }

    public function test_an_expired_invitation_is_not_consumed_and_gets_cleaned_up(): void
    {
        $workspace = Workspace::factory()->create();
        WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => 'invitee@example.com',
            'role' => 'member',
            'expires_at' => now()->subDay(),
        ]);
        $newUser = User::factory()->create(['email' => 'invitee@example.com']);

        (new ConsumePendingInvitations)($newUser);

        $this->assertDatabaseMissing('workspace_user', ['workspace_id' => $workspace->id, 'user_id' => $newUser->id]);
        $this->assertDatabaseCount('workspace_invitations', 0);
    }

    public function test_a_user_with_no_matching_invitation_is_left_alone(): void
    {
        $newUser = User::factory()->create(['email' => 'nobody-invited-me@example.com']);

        (new ConsumePendingInvitations)($newUser);

        $this->assertDatabaseCount('workspace_user', 0);
    }
}
