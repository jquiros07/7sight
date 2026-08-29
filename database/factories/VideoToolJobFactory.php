<?php

namespace Database\Factories;

use App\Enums\VideoToolType;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoToolJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VideoToolJob>
 */
class VideoToolJobFactory extends Factory
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
            'type' => fake()->randomElement(VideoToolType::cases()),
            'status' => fake()->randomElement(['pending', 'processing', 'completed', 'failed']),
            'params' => [],
            'output_disk' => null,
            'output_path' => null,
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }
}
