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

$fromDate = trim((string) ($_GET['from_date'] ?? ''));
$toDate = trim((string) ($_GET['to_date'] ?? ''));
$acctStaff = trim((string) ($_GET['acct_staff'] ?? ''));
$acctDate = trim((string) ($_GET['acct_date'] ?? date('Y-m-d')));
$acctDateDisplay = $acctDate !== '' ? date('F j, Y', strtotime($acctDate)) : '';
$showForm = $acctStaff === '';

$pdo = connection();
$statement = $pdo->query(
	"SELECT r.ris_no, r.office, i.stock_no, i.description, i.unit, i.unit_cost,
	        ri.quantity, ri.unit AS ris_unit,
	        COALESCE(sm.created_at, r.updated_at) AS issue_date
	 FROM requisition_slip_items ri
	 INNER JOIN requisition_slips r ON r.id = ri.ris_id
	 INNER JOIN items i ON i.id = ri.item_id
	 LEFT JOIN stock_movements sm
	        ON sm.item_id = ri.item_id
	       AND sm.movement_type = 'Issue'
	       AND sm.reference_no = r.ris_no COLLATE utf8mb4_unicode_ci
	 WHERE LOWER(ri.status) = 'issued'
	 ORDER BY issue_date ASC, r.ris_no ASC"
)->fetchAll();

$rows = [];
$totalQty = 0;
$totalAmount = 0.0;
foreach ($statement as $m) {
	$issueDay = (string) ($m['issue_date'] ?? '');
	$issueDay = $issueDay !== '' ? date('Y-m-d', strtotime($issueDay)) : '';
	if ($fromDate !== '' && $issueDay !== '' && $issueDay < $fromDate) {
		continue;
	}
	if ($toDate !== '' && $issueDay !== '' && $issueDay > $toDate) {
		continue;
	}
	$unit = trim((string) ($m['ris_unit'] ?: $m['unit']));
	$qty = (int) $m['quantity'];
	$cost = (float) ($m['unit_cost'] ?? 0);
	$amount = $qty * $cost;
	$rows[] = [
		'ris_no' => $m['ris_no'],
		'rc_code' => $m['office'] ?: '',
		'stock_no' => $m['stock_no'],
		'description' => $m['description'],
		'unit' => $unit,
		'quantity' => $qty,
		'unit_cost' => $cost,
		'amount' => $amount,
	];
	$totalQty += $qty;
	$totalAmount += $amount;
}

$fromDateDisplay = $fromDate !== '' ? date('F j, Y', strtotime($fromDate)) : '';
$toDateDisplay = $toDate !== '' ? date('F j, Y', strtotime($toDate)) : '';
$metaDateValue = '___________________________';
$fromShort = $fromDate !== '' ? date('M j, Y', strtotime($fromDate)) : '';
$toShort = $toDate !== '' ? date('M j, Y', strtotime($toDate)) : '';
if ($fromShort !== '' || $toShort !== '') {
	$metaDateValue = ($fromShort !== '' ? '  ' . $fromShort : 'Up to ' . $toShort)
		. ($fromShort !== '' && $toShort !== '' ? ' - ' . $toShort : '');
}
$periodLine = '';
if ($fromDateDisplay !== '' || $toDateDisplay !== '') {
	$periodLine = 'Transaction period: ' . ($fromDateDisplay !== '' ? 'From ' . $fromDateDisplay : '')
		. ($fromDateDisplay !== '' && $toDateDisplay !== '' ? '  to  ' : '')
		. ($toDateDisplay !== '' ? $toDateDisplay : '');
}

