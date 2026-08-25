<?php

namespace Tests\Feature\Actions\Video;

use App\Actions\Video\UploadVideo;
use App\Models\User;
use App\Models\Workspace;
use App\Support\VideoMetadataInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class UploadVideoTest extends TestCase
{
    use RefreshDatabase;

    private function fakeUploadedFile(): UploadedFile
    {
        return UploadedFile::fake()->create('clip.mp4', 100, 'video/mp4');
    }

    public function test_a_workspace_member_can_upload_a_video(): void
    {
        Storage::fake('local');
        $this->mock(VideoMetadataInspector::class, function ($mock) {
            $mock->shouldReceive('inspect')->andReturn(['duration' => 60.0, 'width' => 1280, 'height' => 720]);
        });

        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');

        $video = (app(UploadVideo::class))($member, [
            'workspace_id' => $workspace->id,
            'title' => 'Loading dock footage',
            'file' => $this->fakeUploadedFile(),
            'analysis_types' => ['threat_detection'],
        ]);

        $this->assertSame($workspace->id, $video->workspace_id);
        $this->assertSame($member->id, $video->user_id);
        $this->assertSame(60, $video->duration_seconds);
    }

    public function test_an_outsider_cannot_upload_to_the_workspace(): void
    {
        Storage::fake('local');
        $this->mock(VideoMetadataInspector::class, function ($mock) {
            $mock->shouldReceive('inspect')->andReturn(['duration' => 60.0, 'width' => 1280, 'height' => 720]);
        });

        $workspace = Workspace::factory()->create();
        $outsider = User::factory()->create();

        try {
            (app(UploadVideo::class))($outsider, [
                'workspace_id' => $workspace->id,
                'title' => 'Hijacked upload',
                'file' => $this->fakeUploadedFile(),
                'analysis_types' => ['threat_detection'],
            ]);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertDatabaseCount('videos', 0);
    }
}
