<?php

namespace App\Actions\Video;

use App\Models\User;
use App\Models\Video;
use App\Models\VideoInsight;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;
use Throwable;

class RecordAiContentInsight
{
    /**
     * Fold a completed AI-generated-content finding into a new insight row,
     * carrying forward the video's other already-generated insight fields so
     * this doesn't blank them out - same "new row per update" shape
     * GenerateVideoInsights already uses. Not user-authorized: this runs
     * from AnalyzeAiGeneratedContentJob once the finding exists, not from an
     * HTTP request.
     *
     * @param  array<string, mixed>  $result
     */
    public function __invoke(Video $video, ?User $user, array $result): VideoInsight
    {
        $latest = $video->latestInsight;

        $insight = VideoInsight::create([
            'video_id' => $video->id,
            'user_id' => $user?->id,
            'object_detection' => $latest?->object_detection,
            'threat_assessment' => $latest?->threat_assessment,
            'moderation' => $latest?->moderation,
            'text_detection' => $latest?->text_detection,
            'ai_content_assessment' => $result,
        ]);

        $this->generateEmbedding($insight);

        return $insight;
    }

    /**
     * Generate and store a search embedding for this insight. Failure here
     * is logged but never blocks recording the finding - embeddings are a
     * search-optimization side effect, not the primary value of this action.
     */
    private function generateEmbedding(VideoInsight $insight): void
    {
        try {
            $embedding = Embeddings::for([$insight->searchableText()])->generate(Lab::Gemini)->first();

            $insight->update(['embedding' => $embedding]);
        } catch (Throwable $e) {
            Log::error($e->getMessage(), ['exception' => $e, 'video_insight_id' => $insight->id]);
        }
    }
}
