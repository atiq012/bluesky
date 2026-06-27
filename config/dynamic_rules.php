<?php

return [
    // Safety TTL only — primary invalidation is version bump on rule save/delete.
    'cache_ttl_seconds' => (int) env('DYNAMIC_RULES_CACHE_TTL', 86400),

    // Ably toast on search page when rules change (requires ABLY_KEY + VITE_ABLY_KEY).
    'broadcast_enabled' => (bool) env('DYNAMIC_RULES_BROADCAST_ENABLED', true),
    'broadcast_channel' => env('DYNAMIC_RULES_BROADCAST_CHANNEL', 'dynamic-rules'),
    // Poll cache stamp on search page when VITE_ABLY_KEY is missing (seconds).
    'poll_interval_seconds' => max((int) env('DYNAMIC_RULES_POLL_INTERVAL', 10), 5),

    // Default AIT when rule does not override (0.3% = QR example).
    'ait_rate' => (float) env('DYNAMIC_RULES_AIT_RATE', 0.003),

    // Tax codes subtracted from gross before AIT (when per-code tax data exists).
    'ait_deductible_tax_codes' => ['BD', 'UT', 'E5'],

    // Search uses this API label when form does not send one.
    'default_api' => env('DYNAMIC_RULES_DEFAULT_API', 'Travelport'),

    // Used for domestic vs international when airports.country is unavailable.
    'domestic_airport_codes' => ['DAC', 'CGP', 'ZYL', 'CXB', 'JSR', 'RJH', 'BZL', 'SPD', 'IRD'],
];
