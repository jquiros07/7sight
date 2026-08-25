<?php

namespace Tests\Feature\Actions\Video;

use App\Actions\Video\StreamVideo;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StreamVideoTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_workspace_member_can_stream_the_video_file(): void
    {
        Storage::fake('local');
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');
        $video = Video::factory()->create([
            'workspace_id' => $workspace->id,
            'disk' => 'local',
            'path' => 'videos/sample.mp4',
            'mime_type' => 'video/mp4',
        ]);
        Storage::disk('local')->put($video->path, 'fake-video-bytes');

        $response = (new StreamVideo)($member, $video);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame('video/mp4', $response->headers->get('Content-Type'));
    }

    public function test_an_outsider_cannot_stream_the_video(): void
    {
        Storage::fake('local');
        $workspace = Workspace::factory()->create();
        $video = Video::factory()->create(['workspace_id' => $workspace->id, 'disk' => 'local', 'path' => 'videos/sample.mp4']);
        Storage::disk('local')->put($video->path, 'fake-video-bytes');
        $outsider = User::factory()->create();

        try {
            (new StreamVideo)($outsider, $video);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
