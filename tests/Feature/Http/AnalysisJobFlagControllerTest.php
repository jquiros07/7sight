<?php

namespace Tests\Feature\Http;

use App\Models\AnalysisJob;
use App\Models\AnalysisResult;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exercises the real HTTP route/controller layer - the Action-level flag
 * tests only check the returned model's raw attributes, which wouldn't have
 * caught the actual bug: the JSON response was missing `results` entirely
 * because the Action didn't eager-load it, crashing the frontend on
 * `job.results.length`. See real_verification_over_mocks.
 */
class AnalysisJobFlagControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_flagging_over_http_returns_the_jobs_results_and_flagger(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $user, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        $job = AnalysisJob::factory()->create(['video_id' => $video->id, 'status' => 'completed']);
        AnalysisResult::factory()->create(['analysis_job_id' => $job->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/videos/{$video->id}/analysis-jobs/{$job->id}/flag", ['note' => 'Looked wrong']);

        $response->assertOk();
        $response->assertJsonCount(1, 'results');
        $response->assertJsonPath('flagged_by_user.id', $user->id);
        $response->assertJsonPath('flagged_review_note', 'Looked wrong');
    }

    public function test_unflagging_over_http_returns_the_jobs_results(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $user, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        $job = AnalysisJob::factory()->create([
            'video_id' => $video->id,
            'status' => 'completed',
            'flagged_for_review_at' => now(),
            'flagged_by' => $user->id,
        ]);
        AnalysisResult::factory()->create(['analysis_job_id' => $job->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/videos/{$video->id}/analysis-jobs/{$job->id}/flag");

        $response->assertOk();
        $response->assertJsonCount(1, 'results');
        $response->assertJsonPath('flagged_by_user', null);
    }
}
