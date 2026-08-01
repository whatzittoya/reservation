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

$renderer = new PhpRenderer(__DIR__ . '/templates', [
    'basePath' => $basePath,
    'auth'     => $auth,
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
