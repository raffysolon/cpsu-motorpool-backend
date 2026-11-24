<?php

namespace App\Services;

use Carbon\Carbon;

class TrackerAvailability
{
    public function status(?string $lastHeartbeatAt): string
    {
        if ($lastHeartbeatAt === null || $lastHeartbeatAt === '') {
            return 'unreachable';
        }

        $heartbeatAt = Carbon::parse($lastHeartbeatAt, 'UTC');
        $ageSeconds = max(
            0,
            now('UTC')->getTimestamp() - $heartbeatAt->getTimestamp()
        );

        if ($ageSeconds <= (int) config('tracking.online_threshold_seconds')) {
            return 'online';
        }

        if ($ageSeconds <= (int) config('tracking.unreachable_threshold_seconds')) {
            return 'unstable';
        }

        return 'unreachable';
    }
}
