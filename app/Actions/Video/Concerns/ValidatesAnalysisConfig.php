<?php

namespace App\Actions\Video\Concerns;

use App\Enums\AnalysisType;
use App\Support\ObjectDetectionCatalog;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesAnalysisConfig
{
    /**
     * @param  bool  $required  Whether at least one analysis type must be selected. Required on
     *                          upload; on update, videos may be saved with none selected (including
     *                          videos created before this feature existed, which have none by default).
     * @return array<string, array<int, mixed>>
     */
    protected function analysisConfigRules(bool $required = true): array
    {
        return [
            'analysis_types' => $required ? ['required', 'array', 'min:1'] : ['sometimes', 'array'],
            'analysis_types.*' => [Rule::enum(AnalysisType::class)],
            'auto_start_analysis' => ['nullable', 'boolean'],
            'analysis_config' => ['nullable', 'array'],
            'analysis_config.object_detection.mode' => ['required_with:analysis_config.object_detection', Rule::in(['all', 'specific'])],
            'analysis_config.object_detection.objects' => ['required_if:analysis_config.object_detection.mode,specific', 'array', 'min:1'],
            'analysis_config.object_detection.objects.*' => [Rule::in(ObjectDetectionCatalog::values())],
        ];
    }

    protected function applyAnalysisConfigSometimes(Validator $validator): void
    {
        $validator->sometimes('analysis_config.object_detection', ['required', 'array'], function ($input) {
            return in_array(AnalysisType::ObjectDetection->value, (array) ($input->analysis_types ?? []), true);
        });
    }
}
