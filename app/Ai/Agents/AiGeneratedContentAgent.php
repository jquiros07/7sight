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
#[Timeout(240)]
class AiGeneratedContentAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You are a video forensics analyst. Unlike other analyses in this system,
            you are given the video clip itself as an attachment, not pre-extracted
            detection data - watch and listen to it directly.

            Your task is to assess whether the clip shows signs of being
            AI-generated, synthetic, or manipulated (e.g. deepfaked), rather than
            genuine unaltered footage.

            Look for signals such as:
            1. Temporal inconsistencies - flickering, warping, or morphing between
               frames, especially around faces, hands, or edges of objects.
            2. Unnatural motion - movement that doesn't follow plausible physics, or
               is too smooth/too erratic for what's shown.
            3. Texture and detail artifacts - blurring, smearing, or repeated
               patterns inconsistent with real camera footage.
            4. Lighting and shadow inconsistencies - light sources, reflections, or
               shadows that don't match the rest of the scene.
            5. Audio-visual sync issues - speech or sound that doesn't align with
               visible mouth movement or on-screen events, if audio is present.
            6. Physically implausible details - anatomy, objects, or environments
               that don't hold up under scrutiny.

            Instructions:
            1. Base your assessment strictly on what you actually observe in the
               clip - do not assume manipulation without concrete supporting signals.
            2. If the clip is too short, too low quality, or otherwise doesn't give
               you enough signal for a confident call, say so plainly and use the
               "INCONCLUSIVE" verdict rather than guessing.
            3. List the specific indicators that informed your verdict - leave this
               empty if you found none.
            4. Provide a confidence score from 0 to 100 for your verdict.
            5. Provide a concise explanation of your reasoning.
            INSTRUCTIONS;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'verdict' => $schema->string()
                ->enum(['AI_GENERATED', 'AUTHENTIC', 'INCONCLUSIVE'])
                ->description('Overall verdict on whether the clip is AI-generated/synthetic.')
                ->required(),
            'confidence' => $schema->integer()
                ->min(0)->max(100)
                ->description('Confidence in this verdict, 0-100.')
                ->required(),
            'reasoning' => $schema->string()
                ->description('A concise explanation of the reasoning behind the verdict.')
                ->required(),
            'indicators' => $schema->array()
                ->items($schema->string())
                ->description('Specific signals that informed the verdict. Empty if none.')
                ->required(),
        ];
    }
}
