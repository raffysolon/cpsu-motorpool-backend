<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Login endpoint - strict limit (5 attempts per minute per IP)
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many login attempts. Please try again in 1 minute.',
                        'retry_after' => 60,
                    ], 429, $headers);
                });
        });

        // Password reset - prevent abuse (3 attempts per hour per IP)
        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perHour(3)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many password reset attempts. Please try again later.',
                        'retry_after' => 3600,
                    ], 429, $headers);
                });
        });

        // General API endpoints - reasonable limit (60 requests per minute per user/IP)
        RateLimiter::for('api', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();
            
            return Limit::perMinute(60)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many requests. Please slow down.',
                        'retry_after' => 60,
                    ], 429, $headers);
                });
        });

        // Heavy operations - stricter limit (10 requests per minute)
        RateLimiter::for('heavy', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();
            
            return Limit::perMinute(10)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many requests for this operation. Please wait.',
                        'retry_after' => 60,
                    ], 429, $headers);
                });
        });
    }
}
