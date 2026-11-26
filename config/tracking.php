<?php

return [
    'heartbeat_interval_seconds' => (int) env('TRACKER_HEARTBEAT_INTERVAL_SECONDS', 30),
    'heartbeat_max_age_seconds' => (int) env('TRACKER_HEARTBEAT_MAX_AGE_SECONDS', 60),
    'heartbeat_future_tolerance_seconds' => (int) env('TRACKER_HEARTBEAT_FUTURE_TOLERANCE_SECONDS', 30),
    'online_threshold_seconds' => (int) env('TRACKER_ONLINE_THRESHOLD_SECONDS', 60),
    'unreachable_threshold_seconds' => (int) env('TRACKER_UNREACHABLE_THRESHOLD_SECONDS', 120),
    'offline_after_seconds' => 300,
    'departure_warning_before_minutes' => (int) env('TRACKER_DEPARTURE_WARNING_BEFORE_MINUTES', 10),
    'movement' => [
        'minimum_speed_kph' => (float) env('TRACKER_MOVEMENT_MIN_SPEED_KPH', 5),
        'minimum_displacement_meters' => (float) env('TRACKER_MOVEMENT_MIN_DISPLACEMENT_METERS', 50),
        'sustained_seconds' => (int) env('TRACKER_MOVEMENT_SUSTAINED_SECONDS', 30),
        'minimum_satellites' => (int) env('TRACKER_MOVEMENT_MIN_SATELLITES', 4),
        'maximum_sample_gap_seconds' => (int) env('TRACKER_MOVEMENT_MAX_SAMPLE_GAP_SECONDS', 60),
        'maximum_gps_age_seconds' => (int) env('TRACKER_MOVEMENT_MAX_GPS_AGE_SECONDS', 60),
        'stationary_radius_meters' => (float) env('TRACKER_STATIONARY_RADIUS_METERS', 20),
        'campus_latitude' => env('TRACKER_CAMPUS_LATITUDE') === null
            ? null
            : (float) env('TRACKER_CAMPUS_LATITUDE'),
        'campus_longitude' => env('TRACKER_CAMPUS_LONGITUDE') === null
            ? null
            : (float) env('TRACKER_CAMPUS_LONGITUDE'),
        'campus_radius_meters' => (float) env('TRACKER_CAMPUS_RADIUS_METERS', 100),
    ],
    // Legacy access requires explicit enablement and a configured device ID.
    'legacy_auth_enabled' => env('TRACKER_LEGACY_AUTH_ENABLED', false),
    'legacy_device_id' => env('TRACKER_LEGACY_DEVICE_ID'),
];
