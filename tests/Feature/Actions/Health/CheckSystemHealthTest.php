<?php

namespace Tests\Feature\Actions\Health;

use App\Actions\Health\CheckSystemHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;

class CheckSystemHealthTest extends TestCase
{
    public function test_it_reports_healthy_when_every_dependency_is_reachable(): void
    {
        $result = (new CheckSystemHealth)();

        $this->assertTrue($result['database']['healthy']);
        $this->assertTrue($result['redis']['healthy']);
        $this->assertTrue($result['queue']['healthy']);
        $this->assertTrue($result['analysis_provider']['healthy']);
    }

    public function test_it_reports_the_database_as_unhealthy_when_the_connection_fails(): void
    {
        DB::shouldReceive('connection')->andThrow(new RuntimeException('Connection refused'));

        $result = (new CheckSystemHealth)();

        $this->assertFalse($result['database']['healthy']);
        $this->assertSame('Connection refused', $result['database']['message']);
    }

    public function test_it_reports_redis_as_unhealthy_when_the_connection_fails(): void
    {
        Redis::shouldReceive('connection')->with('default')->andThrow(new RuntimeException('Connection refused'));

        $result = (new CheckSystemHealth)();

        $this->assertFalse($result['redis']['healthy']);
        $this->assertSame('Connection refused', $result['redis']['message']);
    }

    public function test_it_skips_the_queue_check_when_the_queue_driver_is_not_redis(): void
    {
        config(['queue.default' => 'sync']);

        $result = (new CheckSystemHealth)();

        $this->assertTrue($result['queue']['healthy']);
    }

    public function test_it_checks_redis_for_a_redis_backed_queue_connection(): void
    {
        config(['queue.default' => 'redis']);
        Redis::shouldReceive('connection')->with('default')->andThrow(new RuntimeException('Connection refused'));

        $result = (new CheckSystemHealth)();

        $this->assertFalse($result['queue']['healthy']);
    }

    public function test_it_reports_the_analysis_provider_as_unhealthy_when_aws_credentials_are_missing(): void
    {
        config([
            'filesystems.disks.s3.key' => null,
            'filesystems.disks.s3.secret' => null,
        ]);

        $result = (new CheckSystemHealth)();

        $this->assertFalse($result['analysis_provider']['healthy']);
        $this->assertStringContainsString('key', $result['analysis_provider']['message']);
        $this->assertStringContainsString('secret', $result['analysis_provider']['message']);
    }
}
