<?php

namespace App\Enums;

enum AnalysisType: string
{
    case ContentModeration = 'content_moderation';
    case ObjectDetection = 'object_detection';
    case ThreatDetection = 'threat_detection';
    case AiGenerated = 'ai_generated';
}
