<?php
/** @var string $content */
/** @var string $basePath */
/** @var string $title */
/** @var \App\Auth $auth */
$user = $auth->user();
$e = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES);
$nav = static function (string $href, string $label, string $current) use ($basePath, $e) {
    $active = str_starts_with($current, $href) ? ' active' : '';
    echo '<a class="navlink' . $active . '" href="' . $basePath . $href . '">' . $e($label) . '</a>';
};
$path = $_SERVER['REQUEST_URI'] ?? '';
$rel = $basePath !== '' && str_starts_with($path, $basePath) ? substr($path, strlen($basePath)) : $path;
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($title ?? 'Reservation') ?></title>
    <style>
        :root{
            --bg:#f4f6f8; --card:#fff; --line:#e2e6ea; --ink:#1f2933; --muted:#7b8794;
            --accent:#f5a623; --accent-ink:#1f2933; --link:#2563eb;
            --booked:#fff4d6; --booked-ink:#8a5a00; --booked-bd:#f0c150;
            --arrived:#dcfce7; --arrived-ink:#166534; --arrived-bd:#86e0a3;
            --cancel:#fee2e2; --cancel-ink:#991b1b; --cancel-bd:#f3a3a3;
            --danger:#dc2626;
        }
        *{box-sizing:border-box;}
        body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:var(--bg);color:var(--ink);}
        a{color:var(--link);text-decoration:none;}
        a:hover{text-decoration:underline;}
        header{background:var(--card);border-bottom:1px solid var(--line);position:sticky;top:0;z-index:20;}
        .bar{max-width:1180px;margin:0 auto;padding:0 18px;height:56px;display:flex;align-items:center;gap:18px;}
        .brand{font-weight:800;color:var(--ink);font-size:16px;white-space:nowrap;}
        .brand span{color:var(--accent);}
        nav.main{display:flex;gap:4px;flex:1;flex-wrap:wrap;}
        .navlink{padding:7px 12px;border-radius:8px;color:var(--ink);font-size:14px;font-weight:600;}
        .navlink:hover{background:#f0f2f5;text-decoration:none;}
        .navlink.active{background:var(--accent);color:var(--accent-ink);}
        .userbox{display:flex;align-items:center;gap:10px;font-size:13px;color:var(--muted);white-space:nowrap;}
        .role{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;background:#eef2ff;color:#3730a3;}
        .role.owner{background:#fef3c7;color:#8a5a00;}
        main{max-width:1180px;margin:0 auto;padding:22px 18px 60px;}
        .card{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:18px;margin-bottom:16px;}
        .btn{display:inline-block;padding:8px 14px;border-radius:8px;border:1px solid var(--line);background:var(--card);color:var(--ink);cursor:pointer;font-size:14px;font-weight:600;}
        .btn-primary{background:var(--accent);border-color:var(--accent);color:var(--accent-ink);}
        .btn-danger{color:var(--danger);border-color:#f3a3a3;background:#fff;}
        .btn:hover{opacity:.92;text-decoration:none;}
        .toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;}
        h1{font-size:20px;margin:0;}
        table.list{width:100%;border-collapse:collapse;}
        table.list th,table.list td{text-align:left;padding:9px 12px;border-bottom:1px solid var(--line);font-size:14px;}
        table.list th{color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:.03em;}
        .muted{color:var(--muted);}
        .flash{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:9px 13px;border-radius:8px;margin-bottom:14px;}
        .badge{display:inline-block;padding:2px 9px;border-radius:999px;font-size:12px;font-weight:700;}
        .badge.s0{background:var(--booked);color:var(--booked-ink);}
        .badge.s1{background:var(--arrived);color:var(--arrived-ink);}
        .badge.s2{background:var(--cancel);color:var(--cancel-ink);}
        label{display:block;font-size:13px;font-weight:600;margin:12px 0 5px;}
        input,select,textarea{width:100%;padding:9px 11px;border:1px solid var(--line);border-radius:8px;font-size:14px;font-family:inherit;background:#fff;}
        .row{display:flex;gap:14px;flex-wrap:wrap;}
        .row>div{flex:1;min-width:150px;}
        .error{color:var(--danger);font-size:12px;margin-top:4px;}
        .actions{margin-top:20px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;}
        .inline{display:inline;}
        /* searchable select (progressive enhancement — see makeSearchable()) */
        .combo{position:relative;}
        .combo>select{display:none;}
        .combo-list{position:absolute;z-index:30;left:0;right:0;top:calc(100% + 4px);max-height:260px;overflow:auto;padding:4px;background:var(--card);border:1px solid var(--line);border-radius:8px;box-shadow:0 8px 24px rgba(31,41,51,.14);}
        .combo-list[hidden]{display:none;}
        .combo-group{padding:8px 10px 3px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;color:var(--muted);}
        .combo-opt{padding:7px 10px;border-radius:6px;font-size:14px;cursor:pointer;}
        .combo-opt.sel{background:var(--booked);color:var(--booked-ink);font-weight:700;}
        .combo-opt.active{background:var(--accent);color:var(--accent-ink);}
        .combo-empty{padding:9px 10px;font-size:13px;color:var(--muted);}
        /* confirm dialog (any form with data-confirm) */
        dialog.dlg{width:min(420px,calc(100vw - 32px));padding:0;border:1px solid var(--line);border-radius:12px;box-shadow:0 20px 48px rgba(31,41,51,.22);color:var(--ink);background:var(--card);}
        dialog.dlg::backdrop{background:rgba(31,41,51,.42);}
        .dlg-body{padding:20px 20px 0;}
        .dlg-body h2{margin:0 0 8px;font-size:17px;}
        .dlg-body p{margin:0;font-size:14px;line-height:1.5;color:var(--muted);}
        .dlg-actions{display:flex;justify-content:flex-end;gap:10px;padding:18px 20px 20px;}
        /* brand doubles as the link to the search page */
        .brand:hover{text-decoration:none;opacity:.85;}
    </style>
</head>
<body>
<?php if ($user): ?>
    <header>
        <div class="bar">
            <a class="brand" href="<?= $basePath ?>/search" title="Search bookings &amp; customers">Quinos <span>Reservations</span></a>
            <nav class="main">
                <?php $nav('/grid', 'Grid', $rel); ?>
                <?php $nav('/calendar', 'Calendar', $rel); ?>
                <?php $nav('/customers', 'Customers', $rel); ?>
                <?php if ($auth->isOwner()): ?>
                    <?php $nav('/reports', 'Reports', $rel); ?>
                    <?php $nav('/tables', 'Tables', $rel); ?>
                    <?php $nav('/employees', 'Staff', $rel); ?>
                <?php endif; ?>
            </nav>
            <div class="userbox">
                <span><?= $e($user['name']) ?></span>
                <span class="role <?= $auth->isOwner() ? 'owner' : '' ?>"><?= $e($user['role']) ?></span>
                <a href="<?= $basePath ?>/logout">Logout</a>
            </div>
        </div>
    </header>
<?php endif; ?>
    <main>
        <?= $content ?>
    </main>

    <dialog class="dlg" id="confirmDlg">
        <form method="dialog">
            <div class="dlg-body">
                <h2 id="confirmDlgTitle">Are you sure?</h2>
                <p id="confirmDlgText"></p>
            </div>
            <div class="dlg-actions">
                <button class="btn" value="" autofocus>Keep it</button>
                <button class="btn btn-primary" id="confirmDlgOk" value="ok">Confirm</button>
            </div>
        </form>
    </dialog>
    <script>
    /* Any <form data-confirm="…"> asks first, in a real modal dialog.
       Optional: data-confirm-title, data-confirm-ok (button label),
       data-confirm-danger (styles the confirm button red). */
    (function () {
        var dlg = document.getElementById('confirmDlg');
        var okBtn = document.getElementById('confirmDlgOk');
        var titleEl = document.getElementById('confirmDlgTitle');
        var textEl = document.getElementById('confirmDlgText');

        document.addEventListener('submit', function (ev) {
            var form = ev.target;
            if (!(form instanceof HTMLFormElement)) return;
            var msg = form.getAttribute('data-confirm');
            if (!msg || form.dataset.confirmed === '1') return;
            ev.preventDefault();

            if (!dlg || typeof dlg.showModal !== 'function') {   // very old browser
                if (window.confirm(msg)) { form.dataset.confirmed = '1'; form.submit(); }
                return;
            }

            titleEl.textContent = form.getAttribute('data-confirm-title') || 'Are you sure?';
            textEl.textContent = msg;
            okBtn.textContent = form.getAttribute('data-confirm-ok') || 'Confirm';
            okBtn.classList.toggle('btn-danger', form.hasAttribute('data-confirm-danger'));
            okBtn.classList.toggle('btn-primary', !form.hasAttribute('data-confirm-danger'));

            dlg.addEventListener('close', function onClose() {
                dlg.removeEventListener('close', onClose);
                if (dlg.returnValue !== 'ok') return;
                form.dataset.confirmed = '1';
                if (typeof form.requestSubmit === 'function') form.requestSubmit();
                else form.submit();
            });
            dlg.returnValue = '';
            dlg.showModal();
        });
    })();
    </script>
</body>
</html>
