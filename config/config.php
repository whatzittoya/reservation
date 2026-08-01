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

$config = [
    // Shown in the header, the login page, the search page, and page titles.
    // Everything before the first space is plain, the rest takes the accent
    // colour — "Quinos Reservations" renders as Quinos + Reservations.
    'app_name' => getenv('APP_NAME') ?: 'Quinos Reservations',

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

/*
 * Local overrides — config/local.php is gitignored, so this is where a machine's
 * real DB password lives without ever reaching the repo. Return only the keys
 * you want to change, e.g.:
 *
 *     <?php return ['db' => ['pass' => 'your-password']];
 */
$local = __DIR__ . '/local.php';
if (is_file($local)) {
    $config = array_replace_recursive($config, (array) require $local);
}

return $config;
