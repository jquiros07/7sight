<?php

namespace Tests\Feature\Actions\Dashboard;

use App\Actions\Dashboard\GenerateDashboardReport;
use App\Enums\AnalysisType;
use App\Models\AnalysisJob;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoInsight;
use App\Models\VideoToolJob;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateDashboardReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Deliberately does not fake/mock Browsershot - see GenerateVideoReportTest
     * for why: a real render is the only way to catch a Blade error (wrong
     * array key, calling ->format() on a non-Carbon value) in a populated
     * branch, since an empty-state render alone wouldn't exercise it.
     */
    public function test_it_renders_a_real_pdf_with_populated_data(): void
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
        VideoInsight::create([
            'video_id' => $video->id,
            'threat_assessment' => ['threat_detected' => true, 'risk_level' => 'HIGH'],
            'ai_content_assessment' => ['verdict' => 'AI_GENERATED', 'confidence' => 85],
        ]);
        VideoToolJob::factory()->create(['video_id' => $video->id]);

        $pdf = (app(GenerateDashboardReport::class))($member);

        $bytes = base64_decode($pdf->base64());

        $this->assertNotEmpty($bytes);
        $this->assertStringStartsWith('%PDF-', $bytes);
    }

    public function test_it_renders_a_real_pdf_with_no_data(): void
    {
        $member = User::factory()->create();

        $pdf = (app(GenerateDashboardReport::class))($member);

        $bytes = base64_decode($pdf->base64());

        $this->assertNotEmpty($bytes);
        $this->assertStringStartsWith('%PDF-', $bytes);
    }
}