$acctEmployees = $pdo->query(
	"SELECT e.id,
	        CONCAT(TRIM(e.last_name), ', ', TRIM(e.first_name),
	               IFNULL(CONCAT(' ', TRIM(e.middle_name)), ''),
	               IFNULL(CONCAT(', ', TRIM(e.name_extension)), '')) AS name
	 FROM employees e
	 ORDER BY e.last_name, e.first_name"
)->fetchAll(PDO::FETCH_ASSOC);
$acctEmployeeJson = json_encode(array_map(static fn (array $e): array => [
	'id' => (int) $e['id'],
	'name' => (string) $e['name'],
], $acctEmployees), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$recap = [];
foreach ($rows as $r) {
	$key = $r['stock_no'];
	if (!isset($recap[$key])) {
		$recap[$key] = ['stock_no' => $key, 'qty' => 0, 'cost' => $r['unit_cost'], 'total' => 0.0];
	}
	$recap[$key]['qty'] += $r['quantity'];
	$recap[$key]['total'] += $r['amount'];
}
$recapRows = array_values($recap);

$dataCount = count($rows);
$padRows = max(0, 16 - $dataCount);
$recapCount = count($recapRows);
$recapPad = max(0, 8 - $recapCount);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Report of Supplies and Materials Issued</title>
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
.appendix { position:absolute; top:9mm; right:9mm; font-weight:bold; }
.report-title { text-align:center; font-weight:bold; font-size:16pt; margin:14px 0 10px; letter-spacing:1px; }
.meta { width:100%; border-collapse:separate; border-spacing:0; margin-top:4px; }
.meta td { padding:2px 0; vertical-align:bottom; white-space:nowrap; }
.meta .data { font-weight:bold; }
.division-note { width:100%; margin-top:8px; font-weight:bold; }
.division-note td { padding:4px 0; vertical-align:bottom; }
.rsmi-table { width:100%; border-collapse:collapse; margin-top:6px; }
.rsmi-table th, .rsmi-table td { border:1px solid #111; padding:4px 6px; }
.rsmi-table th { text-align:center; font-weight:bold; }
.rsmi-table td { vertical-align:middle; }
.rsmi-table td.desc { text-align:left; }
.rsmi-table td.num { text-align:center; }
.rsmi-table tr.row { height:22px; }
.rsmi-table tr.total td { font-weight:bold; text-align:center; }
.recap-table { width:100%; border-collapse:collapse; margin-top:16px; }
.recap-table th, .recap-table td { border:1px solid #111; padding:4px 6px; }
.recap-table th { text-align:center; font-weight:bold; }
.recap-table tr.recap-blank td { height:24px; }
.recap-table td.num { text-align:center; }
.posted-by { width:100%; text-align:right; font-weight:bold; margin:18px 0 2px; }
.certify { margin:2px 0 0; }
.sig { display:flex; width:100%; margin-top:10px; align-items:flex-end; }
.sig-block { flex:1; text-align:left; padding:0 6px; }
.sig-line { border-bottom:1px solid #111; }
.sig-label { font-size:9.5pt; font-weight:bold; margin-top:2px; }
.sig-date { text-align:center; }
.footer-img { text-align:center; margin-top:10px; }
.footer-img img { width:100%; max-width:210mm; height:auto; display:block; margin:0 auto; }
.sig-modal { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.55); display:flex; align-items:center; justify-content:center; z-index:9999; }
.sig-modal-box { background:#fff; width:480px; max-width:94vw; padding:22px 24px; border-radius:8px; box-shadow:0 10px 40px rgba(0,0,0,.4); font-size:11pt; font-family:Arial,Helvetica,sans-serif; }
.sig-modal-box h3 { margin:0 0 4px; font-size:15pt; color:#1a3a6b; }
.ics-modal-sub { color:#666; font-size:10pt; margin-bottom:16px; }
.fld { margin-bottom:12px; }
.fld label { display:block; font-weight:bold; font-size:10pt; margin-bottom:3px; }
.fld input[type=text], .fld input[type=date] { width:100%; padding:7px 9px; border:1px solid #999; border-radius:4px; font-size:10.5pt; box-sizing:border-box; }
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
.doc-table { width:100%; border-collapse:collapse; }
.doc-table > tbody > tr > td, .doc-table > tfoot > tr > td { padding:0; }
.print-footer-page { display:none; }
.print-footer-page img { width:100%; height:auto; display:block; }
@page { size:A4 portrait; margin:10mm 12mm 16mm; }
@media print {
	body { background:#fff; margin:0; }
	.toolbar { display:none !important; }
	.sig-modal { display:none !important; }
	.sheet { margin:0; width:auto; min-height:0; padding:5mm 6mm; box-shadow:none; }
	.doc-table > tfoot > tr > td { padding-top:3mm; }
	.print-footer-page { display:block !important; }
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

	<div class="appendix">Appendix 64</div>

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

	<div class="report-title">REPORT OF SUPPLIES AND MATERIALS ISSUED</div>
	<?php if ($periodLine !== ''): ?><div style="text-align:center;font-weight:bold;margin:-4px 0 8px;"><?= e($periodLine) ?></div><?php endif; ?>

	<table class="meta">
		<tr>
			<td style="width:60%;">Entity Name : <span class="data">__________________________________</span></td>
			<td style="width:40%;">Serial No. : <span class="data">_______________________</span></td>
		</tr>
		<tr>
			<td>Fund Cluster : <span class="data">________________________________</span></td>
			<td>Date : <span class="data"><?= e($acctDateDisplay) ?></span></td>
		</tr>
	</table>

	<table class="division-note">
		<tr>
			<td style="width:50%;">To be filled up by the Supply and/or Property Division/Unit</td>
			<td style="width:50%;">To be filled up by the Accounting Division/Unit</td>
		</tr>
	</table>

	<table class="rsmi-table">
		<thead>
			<tr>
				<th style="width:11%">RIS No.</th>
				<th style="width:12%">Responsibility Center Code</th>
				<th style="width:9%">Stock No.</th>
				<th style="width:27%">Item</th>
				<th style="width:7%">Unit</th>
				<th style="width:8%">Quantity Issued</th>
				<th style="width:12%">Unit Cost</th>
				<th style="width:14%">Amount</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($rows as $row): ?>
				<tr>
					<td class="num"><?= e($row['ris_no']) ?></td>
					<td class="num"> </td>
					<td class="num"><?= e($row['stock_no']) ?></td>
					<td class="desc"><?= e($row['description']) ?></td>
					<td class="num"><?= e(ucfirst($row['unit'])) ?></td>
					<td class="num"><?= (int) $row['quantity'] ?></td>
					<td class="num"><?= number_format($row['unit_cost'], 2) ?></td>
					<td class="num"><?= number_format($row['amount'], 2) ?></td>
				</tr>
			<?php endforeach; ?>
			<?php for ($i = 0; $i < $padRows; $i++): ?>
				<tr class="row"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td class="desc">&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
			<?php endfor; ?>
			<tr class="total">
				<td colspan="5">TOTAL</td>
				<td class="num"><?= (int) $totalQty ?></td>
				<td></td>
				<td class="num"><?= number_format($totalAmount, 2) ?></td>
			</tr>
		</tbody>
	</table>

	<table class="recap-table">
		<tr>
			<td colspan="4" style="font-weight:bold;">Recapitulation:</td>
			<td colspan="4" style="font-weight:bold;">Recapitulation:</td>
		</tr>
		<tr>
			<th colspan="2">Stock No.</th>
			<th colspan="2">Quantity</th>
			<th colspan="2">Unit Cost</th>
			<th colspan="1">Total Cost</th>
			<th colspan="1">UACS Object Code</th>
		</tr>
		<?php foreach ($recapRows as $rc): ?>
			<tr>
				<td colspan="2" style="text-align:center;"><?= e($rc['stock_no']) ?></td>
				<td colspan="2" style="text-align:center;"><?= (int) $rc['qty'] ?></td>
				<td colspan="2" style="text-align:center;"><?= number_format($rc['cost'], 2) ?></td>
				<td colspan="1" style="text-align:center;"><?= number_format($rc['total'], 2) ?></td>
				<td colspan="1">&nbsp;</td>
			</tr>
		<?php endforeach; ?>
		<?php for ($i = 0; $i < $recapPad; $i++): ?>
			<tr class="recap-blank"><td colspan="8">&nbsp;</td></tr>
		<?php endfor; ?>
	</table>

	
	

	<div class="sig">
		<div class="sig-block" style="flex:1.4;">
			<div class="certify">I hereby certify to the correctness of the above information.</div><br><br>
			<div class="sig-line" style = "text-align:center;"><?= e($custodianName) ?></div>
			<div class="sig-label" style = "text-align:center;">Signature over Printed Name of Supply and/or Property Custodian</div>
		</div>
		
		<div class="sig-block" style="flex:1.0;">
			<div class="posted-by" style = "text-align:center;">Posted by:</div> <br><br><br><br>
			<div class="sig-line" style = "text-align:center;"><?= e($acctStaff) ?></div>
			<div class="sig-label" style = "text-align:center;">Signature over Printed Name of Designated Accounting Staff</div>
		</div>
		<div class="sig-block" style="flex:.6;">
			<div class="sig-line" style = "text-align:center;"><?= e($acctDateDisplay) ?></div>
			<div class="sig-label" style = "text-align:center;">Date</div>&nbsp;
			
		</div>
	</div>
</div>
</td></tr></tbody>
<tfoot><tr><td><div class="print-footer-page"><img src="image/footer.png" alt="Footer Image"></div></td></tr></tfoot>
</table>

<?php if ($showForm): ?>
<div class="sig-modal" id="sigModal">
	<div class="sig-modal-box">
		<h3>Report of Supplies and Materials Issued</h3>
		<div class="ics-modal-sub">Set the transaction period and designated accounting staff before printing.</div>
		<form method="get" action="">
			<div class="fld"><label>From Date</label><input type="date" name="from_date" value="<?= e($fromDate) ?>"></div>
			<div class="fld"><label>To Date</label><input type="date" name="to_date" value="<?= e($toDate) ?>"></div>
			<hr style="border:none;border-top:1px solid #ccc;">
			<div class="fld ac-wrap">
				<label>Designated Accounting Staff — Name</label>
				<input type="text" name="acct_staff" id="acct_staff_input" autocomplete="off" placeholder="Type name to search" required>
				<div class="ac-drop" id="acct_staff_drop"></div>
			</div>
			<div class="fld"><label>Date</label><input type="date" name="acct_date" value="<?= date('Y-m-d') ?>" required></div>
			<div class="ics-actions">
				<a class="skip" href="#" onclick="document.getElementById('sigModal').style.display='none';return false;">Skip</a>
				<button type="submit" class="btn-go">Apply</button>
			</div>
		</form>
	</div>
</div>
<script>
const ACCT_EMPLOYEES = <?= $acctEmployeeJson ?: '[]' ?>;
(function () {
	var input = document.getElementById('acct_staff_input');
	var drop = document.getElementById('acct_staff_drop');
	if (!input || !drop) return;
	function hide() { drop.style.display = 'none'; }
	function show() {
		var q = input.value.trim().toLowerCase();
		if (!q) { hide(); return; }
		var results = ACCT_EMPLOYEES.filter(function (e) { return e.name.toLowerCase().indexOf(q) !== -1; }).slice(0, 50);
		if (!results.length) { hide(); return; }
		drop.innerHTML = '';
		results.forEach(function (e, idx) {
			var item = document.createElement('div');
			item.className = idx === 0 ? 'ac-item active' : 'ac-item';
			item.innerHTML = '<span class="ac-name"></span>';
			item.firstChild.textContent = e.name;
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
</script>
<?php endif; ?>

</body>
</html>