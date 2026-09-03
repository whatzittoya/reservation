<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

/**
 * Access to the existing POS `tbl_tables` table (the bookable resources).
 * Rows are grouped by section_id for the grid.
 */
final class TableRepository
{
    /** @var array<int,string>|null id => name, loaded once per request. */
    private ?array $sections = null;

    private ?bool $hasSections = null;

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * The POS's real sections, from `tbl_sections`.
     *
     * Any section id used by a table but missing from `tbl_sections` still gets
     * a label, so a partial database never hides rows from the grid.
     *
     * @return array<int,string> id => name
     */
    public function sections(): array
    {
        if ($this->sections !== null) {
            return $this->sections;
        }

        $out = [];
        try {
            foreach ($this->pdo->query('SELECT id, name FROM tbl_sections ORDER BY position, id')->fetchAll() as $row) {
                $name = trim((string) $row['name']);
                $out[(int) $row['id']] = $name !== '' ? $name : ('Section ' . (int) $row['id']);
            }
        } catch (PDOException) {
            // Older POS databases (a partial dump, say) have no tbl_sections at
            // all. Fall back to numbered labels rather than taking down every
            // page that draws the grid.
        }
        foreach ($this->sectionIds() as $id) {
            $out[$id] ??= 'Section ' . $id;
        }

        return $this->sections = $out;
    }

    /**
     * Add a POS section (a grid group). Returns its new id.
     *
     * `position` is the only NOT NULL column besides the key, and every
     * existing row uses 100, so new sections join them and order by name.
     * The cached section list is dropped so the caller sees the new row.
     */
    public function createSection(string $name): int
    {
        if (!$this->hasSectionsTable()) {
            throw new \RuntimeException('This database has no tbl_sections table, so sections cannot be created.');
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO tbl_sections (name, code, position, salesType_id)
             VALUES (:name, \'\', :position, NULL)'
        );
        $position = (int) ($this->pdo->query('SELECT COALESCE(MAX(position), 100) FROM tbl_sections')->fetchColumn() ?: 100);
        $stmt->execute(['name' => $name, 'position' => $position]);
        $this->sections = null;

        return (int) $this->pdo->lastInsertId();
    }

    /** Is a section with this name already there? Names are how staff tell them apart. */
    public function sectionNameExists(string $name): bool
    {
        if (!$this->hasSectionsTable()) {
            return false;
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM tbl_sections WHERE name = :n LIMIT 1');
        $stmt->execute(['n' => $name]);

        return $stmt->fetchColumn() !== false;
    }

    /** Whether this database carries the POS `tbl_sections` table at all. */
    public function hasSectionsTable(): bool
    {
        if ($this->hasSections === null) {
            try {
                $this->pdo->query('SELECT 1 FROM tbl_sections LIMIT 1');
                $this->hasSections = true;
            } catch (PDOException) {
                $this->hasSections = false;
            }
        }

        return $this->hasSections;
    }

    public function sectionLabel(?int $id): string
    {
        return $this->sections()[$id] ?? ('Section ' . ($id ?? '?'));
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

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tbl_tables WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Names must stay unique: the grid keys its rows by name, and a booking
     * points at its table by name rather than by id, so two tables sharing one
     * would land in the same row and become indistinguishable.
     */
    public function nameExists(string $name, ?int $exceptId = null): bool
    {
        $sql = 'SELECT 1 FROM tbl_tables WHERE name = :name';
        $params = ['name' => $name];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * How many bookings reference each table name, split into all-time and
     * still-upcoming. Keyed by name, because that is what a booking stores.
     *
     * @return array<string,array{total:int,future:int}>
     */
    public function bookingCounts(): array
    {
        $sql = 'SELECT tableName,
                       COUNT(*) AS total,
                       SUM(reservationTime >= NOW() AND status <> 2) AS future
                FROM tbl_reservation
                WHERE tableName IS NOT NULL AND tableName <> \'\'
                GROUP BY tableName';
        $out = [];
        foreach ($this->pdo->query($sql)->fetchAll() as $row) {
            $out[(string) $row['tableName']] = [
                'total'  => (int) $row['total'],
                'future' => (int) $row['future'],
            ];
        }

        return $out;
    }

    /**
     * Add a bookable resource.
     *
     * `tbl_tables` is the POS floor plan, so several columns are NOT NULL and
     * carry no default: a new row gets the standard 50x50 square, parked at the
     * bottom of its section's column. Drag it into place in the POS if the
     * physical layout matters — nothing in this app reads x/y.
     *
     * @param array<string,mixed> $data name, capacity, section_id
     */
    public function create(array $data): int
    {
        $section = (int) $data['section_id'];

        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(MAX(idx), -1) + 1 AS nextIdx, COALESCE(MAX(y), -50) + 60 AS nextY
             FROM tbl_tables WHERE section_id = :section'
        );
        $stmt->execute(['section' => $section]);
        $spot = $stmt->fetch() ?: ['nextIdx' => 0, 'nextY' => 10];

        $sql = 'INSERT INTO tbl_tables
                    (name, capacity, section_id, idx, x, y, width, height, round, joinTable, appsindo)
                VALUES (:name, :capacity, :section, :idx, 10, :y, 50, 50, b\'0\', \'\', b\'0\')';
        $this->pdo->prepare($sql)->execute([
            'name'     => $data['name'],
            'capacity' => (int) $data['capacity'],
            'section'  => $section,
            'idx'      => (int) $spot['nextIdx'],
            'y'        => (int) $spot['nextY'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Rename / re-capacity / re-section a table.
     *
     * A rename also rewrites `tbl_reservation.tableName`, because a booking
     * stores the name, not the id — without it every existing booking for this
     * table would drop off the grid. Both writes go in one transaction so a
     * failure can never leave bookings pointing at a name that no longer exists.
     *
     * @param array<string,mixed> $data name, capacity, section_id
     */
    public function update(int $id, array $data): void
    {
        $current = $this->find($id);
        if ($current === null) {
            return;
        }
        $oldName = (string) $current['name'];
        $newName = (string) $data['name'];

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare(
                'UPDATE tbl_tables SET name = :name, capacity = :capacity, section_id = :section WHERE id = :id'
            )->execute([
                'id'       => $id,
                'name'     => $newName,
                'capacity' => (int) $data['capacity'],
                'section'  => (int) $data['section_id'],
            ]);

            if ($newName !== $oldName) {
                $this->pdo->prepare(
                    'UPDATE tbl_reservation SET tableName = :new WHERE tableName = :old'
                )->execute(['new' => $newName, 'old' => $oldName]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Remove a table. Past bookings keep the name as plain text, so history
     * stays readable; the route refuses the delete while bookings are still
     * upcoming.
     */
    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM tbl_tables WHERE id = :id')->execute(['id' => $id]);
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
                $groups[$sid] = ['label' => $this->sectionLabel($sid), 'tables' => []];
            }
            $groups[$sid]['tables'][] = $t;
        }

        return $groups;
    }
}
