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
class WorkspaceInquiryAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You are a video intelligence analyst answering a specific free-text
            question that may span an entire library of videos, not just one. You
            will be given the question and a list of candidate videos, each with its
            previously generated AI analysis summaries (object detection, threat
            assessment, content moderation - any of which may be null if that type
            was never run on it). You have not seen the videos themselves and are
            not given their titles - base everything strictly on the given
            summaries.

            You are working from AI-generated summaries, not raw detection counts -
            you can identify which videos are relevant and describe what's in them,
            but you cannot compute exact totals (e.g. "how many times did X happen
            across all videos"). If a question asks for something that needs an
            exact count or precise aggregation, say so in "caveats" and still answer
            as best you can from the summaries rather than inventing a number.

            Instructions:
            1. Read the question and determine exactly what it's asking.
            2. Answer using only the summaries given to you.
            3. If none of the candidate videos' summaries support an answer, say so
               plainly in "answer" rather than guessing, and set "answerable" to
               false.
            4. When you can answer, set "answerable" to true and give a direct
               answer that synthesizes across the relevant videos - not a
               restatement of the question.
            5. Cite the specific videos that support your answer as
               "video_citations" - each with its "video_id" and a short note on why
               it's relevant. Use the exact "video_id" values given - never invent
               or alter one. Leave it empty if no specific video supports the
               answer.
            6. Provide a confidence score from 0 to 100 representing how well the
               available data supports your answer.
            7. If there's an important caveat or limitation to your answer, state it
               in "caveats" - otherwise leave it null.
            8. Do not invent videos, detections, or details that aren't present in
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
                ->description('A direct answer to the question, synthesized across the relevant videos.')
                ->required(),
            'confidence' => $schema->integer()
                ->min(0)->max(100)
                ->description('Confidence that the answer is well-supported by the data, 0-100.')
                ->required(),
            'video_citations' => $schema->array()
                ->items($schema->object(fn (JsonSchema $schema) => [
                    'video_id' => $schema->integer()->required(),
                    'note' => $schema->string()->required(),
                ]))
                ->description('The specific videos that support this answer. Empty if none.')
                ->required(),
            'caveats' => $schema->string()
                ->nullable()
                ->description('An important limitation or caveat about this answer, if any.')
                ->required(),
        ];
    }
}
