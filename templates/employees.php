<?php
/** @var string $basePath */
/** @var array $employees */
/** @var \App\Auth $auth */
$e = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES);
$kw = ['manager', 'owner', 'admin'];
// Match Auth: generic POS accounts carry the label in `name` (e.g. "Manager"),
// real staff carry it in `jobTitle`, so both are searched — checking jobTitle
// alone reported the wrong role for those accounts.
$roleOf = static function ($name, $title) use ($kw) {
    $t = strtolower(trim($name . ' ' . $title));
    foreach ($kw as $k) {
        if (str_contains($t, $k)) {
            return 'owner';
        }
    }
    return 'employee';
};
$meId = (int) ($auth->id() ?? 0);
?>
<div class="toolbar">
    <h1>Staff <span class="muted" style="font-size:14px;font-weight:400;">(<?= count($employees) ?>)</span></h1>
    <a class="btn btn-primary" href="<?= $basePath ?>/employees/new">+ New staff</a>
</div>

<div class="filterbar">
    <input type="search" placeholder="Search staff by name, code or job title…"
           data-filter=".js-staff" data-noun="match" autocomplete="off">
    <span class="count"></span>
</div>

<div class="card">
    <table class="list">
        <thead>
            <tr>
                <th>Name</th>
                <th>Code</th>
                <th>Job title</th>
                <th>Role</th>
                <th>Sign in</th>
                <th style="text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($employees as $emp): ?>
            <?php
            $id       = (int) $emp['id'];
            $role     = $roleOf($emp['name'], $emp['jobTitle']);
            $isOwner  = $role === 'owner';
            $hasPin   = trim((string) ($emp['pin'] ?? '')) !== '';
            $working  = (int) $emp['active'] === 1 && (int) $emp['resigned'] === 0;
            $canLogin = $hasPin && $working;
            ?>
            <tr class="js-staff">
                <td>
                    <strong><?= $e($emp['name']) ?></strong>
                    <?php if ($id === $meId): ?><span class="muted" style="font-size:11px;"> (you)</span><?php endif; ?>
                </td>
                <td class="muted"><?= $e($emp['code']) ?: '—' ?></td>
                <td><?= $e($emp['jobTitle']) ?: '<span class="muted">—</span>' ?></td>
                <td><span class="role <?= $isOwner ? 'owner' : '' ?>"><?= $role ?></span></td>
                <td>
                    <?php if ($canLogin): ?>
                        <span class="cloud ok">Yes</span>
                    <?php elseif (!$working): ?>
                        <span class="cloud off"><?= (int) $emp['resigned'] === 1 ? 'Resigned' : 'Inactive' ?></span>
                    <?php else: ?>
                        <span class="cloud off">No PIN</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:right;white-space:nowrap;">
                    <a class="btn" href="<?= $basePath ?>/employees/<?= $id ?>/edit">Edit</a>
                    <?php if ($id !== $meId): ?>
                        <form method="post" action="<?= $basePath ?>/employees/<?= $id ?>/delete" class="inline"
                              data-confirm="&quot;<?= $e($emp['name']) ?>&quot; will be removed. Staff with POS history cannot be deleted — mark them Resigned instead."
                              data-confirm-title="Delete staff member?"
                              data-confirm-ok="Delete" data-confirm-danger>
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
