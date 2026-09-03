<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

/**
 * Per-customer cloud sync state, kept in `tbl_customer_cloud_sync`.
 *
 * A separate table on purpose: the POS's own `tbl_customers` is left exactly as
 * it is. Run the DDL in sql/schema.sql once before using cloud sync — until
 * then every read here degrades to "no state known" rather than white-screening
 * the Customers page.
 */
final class CustomerSyncRepository
{
    public const SYNCED = 'synced';
    public const FAILED = 'failed';

    private ?bool $installed = null;

    public function __construct(private PDO $pdo)
    {
    }

    /** Whether the sync table exists (checked once per request). */
    public function isInstalled(): bool
    {
        if ($this->installed === null) {
            try {
                $this->pdo->query('SELECT 1 FROM tbl_customer_cloud_sync LIMIT 1');
                $this->installed = true;
            } catch (PDOException) {
                $this->installed = false;
            }
        }

        return $this->installed;
    }

    /** @return array<string,mixed>|null */
    public function find(int $customerId): ?array
    {
        if (!$this->isInstalled()) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM tbl_customer_cloud_sync WHERE customer_id = :id');
        $stmt->execute(['id' => $customerId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * State for every customer, keyed by customer_id — one query for the whole
     * Customers list rather than one per row.
     *
     * @return array<int,array<string,mixed>>
     */
    public function allKeyedByCustomer(): array
    {
        if (!$this->isInstalled()) {
            return [];
        }
        $rows = $this->pdo->query('SELECT * FROM tbl_customer_cloud_sync')->fetchAll();
        $out  = [];
        foreach ($rows as $row) {
            $out[(int) $row['customer_id']] = $row;
        }

        return $out;
    }

    /**
     * Record the outcome of a push. One row per customer, overwritten each time.
     *
     * `cloud_id` is only ever written, never cleared: a later failure must not
     * lose the knowledge that the record does exist up there, or the next retry
     * would try to create a duplicate.
     */
    public function record(int $customerId, string $code, string $status, ?int $cloudId, ?string $error): void
    {
        if (!$this->isInstalled()) {
            return;
        }
        $sql = 'INSERT INTO tbl_customer_cloud_sync
                    (customer_id, cloud_id, code, status, last_error, synced_at)
                VALUES (:id, :cloud_id, :code, :status, :err, :at)
                ON DUPLICATE KEY UPDATE
                    cloud_id   = COALESCE(VALUES(cloud_id), cloud_id),
                    code       = VALUES(code),
                    status     = VALUES(status),
                    last_error = VALUES(last_error),
                    synced_at  = COALESCE(VALUES(synced_at), synced_at)';
        $this->pdo->prepare($sql)->execute([
            'id'       => $customerId,
            'cloud_id' => $cloudId,
            'code'     => $code,
            'status'   => $status,
            // Column is varchar(255); a long cloud message must not blow up the write.
            'err'      => $error === null ? null : mb_substr($error, 0, 255),
            'at'       => $status === self::SYNCED ? date('Y-m-d H:i:s') : null,
        ]);
    }
}
