<?php
/** @var string $basePath */
/** @var array $customer */
/** @var array $history */
/** @var array|null $sync    sync state row, null = never pushed */
/** @var string $syncCode    the code this customer would use in the cloud */
/** @var bool $syncOn */
/** @var string|null $syncOffWhy */
$e = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES);
$c = $customer;
$status = $sync['status'] ?? null;
?>
<div class="toolbar">
    <h1><?= $e($c['name']) ?></h1>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a class="btn btn-primary" href="<?= $basePath ?>/customers/<?= (int) $c['id'] ?>/edit">Edit</a>
        <a class="btn" href="<?= $basePath ?>/customers">← Back</a>
    </div>
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

<div class="card" style="max-width:560px;">
    <h1 style="font-size:16px;margin-bottom:10px;">Quinos Cloud</h1>
    <table class="list">
        <tr>
            <th>Status</th>
            <td>
                <?php if ($status === 'synced'): ?>
                    <span class="cloud ok">Synced</span>
                <?php elseif ($status === 'failed'): ?>
                    <span class="cloud bad">Failed</span>
                <?php else: ?>
                    <span class="cloud off">Not synced</span>
                <?php endif; ?>
            </td>
        </tr>
        <tr><th>Code</th><td><code><?= $e($syncCode) ?></code></td></tr>
        <?php if (!empty($sync['cloud_id'])): ?>
            <tr><th>Cloud id</th><td><?= (int) $sync['cloud_id'] ?></td></tr>
        <?php endif; ?>
        <?php if (!empty($sync['synced_at'])): ?>
            <tr><th>Last synced</th><td><?= $e(date('d M Y, H:i', strtotime((string) $sync['synced_at']))) ?></td></tr>
        <?php endif; ?>
        <?php if ($status === 'failed' && !empty($sync['last_error'])): ?>
            <tr><th>Last error</th><td class="muted"><?= $e($sync['last_error']) ?></td></tr>
        <?php endif; ?>
    </table>

    <div class="actions">
        <?php if ($syncOn): ?>
            <form method="post" action="<?= $basePath ?>/customers/<?= (int) $c['id'] ?>/sync" class="inline">
                <button type="submit" class="btn btn-primary">
                    <?= $status === 'synced' ? 'Sync again' : ($status === 'failed' ? 'Retry sync' : 'Sync to cloud') ?>
                </button>
            </form>
            <span class="muted" style="font-size:12px;">
                <?= $status === 'synced'
                    ? 'Pushes the current details up again.'
                    : 'Sends this customer to Quinos Cloud now.' ?>
            </span>
        <?php else: ?>
            <span class="muted" style="font-size:13px;">Cloud sync is off — <?= $e($syncOffWhy) ?>.</span>
        <?php endif; ?>
    </div>
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
