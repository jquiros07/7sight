<?php

namespace Tests\Feature\Actions\Workspace;

use App\Actions\Workspace\CancelInvitation;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CancelInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_the_invitation(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->assignWorkspaceRole($workspace, $owner, 'owner');
        $invitation = WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => 'pending@example.com',
            'role' => 'member',
            'invited_by' => $owner->id,
            'expires_at' => now()->addDays(7),
        ]);

        (new CancelInvitation)($owner, $workspace, $invitation);

        $this->assertModelMissing($invitation);
    }

    public function test_it_rejects_an_invitation_belonging_to_a_different_workspace(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->assignWorkspaceRole($workspace, $owner, 'owner');
        $otherWorkspace = Workspace::factory()->create();
        $invitation = WorkspaceInvitation::create([
            'workspace_id' => $otherWorkspace->id,
            'email' => 'pending@example.com',
            'role' => 'member',
            'expires_at' => now()->addDays(7),
        ]);

        try {
            (new CancelInvitation)($owner, $workspace, $invitation);
            $this->fail('Expected a 404 exception.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }
}
