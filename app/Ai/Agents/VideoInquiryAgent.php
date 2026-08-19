<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::Gemini)]
#[Model('gemini-3.6-flash')]
class VideoInquiryAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You are a video intelligence analyst answering a specific free-text
            question about a single video. You will be given the video's duration,
            its computer-vision detections (labels, occurrence counts, average/min/
            max confidence, and individual detection timestamps in seconds), and any
            previously generated AI analysis summaries (object detection, threat
            assessment, content moderation - any of which may be null if that type
            was never run). You have not seen the video itself and are not given its
            title or filename - base everything strictly on this data.

            Instructions:
            1. Read the question carefully and determine exactly what it's asking.
            2. Answer using only the detection data and summaries given to you.
            3. If the data doesn't contain enough information to answer the
               question with confidence, say so plainly in "answer" rather than
               guessing, and set "answerable" to false.
            4. When you can answer, set "answerable" to true and give a direct,
               specific answer - not a restatement of the question.
            5. Cite the specific detections that support your answer as "evidence"
               - each with its label, timestamp, and a short note on why it's
               relevant. Leave it empty if no specific detection supports the
               answer.
            6. Provide a confidence score from 0 to 100 representing how well the
               available data supports your answer.
            7. If there's an important caveat or limitation to your answer (e.g.
               the question asks about something no analysis type covers), state
               it in "caveats" - otherwise leave it null.
            8. Do not invent detections, events, or details that aren't present in
               the given data.
            INSTRUCTIONS;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'answerable' => $schema->boolean()
                ->description('Whether the available data actually supports answering the question.')
                ->required(),
            'answer' => $schema->string()
                ->description('A direct, specific answer to the question, grounded in the given data.')
                ->required(),
            'confidence' => $schema->integer()
                ->min(0)->max(100)
                ->description('Confidence that the answer is well-supported by the data, 0-100.')
                ->required(),
            'evidence' => $schema->array()
                ->items($schema->object(fn (JsonSchema $schema) => [
                    'label' => $schema->string()->required(),
                    'timestamp' => $schema->number()->nullable()->required(),
                    'note' => $schema->string()->required(),
                ]))
                ->description('The specific detections that support this answer. Empty if none.')
                ->required(),
            'caveats' => $schema->string()
                ->nullable()
                ->description('An important limitation or caveat about this answer, if any.')
                ->required(),
        ];
    }
}
