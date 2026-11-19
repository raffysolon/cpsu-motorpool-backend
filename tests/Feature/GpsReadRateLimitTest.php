<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GpsReadRateLimitTest extends TestCase
{
    public function test_gps_read_keeps_authentication_role_and_general_throttle(): void
    {
        $route = collect(Route::getRoutes())->first(
            fn ($route) => $route->uri() === 'api/gps/vehicles/{vehicle_id}/latest'
        );
        $this->assertNotNull($route);
        foreach (['auth:sanctum', 'role:admin', 'throttle:api'] as $middleware) {
            $this->assertContains($middleware, $route->gatherMiddleware());
        }
        $this->getJson('/api/gps/vehicles/7/latest')->assertUnauthorized();
        $driver = new User;
        $driver->id = 123;
        $driver->role = 'driver';
        Sanctum::actingAs($driver);
        $this->getJson('/api/gps/vehicles/7/latest')->assertForbidden();
    }

    public function test_shared_limit_still_returns_429_with_browser_readable_retry_header(): void
    {
        // Exercise the real limiter and CORS middleware without any DB writes.
        Route::middleware(['api', 'auth:sanctum', 'throttle:api'])
            ->get('/api/rate-limit-test', fn () => response()->json(['ok' => true]));
        $user = new User;
        $user->id = 12345;
        Sanctum::actingAs($user);
        $headers = ['Origin' => 'https://cpsumotorpool.vercel.app'];
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/rate-limit-test', $headers)->assertOk();
        }
        $response = $this->getJson('/api/rate-limit-test', $headers);
        $response->assertStatus(429)->assertHeader('Retry-After');
        $this->assertStringContainsString(
            'retry-after', strtolower($response->headers->get('Access-Control-Expose-Headers', ''))
        );
        $this->travel(61)->seconds();
        $this->getJson('/api/rate-limit-test', $headers)->assertOk();
        $this->travelBack();
    }
}
