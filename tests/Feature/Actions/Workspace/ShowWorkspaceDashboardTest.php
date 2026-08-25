<?php

namespace Tests\Feature\Actions\Workspace;

use App\Actions\Workspace\ShowWorkspaceDashboard;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ShowWorkspaceDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_workspace_member_can_view_the_dashboard(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');

        $result = (app(ShowWorkspaceDashboard::class))($member, $workspace);

        $this->assertSame($workspace->id, $result['workspace']['id']);
        $this->assertSame(0, $result['stats']['total_videos']);
    }

    public function test_an_outsider_cannot_view_the_dashboard(): void
    {
        $workspace = Workspace::factory()->create();
        $outsider = User::factory()->create();

        try {
            (app(ShowWorkspaceDashboard::class))($outsider, $workspace);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
