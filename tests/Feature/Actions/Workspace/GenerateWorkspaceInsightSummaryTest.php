<?php

namespace Tests\Feature\Actions\Workspace;

use App\Actions\Workspace\GenerateWorkspaceInsightSummary;
use App\Ai\Agents\WorkspaceInsightSummaryAgent;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoInsight;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class GenerateWorkspaceInsightSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function videoWithInsight(Workspace $workspace): Video
    {
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        VideoInsight::create([
            'video_id' => $video->id,
            'object_detection' => ['summary' => 'A person walks by.'],
        ]);

        return $video;
    }

    public function test_a_workspace_member_can_generate_an_insight_summary(): void
    {
        WorkspaceInsightSummaryAgent::fake();

        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');
        $this->videoWithInsight($workspace);

        $summary = (app(GenerateWorkspaceInsightSummary::class))($member, $workspace);

        $this->assertSame($workspace->id, $summary->workspace_id);
        $this->assertSame($member->id, $summary->user_id);
    }

    public function test_an_outsider_cannot_generate_an_insight_summary(): void
    {
        WorkspaceInsightSummaryAgent::fake();

        $workspace = Workspace::factory()->create();
        $outsider = User::factory()->create();
        $this->videoWithInsight($workspace);

        try {
            (app(GenerateWorkspaceInsightSummary::class))($outsider, $workspace);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
