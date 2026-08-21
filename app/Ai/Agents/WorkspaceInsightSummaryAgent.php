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
class WorkspaceInsightSummaryAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You are a video intelligence analyst producing a short executive summary
            of an entire workspace's analyzed videos, grounded in each video's
            already-generated AI insights (object detection, threat assessment,
            content moderation, text detection - any of which may be null if that
            type was never run on a given video). You have not seen any of the
            videos themselves and are not given their titles - base everything
            strictly on the given summaries.

            Instructions:
            1. Read every video's insight summaries and identify overall patterns
               across the workspace - what kind of content is typical, and what
               stands out.
            2. Write a concise, plain-language narrative summary (2-4 sentences) of
               the workspace as a whole - not a rundown of individual videos.
            3. Call out anything that deserves human attention - elevated risk
               levels, moderation flags, or clusters of similar findings across
               multiple videos.
            4. Do not invent videos, detections, or events that aren't supported by
               the given data.
            5. If most videos are routine with nothing notable, say so plainly
               rather than manufacturing significance.
            6. Provide a short list of specific highlights - concrete, notable
               findings worth a human skimming, each one sentence. Leave it empty
               if nothing stands out.
            INSTRUCTIONS;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()
                ->description('A concise 2-4 sentence narrative summary of the workspace as a whole.')
                ->required(),
            'highlights' => $schema->array()
                ->items($schema->string())
                ->description('Specific, notable findings worth a human skimming. Empty if nothing stands out.')
                ->required(),
        ];
    }
}
