<?php

namespace App\Http\Controllers;

use App\Actions\Health\CheckSystemHealth;
use Throwable;

class HealthController extends Controller
{
    /**
     * Public, unauthenticated liveness check. Deliberately minimal - no
     * per-dependency detail or exception messages, since this is reachable
     * by anyone on the internet.
     */
    public function show(CheckSystemHealth $checkSystemHealth)
    {
        try {
            $healthy = collect($checkSystemHealth())->every(fn (array $check) => $check['healthy']);

            return response()->json(['status' => $healthy ? 'ok' : 'degraded'], $healthy ? 200 : 503);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * Authenticated, per-dependency breakdown for monitoring/debugging.
     */
    public function detailed(CheckSystemHealth $checkSystemHealth)
    {
        try {
            $checks = $checkSystemHealth();

            $healthy = collect($checks)->every(fn (array $check) => $check['healthy']);

            return response()->json([
                'status' => $healthy ? 'ok' : 'degraded',
                'checks' => $checks,
            ], $healthy ? 200 : 503);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['status' => 'error', 'message' => 'Something went wrong. Please try again.'], 500);
        }
    }
}
