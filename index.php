<?php

declare(strict_types=1);

use App\Auth;
use App\AuthMiddleware;
use App\CustomerRepository;
use App\Database;
use App\EmployeeRepository;
use App\ReservationRepository;
use App\TableRepository;
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;

require __DIR__ . '/vendor/autoload.php';

session_start();

$config = require __DIR__ . '/config/config.php';

/* -------------------------------------------------------------------------
 * Base path auto-detection — makes the app work at the web root (localhost/)
 * or in a subfolder (localhost/reservation) with no code change, so it drops
 * straight into XAMPP's htdocs/reservation.
 * ---------------------------------------------------------------------- */
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath   = str_replace('\\', '/', dirname($scriptName));
if ($basePath === '/' || $basePath === '.') {
    $basePath = '';
}

/* ------------------------------------------------------------------ Slim */
$app = AppFactory::create();
$app->setBasePath($basePath);
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware((bool) $config['displayErrorDetails'], true, true);

/* ---------------------------------------------------------- Dependencies */
$pdo = Database::connect($config['db']);

$employees    = new EmployeeRepository($pdo);
$customers    = new CustomerRepository($pdo);
$tables       = new TableRepository($pdo);
$reservations = new ReservationRepository($pdo);
$auth         = new Auth($employees, $config['owner_title_keywords']);

/**
 * Brand mark for the header, login and search pages: the first word plain,
 * everything after it in the accent colour. Returns ready-to-echo HTML.
 */
$brand = static function (?string $name = null) use ($config): string {
    $esc   = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES);
    $parts = explode(' ', (string) ($name ?? $config['app_name']), 2);

    return $esc($parts[0]) . (isset($parts[1]) ? ' <span>' . $esc($parts[1]) . '</span>' : '');
};

$renderer = new PhpRenderer(__DIR__ . '/templates', [
    'basePath' => $basePath,
    'auth'     => $auth,
    'appName'  => (string) $config['app_name'],
    'brand'    => $brand,
    // 'resto' | 'spa' — see config/config.php. Templates branch on this to show
    // the therapist field/rows, and to call a bookable resource Table vs Room.
    'appType'   => (string) $config['type'],
    'roomLabel' => $config['type'] === 'spa' ? 'Room' : 'Table',
]);
$renderer->setLayout('layout.php');

$container = [
    'config'       => $config,
    'basePath'     => $basePath,
    'auth'         => $auth,
    'employees'    => $employees,
    'customers'    => $customers,
    'tables'       => $tables,
    'reservations' => $reservations,
    'view'         => $renderer,
];

/* -------------------------------------------- Global auth guard (except login) */
$app->add(new AuthMiddleware($auth, $basePath, [$basePath . '/login']));

/* --------------------------------------------------------------- Routes  */
(require __DIR__ . '/src/routes.php')($app, $container);

$app->run();
