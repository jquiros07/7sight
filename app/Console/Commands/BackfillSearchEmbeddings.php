<?php

namespace App\Console\Commands;

use App\Models\VideoInsight;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;
use Throwable;

class BackfillSearchEmbeddings extends Command
{
    protected $signature = 'videos:backfill-search-embeddings';

    protected $description = 'Generate search embeddings for existing video insights that predate embedding-based search.';

    public function handle(): int
    {
        VideoInsight::query()
            ->whereNull('embedding')
            ->where(function ($query) {
                $query->whereNotNull('object_detection')
                    ->orWhereNotNull('threat_assessment')
                    ->orWhereNotNull('moderation')
                    ->orWhereNotNull('text_detection');
            })
            ->chunkById(50, function ($insights) {
                foreach ($insights as $insight) {
                    try {
                        $embedding = Embeddings::for([$insight->searchableText()])->generate(Lab::Gemini)->first();

                        $insight->update(['embedding' => $embedding]);

                        $this->info("Embedded video insight {$insight->id} (video {$insight->video_id})");
                    } catch (Throwable $e) {
                        Log::error($e->getMessage(), ['exception' => $e, 'video_insight_id' => $insight->id]);
                        $this->error("Failed to embed video insight {$insight->id}: {$e->getMessage()}");
                    }
                }
            });

        return self::SUCCESS;
    }
}
