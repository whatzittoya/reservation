<?php
/** @var string $basePath */
/** @var array $data */
/** @var array $errors */
/** @var array|null $customer  null = creating, a row = editing */
$e = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES);
$err = static fn ($k) => isset($errors[$k]) ? '<div class="error">' . htmlspecialchars($errors[$k]) . '</div>' : '';
$editing = $customer !== null;
$action  = $editing ? $basePath . '/customers/' . (int) $customer['id'] : $basePath . '/customers';
$back    = $editing ? $basePath . '/customers/' . (int) $customer['id'] : $basePath . '/customers';
?>
<div class="toolbar">
    <h1><?= $editing ? 'Edit Customer' : 'New Customer' ?></h1>
    <a class="btn" href="<?= $back ?>">← Back</a>
</div>

<div class="card" style="max-width:520px;">
    <form method="post" action="<?= $action ?>">
        <label>Name *</label>
        <input type="text" name="name" value="<?= $e($data['name'] ?? '') ?>" autofocus required>
        <?= $err('name') ?>

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

        <label>Notes</label>
        <textarea name="notes" rows="2"><?= $e($data['notes'] ?? '') ?></textarea>
        <div class="muted" style="font-size:12px;margin-top:4px;">Stays on this server — the cloud has no notes field.</div>

        <div class="actions">
            <button type="submit" class="btn btn-primary"><?= $editing ? 'Save changes' : 'Create customer' ?></button>
            <a class="btn" href="<?= $back ?>">Cancel</a>
        </div>
    </form>
</div>
