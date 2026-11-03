<?php
require_once(__DIR__ . '/../includes/function.php');
require_once(root() . '/includes/string.php');
require_once(root() . '/includes/database/database.php');
require_once(root() . '/includes/database/account.php');
require_once(root() . '/includes/database/utility.php');
require_once(root() . '/includes/database/position.php');
require_once(root() . '/includes/database/section.php');
require_once(root() . '/includes/database/school.php');
require_once(root() . '/ims/helpers.php');
requireImsStaff();
$pdo = connection();

$issuedId = (int) (decode($_GET['issued_id'] ?? '') ?: 0);
$viewMode = $issuedId > 0;
$csrToken = csrf_token();

if ($viewMode) {
    $issued = find('SELECT * FROM issued_ics WHERE id = ?', [$issuedId]);
    if (!$issued) {
        ?><!DOCTYPE html><html><head><meta charset="utf-8"><title>ICS Not Found</title></head><body style="font-family:sans-serif;text-align:center;padding:60px;"><p>Issued ICS not found.</p><p><a href="javascript:window.close()">Close window</a></p></body></html><?php
        exit;
    }
    $issuedItems = query('SELECT * FROM issued_ics_items WHERE issued_ics_id = ? ORDER BY id ASC', [$issuedId]);
    $lines = array_map(static fn (array $r): array => [
        'item_no' => (string) $r['stock_no'],
        'description' => (string) $r['description'],
        'unit' => (string) $r['unit'],
        'qty' => (int) $r['qty'],
        'amount' => (float) $r['amount'],
        'status' => trim((string) ($r['status'] ?? '')),
    ], $issuedItems);
    $lines = array_values($lines);
    $sumAmount = array_sum(array_map(static fn (array $line): float => $line['amount'], $lines));
} else {
    $employees = $pdo->query("SELECT id, CONCAT(first_name, ' ', IFNULL(CONCAT(middle_name, ' '), ''), last_name, IFNULL(CONCAT(', ', name_extension), '')) AS name FROM employees ORDER BY last_name, first_name")->fetchAll();
    $positionMap = [];
    foreach ($pdo->query("SELECT employee_id, p.official_title FROM station_assignments sa LEFT JOIN positions p ON p.id = sa.position_id ORDER BY sa.assignment_date DESC") as $row) {
        if (!isset($positionMap[(int) $row['employee_id']])) {
            $positionMap[(int) $row['employee_id']] = trim((string) ($row['official_title'] ?? ''));
        }
    }
    $employeeJson = json_encode(array_map(static function (array $e) use ($positionMap): array {
        return [
            'id' => (int) $e['id'],
            'name' => (string) $e['name'],
            'office' => (string) risDivisionOfficeFromEmployee((int) $e['id'])['office'],
            'position' => (string) ($positionMap[(int) $e['id']] ?? ''),
        ];
    }, $employees), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    $filter = trim($_GET['filter'] ?? '');
    $items = $pdo->query(
        "SELECT id, stock_no, description, unit, unit_cost, quantity, min_qty, remarks,
                personnel, office, item_status, transfer_to
         FROM items
         WHERE unit_cost >= 30000
         ORDER BY description ASC"
    )->fetchAll();

    $latestMovement = [];
    if ($items) {
        $ids = implode(',', array_map('intval', array_column($items, 'id')));
        foreach ($pdo->query(
            "SELECT item_id, personnel, office, created_at
             FROM stock_movements
             WHERE item_id IN ($ids)
             ORDER BY created_at ASC"
        ) as $mov) {
            $latestMovement[(int) $mov['item_id']] = [
                'personnel' => trim((string) ($mov['personnel'] ?? '')),
                'office' => trim((string) ($mov['office'] ?? '')),
            ];
        }
    }

    $lines = array_map(static function (array $item) use ($latestMovement): array {
        $assign = $latestMovement[$item['id']] ?? ['personnel' => '', 'office' => ''];
        $personnel = trim((string) ($item['personnel'] ?? ''));
        $office = trim((string) ($item['office'] ?? ''));
        if ($personnel === '') { $personnel = $assign['personnel']; }
        if ($office === '') { $office = $assign['office']; }
        return [
            'item_id' => (int) $item['id'],
            'item_no' => $item['stock_no'],
            'description' => $item['description'],
            'unit' => $item['unit'],
            'unit_cost' => (float) $item['unit_cost'],
            'qty' => (int) $item['quantity'],
            'amount' => round(((float) $item['unit_cost']) * ((int) $item['quantity']), 2),
            'personnel' => $personnel,
            'office' => $office,
            'item_status' => trim((string) ($item['item_status'] ?? 'Functional')),
            'transfer_to' => trim((string) ($item['transfer_to'] ?? '')),
        ];
    }, $items);

    $lines = array_values(array_filter($lines, static function (array $line) use ($filter): bool {
        if ($filter === '') {
            return true;
        }
        $label = $line['item_no'] . ' - ' . $line['description'];
        return stripos($label, $filter) !== false
            || stripos($line['item_no'], $filter) !== false
            || stripos($line['personnel'], $filter) !== false
            || stripos($line['office'], $filter) !== false;
    }));
    $lines = array_values($lines);
    $sumAmount = array_sum(array_map(static fn (array $line): float => $line['amount'], $lines));
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ICS — Inventory Custodian Slip</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font:11pt "Times New Roman", Times, serif; color:#111; background:#f0f0f0; }
.toolbar { text-align:center; padding:14px; background:#fff; border-bottom:1px solid #ccc; }
.toolbar button { padding:8px 20px; font-size:11pt; cursor:pointer; border:1px solid #333; border-radius:4px; margin:0 6px; }
.toolbar .btn-print { background:#007bff; color:#fff; border-color:#007bff; }
.toolbar .btn-close { background:#6c757d; color:#fff; border-color:#6c757d; }
.sheet { box-sizing:border-box; width:210mm; margin:20px auto; padding:12mm 10mm; background:#fff; font:11pt "Times New Roman",serif; box-shadow:0 2px 8px rgba(0,0,0,.15); }
.header { text-align:center; font-size:0.35278cm; margin-bottom:1px; }
.header img { height:2.17cm; width:auto; }
.header-hdr { font-family:"Old English Text MT", Arial, sans-serif; font-size:0.35278cm; }
.header-sub { font-family:"Trajan Pro", Arial, sans-serif; font-size:0.3175cm; }
.appendix { text-align:left; font-weight:bold; font-size:12pt; margin-top:6px; }
.ics-title { text-align:center; font-weight:bold; font-size:18pt; margin:18px 0 2px; letter-spacing:1px; }
.ics-meta { width:100%; border-collapse:collapse; margin-top:16px; }
.ics-meta td { border:1px solid #111; padding:5px 8px; vertical-align:top; }
.ics-meta .label { width:22%; font-weight:bold; }
.ics-table { width:100%; border-collapse:collapse; margin-top:16px; }
.ics-table th, .ics-table td { border:1px solid #111; padding:6px; }
.ics-table th { background:#eee; text-align:center; vertical-align:middle; }
.ics-table td { text-align:center; vertical-align:middle; }
.ics-table td.desc { text-align:left; }
.ics-table .ics-subrow th { font-weight:normal; }
.ics-remarks { margin-top:5px; font-size:9.5pt; font-style:italic; color:#222; }
.ics-total { width:100%; border-collapse:collapse; margin-top:6px; }
.ics-total td { border:1px solid #111; padding:5px; }
.ics-sigs { width:100%; border-collapse:collapse; margin-top:26px; table-layout:fixed; }
.ics-sigs td { border:1px solid #111; padding:8px 14px; vertical-align:top; }
.ics-sig-roles td { text-align:center; font-weight:bold; }
.ics-sigs .line { border-bottom:1px solid #111; margin-top:32px; }
.ics-sigs .sub { text-align:center; font-size:10pt; margin-top:2px; }
.ics-overlay { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.55); display:flex; align-items:center; justify-content:center; z-index:9999; }
.ics-modal { background:#fff; width:480px; max-width:94vw; padding:22px 24px; border-radius:8px; box-shadow:0 10px 40px rgba(0,0,0,.4); font-size:11pt; font-family:Arial,Helvetica,sans-serif; }
.ics-modal h3 { margin:0 0 4px; font-size:15pt; color:#1a3a6b; }
.ics-modal .ics-modal-sub { color:#666; font-size:10pt; margin-bottom:16px; }
.ics-modal .fld { margin-bottom:12px; }
.ics-modal .fld label { display:block; font-weight:bold; font-size:10pt; margin-bottom:3px; }
.ics-modal .fld input, .ics-modal .fld textarea { width:100%; padding:7px 9px; border:1px solid #999; border-radius:4px; font-size:10.5pt; }
.ics-modal .ics-actions { text-align:right; margin-top:16px; }
.ics-modal .ics-actions button { padding:8px 18px; font-size:10.5pt; border:none; border-radius:4px; cursor:pointer; }
.ics-modal .ics-actions .btn-go { background:#007bff; color:#fff; }
.ics-modal .ics-actions .btn-go:hover { background:#005fc4; }
.ics-modal .ac-wrap { position:relative; }
.ics-modal .ac-drop { display:none; position:absolute; left:0; right:0; top:100%; background:#fff; border:1px solid #999; border-top:none; max-height:180px; overflow-y:auto; z-index:1000; border-radius:0 0 4px 4px; box-shadow:0 6px 12px rgba(0,0,0,.15); }
.ics-modal .ac-item { padding:6px 9px; cursor:pointer; border-bottom:1px solid #f0f0f0; }
.ics-modal .ac-item:hover { background:#e7f0ff; }
.ics-modal .ac-item .ac-name { display:block; font-weight:bold; font-size:10pt; }
.ics-modal .ac-item .ac-info { display:block; color:#555; font-size:9pt; }
@media print {
	.ics-overlay { display:none !important; }
	@page { size:A4 portrait; margin:0.2inch; }
	body { background:#fff; }
	.toolbar { display:none !important; }
	.sheet { margin:0; padding:5mm 7mm; box-shadow:none; }
}
</style>
</head>
<body>

<div class="toolbar">
	<button class="btn-print" type="button" onclick="window.print()"><i class="fas fa-print"></i> Print / Save PDF</button>
	<button class="btn-close" type="button" onclick="window.close()"><i class="fas fa-times"></i> Close</button>
</div>

<div class="sheet">
	<div class="header">
		<div class="header-hdr">
			<img src="image/logo.png"> <br>
			Republic of the Philippines <br>
			Department of Education
		</div>
		<div class="header-sub"> Region IX – Zamboanga Peninsula <br>
			SCHOOLS DIVISION OF DIPOLOG CITY
		</div>
	</div>

	<div class="appendix">Appendix 59</div>
	<div class="ics-title">INVENTORY CUSTODIAN  SLIP</div>

	<table class="ics-meta">
		<tr>
			<td class="label">Entity Name:</td>
			<td>Schools Division of Dipolog City</td>
			<td class="label">ICS No:</td>
			<td id="ics-no"><?= $viewMode ? e((string) $issued['ics_no']) : '______________' ?></td>
		</tr>
		<tr>
			<td class="label">Fund Cluster :</td>
			<td colspan="3">__________________</td>
		</tr>
	</table>

	<table class="ics-table" id="ics-table">
		<thead>
			<tr>
				<th>Quantity</th>
				<th>Unit</th>
				<th>Amount</th>
				<th class="desc">Description</th>
				<th>Inventory Item No.</th>
				
				<th>Status</th>
			</tr>
			<tr class="ics-subrow">
				<th>Unit Cost</th>
				<th></th>
				<th>Total Cost</th>
				<th></th>
				<th></th>
				
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php if (!$lines): ?>
				<tr>
					<td>&nbsp;</td>
					<td>&nbsp;</td>
					<td>&nbsp;</td>
					<td class="desc">&nbsp;</td>
					<td>&nbsp;</td>
					<td>&nbsp;</td>
				</tr>
			<?php endif; ?>
			<?php foreach ($lines as $i => $line): ?>
				<?php if ($viewMode): ?>
					<?php $statusValue = (string) ($line['status'] ?? ''); ?>
				<?php else: ?>
					<?php $statusValue = strtolower((string) ($line['item_status'] ?? '')) === 'transferred' ? 'Transferred to: ' . ($line['transfer_to'] ?? '') : 'Functional'; ?>
				<?php endif; ?>
				<tr<?= $viewMode ? '' : ' data-item-id="' . (int) ($line['item_id'] ?? 0) . '" data-desc="' . e($line['description']) . '"' ?>>
					<td><?= (int) $line['qty'] ?></td>
					<td><?= e(ucfirst((string) $line['unit'])) ?></td>
					<td><?= e(number_format($line['amount'], 2)) ?></td>
					<td class="desc"><?= e($line['description']) ?>
						<?php if ($viewMode && !empty($issued['remarks'])): ?><div class="ics-remarks">Remarks: <?= e((string) $issued['remarks']) ?></div><?php endif; ?>
						<?php if (!$viewMode): ?><div class="ics-remarks" id="remarks-<?= (int) $i ?>"></div><?php endif; ?>
					</td>
					<td><?= e($line['item_no']) ?></td>
					<td><?= e($statusValue !== '' ? $statusValue : '______________') ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<table class="ics-total">
		<tr>
			<td><strong>Unit Cost:</strong> per item listed above</td>
			<td><strong>Total Cost:</strong> <?= e('P' . number_format($sumAmount, 2)) ?></td>
		</tr>
	</table>

	<table class="ics-sigs">
		<tr class="ics-sig-roles">
			<td>Received from:</td>
			<td>Received by:</td>
		</tr>
		<tr>
			<td>
				<div class="line" id="recv-name" style="text-align: center;"><?= $viewMode ? e((string) $issued['received_from']) : '' ?></div>
				<div class="sub">Name of Personnel</div>
				<div class="line" id="recv-signature" style="text-align: center;"><?= $viewMode ? e((string) $issued['received_from_signature']) : '' ?></div>
				<div class="sub">Signature Over Printed Name</div>
				<div class="line" id="recv-position" style="text-align: center;"><?= $viewMode ? e((string) $issued['received_from_position']) : '' ?></div>
				<div class="sub">Position/Office</div>
				<div class="line" id="recv-date" style="text-align: center;"><?= $viewMode ? e(date('F j, Y', strtotime((string) $issued['ics_date']))) : '' ?></div>
				<div class="sub">Date</div>
			</td>
			<td>
				<div class="line" id="recvby-name" style="text-align: center;"><?= $viewMode ? e((string) $issued['received_by']) : '' ?></div>
				<div class="sub">Name of Personnel</div>
				<div class="line" id="recvby-signature" style="text-align: center;"><?= $viewMode ? e((string) $issued['received_by_signature']) : '' ?></div>
				<div class="sub">Signature Over Printed Name</div>
				<div class="line" id="recvby-position" style="text-align: center;"><?= $viewMode ? e((string) $issued['received_by_position']) : '' ?></div>
				<div class="sub">Position/Office</div>
				<div class="line" id="recvby-date" style="text-align: center;"><?= $viewMode ? e(date('F j, Y', strtotime((string) $issued['ics_date']))) : '' ?></div>
				<div class="sub">Date</div>
			</td>
		</tr>
	</table>

	<div style="text-align:center; margin-top:12px;">
		<img src="image/footer.png" alt="Footer" style="width:100%; max-width:210mm; height:auto; display:block; margin:0 auto;">
	</div>
</div>

<?php if (!$viewMode): ?>
<div class="ics-overlay" id="ics-overlay">
	<div class="ics-modal">
		<h3>Inventory Custodian Slip Details</h3>
		<div class="ics-modal-sub">Fill in the details below to populate the ICS before printing.</div>
		<div class="fld"><label>ICS No.</label><input type="text" id="in-ics-no"></div>
		<div class="fld ac-wrap">
			<label>Received from</label>
			<input type="text" id="in-recv-from" placeholder="Search employee..." autocomplete="off">
			<div class="ac-drop" id="drop-recv-from"></div>
		</div>
		<div class="fld ac-wrap">
			<label>Received by</label>
			<input type="text" id="in-recv-by" placeholder="Search employee..." autocomplete="off">
			<div class="ac-drop" id="drop-recv-by"></div>
		</div>
		<div class="fld"><label>Position</label><input type="text" id="in-recv-position" placeholder="Auto-filled from employee"></div>
		<div class="fld"><label>Date</label><input type="date" id="in-ics-date"></div>
		<div class="fld"><label>Remarks</label><textarea id="in-remarks" rows="2"></textarea></div>
		<div class="ics-actions">
			<button class="btn-go" type="button" onclick="saveIcsThenApply()">Apply to Form</button>
		</div>
	</div>
</div>
<?php endif; ?>

<?php if (!$viewMode): ?>
<script>
var CSRF_TOKEN = <?= json_encode($csrToken) ?>;
var IMS_EMPLOYEES = <?= $employeeJson ?>;
var imsSelection = { from: null, by: null };

function imsEsc(s) {
	var d = document.createElement('div');
	d.textContent = s == null ? '' : String(s);
	return d.innerHTML;
}

function setupAutocomplete(inputId, dropId, key) {
	var input = document.getElementById(inputId);
	var drop = document.getElementById(dropId);

	function closeDrop() { drop.style.display = 'none'; }

	function showMatches() {
		var q = input.value.trim().toLowerCase();
		drop.innerHTML = '';
		if (!q) { closeDrop(); return; }
		var matches = IMS_EMPLOYEES.filter(function (e) {
			return e.name.toLowerCase().indexOf(q) !== -1;
		}).slice(0, 15);
		if (!matches.length) { closeDrop(); return; }
		matches.forEach(function (e) {
			var item = document.createElement('div');
			item.className = 'ac-item';
			var info = [e.position, e.office].filter(function (v) { return v !== ''; }).join(' - ');
			item.innerHTML = '<span class="ac-name">' + imsEsc(e.name) + '</span><span class="ac-info">' + imsEsc(info) + '</span>';
			item.addEventListener('mousedown', function (ev) {
				ev.preventDefault();
				input.value = e.name;
				imsSelection[key] = e;
				closeDrop();
				if (key === 'by') {
					var posInput = document.getElementById('in-recv-position');
					if (posInput) { posInput.value = e.position || ''; }
				}
			});
			drop.appendChild(item);
		});
		drop.style.display = 'block';
	}

	input.addEventListener('input', function () {
		if (imsSelection[key] && imsSelection[key].name !== input.value.trim()) { imsSelection[key] = null; }
		showMatches();
	});
	input.addEventListener('focus', function () { showMatches(); });
	input.addEventListener('blur', function () { setTimeout(closeDrop, 150); });
	document.addEventListener('click', function (ev) {
		if (ev.target !== input && !drop.contains(ev.target)) { closeDrop(); }
	});
}

setupAutocomplete('in-recv-from', 'drop-recv-from', 'from');
setupAutocomplete('in-recv-by', 'drop-recv-by', 'by');

function applyIcs() {
	function val(id) { var el = document.getElementById(id); return el ? el.value.replace(/\r?\n/g, ' ').trim() : ''; }
	function setText(id, text) { var el = document.getElementById(id); if (el) { el.innerHTML = text !== '' ? imsEsc(text) : '&nbsp;'; } }

	var icsNo = val('in-ics-no');
	var remarks = val('in-remarks');
	var dateVal = val('in-ics-date');
	var dateText = '';
	if (dateVal !== '') {
		var parts = dateVal.split('-');
		var months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
		if (parts.length === 3) {
			var m = parseInt(parts[1], 10);
			var d = parseInt(parts[2], 10);
			if (m >= 1 && m <= 12 && d >= 1 && d <= 31) {
				dateText = months[m - 1] + ' ' + d + ', ' + parts[0];
			} else { dateText = dateVal; }
		}
	}

	setText('ics-no', icsNo);
	setText('recv-date', dateText);
	setText('recvby-date', dateText);

	var from = imsSelection.from;
	var by = imsSelection.by;

	var fromName = from ? from.name : val('in-recv-from');
	var fromPos = from ? [from.position, from.office].filter(function (v) { return v !== ''; }).join(' - ') : '';
	setText('recv-name', fromName);
	setText('recv-signature', fromName);
	setText('recv-position', fromPos);

	var byName = by ? by.name : val('in-recv-by');
	var byPosInput = val('in-recv-position');
	var byPos = byPosInput !== '' ? byPosInput : (by ? [by.position, by.office].filter(function (v) { return v !== ''; }).join(' - ') : '');
	setText('recvby-name', byName);
	setText('recvby-signature', byName);
	setText('recvby-position', byPos);

	if (remarks !== '') {
		var remEls = document.querySelectorAll('.ics-remarks');
		for (var i = 0; i < remEls.length; i++) {
			remEls[i].innerHTML = 'Remarks: ' + imsEsc(remarks);
		}
	}

	document.getElementById('ics-overlay').style.display = 'none';
	window.print();
}

function collectIcsItems() {
	var items = [];
	var rows = document.querySelectorAll('#ics-table tbody tr[data-item-id]');
	for (var i = 0; i < rows.length; i++) {
		var tr = rows[i];
		var cells = tr.querySelectorAll('td');
		if (cells.length < 6) { continue; }
		items.push({
			item_id: parseInt(tr.getAttribute('data-item-id'), 10) || 0,
			description: tr.getAttribute('data-desc') || cells[3].textContent.trim(),
			unit: cells[1].textContent.trim(),
			qty: parseInt(cells[0].textContent, 10) || 0,
			amount: parseFloat((cells[2].textContent || '0').replace(/[^\d.-]/g, '')) || 0,
			stock_no: cells[4].textContent.trim(),
			status: cells[5].textContent.trim()
		});
	}
	return items;
}

function saveIcsThenApply() {
	var icsNo = document.getElementById('in-ics-no').value.replace(/\r?\n/g, ' ').trim();
	var form = new FormData();
	form.append('csrf_token', CSRF_TOKEN);
	form.append('ics_no', icsNo);
	form.append('received_from', document.getElementById('in-recv-from').value.trim());
	form.append('received_from_signature', imsSelection.from ? (imsSelection.from.name) : document.getElementById('in-recv-from').value.trim());
	form.append('received_from_position', imsSelection.from ? [imsSelection.from.position, imsSelection.from.office].filter(function (v) { return v !== ''; }).join(' - ') : '');
	form.append('received_by', document.getElementById('in-recv-by').value.trim());
	form.append('received_by_signature', imsSelection.by ? (imsSelection.by.name) : document.getElementById('in-recv-by').value.trim());
	form.append('received_by_position', document.getElementById('in-recv-position').value.trim());
	form.append('ics_date', document.getElementById('in-ics-date').value);
	form.append('remarks', document.getElementById('in-remarks').value);
	form.append('items', JSON.stringify(collectIcsItems()));

	fetch('ics-save.php', { method: 'POST', body: form })
		.then(function (response) { return response.json(); })
		.then(function (data) {
			if (data && data.success && data.ics_no && icsNo === '') {
				document.getElementById('in-ics-no').value = data.ics_no;
				applyIcs();
				return;
			}
			if (data && data.success && data.id) {
				applyIcs();
				return;
			}
			var msg = (data && data.message) ? data.message : 'Unable to save the issued ICS.';
			if (window.confirm(msg + '\n\nContinue printing without saving?')) {
				applyIcs();
			}
		})
		.catch(function () {
			if (window.confirm('Unable to reach the server. Continue printing without saving?')) {
				applyIcs();
			}
		});
}
</script>
<?php endif; ?>

</body>
</html>