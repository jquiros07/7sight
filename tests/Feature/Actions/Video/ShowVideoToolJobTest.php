<?php

namespace Tests\Feature\Actions\Video;

use App\Actions\Video\ShowVideoToolJob;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoToolJob;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ShowVideoToolJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_workspace_member_can_view_a_tool_jobs_status(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        $toolJob = VideoToolJob::factory()->create(['video_id' => $video->id, 'status' => 'completed']);

        $result = (app(ShowVideoToolJob::class))($member, $video, $toolJob);

        $this->assertTrue($result->is($toolJob));
    }

    public function test_a_tool_job_from_another_video_is_not_found(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        $otherVideo = Video::factory()->create();
        $toolJob = VideoToolJob::factory()->create(['video_id' => $otherVideo->id]);

        try {
            (app(ShowVideoToolJob::class))($member, $video, $toolJob);
            $this->fail('Expected a 404 not found exception.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_an_outsider_cannot_view_a_tool_jobs_status(): void
    {
        $workspace = Workspace::factory()->create();
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        $toolJob = VideoToolJob::factory()->create(['video_id' => $video->id]);
        $outsider = User::factory()->create();

        try {
            (app(ShowVideoToolJob::class))($outsider, $video, $toolJob);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
