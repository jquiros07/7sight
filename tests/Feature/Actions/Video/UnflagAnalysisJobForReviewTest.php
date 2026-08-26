<?php

namespace Tests\Feature\Actions\Video;

use App\Actions\Video\UnflagAnalysisJobForReview;
use App\Models\AnalysisJob;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class UnflagAnalysisJobForReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_plain_member_can_clear_a_flag(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        $job = AnalysisJob::factory()->create([
            'video_id' => $video->id,
            'status' => 'completed',
            'flagged_for_review_at' => now(),
            'flagged_by' => $member->id,
            'flagged_review_note' => 'Looked wrong',
        ]);

        $result = (new UnflagAnalysisJobForReview)($member, $video, $job);

        $this->assertNull($result->flagged_for_review_at);
        $this->assertNull($result->flagged_by);
        $this->assertNull($result->flagged_review_note);
    }

    public function test_an_outsider_cannot_clear_a_flag(): void
    {
        $workspace = Workspace::factory()->create();
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        $job = AnalysisJob::factory()->create([
            'video_id' => $video->id,
            'status' => 'completed',
            'flagged_for_review_at' => now(),
        ]);
        $outsider = User::factory()->create();

        try {
            (new UnflagAnalysisJobForReview)($outsider, $video, $job);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertNotNull($job->fresh()->flagged_for_review_at);
    }
}
