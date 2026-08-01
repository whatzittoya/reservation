<?php
/** @var string $basePath */
/** @var string $date */
/** @var array $totals */
/** @var array $list */
$e = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES);
?>
<div class="toolbar">
    <h1>Daily Report</h1>
    <form method="get" action="<?= $basePath ?>/reports" style="display:flex;gap:8px;align-items:center;">
        <input type="date" name="date" value="<?= $e($date) ?>" onchange="this.form.submit()">
        <button class="btn" type="submit">Go</button>
    </form>
</div>

<div class="row" style="margin-bottom:16px;">
    <div class="card" style="text-align:center;">
        <div class="muted" style="font-size:13px;">Total Guests</div>
        <div style="font-size:30px;font-weight:800;"><?= (int) $totals['guests'] ?></div>
    </div>
    <div class="card" style="text-align:center;">
        <div class="muted" style="font-size:13px;">Total Reservations</div>
        <div style="font-size:30px;font-weight:800;"><?= (int) $totals['reservations'] ?></div>
    </div>
    <div class="card" style="text-align:center;">
        <div class="muted" style="font-size:13px;">Open (booked)</div>
        <div style="font-size:30px;font-weight:800;"><?= (int) $totals['open'] ?></div>
    </div>
</div>

<div class="card">
    <h1 style="font-size:16px;margin-bottom:10px;"><?= $e(date('l, d M Y', strtotime($date))) ?></h1>
    <table class="list">
        <thead><tr><th>Time</th><th>Customer</th><th>Table</th><th>Guests</th><th>Status</th><th>Reserved by</th></tr></thead>
        <tbody>
        <?php if ($list === []): ?>
            <tr><td colspan="6" class="muted">No reservations on this day.</td></tr>
        <?php endif; ?>
        <?php foreach ($list as $r): $st = (int) $r['status']; ?>
            <tr>
                <td><a href="<?= $basePath ?>/reservations/<?= (int) $r['id'] ?>"><?= $e(substr((string) $r['reservationTime'], 11, 5)) ?></a></td>
                <td><?= $e($r['customer_name'] ?? $r['name']) ?></td>
                <td><?= $e($r['tableName']) ?></td>
                <td><?= (int) $r['cover'] ?></td>
                <td><span class="badge s<?= $st ?>"><?= $e(\App\ReservationRepository::statusLabel($st)) ?></span></td>
                <td class="muted"><?= $e($r['reserved_by_name']) ?: '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
