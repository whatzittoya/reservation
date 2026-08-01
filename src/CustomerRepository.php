<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * Access to the existing POS `tbl_customers` table.
 *
 * Only a handful of the table's many columns are relevant to reservations
 * (name, phone1, email, notes); the rest keep their defaults on insert.
 */
final class CustomerRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return $this->pdo->query(
            'SELECT id, name, phone1, email, notes, created FROM tbl_customers ORDER BY name'
        )->fetchAll();
    }

    /**
     * Customers with how many reservations each has (owner view).
     *
     * @return array<int,array<string,mixed>>
     */
    public function allWithBookingCounts(): array
    {
        $sql = 'SELECT c.id, c.name, c.phone1, c.email, c.created,
                       COUNT(r.id) AS booking_count
                FROM tbl_customers c
                LEFT JOIN tbl_reservation r ON r.customer_id = c.id
                GROUP BY c.id, c.name, c.phone1, c.email, c.created
                ORDER BY booking_count DESC, c.name';
        return $this->pdo->query($sql)->fetchAll();
    }

    /**
     * Free-text search on name / phone / email, for the header search box.
     *
     * @return array<int,array<string,mixed>>
     */
    public function search(string $q, int $limit = 8): array
    {
        // Escape LIKE wildcards so a literal % or _ in the query isn't a wildcard.
        $like = '%' . addcslashes($q, '%_\\') . '%';
        // Named params are not reused — PDO runs with emulation off (see Database).
        $sql = 'SELECT c.id, c.name, c.phone1, c.email,
                       COUNT(r.id) AS booking_count
                FROM tbl_customers c
                LEFT JOIN tbl_reservation r ON r.customer_id = c.id
                WHERE c.name LIKE :q1 OR c.phone1 LIKE :q2 OR c.email LIKE :q3
                GROUP BY c.id, c.name, c.phone1, c.email
                ORDER BY c.name
                LIMIT ' . max(1, $limit);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['q1' => $like, 'q2' => $like, 'q3' => $like]);

        return $stmt->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tbl_customers WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Insert a new customer. Returns the new id.
     *
     * @param array<string,mixed> $data name, phone, email, notes
     */
    public function create(array $data): int
    {
        $sql = 'INSERT INTO tbl_customers (active, name, phone1, email, notes, created, type)
                VALUES (b\'1\', :name, :phone, :email, :notes, CURDATE(), \'P\')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'name'  => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
