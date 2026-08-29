<?php

namespace Tests\Feature\Jobs;

use App\Enums\VideoToolType;
use App\Jobs\TrimVideoJob;
use App\Models\Video;
use App\Models\VideoToolJob;
use App\Support\FfmpegVideoProcessor;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class TrimVideoJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_the_job_completed_with_the_output_location(): void
    {
        Storage::fake('local');
        $video = Video::factory()->create();
        $job = VideoToolJob::factory()->create([
            'video_id' => $video->id,
            'type' => VideoToolType::Trim,
            'status' => 'pending',
            'params' => ['start_seconds' => 5, 'end_seconds' => 20],
        ]);
        $processor = Mockery::mock(FfmpegVideoProcessor::class);
        $processor->shouldReceive('trim')->once()->with(
            Mockery::type('string'), Mockery::type('string'), 5.0, 15.0
        );

        (new TrimVideoJob($job))->handle($processor);

        $job->refresh();
        $this->assertSame('completed', $job->status);
        $this->assertSame($video->disk, $job->output_disk);
        $this->assertNotNull($job->output_path);
        $this->assertNotNull($job->completed_at);
    }

    public function test_it_marks_the_job_failed_once_retries_are_exhausted(): void
    {
        $video = Video::factory()->create();
        $job = VideoToolJob::factory()->create(['video_id' => $video->id, 'type' => VideoToolType::Trim]);

        (new TrimVideoJob($job))->failed(new Exception('ffmpeg exploded'));

        $job->refresh();
        $this->assertSame('failed', $job->status);
        $this->assertSame('ffmpeg exploded', $job->error_message);
    }
}
