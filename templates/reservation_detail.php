<?php
/** @var string $basePath */
/** @var array $reservation */
/** @var \App\Auth $auth */
$e = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES);
$r = $reservation;
$st = (int) $r['status'];
$date = substr((string) $r['reservationTime'], 0, 10);
$isOwner = $auth->isOwner();
$isTherapy = !empty($r['servedBy_id']);
?>
<div class="toolbar">
    <h1>Reservation #<?= (int) $r['id'] ?></h1>
    <a class="btn" href="<?= $basePath ?>/grid?date=<?= $e($date) ?><?= $isTherapy ? '&view=therapist' : '' ?>">← Back to grid</a>
</div>

<div class="card" style="max-width:560px;">
    <table class="list">
        <tr><th>Customer</th><td><?= $e($r['customer_name'] ?? $r['name']) ?: '<span class="muted">—</span>' ?></td></tr>
        <tr><th>Status</th><td><span class="badge s<?= $st ?>"><?= $e(\App\ReservationRepository::statusLabel($st)) ?></span></td></tr>
        <?php if ($isTherapy): ?>
            <tr><th>Therapist</th><td><?= $e($r['served_by_name']) ?: '<span class="muted">—</span>' ?></td></tr>
        <?php else: ?>
            <tr><th>Table</th><td><?= $e($r['tableName']) ?: '<span class="muted">—</span>' ?></td></tr>
        <?php endif; ?>
        <tr><th>Date &amp; time</th><td><?= $e(date('l, d M Y — H:i', strtotime((string) $r['reservationTime']))) ?></td></tr>
        <tr><th>Guests</th><td><?= (int) $r['cover'] ?> pax</td></tr>
        <tr><th>Phone</th><td><?= $e($r['phone']) ?: '<span class="muted">—</span>' ?></td></tr>
        <tr><th>Notes</th><td><?= nl2br($e($r['notes'])) ?: '<span class="muted">—</span>' ?></td></tr>
        <tr><th>Reserved by</th><td><?= $e($r['reserved_by_name']) ?: '<span class="muted">—</span>' ?></td></tr>
        <?php if ($st === 2 && !empty($r['voidReason'])): ?>
            <tr><th>Cancel reason</th><td><?= $e($r['voidReason']) ?></td></tr>
        <?php endif; ?>
    </table>

    <?php if ($st !== 2): ?>
        <div class="actions" style="margin-top:6px;">
            <?php if ($st === 0): ?>
                <form class="inline" method="post" action="<?= $basePath ?>/reservations/<?= (int) $r['id'] ?>/arrive">
                    <button type="submit" class="btn btn-primary">✓ Mark as arrived</button>
                </form>
                <span class="muted" style="font-size:13px;">Guest not yet arrived.</span>
            <?php else: /* arrived */ ?>
                <span class="badge s1" style="font-size:13px;">✓ Arrived<?= !empty($r['arrived']) ? ' at ' . $e(date('H:i', strtotime((string) $r['arrived']))) : '' ?></span>
                <form class="inline" method="post" action="<?= $basePath ?>/reservations/<?= (int) $r['id'] ?>/unarrive">
                    <button type="submit" class="btn">Undo</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="actions">
        <a class="btn btn-primary" href="<?= $basePath ?>/reservations/<?= (int) $r['id'] ?>/edit">Edit</a>
        <?php if ($isOwner): ?>
            <form class="inline" method="post" action="<?= $basePath ?>/reservations/<?= (int) $r['id'] ?>/delete"
                  data-confirm-title="Delete this reservation?"
                  data-confirm="Reservation #<?= (int) $r['id'] ?> will be removed permanently. This cannot be undone."
                  data-confirm-ok="Delete permanently" data-confirm-danger>
                <button type="submit" class="btn btn-danger">Delete</button>
            </form>
        <?php endif; ?>
    </div>
    <?php if ($st !== 2): ?>
        <form method="post" action="<?= $basePath ?>/reservations/<?= (int) $r['id'] ?>/cancel" style="margin-top:14px;display:flex;gap:8px;align-items:flex-end;max-width:420px;"
              data-confirm-title="Cancel this booking?"
              <?php $who = (string) ($r['customer_name'] ?? $r['name'] ?? ''); ?>
              data-confirm="Reservation #<?= (int) $r['id'] ?><?= $who !== '' ? ' for ' . $e($who) : '' ?> will be marked cancelled and its slot freed."
              data-confirm-ok="Yes, cancel booking" data-confirm-danger>
            <div style="flex:1;">
                <label style="margin-top:0;">Cancel reason</label>
                <input type="text" name="reason" placeholder="e.g. guest cancelled">
            </div>
            <button type="submit" class="btn">Cancel booking</button>
        </form>
    <?php endif; ?>
</div>
