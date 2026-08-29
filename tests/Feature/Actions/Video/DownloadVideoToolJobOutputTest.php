<?php

namespace Tests\Feature\Actions\Video;

use App\Actions\Video\DownloadVideoToolJobOutput;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoToolJob;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DownloadVideoToolJobOutputTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_workspace_member_can_download_a_completed_jobs_output(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('tool-jobs/1/output.mp4', 'fake video bytes');

        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        $toolJob = VideoToolJob::factory()->create([
            'video_id' => $video->id,
            'status' => 'completed',
            'output_disk' => 'local',
            'output_path' => 'tool-jobs/1/output.mp4',
        ]);

        $response = (app(DownloadVideoToolJobOutput::class))($member, $video, $toolJob);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
    }

    public function test_a_job_that_is_not_yet_completed_cannot_be_downloaded(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        $toolJob = VideoToolJob::factory()->create(['video_id' => $video->id, 'status' => 'processing']);

        try {
            (app(DownloadVideoToolJobOutput::class))($member, $video, $toolJob);
            $this->fail('Expected a 404 not found exception.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }
}
