<?php

namespace Tests\Feature\Console;

use App\Models\Video;
use App\Models\VideoInsight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;
use Tests\TestCase;

class BackfillSearchEmbeddingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_embeds_an_insight_that_has_none(): void
    {
        Embeddings::fake([[[0.1, 0.2, 0.3]]]);

        $video = Video::factory()->create();
        $insight = VideoInsight::create([
            'video_id' => $video->id,
            'object_detection' => ['summary' => 'a forklift'],
        ]);

        $this->artisan('videos:backfill-search-embeddings')->assertExitCode(0);

        $this->assertSame([0.1, 0.2, 0.3], $insight->fresh()->embedding);
    }

    public function test_it_skips_an_insight_that_already_has_an_embedding(): void
    {
        Embeddings::fake([[[0.9, 0.9, 0.9]]]);

        $video = Video::factory()->create();
        $insight = VideoInsight::create([
            'video_id' => $video->id,
            'object_detection' => ['summary' => 'a forklift'],
            'embedding' => [0.1, 0.2, 0.3],
        ]);

        $this->artisan('videos:backfill-search-embeddings')->assertExitCode(0);

        $this->assertSame([0.1, 0.2, 0.3], $insight->fresh()->embedding);
    }
}
