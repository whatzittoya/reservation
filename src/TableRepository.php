<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * Access to the existing POS `tbl_tables` table (the bookable resources).
 * Rows are grouped by section_id for the grid.
 */
final class TableRepository
{
    /** Friendly labels for the known section ids. */
    private const SECTION_LABELS = [
        1 => 'Restaurant',
        2 => 'Terrace',
        3 => 'Rooms',
        4 => 'Pool',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<int,array<string,mixed>> All tables ordered for display. */
    public function all(): array
    {
        return $this->pdo->query(
            'SELECT id, name, capacity, section_id, idx
             FROM tbl_tables
             ORDER BY section_id, idx, name'
        )->fetchAll();
    }

    /** @return array<int,int> Distinct section ids present. */
    public function sectionIds(): array
    {
        $rows = $this->pdo->query(
            'SELECT DISTINCT section_id FROM tbl_tables ORDER BY section_id'
        )->fetchAll(PDO::FETCH_COLUMN);

        return array_map('intval', $rows);
    }

    public static function sectionLabel(?int $id): string
    {
        return self::SECTION_LABELS[$id] ?? ('Section ' . ($id ?? '?'));
    }

    /**
     * Tables grouped by section id, optionally limited to one section.
     *
     * @return array<int,array{label:string,tables:array<int,array<string,mixed>>}>
     */
    public function groupedBySection(?int $onlySection = null): array
    {
        $groups = [];
        foreach ($this->all() as $t) {
            $sid = (int) $t['section_id'];
            if ($onlySection !== null && $sid !== $onlySection) {
                continue;
            }
            if (!isset($groups[$sid])) {
                $groups[$sid] = ['label' => self::sectionLabel($sid), 'tables' => []];
            }
            $groups[$sid]['tables'][] = $t;
        }

        return $groups;
    }
}
