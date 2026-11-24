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
        if (!$this->isAuthenticated($request)) {
            return response()->json([
                'message' => 'Unauthorized gateway.',
            ], 401);
        }

        return $next($request);
    }

    public function isAuthenticated(Request $request): bool
    {
        $expectedKey = config('services.gateway.api_key');
        $providedKeys = $request->headers->all('X-Gateway-Key');
        $providedKey = count($providedKeys) === 1
            ? $providedKeys[0]
            : null;

        return is_string($expectedKey)
            && $expectedKey !== ''
            && is_string($providedKey)
            && hash_equals($expectedKey, $providedKey);
    }
}
