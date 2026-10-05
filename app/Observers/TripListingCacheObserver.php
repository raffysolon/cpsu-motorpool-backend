<?php

namespace App\Observers;

use App\Support\TripListingCache;
use Illuminate\Database\Eloquent\Model;

class TripListingCacheObserver
{
    public function saved(Model $model): void
    {
        TripListingCache::invalidate();
    }

    public function deleted(Model $model): void
    {
        TripListingCache::invalidate();
    }
}
