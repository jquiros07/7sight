<?php

namespace Tests\Feature\Actions\Video;

use App\Actions\Video\ExtractVideoAudio;
use App\Jobs\ExtractAudioJob;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ExtractVideoAudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_uploader_can_queue_audio_extraction_from_their_own_video(): void
    {
        Queue::fake();

        $workspace = Workspace::factory()->create();
        $uploader = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $uploader, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $uploader->id]);

        $job = (app(ExtractVideoAudio::class))($uploader, $video);

        $this->assertSame('pending', $job->status);
        $this->assertSame($video->id, $job->video_id);
        Queue::assertPushed(ExtractAudioJob::class, fn (ExtractAudioJob $pushed) => $pushed->videoToolJob->is($job));
    }

    public function test_an_outsider_cannot_queue_audio_extraction(): void
    {
        Queue::fake();

        $workspace = Workspace::factory()->create();
        $uploader = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $uploader, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $uploader->id]);
        $outsider = User::factory()->create();

        try {
            (app(ExtractVideoAudio::class))($outsider, $video);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        Queue::assertNotPushed(ExtractAudioJob::class);
    }
}
