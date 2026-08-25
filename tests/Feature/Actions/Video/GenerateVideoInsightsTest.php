<?php

namespace Tests\Feature\Actions\Video;

use App\Actions\Video\GenerateVideoInsights;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class GenerateVideoInsightsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Cost-driving action (Gemini) - restricted to admin+. No completed
     * analysis jobs are set up here, so the assertion is specifically about
     * which HTTP status is reached: 403 (blocked by permission, business
     * logic never runs) vs 422 (permission passed, blocked by the "nothing
     * to generate insights from" business rule instead).
     */
    public function test_a_plain_member_cannot_generate_insights(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);

        try {
            (new GenerateVideoInsights)($member, $video);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_an_admin_can_reach_past_the_permission_check(): void
    {
        $workspace = Workspace::factory()->create();
        $admin = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $admin, 'admin');
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);

        try {
            (new GenerateVideoInsights)($admin, $video);
            $this->fail('Expected a 422 (no completed analysis), not a permission error.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }
}
