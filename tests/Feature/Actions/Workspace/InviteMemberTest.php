<?php

namespace Tests\Feature\Actions\Workspace;

use App\Actions\Workspace\InviteMember;
use App\Mail\WorkspaceInvitationMail;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InviteMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_plain_member_cannot_invite(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');

        try {
            (new InviteMember)($member, $workspace, ['email' => 'new@example.com', 'role' => 'member']);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_an_existing_user_is_attached_directly_without_an_email(): void
    {
        Mail::fake();

        $workspace = Workspace::factory()->create();
        $admin = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $admin, 'admin');
        $existingUser = User::factory()->create(['email' => 'existing@example.com']);

        $result = (new InviteMember)($admin, $workspace, ['email' => 'existing@example.com', 'role' => 'member']);

        $this->assertSame(['status' => 'added', 'email' => 'existing@example.com'], $result);
        $this->assertDatabaseHas('workspace_user', ['workspace_id' => $workspace->id, 'user_id' => $existingUser->id, 'role' => 'member']);
        $this->assertDatabaseCount('workspace_invitations', 0);
        Mail::assertNothingSent();
    }

    public function test_an_unregistered_email_gets_a_pending_invitation_and_a_mail(): void
    {
        Mail::fake();

        $workspace = Workspace::factory()->create();
        $admin = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $admin, 'admin');

        $result = (new InviteMember)($admin, $workspace, ['email' => 'new@example.com', 'role' => 'admin']);

        $this->assertSame(['status' => 'invited', 'email' => 'new@example.com'], $result);
        $this->assertDatabaseHas('workspace_invitations', [
            'workspace_id' => $workspace->id,
            'email' => 'new@example.com',
            'role' => 'admin',
            'invited_by' => $admin->id,
        ]);
        Mail::assertSent(WorkspaceInvitationMail::class, fn ($mail) => $mail->hasTo('new@example.com'));
    }

    public function test_it_rejects_inviting_a_user_already_in_the_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $admin = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $admin, 'admin');
        $existingMember = User::factory()->create(['email' => 'already@example.com']);
        $this->assignWorkspaceRole($workspace, $existingMember, 'member');

        try {
            (new InviteMember)($admin, $workspace, ['email' => 'already@example.com', 'role' => 'admin']);
            $this->fail('Expected a 422 validation-style exception.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_it_rejects_an_invalid_role(): void
    {
        $workspace = Workspace::factory()->create();
        $admin = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $admin, 'admin');

        $this->expectException(ValidationException::class);

        (new InviteMember)($admin, $workspace, ['email' => 'new@example.com', 'role' => 'owner']);
    }

    public function test_reinviting_the_same_email_updates_the_existing_invitation_instead_of_duplicating(): void
    {
        Mail::fake();

        $workspace = Workspace::factory()->create();
        $admin = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $admin, 'admin');

        (new InviteMember)($admin, $workspace, ['email' => 'new@example.com', 'role' => 'member']);
        (new InviteMember)($admin, $workspace, ['email' => 'new@example.com', 'role' => 'admin']);

        $this->assertDatabaseCount('workspace_invitations', 1);
        $this->assertSame('admin', WorkspaceInvitation::first()->role);
    }
}
