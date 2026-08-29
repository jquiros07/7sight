<?php

namespace Tests\Feature\Actions\Video;

use App\Actions\Video\ShowAiContentAnalysis;
use App\Models\AiContentAnalysis;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ShowAiContentAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_workspace_member_can_view_an_analysiss_status(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        $analysis = AiContentAnalysis::factory()->create(['video_id' => $video->id, 'status' => 'completed']);

        $result = (app(ShowAiContentAnalysis::class))($member, $video, $analysis);

        $this->assertTrue($result->is($analysis));
    }

    public function test_an_analysis_from_another_video_is_not_found(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        $otherVideo = Video::factory()->create();
        $analysis = AiContentAnalysis::factory()->create(['video_id' => $otherVideo->id]);

        try {
            (app(ShowAiContentAnalysis::class))($member, $video, $analysis);
            $this->fail('Expected a 404 not found exception.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_an_outsider_cannot_view_an_analysiss_status(): void
    {
        $workspace = Workspace::factory()->create();
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        $analysis = AiContentAnalysis::factory()->create(['video_id' => $video->id]);
        $outsider = User::factory()->create();

        try {
            (app(ShowAiContentAnalysis::class))($outsider, $video, $analysis);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
