<?php

namespace Tests\Unit\Models;

use App\Models\VideoInsight;
use Tests\TestCase;

class VideoInsightTest extends TestCase
{
    public function test_searchable_text_includes_only_populated_fields(): void
    {
        $insight = new VideoInsight([
            'object_detection' => ['summary' => 'a forklift'],
            'threat_assessment' => null,
            'moderation' => null,
            'text_detection' => ['summary' => 'a "DANGER" sign'],
        ]);

        $text = $insight->searchableText();

        $this->assertStringContainsString('object_detection', $text);
        $this->assertStringContainsString('a forklift', $text);
        $this->assertStringContainsString('text_detection', $text);
        $this->assertStringContainsString('DANGER', $text);
        $this->assertStringNotContainsString('threat_assessment', $text);
        $this->assertStringNotContainsString('moderation', $text);
    }
}
