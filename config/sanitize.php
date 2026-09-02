<?php

return [
    // Kill switch — 117+ API routes share this middleware.
    'enabled' => env('SANITIZE_INPUT_ENABLED', true),

    // Log-only mode before enabling writes in production.
    'dry_run' => env('SANITIZE_INPUT_DRY_RUN', false),

    // Credentials must never be altered by strip_tags.
    'except' => [
        'password',
        'password_confirmation',
        'old_password',
        'current_password',
        'token',
        'access_token',
        'refresh_token',
    ],
];
