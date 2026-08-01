<?php
/** @var string $basePath */
/** @var array $customer */
/** @var array $history */
$e = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES);
$c = $customer;
?>
<div class="toolbar">
    <h1><?= $e($c['name']) ?></h1>
    <a class="btn" href="<?= $basePath ?>/customers">← Back</a>
</div>

<div class="card" style="max-width:560px;">
    <table class="list">
        <tr><th>Phone</th><td><?= $e($c['phone1']) ?: '<span class="muted">—</span>' ?></td></tr>
        <tr><th>Email</th><td><?= $e($c['email']) ?: '<span class="muted">—</span>' ?></td></tr>
        <tr><th>Address</th><td><?= $e($c['address']) ?: '<span class="muted">—</span>' ?></td></tr>
        <tr><th>Notes</th><td><?= nl2br($e($c['notes'])) ?: '<span class="muted">—</span>' ?></td></tr>
        <tr><th>Total bookings</th><td><span class="badge s1"><?= count($history) ?></span></td></tr>
    </table>
</div>

<div class="card">
    <h1 style="font-size:16px;margin-bottom:10px;">Booking history</h1>
    <table class="list">
        <thead><tr><th>Date &amp; time</th><th>Table</th><th>Guests</th><th>Status</th></tr></thead>
        <tbody>
        <?php if ($history === []): ?>
            <tr><td colspan="4" class="muted">No bookings.</td></tr>
        <?php endif; ?>
        <?php foreach ($history as $h): $st = (int) $h['status']; ?>
            <tr>
                <td><a href="<?= $basePath ?>/reservations/<?= (int) $h['id'] ?>"><?= $e(date('d M Y, H:i', strtotime((string) $h['reservationTime']))) ?></a></td>
                <td><?= $e($h['tableName']) ?></td>
                <td><?= (int) $h['cover'] ?></td>
                <td><span class="badge s<?= $st ?>"><?= $e(\App\ReservationRepository::statusLabel($st)) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
