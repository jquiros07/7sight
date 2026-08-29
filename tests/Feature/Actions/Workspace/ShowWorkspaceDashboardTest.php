<?php

namespace Tests\Feature\Actions\Workspace;

use App\Actions\Workspace\ShowWorkspaceDashboard;
use App\Enums\AnalysisType;
use App\Models\AnalysisJob;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoInsight;
use App\Models\VideoToolJob;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ShowWorkspaceDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_counts_trim_and_resize_jobs_as_tool_jobs_run(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        VideoToolJob::factory()->count(2)->create(['video_id' => $video->id]);

        $result = (app(ShowWorkspaceDashboard::class))($member, $workspace);

        $this->assertSame(2, $result['stats']['tool_jobs_run']);
    }

    public function test_a_workspace_member_can_view_the_dashboard(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');

        $result = (app(ShowWorkspaceDashboard::class))($member, $workspace);

        $this->assertSame($workspace->id, $result['workspace']['id']);
        $this->assertSame(0, $result['stats']['total_videos']);
    }

    public function test_it_reports_flagged_analysis_jobs_for_human_review(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id, 'title' => 'Lobby camera']);
        AnalysisJob::factory()->create([
            'video_id' => $video->id,
            'type' => AnalysisType::ObjectDetection,
            'status' => 'completed',
            'flagged_for_review_at' => now(),
            'flagged_by' => $member->id,
            'flagged_review_note' => 'Missed a person at 0:45',
        ]);

        $result = (app(ShowWorkspaceDashboard::class))($member, $workspace);

        $this->assertSame(1, $result['stats']['flagged_for_review']);
        $this->assertCount(1, $result['needs_review']);
        $this->assertSame('Lobby camera', $result['needs_review'][0]['video_title']);
        $this->assertSame($member->name, $result['needs_review'][0]['flagged_by']);
        $this->assertSame('Missed a person at 0:45', $result['needs_review'][0]['note']);
    }

    public function test_it_flags_a_video_whose_latest_ai_content_verdict_is_ai_generated(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        VideoInsight::create([
            'video_id' => $video->id,
            'ai_content_assessment' => ['verdict' => 'AI_GENERATED', 'confidence' => 90],
        ]);
        $authenticVideo = Video::factory()->create(['workspace_id' => $workspace->id]);
        VideoInsight::create([
            'video_id' => $authenticVideo->id,
            'ai_content_assessment' => ['verdict' => 'AUTHENTIC', 'confidence' => 95],
        ]);

        $result = (app(ShowWorkspaceDashboard::class))($member, $workspace);

        $this->assertSame(1, $result['insight_flags']['ai_generated_content_flagged']);
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
