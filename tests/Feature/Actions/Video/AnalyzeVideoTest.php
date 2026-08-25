<?php

namespace Tests\Feature\Actions\Video;

use App\Actions\Video\AnalyzeVideo;
use App\Enums\AnalysisType;
use App\Enums\VideoStatus;
use App\Models\AnalysisJob;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AnalyzeVideoTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_a_job_for_each_configured_analysis_type(): void
    {
        Redis::shouldReceive('connection')->with('analysis_queue')->andReturnSelf();
        Redis::shouldReceive('xadd')->twice();

        $workspace = Workspace::factory()->create();
        $uploader = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $uploader, 'admin');
        $video = Video::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $uploader->id,
            'status' => VideoStatus::Uploaded,
            'analysis_types' => [AnalysisType::ObjectDetection->value, AnalysisType::ThreatDetection->value],
        ]);

        $result = (new AnalyzeVideo)($uploader, $video);

        $this->assertSame(VideoStatus::Processing, $result->status);
        $this->assertDatabaseHas('analysis_jobs', [
            'video_id' => $video->id,
            'type' => AnalysisType::ObjectDetection->value,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('analysis_jobs', [
            'video_id' => $video->id,
            'type' => AnalysisType::ThreatDetection->value,
            'status' => 'pending',
        ]);
    }

    public function test_it_aborts_when_the_video_has_no_analysis_types_configured(): void
    {
        $workspace = Workspace::factory()->create();
        $uploader = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $uploader, 'admin');
        $video = Video::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $uploader->id,
            'analysis_types' => [],
        ]);

        try {
            (new AnalyzeVideo)($uploader, $video);
            $this->fail('Expected a 422 exception.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertDatabaseCount('analysis_jobs', 0);
    }

    public function test_it_aborts_when_analysis_is_already_in_progress(): void
    {
        $workspace = Workspace::factory()->create();
        $uploader = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $uploader, 'admin');
        $video = Video::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $uploader->id,
            'analysis_types' => [AnalysisType::ObjectDetection->value],
        ]);
        AnalysisJob::factory()->create([
            'video_id' => $video->id,
            'type' => AnalysisType::ObjectDetection,
            'status' => 'processing',
        ]);

        try {
            (new AnalyzeVideo)($uploader, $video);
            $this->fail('Expected a 422 exception.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertDatabaseCount('analysis_jobs', 1);
    }

    public function test_it_only_queues_types_that_have_not_already_completed(): void
    {
        Redis::shouldReceive('connection')->with('analysis_queue')->andReturnSelf();
        Redis::shouldReceive('xadd')->once();

        $workspace = Workspace::factory()->create();
        $uploader = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $uploader, 'admin');
        $video = Video::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $uploader->id,
            'analysis_types' => [AnalysisType::ObjectDetection->value, AnalysisType::ThreatDetection->value],
        ]);
        AnalysisJob::factory()->create([
            'video_id' => $video->id,
            'type' => AnalysisType::ObjectDetection,
            'status' => 'completed',
        ]);

        (new AnalyzeVideo)($uploader, $video);

        $this->assertDatabaseCount('analysis_jobs', 2);
        $this->assertDatabaseHas('analysis_jobs', [
            'video_id' => $video->id,
            'type' => AnalysisType::ThreatDetection->value,
            'status' => 'pending',
        ]);
    }

    public function test_it_aborts_when_all_configured_types_already_completed(): void
    {
        $workspace = Workspace::factory()->create();
        $uploader = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $uploader, 'admin');
        $video = Video::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $uploader->id,
            'analysis_types' => [AnalysisType::ObjectDetection->value],
        ]);
        AnalysisJob::factory()->create([
            'video_id' => $video->id,
            'type' => AnalysisType::ObjectDetection,
            'status' => 'completed',
        ]);

        try {
            (new AnalyzeVideo)($uploader, $video);
            $this->fail('Expected a 422 exception.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertDatabaseCount('analysis_jobs', 1);
    }

    /**
     * Unlike UpdateVideo/DeleteVideo, AnalyzeVideo has no uploader bypass -
     * it triggers real AWS Rekognition spend, so a plain member shouldn't
     * get a free pass just by having uploaded the video.
     */
    public function test_a_member_uploader_cannot_analyze_their_own_video(): void
    {
        $workspace = Workspace::factory()->create();
        $uploader = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $uploader, 'member');
        $video = Video::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $uploader->id,
            'analysis_types' => [AnalysisType::ObjectDetection->value],
        ]);

        try {
            (new AnalyzeVideo)($uploader, $video);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertDatabaseCount('analysis_jobs', 0);
    }

    public function test_an_outsider_cannot_start_analysis(): void
    {
        $workspace = Workspace::factory()->create();
        $uploader = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $uploader, 'admin');
        $video = Video::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $uploader->id,
            'analysis_types' => [AnalysisType::ObjectDetection->value],
        ]);
        $outsider = User::factory()->create();

        try {
            (new AnalyzeVideo)($outsider, $video);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertDatabaseCount('analysis_jobs', 0);
    }
}
