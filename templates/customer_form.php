<?php
/** @var string $basePath */
/** @var array $data */
/** @var array $errors */
$e = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES);
$err = static fn ($k) => isset($errors[$k]) ? '<div class="error">' . htmlspecialchars($errors[$k]) . '</div>' : '';
?>
<div class="toolbar">
    <h1>New Customer</h1>
    <a class="btn" href="<?= $basePath ?>/customers">← Back</a>
</div>

<div class="card" style="max-width:520px;">
    <form method="post" action="<?= $basePath ?>/customers">
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

        <div class="actions">
            <button type="submit" class="btn btn-primary">Create customer</button>
            <a class="btn" href="<?= $basePath ?>/customers">Cancel</a>
        </div>
    </form>
</div>
