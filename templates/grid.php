<?php
/** @var string $basePath */
/** @var string $date */
/** @var string $prevDate */
/** @var string $nextDate */
/** @var array $slots */
/** @var array $groups */      // [ ['label'=>, 'kind'=>'table'|'therapist', 'tables'=>[ rows ]] ]
/** @var string $appType */    // 'resto' | 'spa'
/** @var string $roomLabel */  // 'Table' | 'Room'
/** @var array $sectionIds */
/** @var array $sectionLabels */
/** @var int|null $section */
/** @var array $placed */      // rowKey => [slotIndex => ['res'=>reservation,'span'=>int]]
/** @var array $totals */
$e = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES);
$slotCount = count($slots);
$colCount = $slotCount + 1;
$prettyDate = date('l, d M Y', strtotime($date));
$isSpa = ($appType ?? 'resto') === 'spa';
// Section filter is kept across date navigation.
$navSuffix = $section ? '&section=' . (int) $section : '';
$jsSuffix = $section ? " + '&section=" . (int) $section . "'" : '';
?>
<style>
    .gridwrap{overflow:auto;max-height:72vh;border:1px solid var(--line);border-radius:10px;background:#fff;}
    table.grid{border-collapse:separate;border-spacing:0;font-size:12px;}
    table.grid th,table.grid td{border-right:1px solid var(--line);border-bottom:1px solid var(--line);}
    table.grid thead th{position:sticky;top:0;z-index:5;background:#f8fafc;color:var(--muted);font-weight:700;
        padding:8px 6px;min-width:64px;text-align:center;white-space:nowrap;}
    table.grid thead th.corner{left:0;z-index:6;min-width:110px;text-align:left;padding-left:12px;}
    table.grid th.rowhead{position:sticky;left:0;z-index:4;background:#fff;text-align:left;padding:6px 12px;
        min-width:110px;font-weight:700;color:var(--ink);white-space:nowrap;}
    table.grid th.half{color:#b7c0cb;font-weight:600;}
    tr.sectionrow td{position:sticky;left:0;background:#eef2f6;color:#334155;font-weight:800;
        text-transform:uppercase;letter-spacing:.04em;font-size:11px;padding:6px 12px;}
    td.cell{padding:0;height:34px;}
    td.cell a.empty{display:block;height:100%;width:100%;min-height:34px;}
    td.cell a.empty:hover{background:#fff8e6;text-decoration:none;}
    .resblock{display:block;height:100%;min-height:34px;padding:4px 6px;line-height:1.15;text-decoration:none;color:var(--ink);
        border-left:3px solid var(--booked-bd);background:var(--booked);}
    .resblock.s1{background:var(--arrived);border-left-color:var(--arrived-bd);}
    .resblock.s2{background:var(--cancel);border-left-color:var(--cancel-bd);}
    .resblock:hover{filter:brightness(.97);text-decoration:none;}
    .resblock b{display:block;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:120px;}
    .resblock small{color:var(--muted);}
    .segbtn{padding:6px 12px;border:1px solid var(--line);background:#fff;border-radius:8px;font-weight:600;font-size:13px;color:var(--ink);}
    .segbtn.on{background:var(--accent);border-color:var(--accent);}
    .datenav{display:flex;align-items:center;gap:8px;}
    .datenav .date{border:1px solid var(--accent);border-radius:8px;padding:8px 16px;font-weight:700;min-width:200px;text-align:center;}
    .totbar{display:flex;gap:22px;flex-wrap:wrap;margin-top:12px;color:var(--muted);font-size:14px;}
    .totbar b{color:var(--ink);}
    .qsbox{display:flex;align-items:center;gap:10px;}
    .qsbox input{width:230px;padding:8px 11px;}
    .qs-dim{opacity:.25;filter:grayscale(.7);}
    .resblock.qs-hit{outline:2px solid var(--link);outline-offset:-2px;}
    .qs-count{font-size:13px;color:var(--muted);white-space:nowrap;}
    .qs-count.none{color:var(--danger);font-weight:600;}
</style>

<div class="toolbar">
    <h1><?= $isSpa ? 'Rooms &amp; therapists' : 'Tables' ?></h1>
    <a class="btn btn-primary" href="<?= $basePath ?>/reservations/new?date=<?= $e($date) ?>">+ New reservation</a>
</div>

<div class="toolbar" style="margin-top:-6px;">
    <div class="datenav">
        <a class="segbtn" href="<?= $basePath ?>/grid?date=<?= $e($prevDate) ?><?= $navSuffix ?>">‹</a>
        <label class="date" style="position:relative;cursor:pointer;" title="Pick a date">
            <?= $e($prettyDate) ?>
            <input type="date" value="<?= $e($date) ?>"
                   onclick="this.showPicker&&this.showPicker()"
                   onchange="location.href='<?= $basePath ?>/grid?date='+this.value<?= $jsSuffix ?>"
                   style="position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer;border:0;padding:0;">
        </label>
        <a class="segbtn" href="<?= $basePath ?>/grid?date=<?= $e($nextDate) ?><?= $navSuffix ?>">›</a>
        <a class="segbtn" href="<?= $basePath ?>/grid?date=<?= date('Y-m-d') ?><?= $navSuffix ?>">Today</a>
    </div>

    <div class="qsbox">
        <input type="search" id="qsearch" autocomplete="off" placeholder="Quick search customer…"
               title="Searches only the bookings shown for <?= $e($prettyDate) ?>">
        <span class="qs-count" id="qsCount"></span>
    </div>
</div>

<div class="toolbar" style="margin-top:-6px;">
    <div style="display:flex;gap:6px;flex-wrap:wrap;">
        <a class="segbtn <?= $section === null ? 'on' : '' ?>" href="<?= $basePath ?>/grid?date=<?= $e($date) ?>">All sections</a>
        <?php foreach ($sectionIds as $sid): ?>
            <a class="segbtn <?= $section === $sid ? 'on' : '' ?>" href="<?= $basePath ?>/grid?date=<?= urlencode($date) ?>&section=<?= $sid ?>"><?= $e($sectionLabels[$sid] ?? ('Section ' . $sid)) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="gridwrap">
    <table class="grid">
        <thead>
            <tr>
                <th class="corner"><?= $e($roomLabel ?? 'Table') ?></th>
                <?php foreach ($slots as $s): ?>
                    <th class="<?= $s['isHour'] ? '' : 'half' ?>"><?= $s['isHour'] ? $e($s['label']) : '' ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
        <?php if ($groups === [] || array_sum(array_map(fn ($g) => count($g['tables']), $groups)) === 0): ?>
            <tr><td class="rowhead">—</td><td colspan="<?= $slotCount ?>" class="muted" style="padding:12px;">
                No bookable rows. Add rows to tbl_tables<?= $isSpa ? ', and employees with jobTitle THERAPIST' : '' ?>.
            </td></tr>
        <?php endif; ?>
        <?php foreach ($groups as $group): ?>
            <?php if (count($group['tables']) === 0) { continue; } ?>
            <tr class="sectionrow"><td colspan="<?= $colCount ?>"><?= $e($group['label']) ?></td></tr>
            <?php foreach ($group['tables'] as $t): ?>
                <?php
                    // Therapist rows key on employee id, resource rows on name;
                    // the prefix keeps room "1" and therapist #1 apart.
                    $isTherRow = ($group['kind'] ?? 'table') === 'therapist';
                    $rowName = (string) $t['name'];
                    $rowKey  = $isTherRow ? 'e:' . (int) $t['id'] : 't:' . $rowName;
                    $newLink = $basePath . '/reservations/new?'
                        . ($isTherRow ? 'therapist=' . (int) $t['id'] : 'table=' . urlencode($rowName));
                    $map = $placed[$rowKey] ?? [];
                ?>
                <tr>
                    <th class="rowhead"><?= $e($rowName) ?></th>
                    <?php
                        // A multi-slot booking is drawn once with a colspan; $skip
                        // swallows the columns it covers so the row keeps its width.
                        $skip = 0;
                    ?>
                    <?php foreach ($slots as $s): $idx = $s['index']; ?>
                        <?php if ($skip > 0) { $skip--; continue; } ?>
                        <?php if (isset($map[$idx])):
                            $r = $map[$idx]['res'];
                            $st = (int) $r['status'];
                            // Never run past the last column, whatever the stored duration.
                            $span = max(1, min((int) $map[$idx]['span'], $slotCount - $idx));
                            $skip = $span - 1;
                            // "19:00–21:00" for a booking longer than one slot.
                            $startHHMM = substr((string) $r['reservationTime'], 11, 5);
                            $endHHMM = date('H:i', strtotime((string) $r['reservationTime'])
                                + ((int) ($r['duration_minutes'] ?: 0) ?: 0) * 60);
                            $timeLabel = ((int) ($r['duration_minutes'] ?? 0) > 0 && $span > 1)
                                ? $startHHMM . '–' . $endHHMM
                                : $startHHMM;
                        ?>
                            <td class="cell" colspan="<?= $span ?>">
                                <?php
                                    // On a room row show who serves it; on a therapist
                                    // row show which room — so each cell names the pair.
                                    $pair = $isTherRow
                                        ? (string) ($r['tableName'] ?? '')
                                        : (string) ($isSpa ? ($r['served_by_name'] ?? '') : '');
                                    $sub = $pair !== '' ? $pair . ' · ' . (int) $r['cover'] . ' pax'
                                                        : (int) $r['cover'] . ' pax';
                                    if ($span > 1) {
                                        $sub = $timeLabel . ' · ' . $sub;
                                    }
                                ?>
                                <a class="resblock s<?= $st ?>" href="<?= $basePath ?>/reservations/<?= (int) $r['id'] ?>"
                                   data-res="<?= (int) $r['id'] ?>"
                                   data-name="<?= $e($r['customer_name'] ?? $r['name']) ?>"
                                   title="<?= $e($r['customer_name'] ?? $r['name']) ?> — <?= $e($timeLabel) ?><?= $pair !== '' ? ' — ' . $e($pair) : '' ?>">
                                    <b><?= $e($r['customer_name'] ?? $r['name'] ?: 'Guest') ?></b>
                                    <small><?= $e($sub) ?></small>
                                </a>
                            </td>
                        <?php else: ?>
                            <td class="cell">
                                <a class="empty" href="<?= $newLink ?>&date=<?= $e($date) ?>&time=<?= $e($s['time']) ?>"></a>
                            </td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="totbar">
    <span>Total Guests: <b><?= (int) $totals['guests'] ?></b></span>
    <span>Total Reservations: <b><?= (int) $totals['reservations'] ?></b></span>
    <span>Open (booked): <b><?= (int) $totals['open'] ?></b></span>
</div>

<script>
/* Quick search — filters only the bookings already on this page, i.e. the ones
   for the date shown above. Use the date arrows/picker to search another day. */
(function () {
    var box = document.getElementById('qsearch');
    var count = document.getElementById('qsCount');
    if (!box) return;
    var blocks = Array.prototype.slice.call(document.querySelectorAll('.resblock'));
    var ids = {};
    blocks.forEach(function (b) { ids[b.getAttribute('data-res')] = 1; });
    var total = Object.keys(ids).length;

    function apply() {
        var q = box.value.trim().toLowerCase();
        if (q === '') {
            blocks.forEach(function (b) { b.classList.remove('qs-hit', 'qs-dim'); });
            count.textContent = '';
            count.classList.remove('none');
            return;
        }
        var hits = [], hitIds = {};
        blocks.forEach(function (b) {
            var hit = (b.getAttribute('data-name') || '').toLowerCase().indexOf(q) !== -1;
            b.classList.toggle('qs-hit', hit);
            b.classList.toggle('qs-dim', !hit);
            if (hit) { hits.push(b); hitIds[b.getAttribute('data-res')] = 1; }
        });
        // In spa mode one booking occupies two cells (room + therapist), so
        // count distinct reservations rather than highlighted blocks.
        var found = Object.keys(hitIds).length;
        count.textContent = found + ' of ' + total + ' booking' + (total === 1 ? '' : 's');
        count.classList.toggle('none', hits.length === 0);
        if (hits.length) hits[0].scrollIntoView({block: 'nearest', inline: 'center'});
    }

    box.addEventListener('input', apply);
    box.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') { box.value = ''; apply(); }
    });
})();
</script>
