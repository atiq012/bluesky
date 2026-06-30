<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Agent Upload Base Path
    |--------------------------------------------------------------------------
    |
    | Local example: public_path('uploads/agents')
    | Live example: /home/devblues/public_html/uploads/agents
    |
    */
    'base_path' => env('AGENT_UPLOAD_BASE_PATH', public_path('uploads/agents')),

    /*
    |--------------------------------------------------------------------------
    | Public URL Prefix Stored In Database
    |--------------------------------------------------------------------------
    |
    | Values stored in DB become: /uploads/agents/{folder}/{filename}.jpg
    |
    */
    'db_public_prefix' => env('AGENT_UPLOAD_DB_PREFIX', '/uploads/agents'),
    'fallback_base_paths' => array_filter(array_map('trim', explode(',', (string) env('AGENT_UPLOAD_FALLBACK_BASE_PATHS', '/home/gb053/Projects/BlueSky/public/uploads/agents,/home/devblues/public_html/uploads/agents')))),
];
