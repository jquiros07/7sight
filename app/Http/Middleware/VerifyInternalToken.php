<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyInternalToken
{
    /**
     * Guard internal service-to-service routes (e.g. the analysis-worker
     * calling back into Laravel) with a shared secret instead of Sanctum.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('services.internal.token');

        abort_if(
            $token === '' || ! hash_equals($token, (string) $request->header('X-Internal-Token')),
            401
        );

        return $next($request);
    }
}
