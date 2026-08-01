<?php
/** @var string $basePath */
/** @var string|null $error */
$e = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES);
?>
<div style="max-width:360px;margin:8vh auto 0;">
    <div style="text-align:center;margin-bottom:18px;">
        <div style="font-weight:800;font-size:22px;">Quinos <span style="color:var(--accent);">Reservations</span></div>
        <div class="muted" style="font-size:13px;margin-top:4px;">Staff sign in</div>
    </div>
    <div class="card">
        <?php if (!empty($error)): ?>
            <div class="error" style="margin-bottom:8px;"><?= $e($error) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= $basePath ?>/login">
            <label>Name or code</label>
            <input type="text" name="username" value="<?= $e($username ?? '') ?>" autofocus required>
            <label>PIN</label>
            <input type="password" name="pin" required>
            <div class="actions">
                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Sign in</button>
            </div>
        </form>
    </div>
    <p class="muted" style="text-align:center;font-size:12px;">Use your employee name/code and PIN.</p>
</div>
