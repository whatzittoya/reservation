<?php
/** @var string $basePath */
/** @var bool $isOwner */
/** @var array $customers */
/** @var array $syncMap      customer_id => sync state row */
/** @var bool $syncOn */
/** @var string|null $syncOffWhy */
$e = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES);
$cols = 4 + ($isOwner ? 1 : 0);
?>
<div class="toolbar">
    <h1>Customers <span class="muted" style="font-size:14px;font-weight:400;">(<?= count($customers) ?>)</span></h1>
    <a class="btn btn-primary" href="<?= $basePath ?>/customers/new">+ New customer</a>
</div>

<?php if (!$syncOn): ?>
    <div class="note">Cloud sync is off — <?= $e($syncOffWhy) ?>. Customers save normally; nothing is sent to Quinos Cloud.</div>
<?php endif; ?>

<div class="filterbar">
    <input type="search" placeholder="Search customers by name, phone or email…"
           data-filter=".js-cust" data-noun="match" autocomplete="off">
    <span class="count"></span>
</div>

<div class="card">
    <table class="list">
        <thead>
            <tr>
                <th>Name</th>
                <th>Phone</th>
                <th>Email</th>
                <?php if ($isOwner): ?><th>Bookings</th><?php endif; ?>
                <th>Cloud</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($customers === []): ?>
            <tr><td colspan="<?= $cols ?>" class="muted">No customers yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($customers as $cust): ?>
            <?php
            $id    = (int) $cust['id'];
            $state = $syncMap[$id] ?? null;
            $status = $state['status'] ?? null;
            ?>
            <tr class="js-cust">
                <td><a href="<?= $basePath ?>/customers/<?= $id ?>"><strong><?= $e($cust['name']) ?></strong></a></td>
                <td class="muted"><?= $e($cust['phone1']) ?: '—' ?></td>
                <td class="muted"><?= $e($cust['email']) ?: '—' ?></td>
                <?php if ($isOwner): ?>
                    <td><span class="badge s1"><?= (int) ($cust['booking_count'] ?? 0) ?></span></td>
                <?php endif; ?>
                <td>
                    <?php if ($status === 'synced'): ?>
                        <span class="cloud ok" title="<?= $e($state['code']) ?><?= $state['synced_at'] ? ' · ' . $e($state['synced_at']) : '' ?>">Synced</span>
                    <?php elseif ($status === 'failed'): ?>
                        <span class="cloud bad" title="<?= $e($state['last_error']) ?>">Failed</span>
                    <?php else: ?>
                        <span class="cloud off">Not synced</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
