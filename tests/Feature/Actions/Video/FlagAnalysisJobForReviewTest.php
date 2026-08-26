<?php

namespace Tests\Feature\Actions\Video;

use App\Actions\Video\FlagAnalysisJobForReview;
use App\Models\AnalysisJob;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class FlagAnalysisJobForReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_plain_member_can_flag_a_job_for_review(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        $job = AnalysisJob::factory()->create(['video_id' => $video->id, 'status' => 'completed']);

        $result = (new FlagAnalysisJobForReview)($member, $video, $job, 'Missed a weapon at 1:32');

        $this->assertNotNull($result->flagged_for_review_at);
        $this->assertSame($member->id, $result->flagged_by);
        $this->assertSame('Missed a weapon at 1:32', $result->flagged_review_note);
    }

    public function test_the_note_is_optional(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        $job = AnalysisJob::factory()->create(['video_id' => $video->id, 'status' => 'completed']);

        $result = (new FlagAnalysisJobForReview)($member, $video, $job, null);

        $this->assertNotNull($result->flagged_for_review_at);
        $this->assertNull($result->flagged_review_note);
    }

    public function test_an_outsider_cannot_flag_a_job(): void
    {
        $workspace = Workspace::factory()->create();
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        $job = AnalysisJob::factory()->create(['video_id' => $video->id, 'status' => 'completed']);
        $outsider = User::factory()->create();

        try {
            (new FlagAnalysisJobForReview)($outsider, $video, $job, null);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertNull($job->fresh()->flagged_for_review_at);
    }

    /**
     * Guards against an IDOR-style mismatch: flagging a job through a video
     * it doesn't actually belong to (e.g. a mismatched route parameter).
     */
    public function test_it_rejects_a_job_that_does_not_belong_to_the_given_video(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        $otherVideo = Video::factory()->create(['workspace_id' => $workspace->id]);
        $job = AnalysisJob::factory()->create(['video_id' => $otherVideo->id, 'status' => 'completed']);

        try {
            (new FlagAnalysisJobForReview)($member, $video, $job, null);
            $this->fail('Expected a 404 exception.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }
}
