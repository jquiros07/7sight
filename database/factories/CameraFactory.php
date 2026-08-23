<?php

namespace Database\Factories;

use App\Models\Camera;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Camera>
 */
class CameraFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Camera',
            'location' => fake()->optional()->streetAddress(),
            'stream_url' => 'rtsp://'.fake()->userName().':'.fake()->password().'@'.fake()->ipv4().':554/stream1',
            'created_by' => User::factory(),
        ];
    }
}
