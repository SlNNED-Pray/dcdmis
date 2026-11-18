<?php
/*
 * stock-card-print.php
 * Standalone printable Stock Card Report (opened in a new window/tab).
 *
 * The layout reproduces the official template "Stock-Card.xls" (Appendix 58):
 * A4 portrait, "Appendix 58" tag, STOCK CARD title, entity/fund cluster and
 * item fields, then the two-row header and 29 entry rows of the form.
 *
 * When the filter names a stock item, that item's details and movements are
 * printed. Without a filter the page prints a blank form for hand-writing.
 */

// Load shared helpers, database layer, and IMS-specific utilities.
require_once(__DIR__ . '/../includes/function.php');
require_once(root() . '/includes/string.php');
require_once(root() . '/includes/database/database.php');
require_once(root() . '/includes/database/account.php');
require_once(root() . '/includes/database/employee.php');
require_once(root() . '/includes/database/utility.php');
require_once(root() . '/includes/database/school.php'); // provides station(), schoolByAlias(), schoolById()
require_once(root() . '/includes/database/section.php'); // provides section() used by stationName()
require_once(root() . '/ims/helpers.php');

requireImsStaff();

// Open the shared database connection.
$pdo = connection();

// Optional filter read from the URL (same behaviour as the on-screen page).
$stockCardFilter = trim($_GET['filter'] ?? '');

// Stock items, used to recognise the item a filter refers to.
$items = $pdo->query(
	'SELECT id, stock_no, description, unit, min_qty FROM items ORDER BY description'
)->fetchAll();

// All stock movements in chronological order, so a running balance can be
// computed per item before the rows are filtered.
$movementStatement = $pdo->query(
	"SELECT m.id, m.item_id, m.created_at AS transaction_date, m.reference_no, m.movement_type,
			m.quantity, m.personnel, m.office, m.remarks,
			i.stock_no, i.description, i.unit
	 FROM stock_movements m
	 JOIN items i ON i.id = m.item_id
	 ORDER BY m.item_id ASC, m.created_at ASC, m.id ASC"
)->fetchAll();

$balances = [];
$rows = [];
foreach ($movementStatement as $mov) {
	$itemId = (int) $mov['item_id'];
	$signedQty = (int) $mov['quantity'];
	$balances[$itemId] = ($balances[$itemId] ?? 0) + $signedQty;
	$absQty = abs($signedQty);

	// Receipt / Issue columns: adjustments are filed under the column that
	// matches the direction of the correction.
	$isReceipt = $mov['movement_type'] === 'Receipt' || ($mov['movement_type'] === 'Adjustment' && $signedQty >= 0);
	$isIssue = $mov['movement_type'] === 'Issue' || ($mov['movement_type'] === 'Adjustment' && $signedQty < 0);

	$rows[] = [
		'item_id' => $itemId,
		'date' => $mov['transaction_date'],
		'reference' => $mov['reference_no'] ?: '',
		'receipt' => $isReceipt ? $absQty : '',
		'issue' => $isIssue ? $absQty : '',
		'office' => $mov['office'] ?: '',
		'balance' => $balances[$itemId],
		'days' => '',
	];
}

// Recognise the item named by the filter (exact label, stock no., description,
// then the first partial match).
$selectedItem = null;
if ($stockCardFilter !== '') {
	$needle = strtolower($stockCardFilter);
	foreach ($items as $item) {
		$label = strtolower($item['stock_no'] . ' - ' . $item['description']);
		if (
			$needle === $label
			|| $needle === strtolower($item['stock_no'])
			|| $needle === strtolower($item['description'])
		) {
			$selectedItem = $item;
			break;
		}
	}
	if ($selectedItem === null) {
		foreach ($items as $item) {
			if (
				stripos($item['stock_no'], $stockCardFilter) !== false
				|| stripos($item['description'], $stockCardFilter) !== false
			) {
				$selectedItem = $item;
				break;
			}
		}
	}
}

if ($selectedItem !== null) {
	$rows = array_values(array_filter(
		$rows,
		static fn (array $row): bool => $row['item_id'] === (int) $selectedItem['id']
	));
} else {
	// Blank stock card: the form is printed empty, ready to be filled in.
	$rows = [];
}

// Field values shown in the form header.
$entityName = '';
if (!empty($stationId) && function_exists('schoolById')) {
	$school = schoolById($stationId);
	if ($school && !empty($school['name'])) {
		$entityName = $school['name'];
	}
}
if ($entityName === '') {
	$entityName = SITE_TITLE;
}

$itemName = $selectedItem['description'] ?? '';
$stockNo = $selectedItem['stock_no'] ?? '';
$reorderPoint = $selectedItem !== null ? (string) (int) $selectedItem['min_qty'] : '';
$unitOfMeasure = $selectedItem['unit'] ?? '';

// The template has 29 entry rows; keep them on the form even when there are
// fewer transactions so the printed card matches Stock-Card.xls.
$entryRowCount = max(29, count($rows));
$blankRowCount = $entryRowCount - count($rows);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Stock Card</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
/* Reset default box model for predictable A4 layout. */
* { box-sizing:border-box; margin:0; padding:0; }

