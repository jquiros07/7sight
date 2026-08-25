<?php

namespace Tests\Feature\Actions\Workspace;

use App\Actions\Workspace\DeleteWorkspace;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DeleteWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_can_delete_the_workspace(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->assignWorkspaceRole($workspace, $owner, 'owner');

        (new DeleteWorkspace)($owner, $workspace);

        $this->assertSoftDeleted($workspace);
    }

    public function test_deleting_a_workspace_soft_deletes_its_videos(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->assignWorkspaceRole($workspace, $owner, 'owner');
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);

        (new DeleteWorkspace)($owner, $workspace);

        $this->assertSoftDeleted($video);
    }

    public function test_an_admin_cannot_delete_the_workspace(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->assignWorkspaceRole($workspace, $owner, 'owner');
        $this->assignWorkspaceRole($workspace, $admin, 'admin');

        try {
            (new DeleteWorkspace)($admin, $workspace);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertNotSoftDeleted($workspace);
    }

    public function test_a_member_cannot_delete_the_workspace(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->assignWorkspaceRole($workspace, $owner, 'owner');
        $this->assignWorkspaceRole($workspace, $member, 'member');

        try {
            (new DeleteWorkspace)($member, $workspace);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertNotSoftDeleted($workspace);
    }
}
