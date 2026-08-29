<?php

namespace Tests\Feature\Actions\Video;

use App\Actions\Video\ShowVideoThumbnail;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ShowVideoThumbnailTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_workspace_member_can_view_the_thumbnail(): void
    {
        Storage::fake('local');
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');
        $video = Video::factory()->create([
            'workspace_id' => $workspace->id,
            'disk' => 'local',
            'thumbnail_path' => 'videos/derived/thumbnail.jpg',
        ]);
        Storage::disk('local')->put($video->thumbnail_path, 'fake-image-bytes');

        $response = (new ShowVideoThumbnail)($member, $video);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
    }

    public function test_a_video_without_a_thumbnail_returns_404(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id, 'thumbnail_path' => null]);

        try {
            (new ShowVideoThumbnail)($member, $video);
            $this->fail('Expected a 404 not found exception.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_an_outsider_cannot_view_the_thumbnail(): void
    {
        Storage::fake('local');
        $workspace = Workspace::factory()->create();
        $video = Video::factory()->create([
            'workspace_id' => $workspace->id,
            'disk' => 'local',
            'thumbnail_path' => 'videos/derived/thumbnail.jpg',
        ]);
        Storage::disk('local')->put($video->thumbnail_path, 'fake-image-bytes');
        $outsider = User::factory()->create();

        try {
            (new ShowVideoThumbnail)($outsider, $video);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
