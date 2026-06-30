<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'ably' => [
        'key' => env('ABLY_KEY'),
    ],

    'travelport_v2' => [
        'auth_url' => env('TRAVELPORT_V2_AUTH_URL', 'https://auth.pp.travelport.net/oauth/token'),
        'search_url' => env('TRAVELPORT_V2_SEARCH_URL'),
        'base_url' => env('TRAVELPORT_V2_BASE_URL', 'https://api.pp.travelport.net'),
        'version' => env('TRAVELPORT_V2_VERSION', '11'),
        'access_group' => env('TRAVELPORT_V2_ACCESS_GROUP'),
        'tax_breakdown' => env('TRAVELPORT_V2_TAX_BREAKDOWN', true),
        'grant_type' => env('TRAVELPORT_V2_GRANT_TYPE', 'password'),
        'username' => env('TRAVELPORT_V2_USERNAME'),
        'password' => env('TRAVELPORT_V2_PASSWORD'),
        'client_id' => env('TRAVELPORT_V2_CLIENT_ID'),
        'client_secret' => env('TRAVELPORT_V2_CLIENT_SECRET'),
        'token_cache_key' => env('TRAVELPORT_V2_TOKEN_CACHE_KEY', 'travelport_v2_access_token'),
        'token_refresh_buffer_seconds' => (int) env('TRAVELPORT_V2_TOKEN_REFRESH_BUFFER_SECONDS', 60),
        'search_response_cache_enabled' => env('TRAVELPORT_V2_SEARCH_RESPONSE_CACHE_ENABLED', false),
        'search_response_cache_ttl_seconds' => (int) env('TRAVELPORT_V2_SEARCH_RESPONSE_CACHE_TTL_SECONDS', 30),
        'search_rate_limit_per_minute' => (int) env('TRAVELPORT_V2_SEARCH_RATE_LIMIT_PER_MINUTE', 180),
        'dev_fixture'                  => env('TRAVELPORT_V2_DEV_FIXTURE'),
        'price_url'                    => env('TRAVELPORT_V2_PRICE_URL'),
        'price_fixture'                => env('TRAVELPORT_V2_PRICE_FIXTURE'),
        'fare_rules_outbound_fixture'  => env('TRAVELPORT_V2_FARE_RULES_OUTBOUND_FIXTURE'),
        'fare_rules_inbound_fixture'   => env('TRAVELPORT_V2_FARE_RULES_INBOUND_FIXTURE'),
        'fop_mode'                     => env('TRAVELPORT_V2_FOP_MODE', 'cash'),
        'iata_number'                  => env('TRAVELPORT_V2_IATA_NUMBER', ''),
    ],

];
