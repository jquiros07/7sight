<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Video>
 */
class VideoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $filename = Str::uuid().'.mp4';

        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'status' => fake()->randomElement(['uploaded', 'processing', 'ready', 'failed']),
            'disk' => 'local',
            'path' => 'videos/'.$filename,
            'original_filename' => $filename,
            'mime_type' => 'video/mp4',
            'size' => fake()->numberBetween(1_000_000, 500_000_000),
            'duration_seconds' => fake()->numberBetween(5, 3600),
            'thumbnail_path' => null,
        ];
    }
}
