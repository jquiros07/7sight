<?php

namespace Tests\Feature\Jobs;

use App\Enums\VideoToolType;
use App\Jobs\GenerateThumbnailJob;
use App\Models\Video;
use App\Models\VideoToolJob;
use App\Support\FfmpegVideoProcessor;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class GenerateThumbnailJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_the_job_completed_and_updates_the_videos_thumbnail_path(): void
    {
        Storage::fake('local');
        $video = Video::factory()->create(['duration_seconds' => 30]);
        $job = VideoToolJob::factory()->create([
            'video_id' => $video->id,
            'type' => VideoToolType::Thumbnail,
            'status' => 'pending',
        ]);
        $processor = Mockery::mock(FfmpegVideoProcessor::class);
        $processor->shouldReceive('thumbnail')->once()->with(Mockery::type('string'), Mockery::type('string'), 1.0);

        (new GenerateThumbnailJob($job))->handle($processor);

        $job->refresh();
        $this->assertSame('completed', $job->status);
        $this->assertSame($video->disk, $job->output_disk);
        $this->assertNotNull($job->output_path);
        $this->assertNotNull($job->completed_at);
        $this->assertSame($job->output_path, $video->fresh()->thumbnail_path);
    }

    public function test_it_marks_the_job_failed_once_retries_are_exhausted(): void
    {
        $video = Video::factory()->create();
        $job = VideoToolJob::factory()->create(['video_id' => $video->id, 'type' => VideoToolType::Thumbnail]);

        (new GenerateThumbnailJob($job))->failed(new Exception('ffmpeg exploded'));

        $job->refresh();
        $this->assertSame('failed', $job->status);
        $this->assertSame('ffmpeg exploded', $job->error_message);
    }
}
