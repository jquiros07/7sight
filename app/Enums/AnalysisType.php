<?php

namespace App\Enums;

enum AnalysisType: string
{
    case ContentModeration = 'content_moderation';
    case ObjectDetection = 'object_detection';
    case AiGenerated = 'ai_generated';
}
