<?php

namespace Tests\Feature\Jobs;

use App\Actions\Video\RecordAiContentInsight;
use App\Ai\Agents\AiGeneratedContentAgent;
use App\Jobs\AnalyzeAiGeneratedContentJob;
use App\Models\AiContentAnalysis;
use App\Models\Video;
use App\Models\VideoInsight;
use App\Support\FfmpegVideoProcessor;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Embeddings;
use Mockery;
use Tests\TestCase;

class AnalyzeAiGeneratedContentJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_the_analysis_completed_with_the_agents_verdict(): void
    {
        Storage::fake('local');
        Embeddings::fake([[[0.1, 0.2, 0.3]]]);
        AiGeneratedContentAgent::fake([[
            'verdict' => 'AUTHENTIC',
            'confidence' => 82,
            'reasoning' => 'No manipulation artifacts observed.',
            'indicators' => [],
        ]]);
        $video = Video::factory()->create(['width' => 640, 'height' => 360]);
        $analysis = AiContentAnalysis::factory()->create([
            'video_id' => $video->id,
            'status' => 'pending',
            'start_seconds' => 0,
            'end_seconds' => 30,
        ]);
        $processor = Mockery::mock(FfmpegVideoProcessor::class);
        $processor->shouldReceive('trim')->once();
        $processor->shouldNotReceive('resize');

        (new AnalyzeAiGeneratedContentJob($analysis))->handle($processor, new RecordAiContentInsight);

        $analysis->refresh();
        $this->assertSame('completed', $analysis->status);
        $this->assertSame('AUTHENTIC', $analysis->result['verdict']);
        $this->assertNotNull($analysis->completed_at);

        $insight = $video->fresh()->latestInsight;
        $this->assertNotNull($insight);
        $this->assertSame('AUTHENTIC', $insight->ai_content_assessment['verdict']);
        $this->assertSame(0, $insight->ai_content_assessment['start_seconds']);
        $this->assertSame(30, $insight->ai_content_assessment['end_seconds']);
    }

    public function test_it_downscales_a_clip_larger_than_the_max_dimension(): void
    {
        Storage::fake('local');
        Embeddings::fake([[[0.1, 0.2, 0.3]]]);
        AiGeneratedContentAgent::fake([[
            'verdict' => 'INCONCLUSIVE',
            'confidence' => 40,
            'reasoning' => 'Too little signal.',
            'indicators' => [],
        ]]);
        $video = Video::factory()->create(['width' => 1920, 'height' => 1080]);
        $analysis = AiContentAnalysis::factory()->create(['video_id' => $video->id]);
        $processor = Mockery::mock(FfmpegVideoProcessor::class);
        $processor->shouldReceive('trim')->once();
        $processor->shouldReceive('resize')->once()->with(
            Mockery::type('string'), Mockery::type('string'), 854, 480
        );

        (new AnalyzeAiGeneratedContentJob($analysis))->handle($processor, new RecordAiContentInsight);

        $this->assertSame('completed', $analysis->fresh()->status);
    }

    public function test_it_carries_forward_existing_insight_fields(): void
    {
        Storage::fake('local');
        Embeddings::fake([[[0.1, 0.2, 0.3]]]);
        AiGeneratedContentAgent::fake([[
            'verdict' => 'AI_GENERATED',
            'confidence' => 90,
            'reasoning' => 'Clear manipulation artifacts.',
            'indicators' => ['warping'],
        ]]);
        $video = Video::factory()->create(['width' => 640, 'height' => 360]);
        VideoInsight::create([
            'video_id' => $video->id,
            'object_detection' => ['summary' => 'A person walks by.'],
        ]);
        $analysis = AiContentAnalysis::factory()->create(['video_id' => $video->id]);
        $processor = Mockery::mock(FfmpegVideoProcessor::class);
        $processor->shouldReceive('trim')->once();

        (new AnalyzeAiGeneratedContentJob($analysis))->handle($processor, new RecordAiContentInsight);

        $insight = $video->fresh()->latestInsight;
        $this->assertSame('A person walks by.', $insight->object_detection['summary']);
        $this->assertSame('AI_GENERATED', $insight->ai_content_assessment['verdict']);
    }

    public function test_it_marks_the_analysis_failed_once_retries_are_exhausted(): void
    {
        $analysis = AiContentAnalysis::factory()->create();

        (new AnalyzeAiGeneratedContentJob($analysis))->failed(new Exception('gemini exploded'));

        $analysis->refresh();
        $this->assertSame('failed', $analysis->status);
        $this->assertSame('gemini exploded', $analysis->error_message);
    }
}
