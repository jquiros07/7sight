<?php

namespace Tests\Feature\Jobs;

use App\Enums\VideoToolType;
use App\Jobs\ResizeVideoJob;
use App\Models\Video;
use App\Models\VideoToolJob;
use App\Support\FfmpegVideoProcessor;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ResizeVideoJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_the_job_completed_with_the_output_location(): void
    {
        Storage::fake('local');
        $video = Video::factory()->create();
        $job = VideoToolJob::factory()->create([
            'video_id' => $video->id,
            'type' => VideoToolType::Resize,
            'status' => 'pending',
            'params' => ['width' => 1280, 'height' => 720],
        ]);
        $processor = Mockery::mock(FfmpegVideoProcessor::class);
        $processor->shouldReceive('resize')->once()->with(
            Mockery::type('string'), Mockery::type('string'), 1280, 720
        );

        (new ResizeVideoJob($job))->handle($processor);

        $job->refresh();
        $this->assertSame('completed', $job->status);
        $this->assertSame($video->disk, $job->output_disk);
        $this->assertNotNull($job->output_path);
        $this->assertNotNull($job->completed_at);
    }

    public function test_it_marks_the_job_failed_once_retries_are_exhausted(): void
    {
        $video = Video::factory()->create();
        $job = VideoToolJob::factory()->create(['video_id' => $video->id, 'type' => VideoToolType::Resize]);

        (new ResizeVideoJob($job))->failed(new Exception('ffmpeg exploded'));

        $job->refresh();
        $this->assertSame('failed', $job->status);
        $this->assertSame('ffmpeg exploded', $job->error_message);
    }
}
