<?php

namespace Tests\Feature\Actions\Video;

use App\Actions\Video\RequestVideoInsights;
use App\Enums\AnalysisType;
use App\Jobs\GenerateVideoInsightsJob;
use App\Models\AnalysisJob;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoInsight;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RequestVideoInsightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_with_permission_can_queue_insight_generation_as_themselves(): void
    {
        Queue::fake();

        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $user, 'admin');
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        AnalysisJob::factory()->create([
            'video_id' => $video->id,
            'type' => AnalysisType::ThreatDetection,
            'status' => 'completed',
        ]);

        (app(RequestVideoInsights::class))($user, $video, AnalysisType::ThreatDetection);

        Queue::assertPushed(GenerateVideoInsightsJob::class, function (GenerateVideoInsightsJob $pushed) use ($video, $user) {
            return $pushed->video->is($video)
                && $pushed->user->is($user)
                && $pushed->type === AnalysisType::ThreatDetection;
        });
    }

    public function test_a_member_without_permission_cannot_queue_insight_generation(): void
    {
        Queue::fake();

        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $user, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);

        try {
            (app(RequestVideoInsights::class))($user, $video);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        Queue::assertNotPushed(GenerateVideoInsightsJob::class);
    }

    /**
     * The "nothing to generate insights from" business rule must fail
     * synchronously with its specific 422 - not surface later as a generic
     * background job failure - so it must reject before anything is queued.
     */
    public function test_a_request_with_no_completed_analysis_fails_immediately_without_queuing(): void
    {
        Queue::fake();

        $workspace = Workspace::factory()->create();
        $admin = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $admin, 'admin');
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);

        try {
            (app(RequestVideoInsights::class))($admin, $video);
            $this->fail('Expected a 422 (no completed analysis).');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        Queue::assertNotPushed(GenerateVideoInsightsJob::class);
    }

    /**
     * Same reasoning as the "no completed analysis" case: "already up to
     * date" must also fail synchronously with its specific 422, not queue a
     * job that would just fail later with a generic message.
     */
    public function test_a_request_that_is_already_up_to_date_fails_immediately_without_queuing(): void
    {
        Queue::fake();

        $workspace = Workspace::factory()->create();
        $admin = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $admin, 'admin');
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        AnalysisJob::factory()->create([
            'video_id' => $video->id,
            'type' => AnalysisType::ObjectDetection,
            'status' => 'completed',
            'completed_at' => now()->subMinute(),
        ]);
        VideoInsight::create([
            'video_id' => $video->id,
            'object_detection' => ['summary' => 'already generated'],
        ]);

        try {
            (app(RequestVideoInsights::class))($admin, $video, AnalysisType::ObjectDetection);
            $this->fail('Expected a 422 (already up to date).');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        Queue::assertNotPushed(GenerateVideoInsightsJob::class);
    }
}
