<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GenerateVideoInsightsJob;
use App\Models\Video;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateVideoInsightsJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The job's own backoff runs between separate queue executions (each
     * getting a fresh worker timeout), which is what makes it safe to wait
     * long enough to ride out an AI-provider rate limit - unlike the
     * shorter, in-request retry inside GenerateVideoInsights itself.
     */
    public function test_it_backs_off_long_enough_to_ride_out_a_rate_limit_window(): void
    {
        $video = Video::factory()->create();

        $backoff = (new GenerateVideoInsightsJob($video))->backoff();

        $this->assertSame([20, 60], $backoff);
    }

    public function test_it_records_the_video_as_failed_when_every_attempt_is_exhausted(): void
    {
        $video = Video::factory()->create();

        (new GenerateVideoInsightsJob($video))->failed(new Exception('rate limited'));

        $this->assertNotNull($video->fresh()->insights_failed_at);
    }
}
