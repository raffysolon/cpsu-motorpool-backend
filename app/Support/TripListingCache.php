<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TripListingCache
{
    public const VERSION_KEY = 'trip-listing-cache-version';

    public const TTL_SECONDS = 60;

    public static function version(): string
    {
        return (string) Cache::get(self::VERSION_KEY, 'initial');
    }

    public static function invalidate(): void
    {
        Cache::forever(self::VERSION_KEY, (string) Str::uuid());
    }
}
