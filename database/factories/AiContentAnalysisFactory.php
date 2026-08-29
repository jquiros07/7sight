<?php

namespace Database\Factories;

use App\Models\AiContentAnalysis;
use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiContentAnalysis>
 */
class AiContentAnalysisFactory extends Factory
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
            'user_id' => User::factory(),
            'status' => fake()->randomElement(['pending', 'processing', 'completed', 'failed']),
            'start_seconds' => 0,
            'end_seconds' => 60,
            'result' => null,
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }
}
