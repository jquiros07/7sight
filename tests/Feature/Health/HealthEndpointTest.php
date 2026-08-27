<?php

namespace Tests\Feature\Health;

use App\Models\User;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_the_public_endpoint_returns_200_and_ok_without_dependency_detail(): void
    {
        $response = $this->get('/health');

        $response->assertStatus(200);
        $response->assertExactJson(['status' => 'ok']);
    }

    public function test_the_public_endpoint_returns_503_and_degraded_without_leaking_the_exception_message(): void
    {
        Redis::shouldReceive('connection')->with('default')->andThrow(new RuntimeException('Connection refused'));

        $response = $this->get('/health');

        $response->assertStatus(503);
        $response->assertExactJson(['status' => 'degraded']);
        $response->assertDontSee('Connection refused');
    }

    public function test_the_detailed_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/health/detailed');

        $response->assertStatus(401);
    }

    public function test_the_detailed_endpoint_returns_per_dependency_checks_when_authenticated(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/health/detailed');

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
        $response->assertJsonPath('checks.database.healthy', true);
        $response->assertJsonPath('checks.redis.healthy', true);
        $response->assertJsonPath('checks.queue.healthy', true);
        $response->assertJsonPath('checks.analysis_provider.healthy', true);
    }
}
