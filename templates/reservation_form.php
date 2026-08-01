<?php
/** @var string $basePath */
/** @var string $mode */    // 'create' | 'edit'
/** @var array $data */
/** @var array $errors */
/** @var array $customers */
/** @var array $groups */
/** @var array $therapists */
/** @var array $slotTimes */
/** @var array $statuses */
/** @var string $appType */    // 'resto' | 'spa'
/** @var string $roomLabel */  // 'Table' | 'Room'
$e = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES);
$isEdit = $mode === 'edit';
$id = (int) ($id ?? 0);
$action = $isEdit ? $basePath . '/reservations/' . $id : $basePath . '/reservations';
$selCust = (int) ($data['customer_id'] ?? 0);
$selTable = (string) ($data['tableName'] ?? '');
$selStatus = (int) ($data['status'] ?? 0);
$selTherapist = (int) ($data['therapist_id'] ?? 0);
// Spa bookings take a room AND a therapist; both are locked for the slot.
$isSpa = ($appType ?? 'resto') === 'spa';
$err = static fn ($k) => isset($errors[$k]) ? '<div class="error">' . htmlspecialchars($errors[$k]) . '</div>' : '';
?>
<div class="toolbar">
    <h1><?= $isEdit ? 'Edit Reservation #' . $id : 'New Reservation' ?></h1>
    <a class="btn" href="<?= $basePath ?>/grid?date=<?= $e($data['booking_date'] ?? date('Y-m-d')) ?>">← Back to grid</a>
</div>

