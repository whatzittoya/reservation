<?php
/**
 * Application configuration.
 *
 * Connects to the EXISTING Quinos POS database (db_reservation) and its real
 * tables: tbl_reservation, tbl_customers, tbl_tables, tbl_employees.
 * No schema changes are required to run this app.
 *
 * Values default to the XAMPP/Windows target environment but can be
 * overridden with environment variables.
 */

declare(strict_types=1);

return [
    'db' => [
        'host'    => getenv('DB_HOST') ?: '127.0.0.1',
        'port'    => getenv('DB_PORT') ?: '3306',
        'name'    => getenv('DB_NAME') ?: 'db_reservation',
        'user'    => getenv('DB_USER') ?: 'root',
        // No password is committed. Set DB_PASS in the environment (e.g.
        // `SetEnv DB_PASS …` in Apache's vhost/.htaccess, or the system env),
        // or put your local password here and keep that edit out of commits.
        'pass'    => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
        // The POS tables are latin1 but hold ASCII data; utf8mb4 client is fine.
        'charset' => 'utf8mb4',
    ],

    // Time grid (columns): business hours + slot granularity in minutes.
    // 60 = one column per hour (no empty half-hour columns). Set to 30 for
    // half-hour columns.
    'grid' => [
        'open_time'     => '07:00',
        'close_time'    => '21:00',
        'slot_minutes'  => 60,
    ],

    // A logged-in employee is treated as "owner" when their jobTitle contains
    // any of these keywords (case-insensitive); otherwise "employee".
    'owner_title_keywords' => ['manager', 'owner', 'admin'],

    // Show detailed errors. Set to false in production.
    'displayErrorDetails' => true,
];
