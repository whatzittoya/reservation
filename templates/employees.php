<?php
/** @var string $basePath */
/** @var array $employees */
/** @var \App\Auth $auth */
$e = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES);
$kw = ['manager', 'owner', 'admin'];
$roleOf = static function ($title) use ($kw) {
    $t = strtolower((string) $title);
    foreach ($kw as $k) { if (str_contains($t, $k)) return 'owner'; }
    return 'employee';
};
?>
<div class="toolbar">
    <h1>Staff <span class="muted" style="font-size:14px;font-weight:400;">(<?= count($employees) ?>)</span></h1>
    <span class="muted" style="font-size:13px;">Login uses name/code + PIN. Owner = job title contains manager/owner/admin.</span>
</div>

<div class="card">
    <table class="list">
        <thead><tr><th>Name</th><th>Code</th><th>Job title</th><th>Effective role</th></tr></thead>
        <tbody>
        <?php foreach ($employees as $emp): $role = $roleOf($emp['jobTitle']); ?>
            <tr>
                <td><strong><?= $e($emp['name']) ?></strong></td>
                <td class="muted"><?= $e($emp['code']) ?: '—' ?></td>
                <td><?= $e($emp['jobTitle']) ?: '<span class="muted">—</span>' ?></td>
                <td><span class="role <?= $role === 'owner' ? 'owner' : '' ?>" style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;text-transform:uppercase;background:<?= $role === 'owner' ? '#fef3c7' : '#eef2ff' ?>;color:<?= $role === 'owner' ? '#8a5a00' : '#3730a3' ?>;"><?= $role ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
