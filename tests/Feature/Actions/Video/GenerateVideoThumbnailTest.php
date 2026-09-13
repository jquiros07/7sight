<?php

namespace Tests\Feature\Actions\Video;

use App\Actions\Video\GenerateVideoThumbnail;
use App\Jobs\GenerateThumbnailJob;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class GenerateVideoThumbnailTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_uploader_can_queue_a_thumbnail_for_their_own_video(): void
    {
        Queue::fake();

        $workspace = Workspace::factory()->create();
        $uploader = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $uploader, 'member');
        $video = Video::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $uploader->id,
            'duration_seconds' => 30,
        ]);

        $job = (app(GenerateVideoThumbnail::class))($uploader, $video);

        $this->assertSame('pending', $job->status);
        $this->assertSame($video->id, $job->video_id);
        Queue::assertPushed(GenerateThumbnailJob::class, fn (GenerateThumbnailJob $pushed) => $pushed->videoToolJob->is($job));
    }

    public function test_an_outsider_cannot_queue_a_thumbnail(): void
    {
        Queue::fake();

        $workspace = Workspace::factory()->create();
        $uploader = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $uploader, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $uploader->id]);
        $outsider = User::factory()->create();

        try {
            (app(GenerateVideoThumbnail::class))($outsider, $video);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        Queue::assertNotPushed(GenerateThumbnailJob::class);
    }
}