/* Base typography matching the Times New Roman form. */
body { font:11pt "Times New Roman", Times, serif; color:#000; background:#f0f0f0; }

/* On-screen toolbar with Print and Close buttons (hidden when printing). */
.toolbar { text-align:center; padding:12px 14px; background:#fff; border-bottom:1px solid #ccc; }
.toolbar button { padding:8px 20px; font-size:11pt; cursor:pointer; border:1px solid #333; border-radius:4px; margin:0 6px; }
.toolbar .btn-print { background:#007bff; color:#fff; border-color:#007bff; }
.toolbar .btn-close { background:#6c757d; color:#fff; border-color:#6c757d; }
.toolbar .hint { font-size:9.5pt; color:#555; margin-top:8px; }
.toolbar .hint strong { color:#111; }

/* The A4 "paper": 210mm wide to match A4 portrait. */
.sheet { width:210mm; min-height:297mm; margin:5px auto; padding:8mm 7.9mm 8mm 9.4mm; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,.15); }

/* The template prints at 80% scale inside the A4 margins. */
.form { width:185.2mm; margin:0 auto; }

/* Department letterhead above the form. */
.letterhead { text-align:center; margin:0 0 2mm; }
.letterhead img { display:block; height:11mm; width:auto; margin:0 auto 1mm; }
.letterhead .line1 { font-family:"Old English Text MT", Arial, serif; font-size:9pt; line-height:1.05; }
.letterhead .line2 { font-family:"Trajan Pro", Arial, sans-serif; font-size:8pt; font-weight:bold; line-height:1.05; }

/* Division footer image at the bottom of the form. */
.sheet-footer { text-align:center; margin-top:2mm; }
.sheet-footer img { width:120mm; max-width:100%; height:auto; display:block; margin:0 auto; }

/* Appendix tag, top right. */
.appendix { text-align:right; font-size:12.8pt; font-style:italic; line-height:6.6mm; height:6.6mm; }

/* Report title. */
.report-title { text-align:center; font-size:11.2pt; font-weight:bold; line-height:5mm; height:5mm; margin:4mm 0 3.5mm; letter-spacing:.4px; }

/* Entity / fund cluster line above the item box. */
.meta { width:100%; border-collapse:collapse; table-layout:fixed; }
.meta td { padding:0 1mm; font-size:9.6pt; font-weight:bold; vertical-align:middle; height:4.4mm; }
.meta td.right { text-align:left; }

/* Item header fields (bordered box with a divider before the right column). */
.fields { width:100%; border-collapse:collapse; table-layout:fixed; margin-bottom:0; }
.fields td { border:.5pt solid #000; padding:0 1.2mm; font-size:9.6pt; vertical-align:middle; height:5.2mm; }
.fields tr:first-child td { border-top:1pt solid #000; }
.fields td:first-child { border-left:1pt solid #000; }
.fields td:last-child { border-right:1pt solid #000; }
.fields tr:last-child td { border-bottom:1pt solid #000; }
.fields td.divider { border-left:1pt solid #000; }
.fields td.value { font-size:10.5pt; line-height:1.1; overflow-wrap:anywhere; }
.fields tr.spacer td { height:1mm; padding:0; }

/* The stock card entry table. */
.entry-table { width:100%; border-collapse:collapse; table-layout:fixed; }
.entry-table th, .entry-table td { border:.5pt solid #000; padding:0 1mm; font-size:9.6pt; vertical-align:middle; }
.entry-table thead th { text-align:center; line-height:1.1; }
.entry-table thead tr:first-child th { border-top:1pt solid #000; font-weight:bold; font-style:italic; height:4.7mm; }
.entry-table thead tr:last-child th { font-weight:normal; font-style:normal; height:4.6mm; }
.entry-table thead th:first-child { border-left:1pt solid #000; }
.entry-table thead th:last-child { border-right:1pt solid #000; }
.entry-table td:first-child { border-left:1pt solid #000; }
.entry-table td:last-child { border-right:1pt solid #000; }
.entry-table tbody tr:last-child td { border-bottom:1pt solid #000; }
.entry-table td.col-date, .entry-table td.col-number { text-align:center; }
.entry-table td.col-date { font-size:9.5pt; white-space:nowrap; }
.entry-table td.col-text { text-align:left; overflow-wrap:anywhere; font-size:8pt; line-height:1.05; }
.entry-table td.col-number { font-size:10.5pt; }
.entry-table td.row-height { height:6.4mm; }
.entry-table tbody td { font-size:10pt; line-height:1.05; }

/* Print rules: strip the toolbar and expand the sheet to full A4. */
@media print {
	@page { size:A4 portrait; margin:8mm 7.9mm 8mm 9.4mm; }
	body { background:#fff; }
	.toolbar { display:none !important; }
	.sheet { margin:0; padding:0; box-shadow:none; width:auto; min-height:0; }
	.form { width:185.2mm; margin:0 auto; }
	.entry-table thead { display:table-header-group; }
	.entry-table tr { page-break-inside:avoid; }
}
</style>
</head>
<body>

<!-- On-screen action toolbar: Print / Save PDF and Close (hidden on print). -->
<div class="toolbar">
	<button class="btn-print" type="button" onclick="window.print()"><i class="fas fa-print"></i> Print / Save PDF</button>
	<button class="btn-close" type="button" onclick="window.close()"><i class="fas fa-times"></i> Close</button>
	<div class="hint">
		<?php if ($selectedItem !== null): ?>
			Stock card for <strong><?= e($selectedItem['stock_no'] . ' - ' . $selectedItem['description']) ?></strong>
		<?php else: ?>
			No item selected &mdash; printing a blank stock card. Choose an item in the Stock Card filter to fill it in.
		<?php endif ?>
	</div>
</div>

<!-- The A4 sheet with the stock card form. -->
<div class="sheet">
<div class="form">

	<div class="appendix">Appendix 58</div>

	<!-- Department letterhead, matching the other IMS printouts. -->
	<div class="letterhead">
		<img src="image/logo.png" alt="Department of Education logo">
		<div class="line1">Republic of the Philippines</div>
		<div class="line1">Department of Education</div>
		<div class="line2">Region IX &ndash; Zamboanga Peninsula</div>
		<div class="line2">SCHOOLS DIVISION OF DIPOLOG CITY</div>
	</div>

	<div class="report-title">STOCK CARD</div>

	<!-- Entity / fund cluster line. -->
	<table class="meta">
		<colgroup>
			<col style="width:10.21%">
			<col style="width:12.80%">
			<col style="width:10.67%">
			<col style="width:10.67%">
			<col style="width:21.65%">
			<col style="width:14.33%">
			<col style="width:19.66%">
		</colgroup>
		<tr>
			<td colspan="4">Entity Name: <?= e($entityName) ?></td>
			<td></td>
			<td colspan="2" class="right">Fund Cluster: _________________</td>
		</tr>
	</table>

	<!-- Item fields. -->
	<table class="fields">
		<colgroup>
			<col style="width:10.21%">
			<col style="width:12.80%">
			<col style="width:10.67%">
			<col style="width:10.67%">
			<col style="width:21.65%">
			<col style="width:14.33%">
			<col style="width:19.66%">
		</colgroup>
		<tr class="spacer"><td colspan="5"></td><td class="divider"></td><td></td></tr>
		<tr>
			<td class="label">Item :</td>
			<td colspan="4" class="value"><?= e($itemName) ?></td>
			<td class="label divider">Stock No. :</td>
			<td class="value"><?= e($stockNo) ?></td>
		</tr>
		<tr>
			<td class="label" colspan="2">Description :</td>
			<td colspan="3" class="value"><?= e($itemName) ?></td>
			<td class="label divider">Re-order Point :</td>
			<td class="value"><?= e($reorderPoint) ?></td>
		</tr>
		<tr>
			<td class="label" colspan="2">Unit of Measurement :</td>
			<td colspan="5" class="value"><?= e($unitOfMeasure) ?></td>
		</tr>
	</table>

	<!-- Entry table: Date | Reference | Receipt Qty | Issue Qty | Office | Balance Qty | No. of Days to Consume -->
	<table class="entry-table">
		<colgroup>
			<col style="width:10.21%">
			<col style="width:12.80%">
			<col style="width:10.67%">
			<col style="width:10.67%">
			<col style="width:21.65%">
			<col style="width:14.33%">
			<col style="width:19.66%">
		</colgroup>
		<thead>
			<tr>
				<th rowspan="2">Date</th>
				<th rowspan="2">Reference</th>
				<th>Receipt</th>
				<th colspan="2">Issue</th>
				<th>Balance</th>
				<th rowspan="2">No. of Days to Consume</th>
			</tr>
			<tr>
				<th>Qty.</th>
				<th>Qty.</th>
				<th>Office</th>
				<th>Qty.</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($rows as $row): ?>
				<tr>
					<td class="row-height col-date"><?= e(date('m/d/Y', strtotime($row['date']))) ?></td>
					<td class="col-text"><?= e($row['reference']) ?></td>
					<td class="col-number"><?= $row['receipt'] === '' ? '' : e((string) $row['receipt']) ?></td>
					<td class="col-number"><?= $row['issue'] === '' ? '' : e((string) $row['issue']) ?></td>
					<td class="col-text"><?= e($row['office']) ?></td>
					<td class="col-number"><?= e((string) $row['balance']) ?></td>
					<td class="col-number"><?= e((string) $row['days']) ?></td>
				</tr>
			<?php endforeach; ?>
			<?php for ($i = 0; $i < $blankRowCount; $i++): ?>
				<tr>
					<td class="row-height">&nbsp;</td>
					<td>&nbsp;</td>
					<td>&nbsp;</td>
					<td>&nbsp;</td>
					<td>&nbsp;</td>
					<td>&nbsp;</td>
					<td>&nbsp;</td>
				</tr>
			<?php endfor; ?>
		</tbody>
	</table>

	<!-- Division footer image, matching the other IMS printouts. -->
	<div class="sheet-footer">
		<img src="image/footer.png" alt="Footer Image">
	</div>

</div>
</div>

</body>
</html>
