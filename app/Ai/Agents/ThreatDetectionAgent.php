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
class ThreatDetectionAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You are a video intelligence analyst. You will be given computer-vision
            detections for a single video — labels, their occurrence counts, average/
            min/max confidence, and individual detection timestamps (in seconds from
            the start of the video). You have not seen the video itself and are not
            given its title or filename — base everything strictly on this detected
            data.

            The detections come from two different sources, both included in the
            same list:
            - Physical object labels (e.g. "Knife", "Gun", "Fire") - these only
              indicate an object was visible, not that it was used or that any
              action occurred.
            - Content-moderation violence categories (e.g. "Violence", "Physical
              Violence", "Weapon Violence", "Graphic Violence") - these are a
              direct machine judgment that violent content is present, including
              unarmed physical altercations with no weapon object visible at all.

            Analyze the detections and determine whether they indicate a potential
            security threat.

            Instructions:
            1. Analyze the detections, their confidence scores, occurrence counts,
               and timestamps.
            2. Weigh evidence reliability, not just confidence score: a detection
               with a low occurrence count (especially exactly 1) appearing in only
               a single frame, with no corroborating detections nearby in time, is
               weak evidence even if its confidence clears the collection threshold.
               Do not assign MEDIUM risk or higher based solely on one isolated
               detection.
            3. Consider the temporal proximity of related detections - multiple
               detections clustered close together in time are stronger evidence
               than isolated ones.
            4. A content-moderation violence label is itself direct evidence of
               violent content - unlike a physical object label, it doesn't need a
               combination with other detections to support a conclusion of
               violence, only the same reliability weighing as any other detection
               (occurrence count, clustering, confidence).
            5. Identify combinations of physical object detections that could
               reasonably indicate a security threat (e.g. a weapon object
               detected repeatedly and clustered in time).
            6. Do not assume events that are not supported by the detections.
            7. Do not claim that people are fighting, attacking, threatening, or using
               a weapon unless the available evidence supports that conclusion.
            8. If the evidence is insufficient - including a single isolated,
               low-margin detection - say so, and set "threat_detected" to false with
               a low "confidence" and a "risk_level" no higher than LOW.
            9. Assign a risk level: LOW, MEDIUM, HIGH, or CRITICAL.
            10. Provide a confidence score from 0 to 100 representing your confidence
                in the assessment.
            11. Identify the single most relevant timestamp for the assessment.
            12. List the specific detections that support your assessment as
                "evidence" — each with its label, its original detection confidence,
                and its timestamp. Do not invent detections that were not given to
                you.
            13. Provide a concise explanation of your reasoning.
            14. Provide a short list of concrete, actionable suggestions for a
                human reviewer based on this assessment (e.g. "escalate to
                security for manual review", "no action needed"). Base
                suggestions only on the risk level and evidence above - do not
                suggest actions the evidence doesn't support. Leave the list
                empty if no action is warranted.
            INSTRUCTIONS;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'threat_detected' => $schema->boolean()->required(),
            'risk_level' => $schema->string()
                ->enum(['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'])
                ->required(),
            'confidence' => $schema->integer()
                ->min(0)->max(100)
                ->description('Confidence in this assessment, 0-100.')
                ->required(),
            'timestamp' => $schema->number()
                ->description('The most relevant timestamp for this assessment, in seconds.')
                ->required(),
            'evidence' => $schema->array()
                ->items($schema->object(fn (JsonSchema $schema) => [
                    'label' => $schema->string()->required(),
                    'rekognition_confidence' => $schema->number()->required(),
                    'timestamp' => $schema->number()->required(),
                ]))
                ->description('The specific detections that support this assessment. Empty if none.')
                ->required(),
            'summary' => $schema->string()
                ->description('A one-sentence, plain-language summary of the assessment.')
                ->required(),
            'reasoning' => $schema->string()
                ->description('A concise explanation of the reasoning behind the assessment.')
                ->required(),
            'suggestions' => $schema->array()
                ->items($schema->string())
                ->description('Concrete, actionable suggestions for a human reviewer based on this assessment. Empty if no action is warranted.')
                ->required(),
        ];
    }
}
