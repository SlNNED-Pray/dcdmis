<?php
require_once(__DIR__ . '/navigation.php');
require_once(root() . '/ims/helpers.php');

$pdo = connection();
$employees = $pdo->query("SELECT id, CONCAT(last_name, ', ', first_name, IFNULL(CONCAT(' ', middle_name), ''), IFNULL(CONCAT(' ', name_extension), '')) AS name FROM employees ORDER BY last_name, first_name")->fetchAll();
$functionalDivisions = $pdo->query("SELECT id, name FROM functional_divisions ORDER BY name")->fetchAll();
$items = $pdo->query('SELECT id, stock_no, description, unit, quantity, min_qty, pcs_per_unit, total_units FROM items ORDER BY description')->fetchAll();
$itemUnits = $pdo->query('SELECT item_id, unit, pcs_per_unit FROM item_units ORDER BY item_id')->fetchAll();
$unitOrder = ['bottle','gallon','piece','unit','pack','can','pouch','bundle','roll','tube','pad','box','ream','book','cart','license','ticket','lot','set'];
$unitsMap = [];
foreach ($itemUnits as $iu) {
    $unitsMap[(int) $iu['item_id']][] = [
        'unit' => $iu['unit'],
        'pcs' => max((int) $iu['pcs_per_unit'], 1),
    ];
}
foreach ($unitsMap as $imid => &$units) {
    usort($units, static function ($a, $b) use ($unitOrder) {
        $pa = array_search(strtolower($a['unit']), $unitOrder, true);
        $pb = array_search(strtolower($b['unit']), $unitOrder, true);
        return ($pa === false ? 999 : $pa) <=> ($pb === false ? 999 : $pb);
    });
}
unset($units);
$itemJson = json_encode(array_map(static function ($item) use ($unitsMap) {
    $units = $unitsMap[(int) $item['id']] ?? [['unit' => $item['unit'], 'pcs' => max((int) $item['pcs_per_unit'], 1)]];
    $onHand = (int) $item['quantity'];
    foreach ($units as &$u) {
        $pcs = max((int) $u['pcs'], 1);
        $u['avail'] = $pcs > 0 ? (int) floor($onHand / $pcs) : 0;
    }
    unset($u);
    return [
        'id' => (int) $item['id'],
        'stock_no' => $item['stock_no'],
        'description' => $item['description'],
        'stock' => $item['stock_no'] . ' - ' . $item['description'],
        'unit' => $item['unit'],
        'units' => $units,
        'quantity' => $onHand,
        'min_qty' => (int) $item['min_qty'],
        'pcs_per_unit' => (int) $item['pcs_per_unit'],
        'total_units' => (int) $item['total_units'],
    ];
}, $items), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$employeeJson = json_encode(array_map(static function ($e) {
    $dinfo = risDivisionOfficeFromEmployee((int) $e['id']);
    return ['id' => (int) $e['id'], 'name' => $e['name'], 'division' => $dinfo['division'], 'office' => $dinfo['office']];
}, $employees), JSON_UNESCAPED_UNICODE);
$selectedEmployee = (int) ($_POST['employee_id'] ?? 0);
$fromItemId = (int) (decode($_GET['id'] ?? '') ?: 0);
$selectedItems = $_POST['item_id'] ?? [];
if (!is_array($selectedItems)) {
    $selectedItems = [$selectedItems];
}
if (!empty($selectedItems)) {
    $selectedItems = array_values(array_filter($selectedItems, static fn ($v) => (int) $v > 0));
}
if (empty($selectedItems) && $fromItemId > 0) {
    $selectedItems = [$fromItemId];
}
$quantities = $_POST['quantity'] ?? [];
if (!is_array($quantities)) {
    $quantities = [$quantities];
}
$purpose = trim($_POST['purpose'] ?? '');
$openModal = isset($_POST['save-ris']) || $url === 'Create RIS';
?>
<div class="modal fade" id="risModal" tabindex="-1" role="dialog" aria-labelledby="risModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<form method="post">
				<?= csrf_field(); ?>
				<div class="modal-header">
					<h5 class="modal-title" id="risModalLabel"><i class="fas fa-file-invoice"></i> New requisition slip</h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				</div>
				<div class="modal-body">
<?php
$selectedEmployeeName = '';
if ($selectedEmployee > 0) {
    foreach ($employees as $emp) { if ((int) $emp['id'] === $selectedEmployee) { $selectedEmployeeName = $emp['name']; break; } }
}
?>
<div class="form-group" style="position:relative;">
    <label>Requesting employee</label>
    <input class="form-control emp-search" type="text" autocomplete="off" placeholder="Type name to search" value="<?= e($selectedEmployeeName) ?>" required>
    <input type="hidden" name="employee_id" class="emp-id" value="<?= $selectedEmployee ?>">
    <ul class="emp-suggestions dropdown-menu" style="display:none;left:0;right:0;top:100%;position:absolute;max-height:220px;overflow:auto;z-index:1000;"></ul>
</div>
<?php $selectedDivision = trim((string) ($_POST['division'] ?? '')); ?>
<?php $employeeDinfo = $selectedEmployee > 0 ? risDivisionOfficeFromEmployee($selectedEmployee) : ['division' => '', 'office' => '']; ?>
<?php if ($selectedDivision === '' && !empty($employeeDinfo['division'])) { $selectedDivision = $employeeDinfo['division']; } ?>
<?php $prefillOffice = $employeeDinfo['office']; ?>
<div class="form-row">
    <div class="form-group col-md-6">
        <label>Division</label>
        <input class="form-control" type="text" name="division" value="<?= e($selectedDivision) ?>" readonly required placeholder=" ">
    </div>
    <div class="form-group col-md-6">
        <label>Office</label>
        <input class="form-control ris-office-display" value="<?= e($prefillOffice) ?>" readonly>
    </div>
</div>

<div class="form-group"><label>Items</label>
	<div id="ris-items">
	<?php $count = max(1, count($selectedItems)); ?>
	<?php for ($i = 0; $i < $count; $i++): ?>
	<?php $savedQty = is_array($quantities) && isset($quantities[$i]) ? (int) $quantities[$i] : 0; ?>
	<?php $selectedRowId = $i < count($selectedItems) ? (int) $selectedItems[$i] : 0; ?>
	<?php $selectedText = ''; ?>
	<?php $selectedUnit = ''; ?>
	<?php $rowUnits = []; ?>
	<?php $rowOnHand = 0; ?>
	<?php $rowBaseUnit = ''; ?>
	<?php if ($selectedRowId > 0): foreach ($items as $item) { if ((int) $item['id'] === $selectedRowId) { $selectedText = e($item['stock_no'] . ' - ' . $item['description']); $rowUnits = $unitsMap[$selectedRowId] ?? [['unit' => $item['unit'], 'pcs' => max((int) $item['pcs_per_unit'], 1)]]; $rowOnHand = (int) $item['quantity']; $rowBaseUnit = strtolower(trim((string) $item['unit'])); break; } } endif; ?>
	<?php foreach ($rowUnits as &$rum) { if (!isset($rum['avail'])) { $rum['avail'] = ($rowOnHand > 0 && max((int) $rum['pcs'],1) > 0) ? (int) floor($rowOnHand / max((int) $rum['pcs'],1)) : 0; } } unset($rum); ?>
	<?php $submittedUnits = (array) ($_POST['unit'] ?? []); ?>
	<?php $submittedUnitPcs = (array) ($_POST['unit_pcs'] ?? []); ?>
	<?php $defaultUnit = $rowBaseUnit !== '' ? $rowBaseUnit : (isset($rowUnits[0]['unit']) ? strtolower($rowUnits[0]['unit']) : ''); ?>
	<?php $selectedUnit = isset($submittedUnits[$i]) ? strtolower(trim((string) $submittedUnits[$i])) : $defaultUnit; ?>
	<?php $selectedPcs = 1; ?>
	<?php $selectedAvail = 0; ?>
	<?php foreach ($rowUnits as $rum) { if (strtolower($rum['unit']) === $selectedUnit) { $selectedPcs = (int) $rum['pcs']; $selectedAvail = (int) $rum['avail']; break; } } ?>
	<div class="ris-item-row mb-2 p-2 border rounded" style="position:relative;" data-onhand="<?= (int) $rowOnHand ?>">
		<div class="d-flex align-items-center" style="gap:8px;">
			<input class="form-control item-search" type="text" autocomplete="off" placeholder="Type stock no. or description to search" value="<?= $selectedText ?>" <?= $i === 0 ? 'required' : '' ?>>
			<input type="hidden" class="item-id" name="item_id[]" value="<?= $selectedRowId ?>">
			<button type="button" class="btn btn-outline-danger btn-sm remove-item" title="Remove item"><i class="fas fa-times"></i></button>
		</div>
		<small class="item-avail text-muted d-block mt-1 mb-1"><?php if ($selectedRowId > 0 && $rowOnHand > 0): ?>On hand: <?= (int) $rowOnHand ?> pcs (<?= e(ucfirst($selectedUnit)) ?>)<?php endif; ?></small>
		<div class="d-flex align-items-center" style="gap:8px;">
			<div class="flex-grow-1" style="max-width:220px;">
				<small class="text-muted d-block">Unit</small>
				<select class="form-control form-control-sm item-unit" name="unit[]" aria-label="Unit measurement">
					<?php if (empty($rowUnits)): ?><option value="">Unit</option><?php endif; ?>
					<?php foreach ($rowUnits as $rum): ?><option value="<?= e(strtolower($rum['unit'])) ?>" data-pcs="<?= (int) $rum['pcs'] ?>" data-avail="<?= (int) $rum['avail'] ?>" <?= strtolower($rum['unit']) === $selectedUnit ? 'selected' : '' ?>><?= e(ucfirst($rum['unit'])) ?></option><?php endforeach; ?>
				</select>
			</div>
			<div style="width:auto;">
				<small class="text-muted d-block">Quantity (pcs)</small>
				<input class="form-control form-control-sm" type="number" name="quantity[]" min="1" placeholder="Qty" <?= $i === 0 ? 'required' : '' ?> value="<?= $savedQty > 0 ? $savedQty : '' ?>">
			</div>
			<input type="hidden" class="item-unit-pcs" name="unit_pcs[]" value="<?= (int) $selectedPcs ?>">
		</div>
		<small class="item-stock-warn text-danger d-none"></small>
		<ul class="item-suggestions dropdown-menu" style="display:none;left:0;right:0;top:100%;position:absolute;max-height:220px;overflow:auto;"></ul>
	</div>
	<?php endfor; ?>
</div>
	<button type="button" class="btn btn-outline-primary btn-sm add-item"><i class="fas fa-plus"></i> Add another item</button>
</div>

<div class="form-group"><label>Purpose</label><textarea class="form-control" name="purpose" rows="3" required><?= e($purpose) ?></textarea></div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
					<button class="btn btn-primary" type="submit" name="save-ris"><i class="fas fa-save"></i> Submit RIS</button>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
const ITEMS = <?= $itemJson ?: '[]' ?>;
const EMPLOYEES = <?= $employeeJson ?: '[]' ?>;
function bindItemSearch(row) {
    var input = row.querySelector('.item-search');
    var hidden = row.querySelector('.item-id');
    var list = row.querySelector('.item-suggestions');
    if (!input || !hidden || !list) return;

    function showResults() {
        var q = input.value.trim().toLowerCase();
        var results = ITEMS.filter(function (it) {
            return (it.stock_no + ' ' + it.description).toLowerCase().indexOf(q) !== -1;
        });
        if (results.length === 0) { hideResults(); return; }
        list.innerHTML = '';
        results.forEach(function (it) {
            var li = document.createElement('li');
            li.className = 'dropdown-item';
            li.setAttribute('role', 'option');
            li.innerHTML = '<div class="d-flex justify-content-between"><span>' + esc(it.stock_no + ' - ' + it.description) + '</span><small class="text-muted">Avail: ' + esc(it.quantity) + ' ' + esc(it.unit) + (it.pcs_per_unit > 1 ? ' (' + (it.quantity * it.pcs_per_unit) + ' pcs)' : '') + '</small></div>';
            li.addEventListener('mousedown', function (ev) {
                ev.preventDefault();
                selectItem(row, it);
                hideResults();
                var qty = row.querySelector('[name="quantity[]"]');
                if (qty && qty.value === '') qty.value = 1;
            });
            list.appendChild(li);
        });
        list.style.display = 'block';
    }
    function hideResults() { list.style.display = 'none'; }

    input.addEventListener('input', function () {
        if (hidden.value) { hidden.value = ''; }
        showResults();
    });
    input.addEventListener('focus', function () {
        if (input.value !== '') showResults();
    });
    document.addEventListener('click', function (e) {
        if (!row.contains(e.target)) hideResults();
    });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { hideResults(); }
        if (e.key === 'Enter') {
            if (list.style.display === 'block' && list.children.length > 0) {
                e.preventDefault();
                list.children[0].dispatchEvent(new MouseEvent('mousedown'));
            }
        }
    });
}
function syncUnitPcs(row) {
    var sel = row.querySelector('.item-unit');
    var pcsHidden = row.querySelector('.item-unit-pcs');
    if (sel && pcsHidden) {
        var opt = sel.options[sel.selectedIndex];
        var optPcs = opt ? parseInt(opt.getAttribute('data-pcs'), 10) : 0;
        pcsHidden.value = optPcs > 0 ? optPcs : 1;
    }
    refreshStock(row);
}
function refreshStock(row) {
    var sel = row.querySelector('.item-unit');
    var qty = row.querySelector('[name="quantity[]"]');
    var availEl = row.querySelector('.item-avail');
    var warn = row.querySelector('.item-stock-warn');
    var opt = sel ? sel.options[sel.selectedIndex] : null;
    var avail = parseInt(row.getAttribute('data-onhand'), 10) || 0;
    var unit = opt ? opt.value : '';
    if (availEl) availEl.textContent = avail > 0 ? ('On hand: ' + avail + ' pcs (' + ucfirst(unit) + ')') : (opt ? 'Out of stock' : '');
    if (qty) {
        var q = parseInt(qty.value, 10) || 0;
        if (avail > 0) {
            if (q > avail) { qty.value = avail; q = avail; }
            qty.setAttribute('max', String(avail));
        } else {
            qty.removeAttribute('max');
        }
        if (warn) {
            if (avail === 0 && opt) { warn.textContent = 'Out of stock.'; warn.classList.remove('d-none'); }
            else if (avail > 0 && q >= avail) { warn.textContent = 'Only ' + avail + ' pcs available in stock.'; warn.classList.remove('d-none'); }
            else { warn.classList.add('d-none'); }
        }
    }
}
function reSyncQty(e) {
    var row = e.target.closest('.ris-item-row');
    if (row) syncUnitPcs(row);
}
function selectItem(row, it) {
    var hidden = row.querySelector('.item-id');
    var input = row.querySelector('.item-search');
    var unitSel = row.querySelector('.item-unit');
    hidden.value = it.id;
    input.value = it.stock_no + ' - ' + it.description;
    row.setAttribute('data-onhand', String(Math.max((it.quantity || 0), 0)));
    var units = (it.units && it.units.length) ? it.units : [{ unit: it.unit, pcs: Math.max(it.pcs_per_unit, 1), avail: Math.max(it.quantity, 0) }];
    if (unitSel) {
        unitSel.innerHTML = '';
        units.forEach(function (u) {
            var o = document.createElement('option');
            o.value = String(u.unit).toLowerCase();
            o.textContent = ucfirst(u.unit);
            o.setAttribute('data-pcs', String(u.pcs));
            o.setAttribute('data-avail', String(u.avail !== undefined ? u.avail : Math.floor(it.quantity / Math.max(u.pcs, 1))));
            if (String(u.unit).toLowerCase() === String(it.unit).toLowerCase()) o.selected = true;
            unitSel.appendChild(o);
        });
        unitSel.disabled = false;
    }
    syncUnitPcs(row);
}
document.addEventListener('change', function (e) {
    if (e.target.matches('.ris-item-row .item-unit')) { syncUnitPcs(e.target.closest('.ris-item-row')); }
    if (e.target.matches('.ris-item-row [name="quantity[]"]')) { syncUnitPcs(e.target.closest('.ris-item-row')); }
});
document.addEventListener('input', function (e) {
    if (e.target.matches('.ris-item-row [name="quantity[]"]')) { refreshStock(e.target.closest('.ris-item-row')); }
});
function ucfirst(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
function esc(s) {
    var d = document.createElement('div');
    d.textContent = String(s == null ? '' : s);
    return d.innerHTML;
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.ris-item-row').forEach(function (row) { bindItemSearch(row); syncUnitPcs(row); });
});

