<?php

namespace Tests\Feature\Actions\Dashboard;

use App\Actions\Dashboard\ShowDashboard;
use App\Enums\AnalysisType;
use App\Models\AnalysisJob;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoInsight;
use App\Models\VideoToolJob;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_counts_trim_and_resize_jobs_across_workspaces(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        VideoToolJob::factory()->create(['video_id' => $video->id]);

        $result = (app(ShowDashboard::class))($member);

        $this->assertSame(1, $result['stats']['tool_jobs_run']);
    }

    public function test_it_reports_flagged_analysis_jobs_across_workspaces(): void
    {
        $workspace = Workspace::factory()->create(['name' => 'Retail HQ']);
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id, 'title' => 'Lobby camera']);
        AnalysisJob::factory()->create([
            'video_id' => $video->id,
            'type' => AnalysisType::ThreatDetection,
            'status' => 'completed',
            'flagged_for_review_at' => now(),
            'flagged_by' => $member->id,
            'flagged_review_note' => 'False positive',
        ]);

        $result = (app(ShowDashboard::class))($member);

        $this->assertSame(1, $result['stats']['flagged_for_review']);
        $this->assertCount(1, $result['needs_review']);
        $this->assertSame('Lobby camera', $result['needs_review'][0]['video_title']);
        $this->assertSame('Retail HQ', $result['needs_review'][0]['workspace_name']);
        $this->assertSame($member->name, $result['needs_review'][0]['flagged_by']);
    }

    public function test_it_surfaces_an_ai_generated_content_flag_in_the_safety_spotlight(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id, 'title' => 'Suspicious clip']);
        VideoInsight::create([
            'video_id' => $video->id,
            'ai_content_assessment' => ['verdict' => 'AI_GENERATED', 'confidence' => 92],
        ]);

        $result = (app(ShowDashboard::class))($member);

        $this->assertSame(1, $result['safety_spotlight']['ai_content_flagged']);
        $this->assertSame('ai_content', $result['safety_spotlight']['items'][0]['type']);
        $this->assertSame('HIGH', $result['safety_spotlight']['items'][0]['severity']);
        $this->assertSame('Suspicious clip', $result['safety_spotlight']['items'][0]['video_title']);
    }

    public function test_it_reports_zero_when_nothing_is_flagged(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');

        $result = (app(ShowDashboard::class))($member);

        $this->assertSame(0, $result['stats']['flagged_for_review']);
        $this->assertSame([], $result['needs_review']);
    }

    /**
     * Suggestions are computed once here and rendered verbatim by both the
     * Dashboard page and the PDF report - this locks in that contract so the
     * two can't quietly drift apart again.
     */
    public function test_it_computes_suggestions_shared_by_the_page_and_the_report(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');
        Video::factory()->create(['workspace_id' => $workspace->id, 'status' => 'failed']);

        $result = (app(ShowDashboard::class))($member);

        $types = array_column($result['suggestions'], 'type');
        $this->assertContains('failed_videos', $types);
        $this->assertContains('safety_clear', $types);
        $this->assertSame('1 video failed analysis — review and retry it.', $result['suggestions'][array_search('failed_videos', $types, true)]['text']);
    }

    public function test_it_returns_no_suggestions_when_there_are_no_videos(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');

        $result = (app(ShowDashboard::class))($member);

        $this->assertSame([], $result['suggestions']);
    }
}
