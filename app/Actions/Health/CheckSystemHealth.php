<?php

namespace App\Actions\Health;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class CheckSystemHealth
{
    /**
     * Check each infrastructure dependency the app relies on to function.
     *
     * @return array<string, array{healthy: bool, message: string}>
     */
    public function __invoke(): array
    {
        return [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'queue' => $this->checkQueue(),
            'analysis_provider' => $this->checkAnalysisProvider(),
        ];
    }

    /**
     * @return array{healthy: bool, message: string}
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return $this->healthy();
        } catch (Throwable $e) {
            return $this->unhealthy($e, 'database');
        }
    }

    /**
     * @return array{healthy: bool, message: string}
     */
    private function checkRedis(): array
    {
        try {
            Redis::connection('default')->ping();

            return $this->healthy();
        } catch (Throwable $e) {
            return $this->unhealthy($e, 'redis');
        }
    }

    /**
     * Verify Redis is reachable on whichever connection the configured queue
     * driver uses. If the queue driver isn't Redis-backed, there's nothing
     * meaningful to check here.
     *
     * @return array{healthy: bool, message: string}
     */
    private function checkQueue(): array
    {
        $connection = config('queue.default');
        $driver = config("queue.connections.{$connection}.driver");

        if ($driver !== 'redis') {
            return $this->healthy("Queue driver [{$driver}] does not use Redis; nothing to check.");
        }

        try {
            Redis::connection(config("queue.connections.{$connection}.connection", 'default'))->ping();

            return $this->healthy();
        } catch (Throwable $e) {
            return $this->unhealthy($e, 'queue');
        }
    }

    /**
     * The video analysis provider (AWS Rekognition) is only ever called from
     * the separate Python analysis-worker, so there's no live connection to
     * test from here - this just confirms the AWS credentials it needs are
     * configured, using the same config the "s3" filesystem disk reads from.
     *
     * @return array{healthy: bool, message: string}
     */
    private function checkAnalysisProvider(): array
    {
        $missing = collect([
            'key' => config('filesystems.disks.s3.key'),
            'secret' => config('filesystems.disks.s3.secret'),
            'region' => config('filesystems.disks.s3.region'),
        ])->filter(fn (?string $value) => blank($value))->keys();

        if ($missing->isNotEmpty()) {
            return $this->unhealthy(
                message: 'Missing AWS credentials: '.$missing->implode(', ')
            );
        }

        return $this->healthy();
    }

    /**
     * @return array{healthy: bool, message: string}
     */
    private function healthy(string $message = 'OK'): array
    {
        return ['healthy' => true, 'message' => $message];
    }

    /**
     * @return array{healthy: bool, message: string}
     */
    private function unhealthy(?Throwable $e = null, ?string $check = null, ?string $message = null): array
    {
        if ($e !== null) {
            Log::error("Health check failed: {$check}", ['exception' => $e]);
        }

        return ['healthy' => false, 'message' => $message ?? $e?->getMessage() ?? 'Unhealthy'];
    }
}
