<?php
/** @var string $basePath */
/** @var array|null $employee  null = adding, a row = editing */
/** @var array $data */
/** @var array $errors */
$e = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES);
$err = static fn ($k) => isset($errors[$k]) ? '<div class="error">' . htmlspecialchars($errors[$k]) . '</div>' : '';
$editing = $employee !== null;
$action  = $editing ? $basePath . '/employees/' . (int) $employee['id'] : $basePath . '/employees';
$hasPin  = $editing && trim((string) ($employee['pin'] ?? '')) !== '';
?>
<div class="toolbar">
    <h1><?= $editing ? 'Edit Staff' : 'New Staff' ?></h1>
    <a class="btn" href="<?= $basePath ?>/employees">← Back</a>
</div>

<div class="card" style="max-width:560px;">
    <form method="post" action="<?= $action ?>">
        <label>Name *</label>
        <input type="text" name="name" value="<?= $e($data['name'] ?? '') ?>" autofocus required>
        <?= $err('name') ?>
        <div class="muted" style="font-size:12px;margin-top:4px;">Used to sign in, together with the PIN.</div>

        <div class="row">
            <div>
                <label>Code</label>
                <input type="text" name="code" value="<?= $e($data['code'] ?? '') ?>">
                <?= $err('code') ?>
                <div class="muted" style="font-size:12px;margin-top:4px;">Optional. Can be used to sign in instead of the name.</div>
            </div>
            <div>
                <label>Job title</label>
                <input type="text" name="jobTitle" value="<?= $e($data['jobTitle'] ?? '') ?>">
                <div class="muted" style="font-size:12px;margin-top:4px;">
                    Contains <b>manager</b>, <b>owner</b> or <b>admin</b> → full access.<br>
                    Contains <b>therapist</b> → bookable on the grid in spa mode.
                </div>
            </div>
        </div>

        <div class="row">
            <div>
                <label>Phone</label>
                <input type="text" name="phone" value="<?= $e($data['phone'] ?? '') ?>">
            </div>
            <div>
                <label>Email</label>
                <input type="email" name="email" value="<?= $e($data['email'] ?? '') ?>">
            </div>
        </div>

        <label>PIN<?= $editing ? '' : '' ?></label>
        <input type="text" name="pin" inputmode="numeric" autocomplete="off" value="">
        <?= $err('pin') ?>
        <div class="muted" style="font-size:12px;margin-top:4px;">
            <?php if ($editing): ?>
                <?= $hasPin ? 'This person has a PIN. Leave blank to keep it' : 'No PIN set — they cannot sign in. Enter one to give them access' ?>,
                or type a new one to replace it.
            <?php else: ?>
                Leave blank if this person should not sign in (therapists usually don't need to).
            <?php endif; ?>
            4–10 digits.
        </div>

        <div class="row" style="margin-top:16px;">
            <div>
                <label style="display:flex;align-items:center;gap:8px;font-weight:600;">
                    <input type="checkbox" name="active" value="1" style="width:auto;"
                        <?= !empty($data['active']) ? 'checked' : '' ?>> Active
                </label>
            </div>
            <div>
                <label style="display:flex;align-items:center;gap:8px;font-weight:600;">
                    <input type="checkbox" name="resigned" value="1" style="width:auto;"
                        <?= !empty($data['resigned']) ? 'checked' : '' ?>> Resigned
                </label>
            </div>
        </div>
        <div class="muted" style="font-size:12px;margin-top:4px;">
            Inactive or resigned staff cannot sign in and stop appearing as bookable therapists —
            the way to retire someone whose POS history must be kept.
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary"><?= $editing ? 'Save changes' : 'Add staff' ?></button>
            <a class="btn" href="<?= $basePath ?>/employees">Cancel</a>
        </div>
    </form>
</div>
