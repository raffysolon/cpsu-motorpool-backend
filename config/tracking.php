<?php

return [
    'heartbeat_interval_seconds' => (int) env('TRACKER_HEARTBEAT_INTERVAL_SECONDS', 30),
    'heartbeat_max_age_seconds' => (int) env('TRACKER_HEARTBEAT_MAX_AGE_SECONDS', 60),
    'heartbeat_future_tolerance_seconds' => (int) env('TRACKER_HEARTBEAT_FUTURE_TOLERANCE_SECONDS', 30),
    'online_threshold_seconds' => (int) env('TRACKER_ONLINE_THRESHOLD_SECONDS', 60),
    'unreachable_threshold_seconds' => (int) env('TRACKER_UNREACHABLE_THRESHOLD_SECONDS', 120),
    'offline_after_seconds' => 300,
    // Legacy access requires explicit enablement and a configured device ID.
    'legacy_auth_enabled' => env('TRACKER_LEGACY_AUTH_ENABLED', false),
    'legacy_device_id' => env('TRACKER_LEGACY_DEVICE_ID'),
];
