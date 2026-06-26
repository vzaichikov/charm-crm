<?php

return [
    'users' => [
        'platform' => [
            'name' => env('LADNA_DEMO_PLATFORM_NAME', 'Platform Admin'),
            'email' => env('LADNA_DEMO_PLATFORM_EMAIL'),
            'password' => env('LADNA_DEMO_PLATFORM_PASSWORD'),
        ],
        'owner' => [
            'name' => env('LADNA_DEMO_OWNER_NAME', 'Studio Owner'),
            'email' => env('LADNA_DEMO_OWNER_EMAIL'),
            'password' => env('LADNA_DEMO_OWNER_PASSWORD'),
        ],
    ],
];
