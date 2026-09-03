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

    // What this install books.
    //   'resto' — a booking takes a table.
    //   'spa'   — a booking takes a room AND a therapist, and both are locked
    //             for that time slot (nobody else gets either one).
    'type' => in_array(getenv('APP_TYPE') ?: 'resto', ['resto', 'spa'], true)
        ? (string) (getenv('APP_TYPE') ?: 'resto')
        : 'resto',

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

    // Quinos Cloud sync (POS backend). New and edited customers are pushed to
    // https://quinosbackend.com/api/v1 — see README "Cloud sync".
    //
    // BOTH headers are required by the API: API-KEY is the application key
    // ("must match server configuration" — ask whoever runs the backend for it)
    // and TOKEN is your company token. With either one missing the API answers
    // 401 and sync stays off, so the app runs exactly as before until both are
    // filled in. Put the real values in config/local.php (gitignored).
    'cloud' => [
        // Master switch. false = everything stays on this server; no customer
        // is ever sent to the cloud, whatever the credentials say.
        'enabled' => filter_var(getenv('CLOUD_SYNC') ?: '1', FILTER_VALIDATE_BOOLEAN),

        'base_url' => rtrim(getenv('CLOUD_BASE_URL') ?: 'https://quinosbackend.com/api/v1', '/'),
        'api_key'  => getenv('CLOUD_API_KEY') !== false ? (string) getenv('CLOUD_API_KEY') : '',
        'token'    => getenv('CLOUD_TOKEN') !== false ? (string) getenv('CLOUD_TOKEN') : '',

        // The cloud `code` is required, unique per company and capped at 16
        // chars. It is derived from the local customer id — prefix + zero-padded
        // id (RSV000042) — so the same local row always maps to the same cloud
        // record and a retry can never create a second one. Change the prefix
        // per outlet if several installs share one company.
        'code_prefix' => getenv('CLOUD_CODE_PREFIX') ?: 'RSV',

        // Seconds to wait for the backend. Kept short: a booking must not stall
        // behind a slow/offline connection — the local save always wins and the
        // customer is simply left "Not synced" for the manual button to retry.
        'timeout' => (int) (getenv('CLOUD_TIMEOUT') ?: 8),
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
