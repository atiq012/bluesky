<?php

return [
    // AIT — NBR s.52JJ. 0.3% of (supplier fare − deductible tax codes).
    'ait_rate'                 => (float) env('FARE_RULES_AIT_RATE', 0.003),
    'ait_deductible_tax_codes' => ['BD', 'UT', 'E5'],

    // business case → priority. UI never lets the user type a number.
    'priorities' => [
        'default' => 5,
        'targeted' => 40,
        'campaign' => 60,
        'deal'    => 70,
        'emergency' => 90,
    ],

    // precedence weights — higher = more specific
    'specificity_weights' => [
        'agencies' => 128,
        'tiers' => 64,
        'routes' => 32,
        'scope' => 16,
        'onward'   => 8,
        'booking_classes' => 4,
        'cabins' => 2,
        'airlines' => 1,
        'suppliers' => 1,
    ],

    'cabins' => ['Economy', 'Premium Economy', 'Business', 'First'],

    // Snapshot TTL is a safety net only — every write warms the cache immediately,
    // so a long TTL just keeps orphaned keys in memory.
    'cache_ttl_seconds'     => (int) env('FARE_RULES_CACHE_TTL', 3600),
    'airport_map_ttl'       => (int) env('FARE_RULES_AIRPORT_MAP_TTL', 86400),
    'build_lock_seconds'    => (int) env('FARE_RULES_BUILD_LOCK', 10),
    'build_lock_wait'       => (int) env('FARE_RULES_BUILD_LOCK_WAIT', 3),

    // Log a warning when rule application on one search exceeds this.
    'slow_apply_ms'         => (int) env('FARE_RULES_SLOW_MS', 150),

    'broadcast_enabled'     => (bool) env('FARE_RULES_BROADCAST_ENABLED', true),
    'broadcast_channel'     => env('FARE_RULES_BROADCAST_CHANNEL', 'fare-rules'),
    'poll_interval_seconds' => max((int) env('FARE_RULES_POLL_INTERVAL', 10), 5),
];
