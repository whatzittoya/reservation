<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * Access to the existing POS `tbl_reservation` table.
 *
 * A reservation is point-in-time: `reservationTime` (datetime) places it in a
 * single grid cell; `cover` is the guest count; `tableName` is the table's
 * name string (matches tbl_tables.name); `status` is an int.
 */
final class ReservationRepository
{
    public const STATUS_BOOKED    = 0;
    public const STATUS_ARRIVED   = 1;
    public const STATUS_CANCELLED = 2;

    /** @return array<int,string> */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_BOOKED    => 'Booked',
            self::STATUS_ARRIVED   => 'Arrived',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    public static function statusLabel(int $status): string
    {
        return self::statusLabels()[$status] ?? 'Booked';
    }

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * All reservations on a given date (Y-m-d), newest join with customer name.
     *
     * @return array<int,array<string,mixed>>
     */
    public function forDate(string $date): array
    {
        $sql = 'SELECT r.id, r.reservationTime, r.cover, r.name, r.phone, r.notes,
                       r.status, r.tableName, r.customer_id, r.voidReason, r.servedBy_id,
                       r.duration_minutes,
                       c.name AS customer_name,
                       e.name AS reserved_by_name,
                       st.name AS served_by_name
                FROM tbl_reservation r
                LEFT JOIN tbl_customers c ON c.id = r.customer_id
                LEFT JOIN tbl_employees e ON e.id = r.reservedBy_id
                LEFT JOIN tbl_employees st ON st.id = r.servedBy_id
                WHERE DATE(r.reservationTime) = :d
                ORDER BY r.reservationTime';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['d' => $date]);

        return $stmt->fetchAll();
    }

