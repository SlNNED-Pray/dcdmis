<?php
require_once(__DIR__ . '/../includes/function.php');
require_once(root() . '/includes/string.php');
require_once(root() . '/includes/database/database.php');
require_once(root() . '/includes/database/employee.php');
require_once(root() . '/includes/database/section.php');
require_once(root() . '/includes/database/school.php');
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
$defaultEntity = 'Schools Division of Dipolog City';

$issuedId = (int) (decode($_GET['issued_id'] ?? '') ?: 0);
$viewMode = $issuedId > 0;
$showForm = false;
$poId = 0;

if ($viewMode) {
    $issued = find('SELECT * FROM issued_iar WHERE id = ?', [$issuedId]);
    if (!$issued) {
        ?><!DOCTYPE html><html><head><meta charset="utf-8"><title>IAR Not Found</title></head><body style="font-family:sans-serif;text-align:center;padding:60px;"><p>Issued IAR not found.</p><p><a href="javascript:window.close()">Close window</a></p></body></html><?php
        exit;
    }
    $rows = [];
    foreach (query('SELECT * FROM issued_iar_items WHERE issued_iar_id = ? ORDER BY id ASC', [$issuedId]) ?: [] as $line) {
        $rows[] = [
            'item_id' => (int) $line['item_id'],
            'stock_no' => (string) $line['stock_no'],
            'description' => (string) $line['description'],
            'unit' => (string) $line['unit'],
            'qty' => (int) $line['qty'],
        ];
    }
    $padRows = max(0, 10 - count($rows));

    $iarNo = trim((string) ($issued['iar_no'] ?? ''));
    $entityName = trim((string) ($issued['entity_name'] ?? ''));
    $fundCluster = trim((string) ($issued['fund_cluster'] ?? ''));
    $supplier = trim((string) ($issued['supplier'] ?? ''));
    $poNo = trim((string) ($issued['po_no'] ?? ''));
    $poDateRaw = trim((string) ($issued['po_date'] ?? ''));
    $iarDateRaw = trim((string) ($issued['iar_date'] ?? ''));
    $officeDept = trim((string) ($issued['office_dept'] ?? ''));
    $invoiceNo = trim((string) ($issued['invoice_no'] ?? ''));
    $invoiceDateRaw = trim((string) ($issued['invoice_date'] ?? ''));
    $rcc = trim((string) ($issued['responsibility_center_code'] ?? ''));
    $inspectorName = trim((string) ($issued['inspector_name'] ?? ''));
    $dateInspectedRaw = trim((string) ($issued['date_inspected'] ?? ''));
    $dateReceivedRaw = trim((string) ($issued['date_received'] ?? ''));
    $acceptanceStatus = trim((string) ($issued['acceptance_status'] ?? 'Complete'));
    $partialQty = trim((string) ($issued['partial_qty'] ?? ''));
    $remarks = trim((string) ($issued['remarks'] ?? ''));
    $inspectorJson = '[]';
} else {
    $poId = (int) (decode($_GET['po_id'] ?? '') ?: 0);
    $po = $poId > 0
        ? find(
            "SELECT p.id, p.pr_no, p.po_no, p.po_date, p.quantity, p.unit_cost, p.total_cost, p.purpose, p.employee_id,
                    i.id AS item_id, i.stock_no, i.description, i.unit, s.name AS supplier
             FROM purchase_requests p
             JOIN items i ON i.id = p.item_id
             LEFT JOIN suppliers s ON s.id = p.supplier_id
             WHERE p.id = ?",
            [$poId]
        )
        : null;
    if (!$po) {
        ?><!DOCTYPE html><html><head><meta charset="utf-8"><title>Purchase Order Not Found</title></head><body style="font-family:sans-serif;text-align:center;padding:60px;"><p>The selected purchase order could not be found.</p><p><a href="javascript:window.close()">Close window</a></p></body></html><?php
        exit;
    }

    $rows = [[
        'item_id' => (int) $po['item_id'],
        'stock_no' => (string) $po['stock_no'],
        'description' => (string) $po['description'],
        'unit' => (string) $po['unit'],
        'qty' => max((int) $po['quantity'], 1),
    ]];
    $padRows = max(0, 4 - count($rows));

    $latestIar = $po
        ? (find(
            "SELECT id, iar_no, entity_name, fund_cluster, supplier, po_no, po_date, iar_date, office_dept,
                    invoice_no, invoice_date, responsibility_center_code, inspector_name, date_inspected,
                    date_received, acceptance_status, partial_qty, remarks
             FROM issued_iar
             WHERE po_no = ?
             ORDER BY id DESC LIMIT 1",
            [$po['po_no']]
        ) ?: null)
        : null;
    $editIssuedId = $latestIar ? (int) $latestIar['id'] : 0;
    $editing = ($_GET['edit'] ?? '') === '1';

    $iarNo = trim((string) ($_GET['iar_no'] ?? ($latestIar ? trim((string) $latestIar['iar_no']) : '')));
    $entityName = trim((string) ($_GET['entity'] ?? ($latestIar ? trim((string) $latestIar['entity_name']) : $defaultEntity)));
    $fundCluster = trim((string) ($_GET['fund'] ?? ($latestIar ? trim((string) $latestIar['fund_cluster']) : '')));
    $supplier = trim((string) ($_GET['supplier'] ?? ($latestIar ? trim((string) $latestIar['supplier']) : ($po['supplier'] ?? ''))));
    $supplierOptions = array_column(query('SELECT name FROM suppliers ORDER BY name') ?: [], 'name');
    $poNo = trim((string) ($_GET['po_no'] ?? ($po['po_no'] ?? '')));
    $poDateRaw = trim((string) ($_GET['po_date'] ?? ($po['po_date'] ?? '')));
    $iarDateRaw = trim((string) ($_GET['iar_date'] ?? ($latestIar ? trim((string) $latestIar['iar_date']) : date('Y-m-d'))));
    $defaultOfficeDept = $latestIar ? trim((string) $latestIar['office_dept']) : '';
    if ($defaultOfficeDept === '' && !empty($po['employee_id'])) {
        $defaultOfficeDept = trim(implode(' – ', array_filter([
            (string) userName((int) $po['employee_id'], true),
            (string) risDivisionOfficeFromEmployee((int) $po['employee_id'])['office'],
        ], static fn (string $v): bool => $v !== '')));
    }
    $officeDept = trim((string) ($_GET['office_dept'] ?? $defaultOfficeDept));
    $invoiceNo = trim((string) ($_GET['invoice_no'] ?? ($latestIar ? trim((string) $latestIar['invoice_no']) : '')));
    $invoiceDateRaw = trim((string) ($_GET['invoice_date'] ?? ($latestIar ? trim((string) $latestIar['invoice_date']) : '')));
    $rcc = trim((string) ($_GET['responsibility_center_code'] ?? ($latestIar ? trim((string) $latestIar['responsibility_center_code']) : '')));
    $inspectorName = trim((string) ($_GET['inspector_name'] ?? ($latestIar ? trim((string) $latestIar['inspector_name']) : '')));
    $dateInspectedRaw = trim((string) ($_GET['date_inspected'] ?? ($latestIar ? trim((string) $latestIar['date_inspected']) : date('Y-m-d'))));
    $dateReceivedRaw = trim((string) ($_GET['date_received'] ?? ($latestIar ? trim((string) $latestIar['date_received']) : date('Y-m-d'))));
    $acceptanceStatus = trim((string) ($_GET['acceptance_status'] ?? ($latestIar ? trim((string) $latestIar['acceptance_status']) : 'Complete'))) ?: 'Complete';
    $partialQty = trim((string) ($_GET['partial_qty'] ?? ($latestIar ? trim((string) $latestIar['partial_qty']) : '')));
    $remarks = trim((string) ($_GET['remarks'] ?? ($latestIar ? trim((string) $latestIar['remarks']) : '')));
    $showForm = ($inspectorName === '') || $editing;

    $inspectorEmployees = query(
        "SELECT e.id,
                CONCAT(TRIM(e.last_name), ', ', TRIM(e.first_name),
                       IFNULL(CONCAT(' ', TRIM(e.middle_name)), ''),
                       IFNULL(CONCAT(', ', TRIM(e.name_extension)), '')) AS name
         FROM employees e
         ORDER BY e.last_name, e.first_name"
    ) ?: [];
    $inspectorJson = json_encode(array_map(static fn (array $e): array => [
        'id' => (int) $e['id'],
        'name' => (string) $e['name'],
    ], $inspectorEmployees), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

$poDate = $poDateRaw !== '' ? date('m/d/Y', strtotime($poDateRaw)) : '';
$iarDate = $iarDateRaw !== '' ? date('m/d/Y', strtotime($iarDateRaw)) : '';
$invoiceDate = $invoiceDateRaw !== '' ? date('m/d/Y', strtotime($invoiceDateRaw)) : '';
$dateInspected = $dateInspectedRaw !== '' ? date('m/d/Y', strtotime($dateInspectedRaw)) : '';
$dateReceived = $dateReceivedRaw !== '' ? date('m/d/Y', strtotime($dateReceivedRaw)) : '';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Inspection and Acceptance Report</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font:11pt "Times New Roman", Times, serif; color:#111; background:#f0f0f0; }
.toolbar { text-align:center; padding:14px; background:#fff; border-bottom:1px solid #ccc; }
.toolbar button { padding:8px 20px; font-size:11pt; cursor:pointer; border:1px solid #333; border-radius:4px; margin:0 6px; }
.toolbar .btn-print { background:#007bff; color:#fff; border-color:#007bff; }
.toolbar .btn-close { background:#6c757d; color:#fff; border-color:#6c757d; }
.sheet { box-sizing:border-box; width:210mm; min-height:297mm; margin:20px auto; padding:9mm 9mm 8mm; background:#fff; font:11pt "Times New Roman",serif; box-shadow:0 2px 8px rgba(0,0,0,.15); position:relative; }
.header { font-family:"Old English Text MT", Arial, sans-serif; text-align:center; font-size:0.35278cm; margin-bottom:1px; }
.header img { height:2cm; width:auto; }
.header2 { font-family:"Trajan Pro", Arial, sans-serif; text-align:center; font-size:0.3175cm; margin-bottom:1px; }
.appendix { position:absolute; top:9mm; right:9mm; font-weight:bold; font-style:italic; }
.report-title { text-align:center; font-weight:bold; font-size:15pt; margin:12px 0 12px; letter-spacing:1px; }
.meta { width:100%; border-collapse:collapse; margin-top:2px; }
.meta td { padding:4px 0; vertical-align:bottom; line-height:1.7; }
.meta .data { font-weight:bold; border-bottom:1px solid #111; padding:0 4px; }
.iar-table { width:100%; border-collapse:collapse; margin-top:8px; }
.iar-table th, .iar-table td { border:1.5px solid #111; padding:5px 7px; vertical-align:middle; }
.iar-table th { text-align:center; vertical-align:middle; height:38px; }
.iar-table tr.row { height:26px; }
.iar-table td.num { text-align:center; }
.footer { width:100%; border-collapse:collapse; margin-top:0; }
.footer td { border:1.5px solid #111; padding:8px 10px; vertical-align:top; line-height:1.5; }
.footer .label { text-align:center; font-weight:bold; height:28px; }
.footer .sig td { height:20px; padding-top:14px; text-align:center; }
.footer label { margin:0; font-weight:normal; }
.footer input[type="radio"], .footer input[type="checkbox"] { margin-right:4px; }
.doc-table { width:100%; border-collapse:collapse; }
.doc-table > tbody > tr > td, .doc-table > tfoot > tr > td { padding:0; }
.footer-img { text-align:center; margin-top:14px; }
.footer-img img { width:100%; max-width:210mm; height:auto; display:block; margin:0 auto; }
.sig-modal { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.55); display:flex; align-items:center; justify-content:center; z-index:9999; }
.sig-modal-box { background:#fff; width:640px; max-width:95vw; max-height:92vh; overflow:auto; padding:22px 24px; border-radius:8px; box-shadow:0 10px 40px rgba(0,0,0,.4); font-size:11pt; font-family:Arial,Helvetica,sans-serif; }
.sig-modal-box h3 { margin:0 0 4px; font-size:15pt; color:#1a3a6b; }
.modal-sub { color:#666; font-size:10pt; margin-bottom:14px; }
.fld { margin-bottom:10px; }
.fld label { display:block; font-weight:bold; font-size:10pt; margin-bottom:3px; }
.fld input[type=text], .fld input[type=date] { width:100%; padding:7px 9px; border:1px solid #999; border-radius:4px; font-size:10.5pt; box-sizing:border-box; }
.fld input[readonly] { background:#f0f0f0; }
.grid2 { display:grid; grid-template-columns:1fr 1fr; gap:0 14px; }
.grid3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:0 14px; }
.modal-actions { text-align:right; margin-top:16px; }
.modal-actions button { padding:8px 18px; font-size:10.5pt; border:none; border-radius:4px; cursor:pointer; }
.modal-actions .btn-go { background:#007bff; color:#fff; }
.modal-actions .skip { margin-right:10px; text-decoration:none; color:#666; font-size:10.5pt; }
.ac-wrap { position:relative; }
.ac-drop { display:none; position:absolute; left:0; right:0; top:100%; background:#fff; border:1px solid #999; border-top:none; max-height:180px; overflow-y:auto; z-index:1000; border-radius:0 0 4px 4px; box-shadow:0 6px 12px rgba(0,0,0,.15); }
.ac-item { padding:6px 9px; cursor:pointer; border-bottom:1px solid #f0f0f0; }
.ac-item:hover, .ac-item.active { background:#e7f0ff; }
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

	<div class="appendix">Appendix 62</div>

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

	<div class="report-title">INSPECTION AND ACCEPTANCE REPORT</div>

	<table class="meta">
		<tr>
			<td colspan="2">Entity Name : <span class="data"><?= e($entityName !== '' ? $entityName : $defaultEntity) ?></span></td>
			<td style="width:28%;">Fund Cluster : <span class="data"><?= e($fundCluster !== '' ? $fundCluster : '____________') ?></span></td>
		</tr>
	</table>

	<table class="meta">
		<tr>
			<td style="width:62%;">Supplier : <span class="data"><?= e($supplier !== '' ? $supplier : '____________________________________________') ?></span></td>
			<td>IAR No. : <span class="data"><?= e($iarNo !== '' ? $iarNo : '_________________') ?></span></td>
		</tr>
		<tr>
			<td>PO No./Date : <span class="data"><?= e(trim($poNo . ($poDate !== '' ? '  ' . $poDate : ''))) ?></span></td>
			<td>Date : <span class="data"><?= e($iarDate !== '' ? $iarDate : '_________________') ?></span></td>
		</tr>
		<tr>
			<td>Requisitioning Office/Dept. : <span class="data"><?= e($officeDept) ?></span></td>
			<td>Invoice No. : <span class="data"><?= e($invoiceNo !== '' ? $invoiceNo : '____________') ?></span></td>
		</tr>
		<tr>
			<td>Responsibility Center Code : <span class="data"><?= e($rcc) ?></span></td>
			<td>Date : <span class="data"><?= e($invoiceDate !== '' ? $invoiceDate : '_________________') ?></span></td>
		</tr>
	</table>

	<table class="iar-table" id="iar-items">
		<thead>
			<tr>
				<th style="width:18%">Stock /<br>Property No.</th>
				<th>Description</th>
				<th style="width:10%">Unit</th>
				<th style="width:12%">Quantity</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($rows as $row): ?>
				<tr data-item-id="<?= (int) $row['item_id'] ?>" data-stock="<?= e($row['stock_no']) ?>" data-desc="<?= e($row['description']) ?>" data-unit="<?= e(ucfirst((string) $row['unit'])) ?>" data-qty="<?= (int) $row['qty'] ?>">
					<td><?= e($row['stock_no']) ?></td>
					<td><?= e($row['description']) ?></td>
					<td class="num"><?= e(ucfirst((string) $row['unit'])) ?></td>
					<td class="num"><?= (int) $row['qty'] ?></td>
				</tr>
			<?php endforeach; ?>
			<?php for ($i = 0; $i < $padRows; $i++): ?>
				<tr class="row"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
			<?php endfor; ?>
		</tbody>
	</table>

	<table class="footer">
		<tr>
			<td class="label">INSPECTION</td>
			<td class="label">ACCEPTANCE</td>
		</tr>
		<tr>
			<td>Date Inspected : <span class="data"><?= e($dateInspected !== '' ? $dateInspected : '____________________') ?></span></td>
			<td>Date Received : <span class="data"><?= e($dateReceived !== '' ? $dateReceived : '____________________') ?></span></td>
		</tr>
		<tr>
			<td style="padding-top:12px; font-style:italic;"><input type="checkbox" checked disabled> Inspected, verified and found in order as to quantity and specifications</td>
			<td><input type="checkbox" <?= $acceptanceStatus === 'Complete' ? 'checked' : '' ?> disabled> Complete</td>
		</tr>
		<tr>
			<td></td>
			<td><input type="checkbox" <?= $acceptanceStatus === 'Partial' ? 'checked' : '' ?> disabled> Partial (pls. specify quantity)<?= $acceptanceStatus === 'Partial' && $partialQty !== '' ? '<br><span style="font-weight:bold;">Qty: ' . e($partialQty) . '</span>' : '' ?></td>
		</tr>
		<?php if ($remarks !== ''): ?>
		<tr><td colspan="2">Remarks : <span style="font-weight:bold;"><?= e($remarks) ?></span></td></tr>
		<?php endif; ?>
		<tr class="sig">
			<td>
				<p style="text-decoration:underline;"><?= e($inspectorName) ?> </p> 
				<regular>Inspection Officer/Inspection Committee</regular>
			</td>
			<td>
				<p style="text-decoration:underline;"><?= e($custodianName) ?></p>
				<p>Supply and/or Property Custodian</p>
			</td>
		</tr>
	</table>

	<div class="footer-img">
		<img src="image/footer.png" alt="Footer">
	</div>
</div>
</td></tr></tbody>
</table>

<?php if ($showForm): ?>
<div class="sig-modal" id="sigModal">
	<div class="sig-modal-box">
		<h3>Inspection and Acceptance Report</h3>
		<div class="modal-sub">Fill in the inspection and acceptance details before printing.</div>
		<form method="get" action="" onsubmit="return saveIarThenSubmit(this, event)">
			<input type="hidden" name="issued_id" value="<?= e(cipher((string) $editIssuedId)) ?>">
			<input type="hidden" name="po_id" value="<?= e(cipher((string) $poId)) ?>">
			<div class="grid3">
				<div class="fld"><label>IAR No.</label><input type="text" name="iar_no" value="<?= e($iarNo) ?>" placeholder=""></div>
				<div class="fld"><label>Fund Cluster</label><input type="text" name="fund" value="<?= e($fundCluster) ?>"></div>
				<div class="fld"><label>Date (IAR)</label><input type="date" name="iar_date" value="<?= e($iarDateRaw !== '' ? $iarDateRaw : date('Y-m-d')) ?>"readonly></div>
			</div>
			<div class="fld"><label>Entity Name</label><input type="text" name="entity" value="<?= e($entityName !== '' ? $entityName : $defaultEntity) ?>"readonly></div>
			<div class="grid2">
				<div class="fld"><label>Supplier</label><input type="text" name="supplier" value="<?= e($supplier) ?>" list="iar-supplier-options"><?php if ($supplierOptions): ?><datalist id="iar-supplier-options"><?php foreach ($supplierOptions as $optName): ?><option value="<?= e($optName) ?>"></option><?php endforeach; ?></datalist><?php endif; ?></div>
				<div class="fld"><label>Invoice No.</label><input type="text" name="invoice_no" value="<?= e($invoiceNo) ?>"></div>
			</div>
			<div class="grid2">
				<div class="fld"><label>PO No.</label><input type="text" name="po_no" value="<?= e($poNo) ?>"readonly></div>
				<div class="fld"><label>PO Date</label><input type="date" name="po_date" value="<?= e($poDateRaw) ?>"readonly></div>
			</div>
			<div class="grid2">
				<div class="fld"><label>Invoice Date</label><input type="date" name="invoice_date" value="<?= e($invoiceDateRaw) ?>"></div>
				<div class="fld"><label>Responsibility Center Code</label><input type="text" name="responsibility_center_code" value="<?= e($rcc) ?>"></div>
			</div>
			<div class="fld"><label>Requisitioning Office / Dept.</label><input type="text" name="office_dept" value="<?= e($officeDept) ?>"readonly></div>
			<hr style="border:none;border-top:1px solid #ccc;">
			<div class="fld ac-wrap">
				<label>Inspector — Name</label>
				<input type="text" name="inspector_name" id="inspector_input" autocomplete="off" placeholder="Type name to search" value="<?= e($inspectorName) ?>" required>
				<div class="ac-drop" id="inspector_drop"></div>
			</div>
			<div class="grid2">
				<div class="fld"><label>Date Inspected</label><input type="date" name="date_inspected" value="<?= e($dateInspectedRaw !== '' ? $dateInspectedRaw : date('Y-m-d')) ?>"></div>
				<div class="fld"><label>Date Received</label><input type="date" name="date_received" value="<?= e($dateReceivedRaw !== '' ? $dateReceivedRaw : date('Y-m-d')) ?>"></div>
			</div>
			<div class="fld">
				<label>Acceptance</label>
				<label style="font-weight:normal;"><input type="checkbox" name="acceptance_status" value="Complete" id="complete_cb" <?= $acceptanceStatus !== 'Partial' ? 'checked' : '' ?>> Complete</label>
				<label style="font-weight:normal;"><input type="checkbox" name="acceptance_status" value="Partial" id="partial_cb" <?= $acceptanceStatus === 'Partial' ? 'checked' : '' ?>> Partial (pls. specify quantity)</label>
			</div>
			<div class="fld" id="partial_qty_wrap" style="<?= $acceptanceStatus === 'Partial' ? '' : 'display:none;' ?>"><label>Partial Quantity</label><input type="text" name="partial_qty" value="<?= e($partialQty) ?>"></div>
			<div class="fld"><label>Remarks</label><input type="text" name="remarks" value="<?= e($remarks) ?>"></div>
			<div class="modal-actions">
				<a class="skip" href="<?= e(uri() . '/ims/iar-report-print.php?po_id=' . encode((string) $poId)) ?>">Skip</a>
				<button type="submit" class="btn-go">Apply</button>
			</div>
		</form>
	</div>
</div>
<script>
const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
const INSPECTOR_EMPLOYEES = <?= $inspectorJson ?: '[]' ?>;
(function () {
	var input = document.getElementById('inspector_input');
	var drop = document.getElementById('inspector_drop');
	if (!input || !drop) return;
	function hide() { drop.style.display = 'none'; }
	function show() {
		var q = input.value.trim().toLowerCase();
		if (!q) { hide(); return; }
		var results = INSPECTOR_EMPLOYEES.filter(function (e) { return e.name.toLowerCase().indexOf(q) !== -1; }).slice(0, 50);
		if (!results.length) { hide(); return; }
		drop.innerHTML = '';
		results.forEach(function (e, idx) {
			var item = document.createElement('div');
			item.className = idx === 0 ? 'ac-item active' : 'ac-item';
			item.textContent = e.name;
			item.addEventListener('mousedown', function (ev) { ev.preventDefault(); input.value = e.name; hide(); });
			drop.appendChild(item);
		});
		drop.style.display = 'block';
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
	document.addEventListener('click', function (e) { if (!input.parentNode.contains(e.target)) hide(); });
})();
(function () {
	var boxes = Array.prototype.slice.call(document.querySelectorAll('input[name="acceptance_status"]'));
	var wrap = document.getElementById('partial_qty_wrap');
	if (!boxes.length || !wrap) return;
	var partialBox = boxes.filter(function (b) { return b.value === 'Partial'; })[0];
	function sync() { if (wrap) wrap.style.display = partialBox && partialBox.checked ? '' : 'none'; }
	boxes.forEach(function (bx) {
		bx.addEventListener('change', function () {
			if (bx.checked) { boxes.forEach(function (o) { if (o !== bx) o.checked = false; }); }
			sync();
		});
	});
	sync();
})();
function saveIarThenSubmit(form, ev) {
	if (ev && ev.preventDefault) ev.preventDefault();
	var fd = new FormData();
	fd.append('csrf_token', CSRF_TOKEN);
	var fields = ['issued_id','po_id','iar_no','entity','fund','supplier','po_no','po_date','iar_date','office_dept','invoice_no','invoice_date','responsibility_center_code','inspector_name','date_inspected','date_received','acceptance_status','partial_qty','remarks'];
	for (var i = 0; i < fields.length; i++) {
		var el = form.elements[fields[i]];
		if (!el) { fd.append(fields[i], ''); continue; }
		if (fields[i] === 'acceptance_status') {
			var checkedAcc = form.querySelector('input[name="acceptance_status"]:checked');
			fd.append('acceptance_status', checkedAcc ? checkedAcc.value : 'Complete');
			continue;
		}
		if (el instanceof RadioNodeList) { fd.append(fields[i], el.value); }
		else { fd.append(fields[i], el.value); }
	}
	var rowEls = document.querySelectorAll('#iar-items tbody tr[data-item-id]');
	var items = [];
	for (var j = 0; j < rowEls.length; j++) {
		var tr = rowEls[j];
		items.push({
			item_id: parseInt(tr.getAttribute('data-item-id'), 10) || 0,
			stock_no: tr.getAttribute('data-stock') || '',
			description: tr.getAttribute('data-desc') || '',
			unit: tr.getAttribute('data-unit') || '',
			qty: parseInt(tr.getAttribute('data-qty'), 10) || 0
		});
	}
	fd.append('items', JSON.stringify(items));
	fetch('iar-report-save.php', { method: 'POST', body: fd })
		.then(function (r) { return r.json(); })
		.then(function (d) {
			if (d && d.success) {
				var el = form.elements['iar_no'];
				if (el && d.iar_no) { el.value = d.iar_no; }
				location.href = location.pathname + '?' + serializeIarForm(form);
				return;
			}
			var msg = (d && d.message) ? d.message : 'Unable to save the IAR.';
			if (window.confirm(msg + '\n\nContinue without saving?')) location.href = location.pathname + '?' + serializeIarForm(form);
		})
		.catch(function () {
			if (window.confirm('Unable to reach the server. Continue without saving?')) location.href = location.pathname + '?' + serializeIarForm(form);
		});
	return false;
}
function serializeIarForm(form) {
	var p = [];
	for (var i = 0; i < form.elements.length; i++) {
		var el = form.elements[i];
		if (!el.name || el.disabled || el.value === '') continue;
		if ((el.type === 'radio' || el.type === 'checkbox') && !el.checked) continue;
		p.push(encodeURIComponent(el.name) + '=' + encodeURIComponent(el.value));
	}
	return p.join('&');
}
</script>
<?php endif; ?>

</body>
</html>