<div class="card" style="max-width:640px;">
    <form method="post" action="<?= $action ?>">
        <label>Customer <span class="muted" style="font-weight:400;">(must exist first)</span></label>
        <select name="customer_id" id="customer_id">
            <option value="">— Select existing customer —</option>
            <?php foreach ($customers as $cust): ?>
                <option value="<?= (int) $cust['id'] ?>" <?= $selCust === (int) $cust['id'] ? 'selected' : '' ?>>
                    <?= $e($cust['name']) ?><?= !empty($cust['phone1']) ? ' — ' . $e($cust['phone1']) : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?= $err('customer') ?>

        <details style="margin-top:10px;" <?= !empty($data['new_name']) ? 'open' : '' ?>>
            <summary style="cursor:pointer;font-size:13px;color:var(--link);">+ Add a new customer instead</summary>
            <div class="row" style="margin-top:8px;">
                <div>
                    <label>New customer name</label>
                    <input type="text" name="new_name" value="<?= $e($data['new_name'] ?? '') ?>">
                </div>
                <div>
                    <label>Phone</label>
                    <input type="text" name="new_phone" value="<?= $e($data['new_phone'] ?? '') ?>">
                </div>
                <div>
                    <label>Email</label>
                    <input type="email" name="new_email" value="<?= $e($data['new_email'] ?? '') ?>">
                </div>
            </div>
        </details>

        <label style="margin-top:16px;"><?= $e($roomLabel ?? 'Table') ?></label>
        <select name="tableName" id="tableName">
            <option value="">— Select <?= strtolower($e($roomLabel ?? 'table')) ?> —</option>
            <?php foreach ($groups as $group): ?>
                <optgroup label="<?= $e($group['label']) ?>">
                    <?php foreach ($group['tables'] as $t): ?>
                        <option value="<?= $e($t['name']) ?>" <?= $selTable === (string) $t['name'] ? 'selected' : '' ?>><?= $e($t['name']) ?></option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
        <?= $err('tableName') ?>

        <?php if ($isSpa): ?>
            <label>Therapist</label>
            <select name="therapist_id" id="therapist_id">
                <option value="">— Select therapist —</option>
                <?php foreach ($therapists as $t): ?>
                    <option value="<?= (int) $t['id'] ?>" <?= $selTherapist === (int) $t['id'] ? 'selected' : '' ?>><?= $e($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('therapist_id') ?>
        <?php endif; ?>

        <div class="row" style="margin-top:14px;">
            <div>
                <label>Guests (pax)</label>
                <input type="number" name="cover" min="1" value="<?= $e($data['cover'] ?? 2) ?>" required>
                <?= $err('cover') ?>
            </div>
        </div>

        <div class="row">
            <div>
                <label>Date</label>
                <input type="date" name="booking_date" value="<?= $e($data['booking_date'] ?? '') ?>" required>
                <?= $err('booking_date') ?>
            </div>
            <div>
                <label>Time</label>
                <select name="booking_time" required>
                    <?php foreach ($slotTimes as $tm): ?>
                        <option value="<?= $e($tm) ?>" <?= ($data['booking_time'] ?? '') === $tm ? 'selected' : '' ?>><?= $e($tm) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $err('booking_time') ?>
            </div>
            <div>
                <label>Status</label>
                <select name="status">
                    <?php foreach ($statuses as $val => $label): ?>
                        <option value="<?= (int) $val ?>" <?= $selStatus === (int) $val ? 'selected' : '' ?>><?= $e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <label>Notes</label>
        <textarea name="notes" rows="2"><?= $e($data['notes'] ?? '') ?></textarea>

        <div class="actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save changes' : 'Create reservation' ?></button>
            <a class="btn" href="<?= $basePath ?>/grid?date=<?= $e($data['booking_date'] ?? date('Y-m-d')) ?>">Cancel</a>
        </div>
    </form>
</div>
<script>
/* Turn a <select> into a type-to-filter combobox. The original select stays in
   the DOM (hidden) and keeps holding the value, so the form posts exactly the
   same field and server-side validation/`selected` still work. */
function makeSearchable(select, placeholder){
    if (!select) return;
    var opts = [];
    Array.prototype.forEach.call(select.options, function(o){
        opts.push({
            value: o.value,
            label: o.text.replace(/\s+/g, ' ').trim(),
            group: o.parentNode.tagName === 'OPTGROUP' ? o.parentNode.label : ''
        });
    });

    var wrap = document.createElement('div');
    wrap.className = 'combo';
    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(select);

    var input = document.createElement('input');
    input.type = 'text';
    input.className = 'combo-input';
    input.autocomplete = 'off';
    input.placeholder = placeholder || 'Search…';
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-autocomplete', 'list');

    var list = document.createElement('div');
    list.className = 'combo-list';
    list.setAttribute('role', 'listbox');
    list.hidden = true;

    wrap.appendChild(input);
    wrap.appendChild(list);

    var shown = [];   // options currently rendered
    var active = -1;  // index into `shown`

    function labelFor(value){
        for (var i = 0; i < opts.length; i++) if (opts[i].value === value) return opts[i].label;
        return '';
    }
    function syncInput(){ input.value = select.value === '' ? '' : labelFor(select.value); }

    function render(query){
        var q = query.trim().toLowerCase();
        shown = opts.filter(function(o){
            if (q === '') return true;
            return (o.label + ' ' + o.group).toLowerCase().indexOf(q) !== -1;
        });
        list.innerHTML = '';
        if (!shown.length) {
            var none = document.createElement('div');
            none.className = 'combo-empty';
            none.textContent = 'No match';
            list.appendChild(none);
            active = -1;
            return;
        }
        var lastGroup = null;
        shown.forEach(function(o, i){
            if (o.group !== lastGroup && o.group !== '') {
                var g = document.createElement('div');
                g.className = 'combo-group';
                g.textContent = o.group;
                list.appendChild(g);
            }
            lastGroup = o.group;
            var el = document.createElement('div');
            el.className = 'combo-opt' + (o.value === select.value ? ' sel' : '');
            el.setAttribute('role', 'option');
            el.setAttribute('aria-selected', o.value === select.value ? 'true' : 'false');
            el.textContent = o.label;
            el.addEventListener('mousedown', function(ev){ ev.preventDefault(); choose(i); });
            el.addEventListener('mouseenter', function(){ setActive(i); });
            list.appendChild(el);
        });
        active = shown.findIndex(function(o){ return o.value === select.value; });
        if (active < 0) active = 0;
        paint();
    }

    function items(){ return list.querySelectorAll('.combo-opt'); }
    function paint(){
        var els = items();
        for (var i = 0; i < els.length; i++) els[i].classList.toggle('active', i === active);
        if (active >= 0 && els[active]) {
            var el = els[active];
            if (el.offsetTop < list.scrollTop) list.scrollTop = el.offsetTop;
            else if (el.offsetTop + el.offsetHeight > list.scrollTop + list.clientHeight)
                list.scrollTop = el.offsetTop + el.offsetHeight - list.clientHeight;
        }
    }
    function setActive(i){ active = i; paint(); }

    function open(){ render(''); list.hidden = false; input.setAttribute('aria-expanded', 'true'); }
    function close(){ list.hidden = true; input.setAttribute('aria-expanded', 'false'); syncInput(); }

    function choose(i){
        var o = shown[i];
        if (!o) return;
        select.value = o.value;
        select.dispatchEvent(new Event('change', {bubbles: true}));
        close();
    }

    input.addEventListener('focus', open);
    input.addEventListener('click', function(){ if (list.hidden) open(); });
    input.addEventListener('input', function(){
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        render(input.value);
    });
    input.addEventListener('keydown', function(ev){
        if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
            ev.preventDefault();
            if (list.hidden) { open(); return; }
            if (!shown.length) return;
            setActive((active + (ev.key === 'ArrowDown' ? 1 : shown.length - 1)) % shown.length);
        } else if (ev.key === 'Enter') {
            if (!list.hidden) { ev.preventDefault(); choose(active); }
        } else if (ev.key === 'Escape') {
            if (!list.hidden) { ev.stopPropagation(); close(); }
        }
    });
    input.addEventListener('blur', close);

    syncInput();
}

makeSearchable(document.getElementById('customer_id'), 'Search customer by name or phone…');
makeSearchable(document.getElementById('tableName'), 'Search <?= strtolower($e($roomLabel ?? 'table')) ?>…');
makeSearchable(document.getElementById('therapist_id'), 'Search therapist…');
</script>
