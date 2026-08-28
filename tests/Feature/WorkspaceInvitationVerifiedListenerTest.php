<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Confirms AppServiceProvider actually wires ConsumePendingInvitations to
 * Laravel's real Verified event (fired by Fortify's own verification flow),
 * not just that the action works in isolation - the security-critical part
 * of this feature is that attachment happens on verification, not on
 * registration. See ConsumePendingInvitationsTest for the action's own
 * behavior in isolation.
 */
class WorkspaceInvitationVerifiedListenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_verifying_email_attaches_the_user_to_their_invited_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => 'invitee@example.com',
            'role' => 'member',
            'expires_at' => now()->addDays(7),
        ]);
        $user = User::factory()->create(['email' => 'invitee@example.com']);

        event(new Verified($user));

        $this->assertDatabaseHas('workspace_user', ['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => 'member']);
    }
}
