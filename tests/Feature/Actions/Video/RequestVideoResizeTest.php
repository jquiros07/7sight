<?php

namespace Tests\Feature\Actions\Video;

use App\Actions\Video\RequestVideoResize;
use App\Jobs\ResizeVideoJob;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RequestVideoResizeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_uploader_can_request_a_resize_of_their_own_video(): void
    {
        Queue::fake();

        $workspace = Workspace::factory()->create();
        $uploader = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $uploader, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $uploader->id]);

        $job = (app(RequestVideoResize::class))($uploader, $video, [
            'width' => 1280,
            'height' => 720,
        ]);

        $this->assertSame('pending', $job->status);
        $this->assertSame($video->id, $job->video_id);
        Queue::assertPushed(ResizeVideoJob::class, fn (ResizeVideoJob $pushed) => $pushed->videoToolJob->is($job));
    }

    public function test_dimensions_beyond_the_allowed_maximum_are_rejected(): void
    {
        Queue::fake();

        $workspace = Workspace::factory()->create();
        $uploader = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $uploader, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $uploader->id]);

        $this->expectException(ValidationException::class);

        (app(RequestVideoResize::class))($uploader, $video, ['width' => 7680, 'height' => 4320]);
    }

    public function test_an_outsider_cannot_request_a_resize(): void
    {
        Queue::fake();

        $workspace = Workspace::factory()->create();
        $uploader = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $uploader, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $uploader->id]);
        $outsider = User::factory()->create();

        try {
            (app(RequestVideoResize::class))($outsider, $video, ['width' => 1280, 'height' => 720]);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        Queue::assertNotPushed(ResizeVideoJob::class);
    }
}
