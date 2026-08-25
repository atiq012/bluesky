<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Profile Image Upload Base Path
    |--------------------------------------------------------------------------
    |
    | Local example: public_path('uploads/profile_image')
    | Live example: /home/dev2blue/public_html/uploads/profile_image
    |
    | On live the app dir (/home/dev2blue/apps/b2b) is NOT web-served, so
    | public_path() writes files that can never be reached over HTTP. This must
    | point at the document root instead.
    |
    */
    'base_path' => env('PROFILE_IMAGE_UPLOAD_BASE_PATH', public_path('uploads/profile_image')),

    /*
    |--------------------------------------------------------------------------
    | Public URL Prefix Stored In Database
    |--------------------------------------------------------------------------
    |
    | Values stored in DB become: /uploads/profile_image/{filename}.jpg
    |
    */
    'db_public_prefix' => env('PROFILE_IMAGE_UPLOAD_DB_PREFIX', '/uploads/profile_image'),

    // Read/delete fallbacks: files written into the app dir before base_path was
    // configured, plus the admin panel's own store (same DB, both write img_path).
    'fallback_base_paths' => array_filter(array_map('trim', explode(',', (string) env(
        'PROFILE_IMAGE_UPLOAD_FALLBACK_BASE_PATHS',
        '/home/dev2blue/apps/b2b/public/uploads/profile_image,/home/dev2blue/public_html/uploads/profile_image,/home/devblues/public_html/uploads/profile_image'
    )))),
];
