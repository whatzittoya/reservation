<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * Read access to the existing POS `tbl_employees` table, used for login.
 */
final class EmployeeRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Find an active employee by (name OR code) + pin. Returns null on no match.
     *
     * @return array<string,mixed>|null
     */
    public function authenticate(string $username, string $pin): ?array
    {
        $sql = 'SELECT id, name, code, jobTitle, pin
                FROM tbl_employees
                WHERE (name = :u OR code = :u2) AND pin = :pin AND pin <> \'\'
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
            'SELECT id, name, code, jobTitle, pin FROM tbl_employees ORDER BY name'
        )->fetchAll();
    }

    /**
     * Bookable therapists — employees whose jobTitle marks them as THERAPIST.
     *
     * @return array<int,array<string,mixed>>
     */
    public function therapists(): array
    {
        return $this->pdo->query(
            "SELECT id, name, jobTitle FROM tbl_employees
             WHERE LOWER(jobTitle) LIKE '%therap%'
             ORDER BY name"
        )->fetchAll();
    }
}
