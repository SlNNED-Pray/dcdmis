<?php
/*
 * stock-card-print.php
 * Standalone printable Stock Card Report page (opened in a new window/tab).
 * Reuses the same transaction queries as stock-card.php but renders a clean
 * A4 printable layout with a Print / Save PDF button.
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

// All stock movements from the unified stock_movements table.
$movementStatement = $pdo->query(
	"SELECT m.created_at AS transaction_date, m.reference_no, m.movement_type, m.quantity, m.personnel, m.office, m.remarks,
			i.stock_no, i.description, i.unit
	 FROM stock_movements m
	 JOIN items i ON i.id = m.item_id
	 ORDER BY m.created_at ASC"
)->fetchAll();

// Build a unified row set from all movements.
$rows = [];
foreach ($movementStatement as $mov) {
	$absQty = abs((int) $mov['quantity']);
	$rows[] = [
		'date' => $mov['transaction_date'],
		'reference' => $mov['reference_no'] ?: '—',
		'receipt' => $mov['movement_type'] === 'Receipt' ? $absQty : '—',
		'personnel' => $mov['personnel'] ?: 'Unknown',
		'office' => $mov['office'] ?: '—',
		'qty' => $absQty,
		'type' => $mov['movement_type'],
		'stock_no' => $mov['stock_no'],
		'description' => $mov['description'],
		'unit' => $mov['unit'],
		'remarks' => $mov['remarks'] ?? '',
	];
}

// Sort the combined rows chronologically.
usort($rows, static fn (array $left, array $right): int => strcmp($left['date'], $right['date']));

// Apply the optional filter (name, office, stock no., or item description).
$rows = array_values(array_filter($rows, static function (array $row) use ($stockCardFilter): bool {
	if ($stockCardFilter === '') {
		return true;
	}
	$itemLabel = $row['stock_no'] . ' - ' . $row['description'];
	return stripos($itemLabel, $stockCardFilter) !== false
		|| stripos($row['personnel'], $stockCardFilter) !== false
		|| stripos($row['office'], $stockCardFilter) !== false;
}));
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Stock Card Report</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
/* Reset default box model for predictable A4 layout. */
* { box-sizing:border-box; margin:0; padding:0; }

/* Base typography for the printed report. */
body { font:11pt "Times New Roman", Times, serif; color:#111; background:#f0f0f0; }

/* On-screen toolbar with Print and Close buttons (hidden when printing). */
.toolbar { text-align:center; padding:14px; background:#fff; border-bottom:1px solid #ccc; }
.toolbar button { padding:8px 20px; font-size:11pt; cursor:pointer; border:1px solid #333; border-radius:4px; margin:0 6px; }
.toolbar .btn-print { background:#007bff; color:#fff; border-color:#007bff; }
.toolbar .btn-close { background:#6c757d; color:#fff; border-color:#6c757d; }

/* The A4 "paper": 210mm wide to match A4 portrait. */
.sheet { width:210mm; margin:5px auto; padding:8mm 10mm; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,.15); }

/* Letterhead header (logo + agency). */
.header { font-family:"Old English Text MT", Arial, sans-serif; text-align:center; margin-bottom:1px; }
.header img { height:2.17cm; width:auto; }
.report-title { text-align:center; font-weight:bold; font-size:15pt; margin:10px 0 4px; }
.report-sub { text-align:center; font-size:11pt; margin-bottom:12px; color:#333; }

/* The report data table. */
.report-table { width:100%; border-collapse:collapse; }
.report-table th, .report-table td { border:1px solid #111; padding:5px 6px; font-size:10pt; }
.report-table th { background:#eee; text-align:center; }
.report-table td { vertical-align:middle; }
.report-table .num { text-align:center; }

/* Print rules: strip the toolbar and expand the sheet to full A4. */
@media print {
	@page { size:A4 landscape; margin:8mm; }
	body { background:#fff; }
	.toolbar { display:none !important; }
	.sheet { margin:0; padding:0; box-shadow:none; width:auto; }
}

/* Footer image anchored at the bottom of the sheet, full width. */
.footer-img { text-align:center; margin-top:8px;   }
.footer-img img { width:100%; max-width:210mm; height:auto; display:block; margin:0 auto; }

</style>
</head>
<body>

<!-- On-screen action toolbar: Print / Save PDF and Close (hidden on print). -->
<div class="toolbar">
	<button class="btn-print" type="button" onclick="window.print()"><i class="fas fa-print"></i> Print / Save PDF</button>
	<button class="btn-close" type="button" onclick="window.close()"><i class="fas fa-times"></i> Close</button>
</div>

<!-- The A4 sheet with the report content. -->
<div class="sheet">

	<!-- Department letterhead. -->
	<div class="header">
		<img src="image/logo.png"><br>
		Republic of the Philippines<br>
		Department of Education
	</div>

	<!-- Report title and sub-title. -->
	<div class="report-title">STOCK CARD</div>
	<div class="report-sub"><?= $stockCardFilter !== '' ? 'Filter: ' . e($stockCardFilter) : 'Inventory Transactions Report' ?></div>

	<!-- The transaction table. -->
	<table class="report-table">
		<thead>
			<tr>
				<th style="width:9%">Date</th>
				<th style="width:16%">Reference</th>
				<th style="width:8%">Receipt</th>
				<th style="width:22%">Name of Personnel</th>
				<th style="width:23%">Office Name</th>
				<th style="width:8%">Qty</th>
				<th style="width:14%">Remarks</th>
			</tr>
		</thead>
		<tbody>
			<?php if (!$rows): ?>
				<tr><td colspan="7" style="text-align:center;color:#777;">No stock-card transactions found.</td></tr>
			<?php endif; ?>
			<?php foreach ($rows as $row): ?>
				<tr>
					<td><?= e(date('M d, Y', strtotime($row['date']))) ?></td>
					<td><?= e($row['reference']) ?><?php if ($row['type'] === 'Adjustment'): ?><br><span style="font-weight:bold;color:#0c5460;">(Adjustment)</span><?php elseif ($row['type'] === 'Issue'): ?><br><span style="font-weight:bold;color:#9a6b00;">(Issue)</span><?php endif; ?><br><small><?= e($row['stock_no'] . ' - ' . $row['description']) ?></small></td>
					<td class="num"><?= e((string) $row['receipt']) ?></td>
					<td><?= e($row['personnel']) ?></td>
					<td><?= e($row['office']) ?></td>
					<td class="num"><?= (int) $row['qty'] . ' ' . e($row['unit']) ?></td>
					<td><small><?= e($row['remarks'] ?: '—') ?></small></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<!-- Footer image anchored at the bottom of the page. -->
	<div class="footer-img">
		<img src="image/footer.png" alt="Footer Image">
	</div>

</div>

</body>
</html>
