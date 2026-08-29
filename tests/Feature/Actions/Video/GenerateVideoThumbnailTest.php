<?php

namespace Tests\Feature\Actions\Video;

use App\Actions\Video\GenerateVideoThumbnail;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use App\Support\FfmpegVideoProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class GenerateVideoThumbnailTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_uploader_can_generate_a_thumbnail_for_their_own_video(): void
    {
        Storage::fake('local');
        $this->mock(FfmpegVideoProcessor::class, function ($mock) {
            $mock->shouldReceive('thumbnail')->once();
        });

        $workspace = Workspace::factory()->create();
        $uploader = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $uploader, 'member');
        $video = Video::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $uploader->id,
            'duration_seconds' => 30,
        ]);

        $result = (app(GenerateVideoThumbnail::class))($uploader, $video);

        $this->assertNotNull($result->thumbnail_path);
        $this->assertSame(
            "videos/{$workspace->id}/{$uploader->id}/derived/{$video->id}/thumbnail.jpg",
            $result->thumbnail_path
        );
    }

    public function test_an_outsider_cannot_generate_a_thumbnail(): void
    {
        Storage::fake('local');
        $this->mock(FfmpegVideoProcessor::class, function ($mock) {
            $mock->shouldNotReceive('thumbnail');
        });

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

        $this->assertNull($video->fresh()->thumbnail_path);
    }
}
