<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GatewayAuthentication
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $expectedKey = config('services.gateway.api_key');
        $providedKey = $request->header('X-Gateway-Key');

        if (
            !is_string($expectedKey) ||
            $expectedKey === '' ||
            !is_string($providedKey) ||
            !hash_equals($expectedKey, $providedKey)
        ) {
            return response()->json([
                'message' => 'Unauthorized gateway.',
            ], 401);
        }

        return $next($request);
    }
}
