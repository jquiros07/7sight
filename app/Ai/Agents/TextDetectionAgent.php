<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::Gemini)]
#[Model('gemini-3.6-flash')]
#[Timeout(120)]
class TextDetectionAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You are a video intelligence analyst. You will be given on-screen text
            detected by a computer-vision OCR system for a single video — each
            distinct piece of text, its occurrence count, average/min/max confidence,
            and individual detection timestamps (in seconds from the start of the
            video). You have not seen the video itself and are not given its title or
            filename — base everything strictly on this detected data.

            Instructions:
            1. Analyze the detected text, confidence scores, occurrence counts, and
               timestamps.
            2. Identify the most relevant pieces of text present in the video (e.g.
               signage, captions, license plates, product labels, on-screen
               graphics).
            3. Group repeated or near-identical detections of the same text when
               appropriate.
            4. Consider the timestamps to understand when text appears or
               disappears.
            5. Do not assume text is present if it was not detected, and do not
               correct or "clean up" garbled OCR output — report it as detected.
            6. Do not infer meaning, intent, or events beyond what the text itself
               plainly states.
            7. Highlight notable text — e.g. warning signs, names, addresses, phone
               numbers, or anything that stands out as significant.
            8. If the detections are insufficient to determine something, explicitly
               say so.
            9. Provide a concise summary of what text was detected.
            10. Assign an overall confidence score from 0 to 100 representing your
                confidence in the summary.
            11. Identify the most relevant timestamp when applicable; if none is
                applicable, leave it null.
            INSTRUCTIONS;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()
                ->description('A concise summary of what text was detected.')
                ->required(),
            'confidence' => $schema->integer()
                ->min(0)->max(100)
                ->description('Confidence in this summary, 0-100.')
                ->required(),
            'timestamp' => $schema->number()
                ->nullable()
                ->description('The most relevant timestamp, in seconds, when applicable.')
                ->required(),
            'detected_text' => $schema->array()
                ->items($schema->object(fn (JsonSchema $schema) => [
                    'text' => $schema->string()->required(),
                    'occurrences' => $schema->integer()->required(),
                ]))
                ->description('The most relevant text detected, with repeated detections grouped.')
                ->required(),
            'notable_observations' => $schema->array()
                ->items($schema->string())
                ->description('Notable text — warnings, names, addresses, or anything else significant. Empty if none.')
                ->required(),
        ];
    }
}