document.addEventListener('change', function (e) {
    if (e.target.matches('.ris-item-row .item-search')) { e.target.closest('.ris-item-row').querySelector('[name="quantity[]"]').value = ''; reSyncQty(e); }
});
document.querySelector('.add-item').addEventListener('click', function () {
    var first = document.querySelector('.ris-item-row');
    var row = first.cloneNode(true);
    row.querySelector('.item-search').value = '';
    row.querySelector('.item-id').value = '';
    row.querySelector('.item-unit').innerHTML = '<option value="">Unit</option>';
    row.querySelector('.item-unit').disabled = true;
    row.querySelector('.item-unit-pcs').value = 1;
    row.querySelector('[name="quantity[]"]').value = '';
    row.setAttribute('data-onhand', '0');
    row.querySelector('.item-avail').textContent = '';
    row.querySelector('.item-suggestions').innerHTML = '';
    row.querySelector('.item-suggestions').style.display = 'none';
    var input = row.querySelector('.item-search');
    input.setAttribute('required', true);
    bindItemSearch(row);
    document.getElementById('ris-items').appendChild(row);
    input.focus();
});
document.addEventListener('click', function (e) {
    if (e.target.closest('.remove-item')) {
        var rows = document.querySelectorAll('.ris-item-row');
        if (rows.length > 1) { e.target.closest('.ris-item-row').remove(); }
    }
});
(function () {
    var input = document.querySelector('.emp-search');
    var hidden = document.querySelector('.emp-id');
    var list = document.querySelector('.emp-suggestions');
    if (!input || !hidden || !list) return;
    function showResults() {
        var q = input.value.trim().toLowerCase();
        if (!q) { hideResults(); return; }
        var results = EMPLOYEES.filter(function (e) { return e.name.toLowerCase().indexOf(q) !== -1; });
        if (!results.length) { hideResults(); return; }
        list.innerHTML = '';
        results.forEach(function (e) {
            var li = document.createElement('li');
            li.className = 'dropdown-item';
            li.setAttribute('role', 'option');
            li.innerHTML = esc(e.name) + (e.division ? ' <small class="text-muted">(' + esc(e.division) + ')</small>' : '');
            li.addEventListener('mousedown', function (ev) {
                ev.preventDefault();
                input.value = e.name;
                hidden.value = e.id;
                updateOfficeDisplay(e.id);
                hideResults();
            });
            list.appendChild(li);
        });
        list.style.display = 'block';
    }
    function updateOfficeDisplay(empId) {
        var officeEl = document.querySelector('.ris-office-display');
        var divInput = document.querySelector('[name="division"]');
        var emp = EMPLOYEES.filter(function (e) { return String(e.id) === String(empId); })[0];
        if (officeEl) officeEl.value = emp ? (emp.office || '') : '';
        if (divInput) {
            if (emp && emp.division) {
                divInput.value = emp.division;
            } else if (!emp) {
                divInput.value = '';
            }
        }
    }
    function hideResults() { list.style.display = 'none'; }
    input.addEventListener('input', function () { hidden.value = ''; updateOfficeDisplay(0); showResults(); });
    input.addEventListener('focus', function () { if (input.value) showResults(); });
    document.addEventListener('click', function (e) { if (!input.closest('.form-group').contains(e.target)) hideResults(); });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') hideResults();
        if (e.key === 'Enter' && list.style.display === 'block' && list.children.length > 0) {
            e.preventDefault();
            list.children[0].dispatchEvent(new MouseEvent('mousedown'));
        }
    });
})();
</script>
<?php if ($openModal): ?>
<script>document.addEventListener('DOMContentLoaded', function () { if (window.jQuery && jQuery.fn.modal) { jQuery('#risModal').modal('show'); } });</script>
<?php endif; ?>