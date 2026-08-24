<?php

namespace Database\Factories;

use App\Enums\CameraRecordingStatus;
use App\Models\Camera;
use App\Models\CameraRecording;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CameraRecording>
 */
class CameraRecordingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $durationMinutes = fake()->randomElement([3, 5, 15, 30, 60, 180, 300, 480]);
        $startedAt = fake()->dateTimeBetween('-2 days', 'now');

        return [
            'camera_id' => Camera::factory(),
            'requested_by' => User::factory(),
            'status' => CameraRecordingStatus::Recording,
            'duration_minutes' => $durationMinutes,
            'started_at' => $startedAt,
            'ends_at' => (clone $startedAt)->modify("+{$durationMinutes} minutes"),
            'completed_at' => null,
            'cancel_requested' => false,
            'disk' => 'local',
            'path' => null,
            'size' => null,
            'duration_seconds' => null,
            'process_pid' => null,
            'error_message' => null,
        ];
    }
}
