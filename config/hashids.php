<?php

return [
    'min_length' => 8,
    'salt_base'  => hash('sha256', (string) env('APP_KEY', 'bluesky-hashids')),
    'alphabet'   => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890',
];
