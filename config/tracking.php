<?php

return [
    'heartbeat_max_age_seconds' => 90,
    'heartbeat_future_tolerance_seconds' => 30,
    'offline_after_seconds' => 300,
    // Legacy access requires explicit enablement and a configured device ID.
    'legacy_auth_enabled' => env('TRACKER_LEGACY_AUTH_ENABLED', false),
    'legacy_device_id' => env('TRACKER_LEGACY_DEVICE_ID'),
];