    /**
     * Per-day reservation counts for a month.
     *
     * @return array<string,int> map of 'Y-m-d' => count
     */
    public function countsByMonth(int $year, int $month): array
    {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end   = date('Y-m-01', strtotime($start . ' +1 month'));
        $sql = 'SELECT DATE(reservationTime) AS d, COUNT(*) AS c
                FROM tbl_reservation
                WHERE reservationTime >= :start
                  AND reservationTime < :end
                GROUP BY DATE(reservationTime)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['start' => $start, 'end' => $end]);

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(string) $row['d']] = (int) $row['c'];
        }

        return $out;
    }

    /**
     * Totals for the grid footer on a given date.
     *
     * @return array{reservations:int,guests:int,open:int}
     */
    public function dailyTotals(string $date): array
    {
        $sql = 'SELECT COUNT(*) AS reservations,
                       COALESCE(SUM(cover), 0) AS guests,
                       COALESCE(SUM(status = 0), 0) AS open
                FROM tbl_reservation
                WHERE DATE(reservationTime) = :d';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['d' => $date]);
        $row = $stmt->fetch();

        return [
            'reservations' => (int) $row['reservations'],
            'guests'       => (int) $row['guests'],
            'open'         => (int) $row['open'],
        ];
    }

    /**
     * Does a booking of $durationMinutes starting at $reservationTime overlap an
     * existing booking of the same table?
     *
     * Comparing start times for equality is not enough once a booking can span
     * several slots: a 14:00 two-hour booking has to block a new 15:00 one even
     * though the two start times differ. Two intervals overlap when each starts
     * before the other ends. $defaultMinutes is the grid's slot size, used for
     * the older rows whose duration_minutes is NULL (they occupy one slot).
     *
     * Cancelled reservations don't count; $exceptId ignores the row being edited.
     */
    public function slotTaken(
        string $tableName,
        string $reservationTime,
        int $durationMinutes,
        int $defaultMinutes,
        ?int $exceptId = null
    ): bool {
        return $this->overlaps('tableName = :res', ['res' => $tableName], $reservationTime, $durationMinutes, $defaultMinutes, $exceptId);
    }

    /**
     * Same interval-overlap test for a therapist (spa mode).
     */
    public function therapistTaken(
        int $servedById,
        string $reservationTime,
        int $durationMinutes,
        int $defaultMinutes,
        ?int $exceptId = null
    ): bool {
        return $this->overlaps('servedBy_id = :res', ['res' => $servedById], $reservationTime, $durationMinutes, $defaultMinutes, $exceptId);
    }

    /**
     * Shared overlap query for both bookable resources.
     *
     * @param array<string,mixed> $resourceParams
     */
    private function overlaps(
        string $resourceWhere,
        array $resourceParams,
        string $reservationTime,
        int $durationMinutes,
        int $defaultMinutes,
        ?int $exceptId
    ): bool {
        $sql = 'SELECT COUNT(*) FROM tbl_reservation
                WHERE ' . $resourceWhere . '
                  AND status <> :cancelled
                  AND reservationTime < DATE_ADD(:start, INTERVAL :dur MINUTE)
                  AND DATE_ADD(reservationTime, INTERVAL COALESCE(duration_minutes, :def) MINUTE) > :start2';
        $params = $resourceParams + [
            'cancelled' => self::STATUS_CANCELLED,
            'start'     => $reservationTime,
            'start2'    => $reservationTime,
            'dur'       => max(1, $durationMinutes),
            'def'       => max(1, $defaultMinutes),
        ];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $sql = 'SELECT r.*, c.name AS customer_name,
                       e.name AS reserved_by_name,
                       st.name AS served_by_name
                FROM tbl_reservation r
                LEFT JOIN tbl_customers c ON c.id = r.customer_id
                LEFT JOIN tbl_employees e ON e.id = r.reservedBy_id
                LEFT JOIN tbl_employees st ON st.id = r.servedBy_id
                WHERE r.id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Create a reservation.
     *
     * @param array<string,mixed> $d reservationTime, cover, name, phone, notes,
     *                               tableName, status, customer_id, reservedBy_id
     */
    public function create(array $d): int
    {
        $sql = 'INSERT INTO tbl_reservation
                    (created, reservationTime, duration_minutes, cover, name, phone, notes,
                     tableName, status, customer_id, reservedBy_id, servedBy_id)
                VALUES
                    (NOW(), :rt, :duration, :cover, :name, :phone, :notes,
                     :tableName, :status, :customer_id, :reservedBy_id, :servedBy_id)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'rt'            => $d['reservationTime'],
            'duration'      => $d['duration_minutes'] ?? null,
            'cover'         => $d['cover'],
            'name'          => $d['name'],
            'phone'         => $d['phone'] ?? null,
            'notes'         => $d['notes'] ?? null,
            'tableName'     => $d['tableName'] ?? null,
            'status'        => $d['status'] ?? self::STATUS_BOOKED,
            'customer_id'   => $d['customer_id'] ?? null,
            'reservedBy_id' => $d['reservedBy_id'] ?? null,
            'servedBy_id'   => $d['servedBy_id'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $d */
    public function update(int $id, array $d): bool
    {
        $sql = 'UPDATE tbl_reservation SET
                    reservationTime  = :rt,
                    duration_minutes = :duration,
                    cover            = :cover,
                    name            = :name,
                    phone           = :phone,
                    notes           = :notes,
                    tableName       = :tableName,
                    status          = :status,
                    customer_id     = :customer_id,
                    servedBy_id     = :servedBy_id
                WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            'id'          => $id,
            'rt'          => $d['reservationTime'],
            'duration'    => $d['duration_minutes'] ?? null,
            'cover'       => $d['cover'],
            'name'        => $d['name'],
            'phone'       => $d['phone'] ?? null,
            'notes'       => $d['notes'] ?? null,
            'tableName'   => $d['tableName'] ?? null,
            'status'      => $d['status'] ?? self::STATUS_BOOKED,
            'customer_id' => $d['customer_id'] ?? null,
            'servedBy_id' => $d['servedBy_id'] ?? null,
        ]);
    }

    /**
     * Mark a reservation as arrived: sets status and stamps the POS `arrived`
     * time. Available to any staff member (front-desk check-in).
     */
    public function markArrived(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tbl_reservation SET status = :s, arrived = NOW() WHERE id = :id'
        );

        return $stmt->execute(['s' => self::STATUS_ARRIVED, 'id' => $id]);
    }

    /** Revert an arrival back to Booked (clears the arrived stamp). */
    public function markBooked(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tbl_reservation SET status = :s, arrived = NULL WHERE id = :id'
        );

        return $stmt->execute(['s' => self::STATUS_BOOKED, 'id' => $id]);
    }

    public function cancel(int $id, string $reason): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tbl_reservation SET status = :s, voidReason = :r WHERE id = :id'
        );

        return $stmt->execute(['s' => self::STATUS_CANCELLED, 'r' => $reason, 'id' => $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM tbl_reservation WHERE id = :id');

        return $stmt->execute(['id' => $id]);
    }

    /**
     * Free-text search for the search page: matches the linked customer's name,
     * the name/phone stored on the booking itself, and the table name.
     * Nearest-to-now first, so today's bookings surface before last year's.
     *
     * $onDate (Y-m-d) limits the search to a single day; null searches every date.
     *
     * @return array<int,array<string,mixed>>
     */
    public function search(string $q, int $limit = 12, ?string $onDate = null): array
    {
        // Escape LIKE wildcards so a literal % or _ in the query isn't a wildcard.
        $like = '%' . addcslashes($q, '%_\\') . '%';
        // Named params are not reused — PDO runs with emulation off (see Database).
        $sql = 'SELECT r.id, r.reservationTime, r.cover, r.name, r.phone, r.status,
                       r.tableName, r.customer_id, r.servedBy_id,
                       c.name AS customer_name,
                       st.name AS served_by_name
                FROM tbl_reservation r
                LEFT JOIN tbl_customers c ON c.id = r.customer_id
                LEFT JOIN tbl_employees st ON st.id = r.servedBy_id
                WHERE (c.name LIKE :q1 OR r.name LIKE :q2 OR r.phone LIKE :q3
                       OR r.tableName LIKE :q4)';
        $params = ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        if ($onDate !== null) {
            $sql .= ' AND DATE(r.reservationTime) = :d';
            $params['d'] = $onDate;
        }
        $sql .= ' ORDER BY ABS(TIMESTAMPDIFF(MINUTE, NOW(), r.reservationTime)) ASC
                  LIMIT ' . max(1, $limit);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** Reservations for one customer (customer detail / history). */
    public function forCustomer(int $customerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, reservationTime, cover, tableName, status
             FROM tbl_reservation WHERE customer_id = :cid
             ORDER BY reservationTime DESC'
        );
        $stmt->execute(['cid' => $customerId]);

        return $stmt->fetchAll();
    }
}
