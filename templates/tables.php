<?php
/** @var string $basePath */
/** @var array $groups */
$e = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES);
?>
<div class="toolbar">
    <h1>Tables</h1>
    <span class="muted" style="font-size:13px;">Read-only view of the POS floor tables.</span>
</div>

<?php foreach ($groups as $group): ?>
    <div class="card">
        <h1 style="font-size:15px;margin-bottom:10px;"><?= $e($group['label']) ?> <span class="muted" style="font-weight:400;">(<?= count($group['tables']) ?>)</span></h1>
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
            <?php foreach ($group['tables'] as $t): ?>
                <span class="segbtn" style="cursor:default;"><?= $e($t['name']) ?> <span class="muted" style="font-size:11px;">· <?= (int) $t['capacity'] ?> pax</span></span>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>
<style>.segbtn{padding:6px 12px;border:1px solid var(--line);background:#fff;border-radius:8px;font-weight:600;font-size:13px;color:var(--ink);}</style>
