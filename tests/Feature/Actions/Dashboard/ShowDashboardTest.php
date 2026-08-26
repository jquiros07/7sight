<?php

namespace Tests\Feature\Actions\Dashboard;

use App\Actions\Dashboard\ShowDashboard;
use App\Enums\AnalysisType;
use App\Models\AnalysisJob;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowDashboardTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_it_reports_zero_when_nothing_is_flagged(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');

        $result = (app(ShowDashboard::class))($member);

        $this->assertSame(0, $result['stats']['flagged_for_review']);
        $this->assertSame([], $result['needs_review']);
    }
}
