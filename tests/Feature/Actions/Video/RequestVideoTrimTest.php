<?php

namespace Tests\Feature\Actions\Video;

use App\Actions\Video\RequestVideoTrim;
use App\Jobs\TrimVideoJob;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RequestVideoTrimTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_uploader_can_request_a_trim_of_their_own_video(): void
    {
        Queue::fake();

        $workspace = Workspace::factory()->create();
        $uploader = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $uploader, 'member');
        $video = Video::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $uploader->id,
            'duration_seconds' => 60,
        ]);

        $job = (app(RequestVideoTrim::class))($uploader, $video, [
            'start_seconds' => 5,
            'end_seconds' => 20,
        ]);

        $this->assertSame('pending', $job->status);
        $this->assertSame($video->id, $job->video_id);
        Queue::assertPushed(TrimVideoJob::class, fn (TrimVideoJob $pushed) => $pushed->videoToolJob->is($job));
    }

    public function test_the_end_timestamp_must_be_after_the_start_timestamp(): void
    {
        Queue::fake();

        $workspace = Workspace::factory()->create();
        $uploader = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $uploader, 'member');
        $video = Video::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $uploader->id,
            'duration_seconds' => 60,
        ]);

        $this->expectException(ValidationException::class);

        (app(RequestVideoTrim::class))($uploader, $video, [
            'start_seconds' => 20,
            'end_seconds' => 5,
        ]);
    }

    public function test_an_outsider_cannot_request_a_trim(): void
    {
        Queue::fake();

        $workspace = Workspace::factory()->create();
        $uploader = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $uploader, 'member');
        $video = Video::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $uploader->id,
            'duration_seconds' => 60,
        ]);
        $outsider = User::factory()->create();

        try {
            (app(RequestVideoTrim::class))($outsider, $video, ['start_seconds' => 5, 'end_seconds' => 20]);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        Queue::assertNotPushed(TrimVideoJob::class);
    }
}
