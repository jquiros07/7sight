<?php

namespace Tests\Feature\Actions\Video;

use App\Actions\Video\RequestAiContentAnalysis;
use App\Jobs\AnalyzeAiGeneratedContentJob;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RequestAiContentAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_request_an_analysis(): void
    {
        Queue::fake();

        $workspace = Workspace::factory()->create();
        $admin = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $admin, 'admin');
        $video = Video::factory()->create(['workspace_id' => $workspace->id, 'duration_seconds' => 60]);

        $analysis = (app(RequestAiContentAnalysis::class))($admin, $video, [
            'start_seconds' => 0,
            'end_seconds' => 30,
        ]);

        $this->assertSame('pending', $analysis->status);
        $this->assertSame($video->id, $analysis->video_id);
        Queue::assertPushed(AnalyzeAiGeneratedContentJob::class, fn (AnalyzeAiGeneratedContentJob $job) => $job->analysis->is($analysis));
    }

    public function test_a_plain_member_cannot_request_an_analysis(): void
    {
        Queue::fake();

        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id, 'duration_seconds' => 60, 'user_id' => $member->id]);

        try {
            (app(RequestAiContentAnalysis::class))($member, $video, ['start_seconds' => 0, 'end_seconds' => 30]);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        Queue::assertNotPushed(AnalyzeAiGeneratedContentJob::class);
    }

    public function test_a_clip_longer_than_ninety_seconds_is_rejected(): void
    {
        Queue::fake();

        $workspace = Workspace::factory()->create();
        $admin = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $admin, 'admin');
        $video = Video::factory()->create(['workspace_id' => $workspace->id, 'duration_seconds' => 600]);

        $this->expectException(ValidationException::class);

        (app(RequestAiContentAnalysis::class))($admin, $video, ['start_seconds' => 0, 'end_seconds' => 91]);
    }

    public function test_the_end_timestamp_must_be_within_the_videos_duration(): void
    {
        Queue::fake();

        $workspace = Workspace::factory()->create();
        $admin = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $admin, 'admin');
        $video = Video::factory()->create(['workspace_id' => $workspace->id, 'duration_seconds' => 60]);

        $this->expectException(ValidationException::class);

        (app(RequestAiContentAnalysis::class))($admin, $video, ['start_seconds' => 0, 'end_seconds' => 90]);
    }
}
