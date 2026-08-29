<?php

namespace Tests\Feature\Actions\Video;

use App\Actions\Video\RecordAiContentInsight;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoInsight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;
use Tests\TestCase;

class RecordAiContentInsightTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_insight_with_the_ai_content_finding(): void
    {
        Embeddings::fake([[[0.1, 0.2, 0.3]]]);
        $video = Video::factory()->create();
        $user = User::factory()->create();

        $insight = (new RecordAiContentInsight)($video, $user, [
            'verdict' => 'AI_GENERATED',
            'confidence' => 88,
            'reasoning' => 'Temporal artifacts observed.',
            'indicators' => ['flickering'],
            'start_seconds' => 5,
            'end_seconds' => 20,
        ]);

        $this->assertSame($video->id, $insight->video_id);
        $this->assertSame($user->id, $insight->user_id);
        $this->assertSame('AI_GENERATED', $insight->ai_content_assessment['verdict']);
        $this->assertNotNull($insight->embedding);
    }

    public function test_it_carries_forward_other_insight_fields_from_the_latest_row(): void
    {
        Embeddings::fake([[[0.1, 0.2, 0.3]]]);
        $video = Video::factory()->create();
        VideoInsight::create([
            'video_id' => $video->id,
            'threat_assessment' => ['risk_level' => 'LOW'],
            'moderation' => ['status' => 'SAFE'],
        ]);

        $insight = (new RecordAiContentInsight)($video, null, [
            'verdict' => 'AUTHENTIC',
            'confidence' => 95,
            'reasoning' => 'No artifacts found.',
            'indicators' => [],
            'start_seconds' => 0,
            'end_seconds' => 10,
        ]);

        $this->assertSame('LOW', $insight->threat_assessment['risk_level']);
        $this->assertSame('SAFE', $insight->moderation['status']);
        $this->assertSame('AUTHENTIC', $insight->ai_content_assessment['verdict']);
        $this->assertNull($insight->user_id);
    }
}
