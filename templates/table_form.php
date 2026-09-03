<?php
/** @var string $basePath */
/** @var array|null $table    null = adding, a row = editing */
/** @var array $data */
/** @var array $errors */
/** @var array $sections      section id => label */
/** @var string $roomLabel    'Table' or 'Room' (spa mode) */
$e = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES);
$err = static fn ($k) => isset($errors[$k]) ? '<div class="error">' . htmlspecialchars($errors[$k]) . '</div>' : '';
$editing = $table !== null;
$action  = $editing ? $basePath . '/tables/' . (int) $table['id'] : $basePath . '/tables';
?>
<div class="toolbar">
    <h1><?= $editing ? 'Edit ' . $e($roomLabel) : 'New ' . $e($roomLabel) ?></h1>
    <a class="btn" href="<?= $basePath ?>/tables">← Back</a>
</div>

<div class="card" style="max-width:480px;">
    <form method="post" action="<?= $action ?>">
        <label>Name *</label>
        <input type="text" name="name" value="<?= $e($data['name'] ?? '') ?>" autofocus required>
        <?= $err('name') ?>
        <?php if ($editing): ?>
            <div class="muted" style="font-size:12px;margin-top:4px;">
                Renaming moves existing bookings across — a booking stores the name, not the id.
            </div>
        <?php endif; ?>

        <div class="row">
            <div>
                <label>Capacity *</label>
                <input type="number" name="capacity" min="1" max="99" value="<?= (int) ($data['capacity'] ?? 4) ?>" required>
                <?= $err('capacity') ?>
            </div>
            <div>
                <label>Section *</label>
                <?php $picked = (string) ($data['section_id'] ?? ''); ?>
                <select name="section_id" id="sectionPick">
                    <?php foreach ($sections as $id => $label): ?>
                        <option value="<?= (int) $id ?>" <?= $picked === (string) $id ? 'selected' : '' ?>>
                            <?= $e($label) ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="new" <?= $picked === 'new' ? 'selected' : '' ?>>+ New section…</option>
                </select>
                <?= $err('section_id') ?>
            </div>
        </div>

        <!-- Only relevant once "+ New section…" is picked; the server checks
             the name too, so this staying visible without JS is harmless. -->
        <div class="row" id="newSectionRow" style="<?= $picked === 'new' ? '' : 'display:none;' ?>">
            <div>
                <label>New section name *</label>
                <input type="text" name="new_section" value="<?= $e($data['new_section'] ?? '') ?>"
                       placeholder="e.g. GARDEN">
            </div>
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary"><?= $editing ? 'Save changes' : 'Add ' . $e(strtolower($roomLabel)) ?></button>
            <a class="btn" href="<?= $basePath ?>/tables">Cancel</a>
        </div>
    </form>
</div>

<script>
(function () {
    var pick = document.getElementById('sectionPick');
    var row  = document.getElementById('newSectionRow');
    if (!pick || !row) { return; }
    function sync() {
        var isNew = pick.value === 'new';
        row.style.display = isNew ? '' : 'none';
        var input = row.querySelector('input');
        if (isNew) { input.focus(); } else { input.value = ''; }
    }
    pick.addEventListener('change', sync);
})();
</script>
