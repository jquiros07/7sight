<?php

namespace Tests\Feature\Actions\Video;

use App\Actions\Video\ExtractVideoAudio;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use App\Support\FfmpegVideoProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ExtractVideoAudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_uploader_can_extract_audio_from_their_own_video(): void
    {
        Storage::fake('local');
        $this->mock(FfmpegVideoProcessor::class, function ($mock) {
            $mock->shouldReceive('extractAudio')->once()->andReturnUsing(function ($input, string $output) {
                touch($output);
            });
        });

        $workspace = Workspace::factory()->create();
        $uploader = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $uploader, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $uploader->id]);

        $response = (app(ExtractVideoAudio::class))($uploader, $video);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
    }

    public function test_an_outsider_cannot_extract_audio(): void
    {
        Storage::fake('local');
        $this->mock(FfmpegVideoProcessor::class, function ($mock) {
            $mock->shouldNotReceive('extractAudio');
        });

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
    }
}
