<?php
/** @var string $basePath */
/** @var int $year */
/** @var int $month */
/** @var string $monthStr */
/** @var string $prevMonth */
/** @var string $nextMonth */
/** @var array $counts */  // 'Y-m-d' => count
$e = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES);
$first = strtotime(sprintf('%04d-%02d-01', $year, $month));
$daysIn = (int) date('t', $first);
$startDow = (int) date('w', $first); // 0=Sun
$monthName = date('F Y', $first);
$todayStr = date('Y-m-d');
$cells = [];
for ($i = 0; $i < $startDow; $i++) { $cells[] = null; }
for ($d = 1; $d <= $daysIn; $d++) { $cells[] = $d; }
while (count($cells) % 7 !== 0) { $cells[] = null; }
$dows = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
?>
<style>
    .cal{width:100%;border-collapse:collapse;table-layout:fixed;background:#fff;border:1px solid var(--line);border-radius:10px;overflow:hidden;}
    .cal th{background:#f8fafc;color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:.04em;padding:8px;border:1px solid var(--line);}
    .cal td{border:1px solid var(--line);height:96px;vertical-align:top;padding:0;}
    .cal td a{display:block;height:100%;padding:8px;color:var(--ink);}
    .cal td a:hover{background:#fff8e6;text-decoration:none;}
    .cal .daynum{font-weight:700;font-size:13px;}
    .cal .empty{background:#fafbfc;}
    .cal .today .daynum{color:var(--accent);}
    .cal .cnt{margin-top:8px;display:inline-block;background:var(--accent);color:#1f2933;font-weight:700;font-size:12px;padding:3px 9px;border-radius:999px;}
    .cal .none{margin-top:8px;color:#c3ccd6;font-size:12px;}
</style>

<div class="toolbar">
    <div class="datenav" style="display:flex;gap:8px;align-items:center;">
        <a class="btn" href="<?= $basePath ?>/calendar?month=<?= $e($prevMonth) ?>">‹</a>
        <h1 style="min-width:200px;text-align:center;position:relative;cursor:pointer;" title="Pick month & year">
            <?= $e($monthName) ?>
            <input type="month" value="<?= $e($monthStr) ?>"
                   onclick="this.showPicker&&this.showPicker()"
                   onchange="location.href='<?= $basePath ?>/calendar?month='+this.value"
                   style="position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer;border:0;padding:0;">
        </h1>
        <a class="btn" href="<?= $basePath ?>/calendar?month=<?= $e($nextMonth) ?>">›</a>
    </div>
    <a class="btn" href="<?= $basePath ?>/calendar?month=<?= date('Y-m') ?>">This month</a>
</div>

<table class="cal">
    <thead>
        <tr><?php foreach ($dows as $dn): ?><th><?= $dn ?></th><?php endforeach; ?></tr>
    </thead>
    <tbody>
        <?php foreach (array_chunk($cells, 7) as $week): ?>
            <tr>
                <?php foreach ($week as $d): ?>
                    <?php if ($d === null): ?>
                        <td class="empty"></td>
                    <?php else:
                        $ymd = sprintf('%04d-%02d-%02d', $year, $month, $d);
                        $cnt = $counts[$ymd] ?? 0;
                        $isToday = $ymd === $todayStr; ?>
                        <td class="<?= $isToday ? 'today' : '' ?>">
                            <a href="<?= $basePath ?>/grid?date=<?= $ymd ?>">
                                <span class="daynum"><?= $d ?></span>
                                <?php if ($cnt > 0): ?>
                                    <span class="cnt"><?= (int) $cnt ?> booking<?= $cnt > 1 ? 's' : '' ?></span>
                                <?php else: ?>
                                    <span class="none">—</span>
                                <?php endif; ?>
                            </a>
                        </td>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
