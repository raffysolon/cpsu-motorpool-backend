<?php

namespace App\Services;

use App\Models\Device;

class TrackerHeartbeatMonitor
{
    public function check(): int
    {
        // Conditional update cannot overwrite a heartbeat committed before it.
        return Device::where('is_online', true)
            ->where(function ($query) {
                $query->whereNull('last_seen')->orWhere(
                    'last_seen', '<=', now()->subSeconds(config('tracking.offline_after_seconds'))
                );
            })->update(['is_online' => false]);
    }
}
