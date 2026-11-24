<?php

namespace App\Services;

use Carbon\Carbon;

class TrackerAvailability
{
    public function status(
        ?string $senderTimestamp,
        ?string $receivedTimestamp = null
    ): string {
        if ($senderTimestamp === null || $senderTimestamp === '') {
            return 'unreachable';
        }

        $senderAt = Carbon::parse($senderTimestamp, 'UTC');
        $receivedAt = $receivedTimestamp === null || $receivedTimestamp === ''
            ? $senderAt
            : Carbon::parse($receivedTimestamp, 'UTC');
        $freshnessAt = $senderAt->lessThan($receivedAt)
            ? $senderAt
            : $receivedAt;
        $ageSeconds = max(
            0,
            now('UTC')->getTimestamp() - $freshnessAt->getTimestamp()
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
