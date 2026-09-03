<?php
/** @var string $basePath */
/** @var array $groups */
/** @var array $bookings   table name => ['total'=>int,'future'=>int] */
/** @var string $roomLabel 'Table' or 'Room' (spa mode) */
$e = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES);
$total = 0;
foreach ($groups as $g) {
    $total += count($g['tables']);
}
?>
<div class="toolbar">
    <h1><?= $e($roomLabel) ?>s <span class="muted" style="font-size:14px;font-weight:400;">(<?= $total ?>)</span></h1>
    <a class="btn btn-primary" href="<?= $basePath ?>/tables/new">+ New <?= $e(strtolower($roomLabel)) ?></a>
</div>

<div class="filterbar">
    <input type="search" placeholder="Search by name…"
           data-filter=".js-table" data-noun="match" autocomplete="off">
    <span class="count"></span>
</div>

<?php foreach ($groups as $group): ?>
    <div class="card" data-filter-group>
        <h1 style="font-size:15px;margin-bottom:10px;">
            <?= $e($group['label']) ?>
            <span class="muted" style="font-weight:400;">(<?= count($group['tables']) ?>)</span>
        </h1>
        <table class="list">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Capacity</th>
                    <th>Bookings</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($group['tables'] as $t): ?>
                <?php
                $name   = (string) $t['name'];
                $counts = $bookings[$name] ?? ['total' => 0, 'future' => 0];
                ?>
                <tr class="js-table">
                    <td><strong><?= $e($name) ?></strong></td>
                    <td class="muted"><?= (int) $t['capacity'] ?> pax</td>
                    <td>
                        <?php if ($counts['future'] > 0): ?>
                            <span class="badge s0"><?= $counts['future'] ?> upcoming</span>
                        <?php elseif ($counts['total'] > 0): ?>
                            <span class="muted"><?= $counts['total'] ?> past</span>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;white-space:nowrap;">
                        <a class="btn" href="<?= $basePath ?>/tables/<?= (int) $t['id'] ?>/edit">Edit</a>
                        <form method="post" action="<?= $basePath ?>/tables/<?= (int) $t['id'] ?>/delete" class="inline"
                              data-confirm="&quot;<?= $e($name) ?>&quot; will be removed from the grid.<?= $counts['total'] > 0 ? ' Its ' . $counts['total'] . ' past booking' . ($counts['total'] === 1 ? '' : 's') . ' stay in the history.' : '' ?>"
                              data-confirm-title="Delete <?= $e(strtolower($roomLabel)) ?>?"
                              data-confirm-ok="Delete" data-confirm-danger>
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endforeach; ?>
