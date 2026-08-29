<?php

namespace Tests\Feature\Actions\Video;

use App\Actions\Video\GenerateVideoInsights;
use App\Ai\Agents\ObjectDetectionAgent;
use App\Enums\AnalysisType;
use App\Models\AnalysisJob;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoInsight;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;
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

    public function test_it_carries_forward_an_existing_ai_content_assessment(): void
    {
        Embeddings::fake([[[0.1, 0.2, 0.3]]]);
        ObjectDetectionAgent::fake([[
            'summary' => 'A car passes by.',
            'confidence' => 90,
            'timestamp' => null,
            'objects' => [],
            'notable_observations' => [],
        ]]);
        $workspace = Workspace::factory()->create();
        $admin = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $admin, 'admin');
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        AnalysisJob::factory()->create([
            'video_id' => $video->id,
            'type' => AnalysisType::ObjectDetection,
            'status' => 'completed',
        ]);
        VideoInsight::create([
            'video_id' => $video->id,
            'ai_content_assessment' => ['verdict' => 'AI_GENERATED', 'confidence' => 90],
        ]);

        $result = (new GenerateVideoInsights)($admin, $video, AnalysisType::ObjectDetection);

        $this->assertNotNull($result['object_detection']);
        $insight = $video->fresh()->latestInsight;
        $this->assertSame('AI_GENERATED', $insight->ai_content_assessment['verdict']);
    }
}
