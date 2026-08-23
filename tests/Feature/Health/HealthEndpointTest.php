<?php

namespace Tests\Feature\Health;

use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_it_returns_200_and_ok_when_every_dependency_is_healthy(): void
    {
        $response = $this->get('/health');

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
        $response->assertJsonPath('checks.database.healthy', true);
        $response->assertJsonPath('checks.redis.healthy', true);
        $response->assertJsonPath('checks.queue.healthy', true);
        $response->assertJsonPath('checks.analysis_provider.healthy', true);
    }

    public function test_it_returns_503_and_degraded_when_a_dependency_is_unhealthy(): void
    {
        Redis::shouldReceive('connection')->with('default')->andThrow(new RuntimeException('Connection refused'));

        $response = $this->get('/health');

        $response->assertStatus(503);
        $response->assertJson(['status' => 'degraded']);
        $response->assertJsonPath('checks.redis.healthy', false);
    }
}
