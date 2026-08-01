<?php
/** @var string $basePath */
/** @var string $q */
/** @var array $customers */
/** @var array $bookings */
$e = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES);
$hasQuery = $q !== '';
$hits = count($customers) + count($bookings);
?>
<style>
    .searchhome{max-width:640px;margin:0 auto;text-align:center;padding:<?= $hasQuery ? '4px' : '11vh' ?> 0 18px;}
    .searchhome h1{font-size:<?= $hasQuery ? '22px' : '38px' ?>;font-weight:800;margin:0 0 <?= $hasQuery ? '14px' : '8px' ?>;}
    .searchhome h1 span{color:var(--accent);}
    .searchhome .tagline{color:var(--muted);font-size:14px;margin:0 0 26px;}
    .searchform{display:flex;gap:10px;align-items:center;}
    .searchform input{padding:14px 20px;border-radius:999px;font-size:16px;box-shadow:0 2px 10px rgba(31,41,51,.06);}
    .searchform input:focus{outline:none;border-color:var(--accent);box-shadow:0 2px 14px rgba(245,166,35,.25);}
    .searchform button{border-radius:999px;padding:12px 22px;white-space:nowrap;}
    .searchhint{margin-top:14px;color:var(--muted);font-size:13px;}
    .results{max-width:820px;margin:22px auto 0;}
    .results h2{font-size:14px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin:0 0 10px;}
</style>

<div class="searchhome">
    <h1>Quinos <span>Reservations</span></h1>
    <?php if (!$hasQuery): ?>
        <p class="tagline">Search every booking and customer — any date, past or upcoming.</p>
    <?php endif; ?>

    <form class="searchform" method="get" action="<?= $basePath ?>/search">
        <input type="search" name="q" value="<?= $e($q) ?>" autofocus
               placeholder="Customer name, phone, or table…">
        <button type="submit" class="btn btn-primary">Search</button>
    </form>

    <?php if (!$hasQuery): ?>
        <p class="searchhint">Tip: the grid's quick search filters one day — this searches them all.</p>
    <?php else: ?>
        <p class="searchhint"><?= $hits ?> result<?= $hits === 1 ? '' : 's' ?> for “<?= $e($q) ?>”</p>
    <?php endif; ?>
</div>

<?php if ($hasQuery): ?>
    <div class="results">
        <div class="card">
            <h2>Customers</h2>
            <?php if ($customers === []): ?>
                <p class="muted" style="margin:0;">No customer matches “<?= $e($q) ?>”.</p>
            <?php else: ?>
                <table class="list">
                    <tr><th>Name</th><th>Phone</th><th>Bookings</th></tr>
                    <?php foreach ($customers as $c): ?>
                        <tr>
                            <td><a href="<?= $basePath ?>/customers/<?= (int) $c['id'] ?>"><?= $e($c['name']) ?></a></td>
                            <td><?= $e($c['phone1']) ?: '<span class="muted">—</span>' ?></td>
                            <td><?= (int) $c['booking_count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Bookings <span style="text-transform:none;letter-spacing:0;font-weight:400;">(all dates, nearest first)</span></h2>
            <?php if ($bookings === []): ?>
                <p class="muted" style="margin:0;">No booking matches “<?= $e($q) ?>”.</p>
            <?php else: ?>
                <table class="list">
                    <tr><th>Customer</th><th>When</th><th>Table / Therapist</th><th>Pax</th><th>Status</th></tr>
                    <?php foreach ($bookings as $b): $st = (int) $b['status']; ?>
                        <tr>
                            <td><a href="<?= $basePath ?>/reservations/<?= (int) $b['id'] ?>"><?= $e($b['customer_name'] ?? $b['name'] ?: 'Guest') ?></a></td>
                            <td><?= $e(date('D, d M Y — H:i', strtotime((string) $b['reservationTime']))) ?></td>
                            <td><?= $e(!empty($b['servedBy_id']) ? ($b['served_by_name'] ?? 'Therapist') : $b['tableName']) ?: '<span class="muted">—</span>' ?></td>
                            <td><?= (int) $b['cover'] ?></td>
                            <td><span class="badge s<?= $st ?>"><?= $e(\App\ReservationRepository::statusLabel($st)) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
