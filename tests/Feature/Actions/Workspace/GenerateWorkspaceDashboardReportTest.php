<?php

namespace Tests\Feature\Actions\Workspace;

use App\Actions\Workspace\GenerateWorkspaceDashboardReport;
use App\Enums\AnalysisType;
use App\Models\AnalysisJob;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoInsight;
use App\Models\VideoToolJob;
use App\Models\Workspace;
use App\Models\WorkspaceInsightSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class GenerateWorkspaceDashboardReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Deliberately does not fake/mock Browsershot - see GenerateVideoReportTest
     * for why: a real render is the only way to catch a Blade error in a
     * populated branch (workspace summary, needs review), since an
     * empty-state render alone wouldn't exercise those.
     */
    public function test_it_renders_a_real_pdf_with_populated_data(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');

        WorkspaceInsightSummary::create([
            'workspace_id' => $workspace->id,
            'summary' => 'Overall activity is normal.',
            'highlights' => ['No threats this week.'],
        ]);

        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        AnalysisJob::factory()->create([
            'video_id' => $video->id,
            'type' => AnalysisType::ObjectDetection,
            'status' => 'completed',
            'flagged_for_review_at' => now(),
            'flagged_by' => $member->id,
            'flagged_review_note' => 'Missed a person at 0:45',
        ]);
        VideoInsight::create([
            'video_id' => $video->id,
            'threat_assessment' => ['threat_detected' => true, 'risk_level' => 'HIGH'],
            'moderation' => ['status' => 'REVIEW', 'severity' => 'MEDIUM'],
            'ai_content_assessment' => ['verdict' => 'AI_GENERATED', 'confidence' => 85],
        ]);
        VideoToolJob::factory()->create(['video_id' => $video->id]);

        $pdf = (app(GenerateWorkspaceDashboardReport::class))($member, $workspace);

        $bytes = base64_decode($pdf->base64());

        $this->assertNotEmpty($bytes);
        $this->assertStringStartsWith('%PDF-', $bytes);
    }

    public function test_it_renders_a_real_pdf_with_no_data(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');

        $pdf = (app(GenerateWorkspaceDashboardReport::class))($member, $workspace);

        $bytes = base64_decode($pdf->base64());

        $this->assertNotEmpty($bytes);
        $this->assertStringStartsWith('%PDF-', $bytes);
    }

    public function test_an_outsider_cannot_generate_a_report(): void
    {
        $workspace = Workspace::factory()->create();
        $outsider = User::factory()->create();

        try {
            (app(GenerateWorkspaceDashboardReport::class))($outsider, $workspace);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
