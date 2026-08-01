<?php

declare(strict_types=1);

use App\ReservationRepository;
use App\RoleMiddleware;
use App\TableRepository;
use App\TimeGrid;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

/**
 * @param array<string,mixed> $c dependency container (see index.php)
 */
return function (App $app, array $c): void {

    /** @var \App\Auth $auth */
    $auth = $c['auth'];
    /** @var \App\CustomerRepository $customers */
    $customers = $c['customers'];
    /** @var TableRepository $tables */
    $tables = $c['tables'];
    /** @var ReservationRepository $reservations */
    $reservations = $c['reservations'];
    /** @var \App\EmployeeRepository $employees */
    $employees = $c['employees'];
    /** @var \Slim\Views\PhpRenderer $view */
    $view = $c['view'];
    $basePath = $c['basePath'];

    $grid = new TimeGrid(
        $c['config']['grid']['open_time'],
        $c['config']['grid']['close_time'],
        (int) $c['config']['grid']['slot_minutes']
    );

    $redirect = static function (Response $res, string $to) use ($basePath): Response {
        return $res->withHeader('Location', $basePath . $to)->withStatus(303);
    };
    $today = static fn (): string => date('Y-m-d');

    /* =========================================================== Auth */

    $app->get('/login', function (Request $req, Response $res) use ($view, $auth, $basePath) {
        if ($auth->check()) {
            return $res->withHeader('Location', $basePath . '/')->withStatus(302);
        }
        return $view->render($res, 'login.php', ['title' => 'Sign in', 'error' => null]);
    });

    $app->post('/login', function (Request $req, Response $res) use ($view, $auth, $basePath) {
        $body = (array) $req->getParsedBody();
        $ok = $auth->attempt((string) ($body['username'] ?? ''), (string) ($body['pin'] ?? ''));
        if (!$ok) {
            return $view->render($res->withStatus(401), 'login.php', [
                'title' => 'Sign in',
                'error' => 'Invalid name/code or PIN.',
                'username' => (string) ($body['username'] ?? ''),
            ]);
        }
        return $res->withHeader('Location', $basePath . '/')->withStatus(303);
    });

    $app->get('/logout', function (Request $req, Response $res) use ($auth, $basePath) {
        $auth->logout();
        return $res->withHeader('Location', $basePath . '/login')->withStatus(302);
    });

    /* =========================================================== Home */

    $app->get('/', function (Request $req, Response $res) use ($redirect, $today) {
        return $redirect($res, '/grid?date=' . $today());
    });

    /* ========================================================= Search */

    /**
     * Search page — reached from the brand in the header. Shows a big centred
     * box; with a query it lists matching customers and bookings on any date.
     */
    $app->get('/search', function (Request $req, Response $res) use ($view, $customers, $reservations) {
        $q = trim((string) ($req->getQueryParams()['q'] ?? ''));

        return $view->render($res, 'search.php', [
            'title'     => $q === '' ? 'Search' : 'Search — ' . $q,
            'q'         => $q,
            'customers' => $q === '' ? [] : $customers->search($q, 20),
            'bookings'  => $q === '' ? [] : $reservations->search($q, 30),
        ]);
    });

    /* =========================================================== Grid */

    $app->get('/grid', function (Request $req, Response $res) use ($view, $tables, $reservations, $employees, $grid, $today) {
        $params = $req->getQueryParams();
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $params['date'] ?? '') ? $params['date'] : $today();
        $viewMode = ($params['view'] ?? 'room') === 'therapist' ? 'therapist' : 'room';
        $section = isset($params['section']) && $params['section'] !== 'all' && $params['section'] !== ''
            ? (int) $params['section'] : null;

        if ($viewMode === 'therapist') {
            // Rows = therapists; placement keyed by servedBy_id.
            $groups = [['label' => 'Therapists', 'tables' => $employees->therapists()]];
            $keyOf = static fn (array $r): ?string => $r['servedBy_id'] !== null ? (string) (int) $r['servedBy_id'] : null;
        } else {
            // Rows = tables grouped by section; placement keyed by tableName.
            $groups = $tables->groupedBySection($section);
            $keyOf = static fn (array $r): ?string => (string) $r['tableName'];
        }

        // Build lookup: rowKey => [ slotIndex => reservation ]
        $placed = [];
        foreach ($reservations->forDate($date) as $r) {
            $key = $keyOf($r);
            if ($key === null || $key === '') {
                continue;
            }
            $idx = $grid->slotIndexFor(substr((string) $r['reservationTime'], 11, 5));
            if ($idx !== null) {
                $placed[$key][$idx] = $r;
            }
        }

        return $view->render($res, 'grid.php', [
            'title'      => 'Reservations — ' . $date,
            'date'       => $date,
            'viewMode'   => $viewMode,
            'prevDate'   => date('Y-m-d', strtotime($date . ' -1 day')),
            'nextDate'   => date('Y-m-d', strtotime($date . ' +1 day')),
            'slots'      => $grid->slots(),
            'groups'     => $groups,
            'sectionIds' => $tables->sectionIds(),
            'section'    => $section,
            'placed'     => $placed,
            'totals'     => $reservations->dailyTotals($date),
        ]);
    });

    /* ======================================================= Calendar */

    $app->get('/calendar', function (Request $req, Response $res) use ($view, $reservations) {
        $params = $req->getQueryParams();
        $month = preg_match('/^\d{4}-\d{2}$/', $params['month'] ?? '') ? $params['month'] : date('Y-m');
        [$y, $m] = array_map('intval', explode('-', $month));

        return $view->render($res, 'calendar.php', [
            'title'     => 'Calendar — ' . $month,
            'year'      => $y,
            'month'     => $m,
            'monthStr'  => $month,
            'prevMonth' => date('Y-m', strtotime($month . '-01 -1 month')),
            'nextMonth' => date('Y-m', strtotime($month . '-01 +1 month')),
            'counts'    => $reservations->countsByMonth($y, $m),
        ]);
    });

    /* =================================================== Reservations */

    // Shared: build a slot-time list for the form dropdown.
    $slotTimes = array_map(static fn ($s) => $s['time'], $grid->slots());

    $app->get('/reservations/new', function (Request $req, Response $res) use ($view, $customers, $tables, $employees, $slotTimes, $today) {
        $q = $req->getQueryParams();
        $resType = ($q['type'] ?? 'table') === 'therapist' ? 'therapist' : 'table';
        return $view->render($res, 'reservation_form.php', [
            'title'      => 'New Reservation',
            'mode'       => 'create',
            'data'       => [
                'res_type'     => $resType,
                'tableName'    => $q['table'] ?? '',
                'therapist_id' => (int) ($q['therapist'] ?? 0),
                'booking_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $q['date'] ?? '') ? $q['date'] : $today(),
                'booking_time' => $q['time'] ?? ($slotTimes[0] ?? '09:00'),
                'cover'        => 2,
                'status'       => 0,
            ],
            'errors'     => [],
            'customers'  => $customers->all(),
            'groups'     => $tables->groupedBySection(),
            'therapists' => $employees->therapists(),
            'slotTimes'  => $slotTimes,
            'statuses'   => \App\ReservationRepository::statusLabels(),
        ]);
    });

    // Validate + resolve customer, shared by create/update.
    // $exceptId lets an edit skip the conflict check against its own row.
    $prepare = function (array $body, ?int $exceptId = null) use ($customers, $tables, $employees, $reservations): array {
        $errors = [];
        $resType = ($body['res_type'] ?? 'table') === 'therapist' ? 'therapist' : 'table';
        $date = trim((string) ($body['booking_date'] ?? ''));
        $time = trim((string) ($body['booking_time'] ?? ''));
        $tableName = trim((string) ($body['tableName'] ?? ''));
        $therapistId = (int) ($body['therapist_id'] ?? 0);
        $cover = (int) ($body['cover'] ?? 0);
        $status = (int) ($body['status'] ?? 0);
        $notes = trim((string) ($body['notes'] ?? ''));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $errors['booking_date'] = 'Valid date required.';
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            $errors['booking_time'] = 'Valid time required.';
        }
        if ($cover < 1) {
            $errors['cover'] = 'At least 1 guest.';
        }

        $reservationTime = $date . ' ' . $time . ':00';
        $servedById = null;

        if ($resType === 'therapist') {
            // Therapy booking: pick a therapist (servedBy_id); no table.
            $tableName = null;
            $validTherapist = false;
            foreach ($employees->therapists() as $t) {
                if ((int) $t['id'] === $therapistId) {
                    $validTherapist = true;
                    break;
                }
            }
            if (!$validTherapist) {
                $errors['therapist_id'] = 'Select a therapist.';
            } elseif (!isset($errors['booking_date']) && !isset($errors['booking_time'])
                && $reservations->therapistTaken($therapistId, $reservationTime, $exceptId)) {
                $errors['therapist_id'] = 'This therapist is already booked at that time — pick another time or therapist.';
            }
            $servedById = $validTherapist ? $therapistId : null;
        } else {
            // Table/room booking.
            if ($tableName === '') {
                $errors['tableName'] = 'Select a table.';
            }
            $timeFieldsOk = !isset($errors['tableName']) && !isset($errors['booking_date']) && !isset($errors['booking_time']);
            if ($timeFieldsOk && $reservations->slotTaken($tableName, $reservationTime, $exceptId)) {
                $errors['tableName'] = 'This table is already booked at that time — pick another time or table.';
            }
        }

        // Resolve customer: existing id OR inline new customer.
        $customerId = null;
        $custName = '';
        $custPhone = '';
        $existingId = (int) ($body['customer_id'] ?? 0);
        $newName = trim((string) ($body['new_name'] ?? ''));

        if ($existingId > 0) {
            $cust = $customers->find($existingId);
            if ($cust === null) {
                $errors['customer'] = 'Selected customer not found.';
            } else {
                $customerId = $existingId;
                $custName = (string) $cust['name'];
                $custPhone = (string) ($cust['phone1'] ?? '');
            }
        } elseif ($newName !== '') {
            // Only actually create the customer once everything else validates,
            // so a rejected booking doesn't leave an orphan customer behind.
            if ($errors === []) {
                $customerId = $customers->create([
                    'name'  => $newName,
                    'phone' => trim((string) ($body['new_phone'] ?? '')),
                    'email' => trim((string) ($body['new_email'] ?? '')) ?: null,
                ]);
            }
            $custName = $newName;
            $custPhone = trim((string) ($body['new_phone'] ?? ''));
        } else {
            $errors['customer'] = 'Choose an existing customer or add a new one.';
        }

        if (!array_key_exists($status, \App\ReservationRepository::statusLabels())) {
            $status = 0;
        }

        $data = [
            'reservationTime' => $reservationTime,
            'cover'           => $cover,
            'name'            => $custName,
            'phone'           => $custPhone,
            'notes'           => $notes !== '' ? $notes : null,
            'tableName'       => $tableName,
            'status'          => $status,
            'customer_id'     => $customerId,
            'servedBy_id'     => $servedById,
        ];

        return [$data, $errors];
    };

    $app->post('/reservations', function (Request $req, Response $res) use ($view, $customers, $tables, $employees, $reservations, $auth, $slotTimes, $prepare, $redirect) {
        $body = (array) $req->getParsedBody();
        [$data, $errors] = $prepare($body);

        if ($errors !== []) {
            return $view->render($res->withStatus(422), 'reservation_form.php', [
                'title'      => 'New Reservation',
                'mode'       => 'create',
                'data'       => $body + $data,
                'errors'     => $errors,
                'customers'  => $customers->all(),
                'groups'     => $tables->groupedBySection(),
                'therapists' => $employees->therapists(),
                'slotTimes'  => $slotTimes,
                'statuses'   => \App\ReservationRepository::statusLabels(),
            ]);
        }

        $data['reservedBy_id'] = $auth->id();
        $reservations->create($data);

        $view_q = $data['servedBy_id'] ? '&view=therapist' : '';
        return $redirect($res, '/grid?date=' . substr($data['reservationTime'], 0, 10) . $view_q);
    });

    $app->get('/reservations/{id:[0-9]+}', function (Request $req, Response $res, array $args) use ($view, $reservations, $auth) {
        $r = $reservations->find((int) $args['id']);
        if ($r === null) {
            $res->getBody()->write('Reservation not found');
            return $res->withStatus(404);
        }
        return $view->render($res, 'reservation_detail.php', [
            'title'       => 'Reservation #' . $r['id'],
            'reservation' => $r,
        ]);
    });

    // Check-in / arrival — available to ANY signed-in staff (front desk).
    $app->post('/reservations/{id:[0-9]+}/arrive', function (Request $req, Response $res, array $args) use ($reservations, $redirect) {
        $r = $reservations->find((int) $args['id']);
        if ($r !== null) {
            $reservations->markArrived((int) $args['id']);
        }
        $date = $r ? substr((string) $r['reservationTime'], 0, 10) : date('Y-m-d');
        return $redirect($res, '/reservations/' . (int) $args['id']);
    });

    $app->post('/reservations/{id:[0-9]+}/unarrive', function (Request $req, Response $res, array $args) use ($reservations, $redirect) {
        $reservations->markBooked((int) $args['id']);
        return $redirect($res, '/reservations/' . (int) $args['id']);
    });

    // Edit + cancel — available to ANY signed-in staff. (Delete stays owner-only.)
    $app->get('/reservations/{id:[0-9]+}/edit', function (Request $req, Response $res, array $args) use ($view, $customers, $tables, $employees, $reservations, $slotTimes) {
        $r = $reservations->find((int) $args['id']);
        if ($r === null) {
            $res->getBody()->write('Reservation not found');
            return $res->withStatus(404);
        }
        $isTherapy = !empty($r['servedBy_id']);
        return $view->render($res, 'reservation_form.php', [
            'title'      => 'Edit Reservation #' . $r['id'],
            'mode'       => 'edit',
            'id'         => (int) $r['id'],
            'data'       => [
                'res_type'     => $isTherapy ? 'therapist' : 'table',
                'tableName'    => $r['tableName'],
                'therapist_id' => (int) ($r['servedBy_id'] ?? 0),
                'booking_date' => substr((string) $r['reservationTime'], 0, 10),
                'booking_time' => substr((string) $r['reservationTime'], 11, 5),
                'cover'        => $r['cover'],
                'status'       => (int) $r['status'],
                'notes'        => $r['notes'],
                'customer_id'  => $r['customer_id'],
                'name'         => $r['name'],
            ],
            'errors'     => [],
            'customers'  => $customers->all(),
            'groups'     => $tables->groupedBySection(),
            'therapists' => $employees->therapists(),
            'slotTimes'  => $slotTimes,
            'statuses'   => \App\ReservationRepository::statusLabels(),
        ]);
    });

    $app->post('/reservations/{id:[0-9]+}', function (Request $req, Response $res, array $args) use ($view, $customers, $tables, $employees, $reservations, $slotTimes, $prepare, $redirect) {
        $id = (int) $args['id'];
        if ($reservations->find($id) === null) {
            $res->getBody()->write('Reservation not found');
            return $res->withStatus(404);
        }
        $body = (array) $req->getParsedBody();
        [$data, $errors] = $prepare($body, $id);
        if ($errors !== []) {
            return $view->render($res->withStatus(422), 'reservation_form.php', [
                'title' => 'Edit Reservation #' . $id, 'mode' => 'edit', 'id' => $id,
                'data' => $body + $data, 'errors' => $errors,
                'customers' => $customers->all(), 'groups' => $tables->groupedBySection(),
                'therapists' => $employees->therapists(),
                'slotTimes' => $slotTimes, 'statuses' => \App\ReservationRepository::statusLabels(),
            ]);
        }
        $reservations->update($id, $data);
        $view_q = $data['servedBy_id'] ? '&view=therapist' : '';
        return $redirect($res, '/grid?date=' . substr($data['reservationTime'], 0, 10) . $view_q);
    });

    $app->post('/reservations/{id:[0-9]+}/cancel', function (Request $req, Response $res, array $args) use ($reservations, $redirect) {
        $body = (array) $req->getParsedBody();
        $r = $reservations->find((int) $args['id']);
        $reservations->cancel((int) $args['id'], trim((string) ($body['reason'] ?? 'Cancelled')));
        return $redirect($res, '/reservations/' . (int) $args['id']);
    });

    $app->get('/customers', function (Request $req, Response $res) use ($view, $customers, $auth) {
        $isOwner = $auth->isOwner();
        return $view->render($res, 'customers.php', [
            'title'     => 'Customers',
            'isOwner'   => $isOwner,
            'customers' => $isOwner ? $customers->allWithBookingCounts() : $customers->all(),
        ]);
    });

    $app->get('/customers/new', function (Request $req, Response $res) use ($view) {
        return $view->render($res, 'customer_form.php', [
            'title'  => 'New Customer',
            'data'   => [],
            'errors' => [],
        ]);
    });

    $app->post('/customers', function (Request $req, Response $res) use ($view, $customers, $redirect) {
        $body = (array) $req->getParsedBody();
        $name = trim((string) ($body['name'] ?? ''));
        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Name is required.';
        }
        if ($errors !== []) {
            return $view->render($res->withStatus(422), 'customer_form.php', [
                'title' => 'New Customer', 'data' => $body, 'errors' => $errors,
            ]);
        }
        $id = $customers->create([
            'name'  => $name,
            'phone' => trim((string) ($body['phone'] ?? '')),
            'email' => trim((string) ($body['email'] ?? '')) ?: null,
            'notes' => trim((string) ($body['notes'] ?? '')) ?: null,
        ]);
        return $redirect($res, '/customers/' . $id);
    });

    $app->get('/customers/{id:[0-9]+}', function (Request $req, Response $res, array $args) use ($view, $customers, $reservations) {
        $cust = $customers->find((int) $args['id']);
        if ($cust === null) {
            $res->getBody()->write('Customer not found');
            return $res->withStatus(404);
        }
        return $view->render($res, 'customer_detail.php', [
            'title'    => $cust['name'],
            'customer' => $cust,
            'history'  => $reservations->forCustomer((int) $args['id']),
        ]);
    });

    /* ============================================== Owner-only group */

    $app->group('', function (RouteCollectorProxy $g) use ($view, $customers, $tables, $reservations, $employees, $slotTimes, $prepare, $redirect) {

        // Delete stays owner-only (permanent removal).
        $g->post('/reservations/{id:[0-9]+}/delete', function (Request $req, Response $res, array $args) use ($reservations, $redirect) {
            $r = $reservations->find((int) $args['id']);
            $date = $r ? substr((string) $r['reservationTime'], 0, 10) : date('Y-m-d');
            $reservations->delete((int) $args['id']);
            return $redirect($res, '/grid?date=' . $date);
        });

        $g->get('/reports', function (Request $req, Response $res) use ($view, $reservations) {
            $date = $req->getQueryParams()['date'] ?? date('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $date = date('Y-m-d');
            }
            return $view->render($res, 'reports.php', [
                'title'   => 'Reports',
                'date'    => $date,
                'totals'  => $reservations->dailyTotals($date),
                'list'    => $reservations->forDate($date),
            ]);
        });

        $g->get('/tables', function (Request $req, Response $res) use ($view, $tables) {
            return $view->render($res, 'tables.php', [
                'title'  => 'Tables',
                'groups' => $tables->groupedBySection(),
            ]);
        });

        $g->get('/employees', function (Request $req, Response $res) use ($view, $employees) {
            return $view->render($res, 'employees.php', [
                'title'     => 'Employees',
                'employees' => $employees->all(),
            ]);
        });

    })->add(new RoleMiddleware($c['auth'], 'owner'));
};
