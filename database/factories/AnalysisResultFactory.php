<?php

namespace Database\Factories;

use App\Models\AnalysisJob;
use App\Models\AnalysisResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalysisResult>
 */
class AnalysisResultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $occurrences = fake()->numberBetween(1, 500);
        $confidences = collect(range(1, min($occurrences, 20)))
            ->map(fn () => fake()->randomFloat(4, 0.5, 1));
        $timestamps = collect(range(1, min($occurrences, 20)))
            ->map(fn () => fake()->randomFloat(3, 0, 600))
            ->sort()
            ->values();

        return [
            'analysis_job_id' => AnalysisJob::factory(),
            'label' => fake()->randomElement(['person', 'vehicle', 'animal', 'text', 'logo']),
            'occurrences' => $occurrences,
            'avg_confidence' => round($confidences->avg(), 4),
            'min_confidence' => $confidences->min(),
            'max_confidence' => $confidences->max(),
            'first_seen_at' => $timestamps->first(),
            'last_seen_at' => $timestamps->last(),
            'data' => ['sample_timestamps' => $timestamps->all()],
        ];
    }
}
