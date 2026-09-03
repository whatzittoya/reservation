<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

/**
 * The existing POS `tbl_employees` table: login accounts, roles, and the
 * therapists that spa mode books.
 */
final class EmployeeRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Find a working employee by (name OR code) + pin. Returns null on no match.
     *
     * Resigned and deactivated staff are refused: the Staff page offers both
     * flags precisely so an account can be switched off without deleting the
     * person and losing the history attached to them.
     *
     * @return array<string,mixed>|null
     */
    public function authenticate(string $username, string $pin): ?array
    {
        $sql = 'SELECT id, name, code, jobTitle, pin
                FROM tbl_employees
                WHERE (name = :u OR code = :u2) AND pin = :pin AND pin <> \'\'
                  AND active = b\'1\' AND resigned = b\'0\'
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['u' => $username, 'u2' => $username, 'pin' => $pin]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<int,array<string,mixed>> All employees (for owner reference). */
    public function all(): array
    {
        return $this->pdo->query(
            'SELECT id, name, code, jobTitle, pin, phone1, email,
                    CAST(active AS UNSIGNED) AS active,
                    CAST(resigned AS UNSIGNED) AS resigned
             FROM tbl_employees ORDER BY name'
        )->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT *, CAST(active AS UNSIGNED) AS active_int,
                       CAST(resigned AS UNSIGNED) AS resigned_int
             FROM tbl_employees WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Is this name or code already used by someone else?
     *
     * Login matches `name = :u OR code = :u`, so a value that collides with
     * either column on another row would make the login ambiguous — whoever
     * the database returned first would get in.
     */
    public function loginNameTaken(string $value, ?int $exceptId = null): bool
    {
        if ($value === '') {
            return false;
        }
        $sql = 'SELECT 1 FROM tbl_employees WHERE (name = :v1 OR code = :v2)';
        $params = ['v1' => $value, 'v2' => $value];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Bookable therapists — employees whose jobTitle marks them as THERAPIST.
     * Resigned/deactivated staff drop out, so they stop appearing on the grid.
     *
     * @return array<int,array<string,mixed>>
     */
    public function therapists(): array
    {
        return $this->pdo->query(
            "SELECT id, name, jobTitle FROM tbl_employees
             WHERE LOWER(jobTitle) LIKE '%therap%'
               AND active = b'1' AND resigned = b'0'
             ORDER BY name"
        )->fetchAll();
    }

    /**
     * How many bookings this employee is attached to — as the therapist serving
     * them, and as the person who took the booking.
     *
     * @return array{served:int,servedFuture:int,reserved:int}
     */
    public function bookingCounts(int $id): array
    {
        $sql = 'SELECT
                    SUM(servedBy_id   = :a)                                            AS served,
                    SUM(servedBy_id   = :b AND reservationTime >= NOW() AND status <> 2) AS servedFuture,
                    SUM(reservedBy_id = :c)                                            AS reserved
                FROM tbl_reservation';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['a' => $id, 'b' => $id, 'c' => $id]);
        $row = $stmt->fetch() ?: [];

        return [
            'served'       => (int) ($row['served'] ?? 0),
            'servedFuture' => (int) ($row['servedFuture'] ?? 0),
            'reserved'     => (int) ($row['reserved'] ?? 0),
        ];
    }

    /**
     * Add a member of staff.
     *
     * `active`, `driver` and `resigned` are NOT NULL with no default in the POS
     * schema, so they have to be written explicitly.
     *
     * @param array<string,mixed> $d name, code, jobTitle, pin, phone, email, active, resigned
     */
    public function create(array $d): int
    {
        // bit(1) columns must be written as b'1'/b'0' literals: PDO binds a PHP
        // int as the *string* "1", which MySQL reads as the character '1'
        // (0x31) and rejects with "Data too long for column". Both values are
        // booleans resolved here, so nothing user-supplied reaches the SQL.
        $active   = !empty($d['active']) ? "b'1'" : "b'0'";
        $resigned = !empty($d['resigned']) ? "b'1'" : "b'0'";

        $sql = "INSERT INTO tbl_employees
                    (active, driver, resigned, name, code, jobTitle, pin, phone1, email, joined)
                VALUES
                    ($active, b'0', $resigned, :name, :code, :jobTitle, :pin, :phone, :email, CURDATE())";
        $this->pdo->prepare($sql)->execute([
            'name'     => $d['name'],
            'code'     => $d['code'] ?? null,
            'jobTitle' => $d['jobTitle'] ?? null,
            'pin'      => $d['pin'] ?? null,
            'phone'    => $d['phone'] ?? null,
            'email'    => $d['email'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update a member of staff. A null `pin` leaves the existing one alone, so
     * the edit form can offer "leave blank to keep current".
     *
     * @param array<string,mixed> $d
     */
    public function update(int $id, array $d): void
    {
        // See create(): bit(1) needs literals, not bound parameters.
        $active   = !empty($d['active']) ? "b'1'" : "b'0'";
        $resigned = !empty($d['resigned']) ? "b'1'" : "b'0'";

        $sql = "UPDATE tbl_employees SET
                    name     = :name,
                    code     = :code,
                    jobTitle = :jobTitle,
                    phone1   = :phone,
                    email    = :email,
                    active   = $active,
                    resigned = $resigned";
        $params = [
            'id'       => $id,
            'name'     => $d['name'],
            'code'     => $d['code'] ?? null,
            'jobTitle' => $d['jobTitle'] ?? null,
            'phone'    => $d['phone'] ?? null,
            'email'    => $d['email'] ?? null,
        ];
        if (($d['pin'] ?? null) !== null) {
            $sql .= ', pin = :pin';
            $params['pin'] = $d['pin'];
        }
        $sql .= ' WHERE id = :id';

        $this->pdo->prepare($sql)->execute($params);
    }

    /**
     * Remove a member of staff.
     *
     * Many POS tables carry a foreign key to `tbl_employees` (attendances, cash
     * payments, shifts, …), so the database itself refuses to delete anyone
     * with history. That rejection is reported rather than thrown, because it
     * is an ordinary outcome here, not a bug — the answer is to mark them
     * resigned instead.
     */
    public function delete(int $id): bool
    {
        try {
            $this->pdo->prepare('DELETE FROM tbl_employees WHERE id = :id')->execute(['id' => $id]);

            return true;
        } catch (PDOException) {
            return false;
        }
    }
}
