<?php
require_once(__DIR__ . '/../includes/function.php');
require_once(root() . '/includes/string.php');
require_once(root() . '/includes/database/database.php');
require_once(root() . '/includes/database/employee.php');
require_once(root() . '/includes/database/section.php');
require_once(root() . '/includes/database/position.php');
require_once(root() . '/includes/database/utility.php');
require_once(root() . '/ims/helpers.php');

requireImsStaff();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$custodianHead = section('PSS')['head_id'] ?? null;
$custodianName = !empty($custodianHead) ? userName((int) $custodianHead, true) : '';
$custodianDesignation = !empty($custodianHead)
	? (string) (position((int) $custodianHead)['official_title'] ?? 'Administrative Officer IV')
	: 'Administrative Officer IV';
$todayDisplay = date('F j, Y');

$issuedId = (int) (decode($_GET['issued_id'] ?? '') ?: 0);
$viewMode = $issuedId > 0;
$showForm = false;

if ($viewMode) {
    $issued = find('SELECT * FROM issued_par WHERE id = ?', [$issuedId]);
    if (!$issued) {
        ?><!DOCTYPE html><html><head><meta charset="utf-8"><title>PAR Not Found</title></head><body style="font-family:sans-serif;text-align:center;padding:60px;"><p>Issued PAR not found.</p><p><a href="javascript:window.close()">Close window</a></p></body></html><?php
        exit;
    }
    $rows = [];
    $totalAmount = 0.0;
    foreach (query('SELECT * FROM issued_par_items WHERE issued_par_id = ? ORDER BY id ASC', [$issuedId]) ?: [] as $line) {
        $amount = (float) $line['amount'];
        $rows[] = [
            'item_id' => (int) $line['item_id'],
            'stock_no' => (string) $line['stock_no'],
            'description' => (string) $line['description'],
            'remarks' => trim((string) ($line['remarks'] ?? '')),
            'unit' => (string) $line['unit'],
            'qty' => (int) $line['qty'],
            'amount' => $amount,
            'date_acquired' => (string) ($line['date_acquired'] ?? ''),
        ];
        $totalAmount += $amount;
    }
    $padRows = max(0, 10 - count($rows));
    $parFilter = '';
    $parNo = trim((string) ($issued['par_no'] ?? ''));
    $entityName = trim((string) ($issued['entity_name'] ?? ''));
    $fundCluster = trim((string) ($issued['fund_cluster'] ?? ''));
    $endUser = trim((string) ($issued['end_user'] ?? ''));
    $endUserPos = trim((string) ($issued['end_user_position'] ?? ''));
    $endUserDateRaw = trim((string) ($issued['end_user_date'] ?? ''));
    $endUserDateDisplay = $endUserDateRaw !== '' ? date('F j, Y', strtotime($endUserDateRaw)) : '';
    $parRemarks = trim((string) ($issued['remarks'] ?? ''));
    $endUserJson = '[]';
} else {
    $parFilter = trim((string) ($_GET['filter'] ?? ''));
    $parNo = trim((string) ($_GET['par_no'] ?? ''));
    $entityName = trim((string) ($_GET['entity'] ?? ''));
    $fundCluster = trim((string) ($_GET['fund'] ?? ''));
    $endUser = trim((string) ($_GET['end_user'] ?? ''));
    $endUserPos = trim((string) ($_GET['end_user_pos'] ?? ''));
    $endUserDate = trim((string) ($_GET['end_user_date'] ?? date('Y-m-d')));
    $endUserDateDisplay = $endUserDate !== '' ? date('F j, Y', strtotime($endUserDate)) : '';
    $parRemarks = trim((string) ($_GET['remarks'] ?? ''));
    $showForm = $endUser === '';

$endUserEmployees = query(
    "SELECT e.id,
            CONCAT(TRIM(e.last_name), ', ', TRIM(e.first_name),
                   IFNULL(CONCAT(' ', TRIM(e.middle_name)), ''),
                   IFNULL(CONCAT(', ', TRIM(e.name_extension)), '')) AS name,
            (SELECT p.official_title
               FROM station_assignments sa
               INNER JOIN positions p ON p.id = sa.position_id
              WHERE sa.employee_id = e.id
              ORDER BY sa.assignment_date DESC LIMIT 1) AS position
     FROM employees e
     ORDER BY e.last_name, e.first_name"
);
$endUserJson = json_encode(array_map(static fn (array $e): array => [
    'id' => (int) $e['id'],
    'name' => (string) $e['name'],
    'position' => (string) ($e['position'] ?? ''),
], $endUserEmployees ?: []), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$statement = query(
        "SELECT id, stock_no, description, unit, unit_cost, total_units, created_at, remarks
         FROM items
         WHERE unit_cost >= 50000
         ORDER BY description ASC"
    );

    $rows = [];
    $totalAmount = 0.0;
    foreach ($statement ?: [] as $item) {
        $qty = max((int) $item['total_units'], 1);
        $unitCost = (float) $item['unit_cost'];
        $amount = round($qty * $unitCost, 2);
        $label = $item['stock_no'] . ' - ' . $item['description'];
        if ($parFilter !== ''
            && stripos($label, $parFilter) === false
            && stripos($item['stock_no'], $parFilter) === false
            && stripos($item['description'], $parFilter) === false) {
            continue;
        }
        $rows[] = [
            'item_id' => (int) $item['id'],
            'stock_no' => $item['stock_no'],
            'description' => $item['description'],
            'remarks' => trim((string) ($item['remarks'] ?? '')),
            'unit' => $item['unit'],
            'qty' => $qty,
            'amount' => $amount,
            'date_acquired' => $item['created_at'] !== ''
                ? date('M d, Y', strtotime($item['created_at']))
                : '',
        ];
        $totalAmount += $amount;
    }

    $padRows = max(0, 10 - count($rows));
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Property Acknowledgement Receipt</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font:11pt "Times New Roman", Times, serif; color:#111; background:#f0f0f0; }
.toolbar { text-align:center; padding:14px; background:#fff; border-bottom:1px solid #ccc; }
.toolbar button { padding:8px 20px; font-size:11pt; cursor:pointer; border:1px solid #333; border-radius:4px; margin:0 6px; }
.toolbar .btn-print { background:#007bff; color:#fff; border-color:#007bff; }
.toolbar .btn-close { background:#6c757d; color:#fff; border-color:#6c757d; }
.sheet { box-sizing:border-box; width:210mm; min-height:297mm; margin:10px auto; padding:9mm 9mm 2mm; background:#fff; font:11pt "Times New Roman",serif; box-shadow:0 2px 8px rgba(0,0,0,.15); position:relative; }
.header { font-family:"Old English Text MT", Arial, sans-serif; text-align:center; font-size:0.35278cm; margin-bottom:1px; }
.header img { height:2cm; width:auto; }
.header2 { font-family:"Trajan Pro", Arial, sans-serif; text-align:center; font-size:0.3175cm; margin-bottom:1px; }
.appendix { position:absolute; top:9mm; right:9mm; font-weight:bold; }
.report-title { text-align:center; font-weight:bold; font-size:15pt; margin:16px 0 14px; letter-spacing:1px; text-decoration:underline; }
.meta { width:100%; border-collapse:separate; border-spacing:0; margin-top:4px; }
.meta td { padding:3px 0; vertical-align:bottom; white-space:nowrap; }
.meta .data { font-weight:bold; }
.par-table { width:100%; border-collapse:collapse; margin-top:8px; }
.par-table th, .par-table td { border:1px solid #111; padding:3px 6px; vertical-align:middle; }
.par-table th { text-align:center; font-weight:bold; }
.par-table td.desc { text-align:left; }
.par-table td.num { text-align:center; }
.par-table tr.row { height:24px; }
.par-table tr.total td { font-weight:bold; text-align:center; }
.sig { display:flex; width:100%; margin-top:26px; align-items:flex-end; }
.sig-block { flex:1; text-align:left; padding:0 6px; }
.sig-line { border-bottom:1px solid #111; }
.sig-label { font-size:9.5pt; font-weight:bold; margin-top:2px; }
.sig-date { text-align:center; margin-top:4px; }
.sig-position { text-align:center; margin-top:4px; }
.footer-img { text-align:center; margin-top:14px; }
.footer-img img { width:100%; max-width:210mm; height:auto; display:block; margin:0 auto; }
.sig-modal { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.55); display:flex; align-items:center; justify-content:center; z-index:9999; }
.sig-modal-box { background:#fff; width:480px; max-width:94vw; padding:22px 24px; border-radius:8px; box-shadow:0 10px 40px rgba(0,0,0,.4); font-size:11pt; font-family:Arial,Helvetica,sans-serif; }
.sig-modal-box h3 { margin:0 0 4px; font-size:15pt; color:#1a3a6b; }
.ics-modal-sub { color:#666; font-size:10pt; margin-bottom:16px; }
.fld { margin-bottom:12px; }
.fld label { display:block; font-weight:bold; font-size:10pt; margin-bottom:3px; }
.fld input[type=text], .fld input[type=date] { width:100%; padding:7px 9px; border:1px solid #999; border-radius:4px; font-size:10.5pt; box-sizing:border-box; }
.fld input[readonly] { background:#f0f0f0; }
.ics-actions { text-align:right; margin-top:16px; }
.ics-actions button { padding:8px 18px; font-size:10.5pt; border:none; border-radius:4px; cursor:pointer; }
.ics-actions .btn-go { background:#007bff; color:#fff; }
.ics-actions .btn-go:hover { background:#005fc4; }
.ics-actions .skip { margin-right:10px; text-decoration:none; color:#666; font-size:10.5pt; }
.ac-wrap { position:relative; }
.ac-drop { display:none; position:absolute; left:0; right:0; top:100%; background:#fff; border:1px solid #999; border-top:none; max-height:180px; overflow-y:auto; z-index:1000; border-radius:0 0 4px 4px; box-shadow:0 6px 12px rgba(0,0,0,.15); }
.ac-item { padding:6px 9px; cursor:pointer; border-bottom:1px solid #f0f0f0; }
.ac-item:hover, .ac-item.active { background:#e7f0ff; }
.ac-item .ac-name { display:block; font-weight:bold; font-size:10pt; }
.ac-item .ac-pos { display:block; font-size:8.5pt; color:#666; }
.doc-table { width:100%; border-collapse:collapse; }
.doc-table > tbody > tr > td, .doc-table > tfoot > tr > td { padding:0; }
@page { size:A4 portrait; margin:10mm 12mm 16mm; }
@media print {
	body { background:#fff; margin:0; }
	.toolbar { display:none !important; }
	.sig-modal { display:none !important; }
	.sheet { margin:0; width:auto; min-height:0; padding:5mm 6mm; box-shadow:none; }
	.doc-table > tfoot > tr > td { padding-top:3mm; }
}
</style>
</head>
<body>

<div class="toolbar">
	<button class="btn-print" type="button" onclick="window.print()"><i class="fas fa-print"></i> Print / Save PDF</button>
	<button class="btn-close" type="button" onclick="window.close()"><i class="fas fa-times"></i> Close</button>
</div>

<table class="doc-table">
<tbody><tr><td>
<div class="sheet">

	<div class="appendix">Appendix 71</div>

	<div class="page-header">
		<div class="header">
			<img src="image/logo.png"><br>
			Republic of the Philippines<br>
			Department of Education
		</div>
		<div class="header2"> Region IX – Zamboanga Peninsula <br>
			SCHOOLS DIVISION OF DIPOLOG CITY
		</div>
	</div>

	<div class="report-title">PROPERTY ACKNOWLEDGMENT RECEIPT</div>

	<table class="meta">
		<tr>
			<td style="width:55%;">Entity Name : <span class="data sig-line"><?= e($entityName !== '' ? $entityName : 'DIPOLOG CITY DIVISION ') ?></span></td>
			<td style="width:45%;">PAR No. : <span class="data sig-line"><?= e($parNo !== '' ? $parNo : '_________________') ?></span></td>
		</tr>
		<tr>
			<td>Fund Cluster : <span class="data sig-line"><?= e($fundCluster !== '' ? $fundCluster : '________________________________') ?></span></td>
			<td>&nbsp;</td>
		</tr>
	</table>

	<table class="par-table" id="par-table">
		<thead>
			<tr>
				<th style="width:9%">Quantity</th>
				<th style="width:10%">Unit</th>
				<th style="width:32%">Description</th>
				<th style="width:18%">Property Number</th>
				<th style="width:15%">Date Acquired</th>
				<th style="width:16%">Amount</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($rows as $row): ?>
				<tr data-item-id="<?= (int) $row['item_id'] ?>" data-desc="<?= e($row['description']) ?>" data-stock="<?= e($row['stock_no']) ?>" data-unit="<?= e(ucfirst((string) $row['unit'])) ?>" data-qty="<?= (int) $row['qty'] ?>" data-date="<?= e($row['date_acquired']) ?>" data-amount="<?= number_format($row['amount'], 2, '.', '') ?>" data-remarks="<?= e($row['remarks']) ?>">
					<td class="num"><?= (int) $row['qty'] ?></td>
					<td class="num"><?= e(ucfirst((string) $row['unit'])) ?></td>
					<td class="desc"><?= e($row['description']) ?><?php if ($row['remarks'] !== ''): ?><div style="font-size:9pt;font-style:italic;margin-top:2px;"><?= e($row['remarks']) ?></div><?php endif; ?></td>
					<td class="num"><?= e($row['stock_no']) ?></td>
					<td class="num"><?= e($row['date_acquired']) ?></td>
					<td class="num"><?= number_format($row['amount'], 2) ?></td>
				</tr>
			<?php endforeach; ?>
			<?php for ($i = 0; $i < $padRows; $i++): ?>
				<tr class="row"><td>&nbsp;</td><td>&nbsp;</td><td class="desc">&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
			<?php endfor; ?>
			<tr class="total">
				<td colspan="3">TOTAL</td>
				<td></td>
				<td></td>
				<td class="num"><?= number_format($totalAmount, 2) ?></td>
			</tr>
		</tbody>
	</table>

	<?php if ($parRemarks !== ''): ?>
		<div style="margin-top:6px;font-weight:bold;">Remarks: <span style="font-weight:normal;"><?= e($parRemarks) ?></span></div>
	<?php endif; ?>

 <br> <br>
	<div class="sig">
		<div class="sig-block">
			<div class="sig-line" style="text-align:center;"><?= e(mb_strtoupper($endUser)) ?></div>
			<div class="sig-label" style="text-align:center;">Signature over Printed Name of End User</div><br>
			<div class="sig-line" style="text-align:center;"><?= e($endUserPos) ?></div> 
			<div class="sig-position">Position/Office</div>&nbsp;
			<div class="sig-line" style="text-align:center;"><?= e($endUserDateDisplay !== '' ? $endUserDateDisplay : $todayDisplay) ?></div>
			<div class="sig-date">Date</div>
		</div>
		<div class="sig-block">
			<div class="sig-line" style="text-align:center;"><?= e($custodianName) ?></div>
			<div class="sig-label" style="text-align:center;">Signature over Printed Name of Supply and/or Property Custodian</div>
			 &nbsp;
			<div class="sig-line" style="text-align:center;"><?= e($custodianDesignation) ?></div>
			<div class="sig-position">Position/Office</div>&nbsp;
			<div class="sig-line" style="text-align:center;"><?= e($todayDisplay) ?></div>
			<div class="sig-position">Date</div>
		</div>
	</div>
	<br><br>
	<div class="footer-img">
		<img src="image/footer.png" alt="Footer">
	</div>
</div>
</td></tr></tbody>
</table>

<?php if ($showForm): ?>
<div class="sig-modal" id="sigModal">
	<div class="sig-modal-box">
		<h3>Property Acknowledgement Receipt</h3>
		<div class="ics-modal-sub">Fill in the end user details before printing.</div>
		<form method="get" action="" onsubmit="return saveParThenSubmit(this, event)">
			<input type="hidden" name="filter" value="<?= e($parFilter) ?>">
			<input type="hidden" name="entity" value="<?= e($entityName) ?>">
			<div class="fld"><label>PAR No.</label><input type="text" name="par_no" value="<?= e($parNo) ?>"></div>
			<div class="fld"><label>Fund Cluster</label><input type="text" name="fund" value="<?= e($fundCluster) ?>"></div>
			<hr style="border:none;border-top:1px solid #ccc;">
			<div class="fld ac-wrap">
				<label>End User — Name</label>
				<input type="text" name="end_user" id="end_user_input" autocomplete="off" placeholder="Type name to search" required>
				<div class="ac-drop" id="end_user_drop"></div>
			</div>
			<div class="fld"><label>End User — Position / Office</label><input type="text" name="end_user_pos" id="end_user_pos" readonly></div>
			<div class="fld"><label>Date</label><input type="date" name="end_user_date" value="<?= date('Y-m-d') ?>" required></div>
			<div class="fld"><label>Remarks</label><input type="text" name="remarks" value="<?= e($parRemarks) ?>"></div>
			<div class="ics-actions">
				<a class="skip" href="<?= e(uri() . '/ims/par-print.php?filter=' . urlencode($parFilter)) ?>">Skip</a>
				<button type="submit" class="btn-go">Apply</button>
			</div>
		</form>
	</div>
</div>
<script>
const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
const END_USER_EMPLOYEES = <?= $endUserJson ?: '[]' ?>;
(function () {
	var input = document.getElementById('end_user_input');
	var drop = document.getElementById('end_user_drop');
	var pos = document.getElementById('end_user_pos');
	if (!input || !drop) return;
	function hide() { drop.style.display = 'none'; }
	function show() {
		var q = input.value.trim().toLowerCase();
		if (!q) { hide(); return; }
		var results = END_USER_EMPLOYEES.filter(function (e) { return e.name.toLowerCase().indexOf(q) !== -1; }).slice(0, 50);
		if (!results.length) { hide(); return; }
		drop.innerHTML = '';
		results.forEach(function (e, idx) {
			var item = document.createElement('div');
			item.className = idx === 0 ? 'ac-item active' : 'ac-item';
			item.innerHTML = '<span class="ac-name"></span><span class="ac-pos"></span>';
			item.firstChild.textContent = e.name;
			item.children[1].textContent = e.position || '';
			item.addEventListener('mousedown', function (ev) {
				ev.preventDefault();
				pick(e);
			});
			drop.appendChild(item);
		});
		drop.style.display = 'block';
	}
	function pick(e) {
		input.value = e.name;
		if (pos) { pos.value = e.position || ''; }
		hide();
	}
	input.addEventListener('input', show);
	input.addEventListener('focus', function () { if (input.value) show(); });
	input.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') hide();
		if (e.key === 'Enter' && drop.style.display === 'block' && drop.children.length) {
			e.preventDefault();
			drop.children[0].dispatchEvent(new MouseEvent('mousedown'));
		}
	});
	document.addEventListener('click', function (e) {
		if (!input.parentNode.contains(e.target)) hide();
	});
})();
function saveParThenSubmit(form, ev) {
	if (ev && ev.preventDefault) ev.preventDefault();
	var fd = new FormData();
	fd.append('csrf_token', CSRF_TOKEN);
	var fields = ['filter','entity','par_no','fund','end_user','end_user_pos','end_user_date','remarks'];
	for (var i = 0; i < fields.length; i++) {
		var el = form.elements[fields[i]];
		fd.append(fields[i], el ? el.value : '');
	}
	var rows = document.querySelectorAll('#par-table tbody tr[data-item-id]');
	var items = [];
	for (var j = 0; j < rows.length; j++) {
		var tr = rows[j];
		items.push({
			item_id: parseInt(tr.getAttribute('data-item-id'), 10) || 0,
			stock_no: tr.getAttribute('data-stock') || '',
			description: tr.getAttribute('data-desc') || '',
			unit: tr.getAttribute('data-unit') || '',
			qty: parseInt(tr.getAttribute('data-qty'), 10) || 0,
			date_acquired: tr.getAttribute('data-date') || '',
			amount: parseFloat((tr.getAttribute('data-amount') || '0').replace(/[^\d.-]/g, '')) || 0,
			remarks: tr.getAttribute('data-remarks') || ''
		});
	}
	fd.append('items', JSON.stringify(items));
	fetch('par-save.php', { method: 'POST', body: fd })
		.then(function (r) { return r.json(); })
		.then(function (d) {
			if (d && d.success) { location.href = location.pathname + '?' + serializeForm(form); return; }
			var msg = (d && d.message) ? d.message : 'Unable to save the PAR.';
			if (window.confirm(msg + '\n\nContinue without saving?')) location.href = location.pathname + '?' + serializeForm(form);
		})
		.catch(function () {
			if (window.confirm('Unable to reach the server. Continue without saving?')) location.href = location.pathname + '?' + serializeForm(form);
		});
	return false;
}
function serializeForm(form) {
	var p = [];
	for (var i = 0; i < form.elements.length; i++) {
		var el = form.elements[i];
		if (!el.name || el.disabled || el.value === '') continue;
		p.push(encodeURIComponent(el.name) + '=' + encodeURIComponent(el.value));
	}
	return p.join('&');
}
</script>
<?php endif; ?>

</body>
</html>