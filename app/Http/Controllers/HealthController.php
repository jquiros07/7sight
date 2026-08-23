<?php

namespace App\Http\Controllers;

use App\Actions\Health\CheckSystemHealth;
use Throwable;

class HealthController extends Controller
{
    public function show(CheckSystemHealth $checkSystemHealth)
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
