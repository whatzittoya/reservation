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
    /** @var \App\CustomerSync $customerSync */
    $customerSync = $c['customerSync'];
    /** @var \App\CustomerSyncRepository $syncState */
    $syncState = $c['syncState'];
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

    // Landing page after login is the search box; the grid is a click away.
    $app->get('/', function (Request $req, Response $res) use ($redirect) {
        return $redirect($res, '/search');
    });

    /* ========================================================= Search */

    /**
     * Search page — reached from the brand in the header. Shows a big centred
     * box; with a query it lists matching customers and bookings on any date.
     */
    $app->get('/search', function (Request $req, Response $res) use ($view, $customers, $reservations, $today) {
        $params = $req->getQueryParams();
        $q = trim((string) ($params['q'] ?? ''));
        // Today unless explicitly widened, so the common front-desk lookup is
        // one keystroke away and old bookings don't bury it.
        $scope = ($params['scope'] ?? 'today') === 'all' ? 'all' : 'today';
        $onDate = $scope === 'today' ? $today() : null;

        return $view->render($res, 'search.php', [
            'title'     => $q === '' ? 'Search' : 'Search — ' . $q,
            'q'         => $q,
            'scope'     => $scope,
            'today'     => $today(),
            'customers' => $q === '' ? [] : $customers->search($q, 20, $onDate),
            'bookings'  => $q === '' ? [] : $reservations->search($q, 30, $onDate),
        ]);
    });

    /* =========================================================== Grid */

    $app->get('/grid', function (Request $req, Response $res) use ($view, $tables, $reservations, $employees, $grid, $today, $c) {
        $isSpa = $c['config']['type'] === 'spa';
        $params = $req->getQueryParams();
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $params['date'] ?? '') ? $params['date'] : $today();
        $section = isset($params['section']) && $params['section'] !== 'all' && $params['section'] !== ''
            ? (int) $params['section'] : null;

        // Rows: bookable resources grouped by section. In spa mode the
        // therapists follow as one more group, so a single board shows both
        // things a booking locks. 'kind' tells the template how to render.
        $groups = [];
        foreach ($tables->groupedBySection($section) as $g) {
            $groups[] = $g + ['kind' => 'table'];
        }
        if ($isSpa) {
            $groups[] = ['label' => 'Therapists', 'kind' => 'therapist', 'tables' => $employees->therapists()];
        }

        // Lookup: rowKey => [ slotIndex => reservation ]. A spa booking lands in
        // TWO rows — its room and its therapist — because it occupies both.
        // Keys are prefixed so room "1" can't collide with therapist id 1.
        $placed = [];
        foreach ($reservations->forDate($date) as $r) {
            $idx = $grid->slotIndexFor(substr((string) $r['reservationTime'], 11, 5));
            if ($idx === null) {
                continue;
            }
            // A booking can run for several columns; the template draws one cell
            // with this colspan and skips the columns underneath it.
            $span = $grid->spanFor(isset($r['duration_minutes']) ? (int) $r['duration_minutes'] : null);
            $cell = ['res' => $r, 'span' => $span];
            if (!empty($r['tableName'])) {
                $placed['t:' . $r['tableName']][$idx] = $cell;
            }
            if (!empty($r['servedBy_id'])) {
                $placed['e:' . (int) $r['servedBy_id']][$idx] = $cell;
            }
        }

        return $view->render($res, 'grid.php', [
            'title'      => 'Reservations — ' . $date,
            'date'       => $date,
            'prevDate'   => date('Y-m-d', strtotime($date . ' -1 day')),
            'nextDate'   => date('Y-m-d', strtotime($date . ' +1 day')),
            'slots'      => $grid->slots(),
            'groups'     => $groups,
            'sectionIds'    => $tables->sectionIds(),
            'sectionLabels' => $tables->sections(),
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
    $slotTimes   = array_map(static fn ($s) => $s['time'], $grid->slots());
    // The Duration dropdown is built from these: how long one column is, and
    // how many columns the day has in total.
    $slotMinutes = $grid->slotMinutes();
    $slotCount   = $grid->slotCount();

    $app->get('/reservations/new', function (Request $req, Response $res) use ($view, $customers, $tables, $employees, $slotTimes, $slotMinutes, $slotCount, $today) {
        $q = $req->getQueryParams();
        return $view->render($res, 'reservation_form.php', [
            'title'      => 'New Reservation',
            'mode'       => 'create',
            'data'       => [
                'tableName'    => $q['table'] ?? '',
                'therapist_id' => (int) ($q['therapist'] ?? 0),
                'booking_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $q['date'] ?? '') ? $q['date'] : $today(),
                'booking_time' => $q['time'] ?? ($slotTimes[0] ?? '09:00'),
                'cover'        => 2,
                'status'       => 0,
                'duration_slots' => 1,
            ],
            'errors'     => [],
            'customers'  => $customers->all(),
            'groups'     => $tables->groupedBySection(),
            'therapists' => $employees->therapists(),
            'slotTimes'  => $slotTimes,
            'slotMinutes' => $slotMinutes,
            'slotCount'   => $slotCount,
            'statuses'   => \App\ReservationRepository::statusLabels(),
        ]);
    });

    // Validate + resolve customer, shared by create/update.
    // $exceptId lets an edit skip the conflict check against its own row.
    $prepare = function (array $body, ?int $exceptId = null) use ($customers, $tables, $employees, $reservations, $customerSync, $grid, $c): array {
        $errors = [];
        $isSpa = $c['config']['type'] === 'spa';
        $date = trim((string) ($body['booking_date'] ?? ''));
        $time = trim((string) ($body['booking_time'] ?? ''));
        $tableName = trim((string) ($body['tableName'] ?? ''));
        $therapistId = (int) ($body['therapist_id'] ?? 0);
        $cover = (int) ($body['cover'] ?? 0);
        $status = (int) ($body['status'] ?? 0);
        $notes = trim((string) ($body['notes'] ?? ''));
        // How many consecutive grid columns this booking occupies.
        $durationSlots = max(1, (int) ($body['duration_slots'] ?? 1));

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
        $timeOk = !isset($errors['booking_date']) && !isset($errors['booking_time']);
        $resourceWord = $isSpa ? 'room' : 'table';

        // A booking may not run past closing time.
        $maxSlots = $timeOk ? $grid->slotsRemainingFrom($time) : 1;
        if ($timeOk && $maxSlots < 1) {
            $errors['booking_time'] = 'That time is outside business hours.';
        } elseif ($durationSlots > $maxSlots) {
            $errors['duration_slots'] = $maxSlots === 1
                ? 'A booking at that time can only run for one slot before closing.'
                : 'A booking at that time can run for at most ' . $maxSlots . ' slots before closing.';
            $durationSlots = $maxSlots;
        }
        $slotMinutes     = $grid->slotMinutes();
        $durationMinutes = $durationSlots * $slotMinutes;

        // Every booking takes a table/room…
        if ($tableName === '') {
            $errors['tableName'] = 'Select a ' . $resourceWord . '.';
        } elseif ($timeOk && $reservations->slotTaken($tableName, $reservationTime, $durationMinutes, $slotMinutes, $exceptId)) {
            $errors['tableName'] = 'This ' . $resourceWord . ' is already booked at that time — pick another time or '
                . $resourceWord . '.';
        }

        // …and in spa mode a therapist as well, locked for the same slot.
        if ($isSpa) {
            $validTherapist = false;
            foreach ($employees->therapists() as $t) {
                if ((int) $t['id'] === $therapistId) {
                    $validTherapist = true;
                    break;
                }
            }
            if (!$validTherapist) {
                $errors['therapist_id'] = 'Select a therapist.';
            } elseif ($timeOk && $reservations->therapistTaken($therapistId, $reservationTime, $durationMinutes, $slotMinutes, $exceptId)) {
                $errors['therapist_id'] = 'This therapist is already booked at that time — pick another time or therapist.';
            }
            $servedById = $validTherapist ? $therapistId : null;
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
                // A walk-in typed straight into the booking form is a genuine
                // new customer, so it syncs like one. Only a failure is worth
                // interrupting the booking confirmation for.
                $sync = $customerSync->push($customerId);
                if (!$sync['ok'] && !$sync['skipped']) {
                    \App\Flash::set('err', $newName . ': ' . $sync['message']);
                }
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
            'reservationTime'  => $reservationTime,
            'duration_minutes' => $durationMinutes,
            'cover'            => $cover,
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

    $app->post('/reservations', function (Request $req, Response $res) use ($view, $customers, $tables, $employees, $reservations, $auth, $slotTimes, $slotMinutes, $slotCount, $prepare, $redirect) {
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
                'slotMinutes' => $slotMinutes,
                'slotCount'   => $slotCount,
                'statuses'   => \App\ReservationRepository::statusLabels(),
            ]);
        }

        $data['reservedBy_id'] = $auth->id();
        $reservations->create($data);

        return $redirect($res, '/grid?date=' . substr($data['reservationTime'], 0, 10));
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
    $app->get('/reservations/{id:[0-9]+}/edit', function (Request $req, Response $res, array $args) use ($view, $customers, $tables, $employees, $reservations, $slotTimes, $slotMinutes, $slotCount, $grid) {
        $r = $reservations->find((int) $args['id']);
        if ($r === null) {
            $res->getBody()->write('Reservation not found');
            return $res->withStatus(404);
        }
        return $view->render($res, 'reservation_form.php', [
            'title'      => 'Edit Reservation #' . $r['id'],
            'mode'       => 'edit',
            'id'         => (int) $r['id'],
            'data'       => [
                'tableName'    => $r['tableName'],
                'therapist_id' => (int) ($r['servedBy_id'] ?? 0),
                'booking_date' => substr((string) $r['reservationTime'], 0, 10),
                'booking_time' => substr((string) $r['reservationTime'], 11, 5),
                'duration_slots' => $grid->spanFor(isset($r['duration_minutes']) ? (int) $r['duration_minutes'] : null),
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
            'slotMinutes' => $slotMinutes,
            'slotCount'   => $slotCount,
            'statuses'   => \App\ReservationRepository::statusLabels(),
        ]);
    });

    $app->post('/reservations/{id:[0-9]+}', function (Request $req, Response $res, array $args) use ($view, $customers, $tables, $employees, $reservations, $slotTimes, $slotMinutes, $slotCount, $prepare, $redirect) {
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
                'slotMinutes' => $slotMinutes, 'slotCount' => $slotCount,
            ]);
        }
        $reservations->update($id, $data);
        return $redirect($res, '/grid?date=' . substr($data['reservationTime'], 0, 10));
    });

    $app->post('/reservations/{id:[0-9]+}/cancel', function (Request $req, Response $res, array $args) use ($reservations, $redirect) {
        $body = (array) $req->getParsedBody();
        $r = $reservations->find((int) $args['id']);
        $reservations->cancel((int) $args['id'], trim((string) ($body['reason'] ?? 'Cancelled')));
        return $redirect($res, '/reservations/' . (int) $args['id']);
    });

    /* ====================================================== Customers */

    // Name is the only thing this app insists on; everything else is optional
    // here and optional in the cloud API too.
    $validateCustomer = static function (array $body): array {
        $name = trim((string) ($body['name'] ?? ''));

        return $name === '' ? ['name' => 'Name is required.'] : [];
    };

    $app->get('/customers', function (Request $req, Response $res) use ($view, $customers, $auth, $customerSync, $syncState) {
        $isOwner = $auth->isOwner();
        return $view->render($res, 'customers.php', [
            'title'      => 'Customers',
            'isOwner'    => $isOwner,
            'customers'  => $isOwner ? $customers->allWithBookingCounts() : $customers->all(),
            'syncMap'    => $syncState->allKeyedByCustomer(),
            'syncOn'     => $customerSync->isEnabled(),
            'syncOffWhy' => $customerSync->disabledReason(),
        ]);
    });

    $app->get('/customers/new', function (Request $req, Response $res) use ($view) {
        return $view->render($res, 'customer_form.php', [
            'title'    => 'New Customer',
            'data'     => [],
            'errors'   => [],
            'customer' => null,
        ]);
    });

    $app->post('/customers', function (Request $req, Response $res) use ($view, $customers, $customerSync, $redirect, $validateCustomer) {
        $body   = (array) $req->getParsedBody();
        $errors = $validateCustomer($body);
        if ($errors !== []) {
            return $view->render($res->withStatus(422), 'customer_form.php', [
                'title' => 'New Customer', 'data' => $body, 'errors' => $errors, 'customer' => null,
            ]);
        }
        $id = $customers->create([
            'name'  => trim((string) $body['name']),
            'phone' => trim((string) ($body['phone'] ?? '')),
            'email' => trim((string) ($body['email'] ?? '')) ?: null,
            'notes' => trim((string) ($body['notes'] ?? '')) ?: null,
        ]);

        // A brand new customer goes up straight away. The row is already saved,
        // so a cloud failure only ever becomes a message — never a lost record.
        \App\Flash::set('ok', 'Customer created.');
        $sync = $customerSync->push($id);
        if (!$sync['skipped']) {
            \App\Flash::set($sync['ok'] ? 'ok' : 'err', 'Customer created. ' . $sync['message']);
        }

        return $redirect($res, '/customers/' . $id);
    });

    $app->get('/customers/{id:[0-9]+}/edit', function (Request $req, Response $res, array $args) use ($view, $customers) {
        $cust = $customers->find((int) $args['id']);
        if ($cust === null) {
            $res->getBody()->write('Customer not found');
            return $res->withStatus(404);
        }
        return $view->render($res, 'customer_form.php', [
            'title'    => 'Edit ' . $cust['name'],
            'customer' => $cust,
            'data'     => [
                'name'  => $cust['name'],
                'phone' => $cust['phone1'],
                'email' => $cust['email'],
                'notes' => $cust['notes'],
            ],
            'errors'   => [],
        ]);
    });

    $app->post('/customers/{id:[0-9]+}', function (Request $req, Response $res, array $args) use ($view, $customers, $customerSync, $redirect, $validateCustomer) {
        $id   = (int) $args['id'];
        $cust = $customers->find($id);
        if ($cust === null) {
            $res->getBody()->write('Customer not found');
            return $res->withStatus(404);
        }
        $body   = (array) $req->getParsedBody();
        $errors = $validateCustomer($body);
        if ($errors !== []) {
            return $view->render($res->withStatus(422), 'customer_form.php', [
                'title' => 'Edit ' . $cust['name'], 'data' => $body, 'errors' => $errors, 'customer' => $cust,
            ]);
        }
        $customers->update($id, [
            'name'  => trim((string) $body['name']),
            'phone' => trim((string) ($body['phone'] ?? '')),
            'email' => trim((string) ($body['email'] ?? '')) ?: null,
            'notes' => trim((string) ($body['notes'] ?? '')) ?: null,
        ]);

        // Edits sync too. For a customer that has never been up there this is
        // its first push (a create) rather than an update — see CustomerSync.
        \App\Flash::set('ok', 'Customer updated.');
        $sync = $customerSync->push($id);
        if (!$sync['skipped']) {
            \App\Flash::set($sync['ok'] ? 'ok' : 'err', 'Customer updated. ' . $sync['message']);
        }

        return $redirect($res, '/customers/' . $id);
    });

    // Manual sync — the only way a customer that was already here reaches the
    // cloud, and the retry for anything that failed.
    $app->post('/customers/{id:[0-9]+}/sync', function (Request $req, Response $res, array $args) use ($customerSync, $redirect) {
        $sync = $customerSync->push((int) $args['id']);
        \App\Flash::set($sync['ok'] ? 'ok' : 'err', $sync['message']);

        return $redirect($res, '/customers/' . (int) $args['id']);
    });

    $app->get('/customers/{id:[0-9]+}', function (Request $req, Response $res, array $args) use ($view, $customers, $reservations, $customerSync, $syncState) {
        $cust = $customers->find((int) $args['id']);
        if ($cust === null) {
            $res->getBody()->write('Customer not found');
            return $res->withStatus(404);
        }
        return $view->render($res, 'customer_detail.php', [
            'title'      => $cust['name'],
            'customer'   => $cust,
            'history'    => $reservations->forCustomer((int) $args['id']),
            'sync'       => $syncState->find((int) $args['id']),
            'syncCode'   => $customerSync->codeFor((int) $args['id']),
            'syncOn'     => $customerSync->isEnabled(),
            'syncOffWhy' => $customerSync->disabledReason(),
        ]);
    });

    /* ============================================== Owner-only group */

    $app->group('', function (RouteCollectorProxy $g) use ($view, $customers, $tables, $reservations, $employees, $slotTimes, $prepare, $redirect, $auth, $c) {

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

        /* ------------------------------------------------------- Tables */

        // In spa mode these are rooms; the label follows $roomLabel in the views.
        $tableLabel = $c['config']['type'] === 'spa' ? 'Room' : 'Table';

        // Shared validation for add/edit. Name is what a booking stores, so it
        // has to be present and unique.
        $checkTable = static function (array $body, ?int $exceptId) use ($tables): array {
            $errors = [];
            $name = trim((string) ($body['name'] ?? ''));
            $capacity = (int) ($body['capacity'] ?? 0);

            if ($name === '') {
                $errors['name'] = 'Name is required.';
            } elseif (mb_strlen($name) > 255) {
                $errors['name'] = 'Name is too long.';
            } elseif ($tables->nameExists($name, $exceptId)) {
                $errors['name'] = 'Another table already uses that name.';
            }
            if ($capacity < 1) {
                $errors['capacity'] = 'Capacity must be at least 1.';
            }

            // The section dropdown carries a "new section" choice; picking it
            // swaps the id for a name typed alongside.
            if ((string) ($body['section_id'] ?? '') === 'new') {
                $newSection = trim((string) ($body['new_section'] ?? ''));
                if ($newSection === '') {
                    $errors['section_id'] = 'Name the new section.';
                } elseif (mb_strlen($newSection) > 255) {
                    $errors['section_id'] = 'Section name is too long.';
                } elseif ($tables->sectionNameExists($newSection)) {
                    $errors['section_id'] = 'A section called "' . $newSection . '" already exists — pick it from the list.';
                }
            } elseif (!array_key_exists((int) ($body['section_id'] ?? 0), $tables->sections())) {
                $errors['section_id'] = 'Choose a section.';
            }

            return $errors;
        };

        // Resolve the section field to an id, creating the section when asked.
        // Called only after $checkTable has passed, so the name is known good.
        $resolveSection = static function (array $body) use ($tables): int {
            if ((string) ($body['section_id'] ?? '') === 'new') {
                return $tables->createSection(trim((string) $body['new_section']));
            }

            return (int) $body['section_id'];
        };

        $g->get('/tables', function (Request $req, Response $res) use ($view, $tables, $tableLabel) {
            return $view->render($res, 'tables.php', [
                'title'    => $tableLabel . 's',
                'groups'   => $tables->groupedBySection(),
                'bookings' => $tables->bookingCounts(),
            ]);
        });

        $g->get('/tables/new', function (Request $req, Response $res) use ($view, $tables, $tableLabel) {
            return $view->render($res, 'table_form.php', [
                'title'    => 'New ' . $tableLabel,
                'table'    => null,
                'data'     => ['capacity' => 4],
                'errors'   => [],
                'sections' => $tables->sections(),
            ]);
        });

        $g->post('/tables', function (Request $req, Response $res) use ($view, $tables, $redirect, $checkTable, $resolveSection, $tableLabel) {
            $body   = (array) $req->getParsedBody();
            $errors = $checkTable($body, null);
            if ($errors !== []) {
                return $view->render($res->withStatus(422), 'table_form.php', [
                    'title'    => 'New ' . $tableLabel,
                    'table'    => null,
                    'data'     => $body,
                    'errors'   => $errors,
                    'sections' => $tables->sections(),
                ]);
            }
            $tables->create([
                'name'       => trim((string) $body['name']),
                'capacity'   => (int) $body['capacity'],
                'section_id' => $resolveSection($body),
            ]);
            \App\Flash::set('ok', $tableLabel . ' "' . trim((string) $body['name']) . '" added.');

            return $redirect($res, '/tables');
        });

        $g->get('/tables/{id:[0-9]+}/edit', function (Request $req, Response $res, array $args) use ($view, $tables, $tableLabel) {
            $t = $tables->find((int) $args['id']);
            if ($t === null) {
                $res->getBody()->write('Table not found');
                return $res->withStatus(404);
            }
            return $view->render($res, 'table_form.php', [
                'title'    => 'Edit ' . $t['name'],
                'table'    => $t,
                'data'     => $t,
                'errors'   => [],
                'sections' => $tables->sections(),
            ]);
        });

        $g->post('/tables/{id:[0-9]+}', function (Request $req, Response $res, array $args) use ($view, $tables, $redirect, $checkTable, $resolveSection, $tableLabel) {
            $id = (int) $args['id'];
            $t  = $tables->find($id);
            if ($t === null) {
                $res->getBody()->write('Table not found');
                return $res->withStatus(404);
            }
            $body   = (array) $req->getParsedBody();
            $errors = $checkTable($body, $id);
            if ($errors !== []) {
                return $view->render($res->withStatus(422), 'table_form.php', [
                    'title'    => 'Edit ' . $t['name'],
                    'table'    => $t,
                    'data'     => $body,
                    'errors'   => $errors,
                    'sections' => $tables->sections(),
                ]);
            }
            $newName = trim((string) $body['name']);
            $tables->update($id, [
                'name'       => $newName,
                'capacity'   => (int) $body['capacity'],
                'section_id' => $resolveSection($body),
            ]);
            // A rename carries the existing bookings across — say so, because
            // otherwise it looks like nothing happened to them.
            $moved = $newName !== (string) $t['name']
                ? ' Existing bookings now show "' . $newName . '".'
                : '';
            \App\Flash::set('ok', $tableLabel . ' saved.' . $moved);

            return $redirect($res, '/tables');
        });

        $g->post('/tables/{id:[0-9]+}/delete', function (Request $req, Response $res, array $args) use ($tables, $redirect, $tableLabel) {
            $id = (int) $args['id'];
            $t  = $tables->find($id);
            if ($t === null) {
                $res->getBody()->write('Table not found');
                return $res->withStatus(404);
            }
            $name   = (string) $t['name'];
            $counts = $tables->bookingCounts()[$name] ?? ['total' => 0, 'future' => 0];

            // Bookings store the name, not the id, so deleting a table with
            // upcoming bookings would strand them off the grid with no way back.
            if ($counts['future'] > 0) {
                \App\Flash::set('err', sprintf(
                    'Cannot delete %s "%s" — it still has %d upcoming booking%s. Move or cancel them first.',
                    strtolower($tableLabel),
                    $name,
                    $counts['future'],
                    $counts['future'] === 1 ? '' : 's'
                ));

                return $redirect($res, '/tables');
            }

            $tables->delete($id);
            $kept = $counts['total'] > 0
                ? sprintf(' %d past booking%s keep the name for history.', $counts['total'], $counts['total'] === 1 ? '' : 's')
                : '';
            \App\Flash::set('ok', $tableLabel . ' "' . $name . '" deleted.' . $kept);

            return $redirect($res, '/tables');
        });

        /* -------------------------------------------------------- Staff */

        // Shared validation for add/edit. $pinRequired is false on edit, where
        // a blank PIN means "keep the current one".
        $checkStaff = static function (array $body, ?int $exceptId, bool $isEdit) use ($employees): array {
            $errors = [];
            $name = trim((string) ($body['name'] ?? ''));
            $code = trim((string) ($body['code'] ?? ''));
            $pin  = trim((string) ($body['pin'] ?? ''));

            if ($name === '') {
                $errors['name'] = 'Name is required.';
            } elseif ($employees->loginNameTaken($name, $exceptId)) {
                $errors['name'] = 'Another staff member already uses that name or code.';
            }
            if ($code !== '' && $employees->loginNameTaken($code, $exceptId)) {
                $errors['code'] = 'Another staff member already uses that name or code.';
            }
            // Blank is allowed and means "cannot log in" — right for therapists,
            // who are bookable resources rather than users. On edit it means
            // "leave the existing PIN alone".
            if ($pin !== '') {
                if (!preg_match('/^\d{4,10}$/', $pin)) {
                    $errors['pin'] = 'PIN must be 4–10 digits. Short PINs are trivially guessable on a public site.';
                }
            } elseif (!$isEdit && ($body['wants_login'] ?? '') === '1') {
                $errors['pin'] = 'Set a PIN, or untick "can sign in".';
            }

            return $errors;
        };

        $g->get('/employees', function (Request $req, Response $res) use ($view, $employees) {
            return $view->render($res, 'employees.php', [
                'title'     => 'Staff',
                'employees' => $employees->all(),
            ]);
        });

        $g->get('/employees/new', function (Request $req, Response $res) use ($view) {
            return $view->render($res, 'employee_form.php', [
                'title'    => 'New Staff',
                'employee' => null,
                'data'     => ['active' => 1, 'resigned' => 0],
                'errors'   => [],
            ]);
        });

        $g->post('/employees', function (Request $req, Response $res) use ($view, $employees, $redirect, $checkStaff) {
            $body   = (array) $req->getParsedBody();
            $errors = $checkStaff($body, null, false);
            if ($errors !== []) {
                return $view->render($res->withStatus(422), 'employee_form.php', [
                    'title' => 'New Staff', 'employee' => null, 'data' => $body, 'errors' => $errors,
                ]);
            }
            $pin = trim((string) ($body['pin'] ?? ''));
            $employees->create([
                'name'     => trim((string) $body['name']),
                'code'     => trim((string) ($body['code'] ?? '')) ?: null,
                'jobTitle' => trim((string) ($body['jobTitle'] ?? '')) ?: null,
                'pin'      => $pin !== '' ? $pin : null,
                'phone'    => trim((string) ($body['phone'] ?? '')) ?: null,
                'email'    => trim((string) ($body['email'] ?? '')) ?: null,
                'active'   => !empty($body['active']),
                'resigned' => !empty($body['resigned']),
            ]);
            \App\Flash::set('ok', 'Staff member "' . trim((string) $body['name']) . '" added.');

            return $redirect($res, '/employees');
        });

        $g->get('/employees/{id:[0-9]+}/edit', function (Request $req, Response $res, array $args) use ($view, $employees) {
            $emp = $employees->find((int) $args['id']);
            if ($emp === null) {
                $res->getBody()->write('Staff member not found');
                return $res->withStatus(404);
            }
            return $view->render($res, 'employee_form.php', [
                'title'    => 'Edit ' . $emp['name'],
                'employee' => $emp,
                'data'     => [
                    'name'     => $emp['name'],
                    'code'     => $emp['code'],
                    'jobTitle' => $emp['jobTitle'],
                    'phone'    => $emp['phone1'],
                    'email'    => $emp['email'],
                    'active'   => (int) $emp['active_int'],
                    'resigned' => (int) $emp['resigned_int'],
                    'pin'      => '',
                ],
                'errors'   => [],
            ]);
        });

        $g->post('/employees/{id:[0-9]+}', function (Request $req, Response $res, array $args) use ($view, $employees, $redirect, $checkStaff) {
            $id  = (int) $args['id'];
            $emp = $employees->find($id);
            if ($emp === null) {
                $res->getBody()->write('Staff member not found');
                return $res->withStatus(404);
            }
            $body   = (array) $req->getParsedBody();
            $errors = $checkStaff($body, $id, true);
            if ($errors !== []) {
                return $view->render($res->withStatus(422), 'employee_form.php', [
                    'title' => 'Edit ' . $emp['name'], 'employee' => $emp, 'data' => $body, 'errors' => $errors,
                ]);
            }
            $pin = trim((string) ($body['pin'] ?? ''));
            $employees->update($id, [
                'name'     => trim((string) $body['name']),
                'code'     => trim((string) ($body['code'] ?? '')) ?: null,
                'jobTitle' => trim((string) ($body['jobTitle'] ?? '')) ?: null,
                // null = leave the stored PIN untouched.
                'pin'      => $pin !== '' ? $pin : null,
                'phone'    => trim((string) ($body['phone'] ?? '')) ?: null,
                'email'    => trim((string) ($body['email'] ?? '')) ?: null,
                'active'   => !empty($body['active']),
                'resigned' => !empty($body['resigned']),
            ]);
            \App\Flash::set('ok', 'Staff member saved.' . ($pin !== '' ? ' PIN changed.' : ''));

            return $redirect($res, '/employees');
        });

        $g->post('/employees/{id:[0-9]+}/delete', function (Request $req, Response $res, array $args) use ($employees, $auth, $redirect) {
            $id  = (int) $args['id'];
            $emp = $employees->find($id);
            if ($emp === null) {
                $res->getBody()->write('Staff member not found');
                return $res->withStatus(404);
            }
            // Locking yourself out mid-session is never what was meant.
            if ($id === $auth->id()) {
                \App\Flash::set('err', 'You cannot delete the account you are signed in with.');
                return $redirect($res, '/employees');
            }

            $counts = $employees->bookingCounts($id);
            if ($counts['servedFuture'] > 0) {
                \App\Flash::set('err', sprintf(
                    'Cannot delete %s — they are the therapist on %d upcoming booking%s. Reassign or cancel those first.',
                    $emp['name'],
                    $counts['servedFuture'],
                    $counts['servedFuture'] === 1 ? '' : 's'
                ));
                return $redirect($res, '/employees');
            }

            // The POS links staff to attendances, shifts, payments and more, so
            // the database blocks the delete for anyone with history.
            if ($employees->delete($id)) {
                \App\Flash::set('ok', 'Staff member "' . $emp['name'] . '" deleted.');
            } else {
                \App\Flash::set('err', sprintf(
                    '"%s" has POS history (bookings, shifts or payments) and cannot be deleted. Tick "Resigned" instead — that stops them signing in and keeps the records intact.',
                    $emp['name']
                ));
            }

            return $redirect($res, '/employees');
        });

    })->add(new RoleMiddleware($c['auth'], 'owner'));
};
