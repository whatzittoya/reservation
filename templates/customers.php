<?php
/** @var string $basePath */
/** @var bool $isOwner */
/** @var array $customers */
$e = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES);
?>
<div class="toolbar">
    <h1>Customers <span class="muted" style="font-size:14px;font-weight:400;">(<?= count($customers) ?>)</span></h1>
    <a class="btn btn-primary" href="<?= $basePath ?>/customers/new">+ New customer</a>
</div>

<div class="card">
    <table class="list">
        <thead>
            <tr>
                <th>Name</th>
                <th>Phone</th>
                <th>Email</th>
                <?php if ($isOwner): ?><th>Bookings</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php if ($customers === []): ?>
            <tr><td colspan="4" class="muted">No customers yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($customers as $cust): ?>
            <tr>
                <td><a href="<?= $basePath ?>/customers/<?= (int) $cust['id'] ?>"><strong><?= $e($cust['name']) ?></strong></a></td>
                <td class="muted"><?= $e($cust['phone1']) ?: '—' ?></td>
                <td class="muted"><?= $e($cust['email']) ?: '—' ?></td>
                <?php if ($isOwner): ?>
                    <td><span class="badge s1"><?= (int) ($cust['booking_count'] ?? 0) ?></span></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
