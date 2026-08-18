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
class VideoSearchAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You are a video intelligence analyst helping a user search a library of
            already-analyzed videos using a free-text query. You will be given the
            user's query and a list of candidate videos, each with its previously
            generated analysis summaries (object detection, threat assessment,
            content moderation). Any of these three may be null for a given video if
            that analysis type was never run on it. You have not seen the videos
            themselves - base everything strictly on the given summaries.

            Instructions:
            1. Read the query and determine its intent, including reasonable
               synonyms and related concepts - e.g. a query about "weapons" should
               match videos whose data mentions guns, knives, or similar, even if
               the word "weapon" doesn't appear verbatim.
            2. Compare the query's intent against each candidate video's summaries.
            3. Only return videos that are genuinely relevant to the query. It's
               fine, and expected, to return no matches if none of the candidates
               fit.
            4. Do not invent details that aren't present in the given summaries.
            5. For each match, assign a relevance of HIGH, MEDIUM, or LOW.
            6. For each match, give a one-sentence reason grounded specifically in
               that video's data - not a restatement of the query.
            7. Order matches from most to least relevant.
            8. Use the exact "video_id" values given - never invent or alter one.
            INSTRUCTIONS;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'matches' => $schema->array()
                ->items($schema->object(fn (JsonSchema $schema) => [
                    'video_id' => $schema->integer()->required(),
                    'relevance' => $schema->string()
                        ->enum(['HIGH', 'MEDIUM', 'LOW'])
                        ->required(),
                    'reason' => $schema->string()
                        ->description('A one-sentence reason this video matches the query, grounded in its analysis data.')
                        ->required(),
                ]))
                ->description('Candidate videos that genuinely match the query, ordered from most to least relevant. Empty if none match.')
                ->required(),
        ];
    }
}
