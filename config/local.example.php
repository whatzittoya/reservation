<?php

declare(strict_types=1);

return [

    // 'resto' = a booking takes a table
    // 'spa'   = a booking takes a room AND a therapist
    'type' => 'resto',

    'app_name' => 'Quinos Reservations',

    'grid' => [
        'open_time'    => '07:00',
        'close_time'   => '21:00',
        'slot_minutes' => 60,
    ],

    'owner_title_keywords' => ['manager', 'owner', 'admin'],

    'displayErrorDetails' => false,

    'db' => [
        'host' => 'localhost',
        'port' => '3306',
        'name' => 'your_database',
        'user' => 'your_user',
        'pass' => 'your-password',
    ],

    'cloud' => [
        // true  = new/edited customers are sent to Quinos Cloud
        // false = everything stays on this server, nothing is sent
        'enabled'     => false,

        'api_key'     => '',
        'token'       => '',
        'code_prefix' => 'RSV',
        'timeout'     => 8,
    ],
];
