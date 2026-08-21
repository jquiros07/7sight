<?php

namespace Database\Factories;

use App\Enums\AnalysisType;
use App\Models\AnalysisJob;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalysisJob>
 */
class AnalysisJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'video_id' => Video::factory(),
            'type' => fake()->randomElement([AnalysisType::ObjectDetection, AnalysisType::ThreatDetection, AnalysisType::ContentModeration, AnalysisType::TextDetection]),
            'status' => fake()->randomElement(['pending', 'processing', 'completed', 'failed']),
            'attempts' => 0,
            'external_job_id' => null,
            'error_message' => null,
            'raw_output_path' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }
}
