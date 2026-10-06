<?php
/*
 * physical-count-print.php
 * Standalone printable Physical Count worksheet (opened in a new window/tab).
 * Mirrors physical-count.php data but renders a clean A4 printable page with
 * blank "Actual count", "Variance" and "Remarks" columns for hand-writing
 * during the physical inventory count.
 */

// Load shared helpers, database layer, and IMS-specific utilities.
require_once(__DIR__ . '/../includes/function.php');
require_once(root() . '/includes/string.php');
require_once(root() . '/includes/database/database.php');
require_once(root() . '/includes/database/account.php');
require_once(root() . '/includes/database/employee.php');
require_once(root() . '/includes/database/utility.php');
require_once(root() . '/includes/database/school.php');
require_once(root() . '/includes/database/section.php');
require_once(root() . '/ims/helpers.php');

requireImsStaff();

// Open the shared database connection.
$pdo = connection();

// All stock items, sorted by description (same as the on-screen worksheet).
$items = $pdo->query('SELECT id, stock_no, description, unit, quantity, min_qty FROM items ORDER BY description')->fetchAll();

// Print date shown on the worksheet.
$printDate = date('F d, Y');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Physical Count Worksheet</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
/* Reset default box model for predictable A4 layout. */
* { box-sizing:border-box; margin:0; padding:0; }

/* Base typography for the printed worksheet. */
body { font:11pt "Times New Roman", Times, serif; color:#111; background:#f0f0f0; }

/* On-screen toolbar with Print and Close buttons (hidden when printing). */
.toolbar { text-align:center; padding:14px; background:#fff; border-bottom:1px solid #ccc; }
.toolbar button { padding:8px 20px; font-size:11pt; cursor:pointer; border:1px solid #333; border-radius:4px; margin:0 6px; }
.toolbar .btn-print { background:#007bff; color:#fff; border-color:#007bff; }
.toolbar .btn-close { background:#6c757d; color:#fff; border-color:#6c757d; }

/* The A4 "paper": 297mm tall to match A4 portrait. */
.sheet { width:210mm; margin:5px auto; padding:8mm 10mm; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,.15); }

/* Letterhead header (logo + agency). */
.header { font-family:"Old English Text MT", Arial, sans-serif; text-align:center; margin-bottom:1px; }
.header img { height:2.17cm; width:auto; }
.report-title { text-align:center; font-weight:bold; font-size:15pt; margin:10px 0 2px; }
.report-sub { text-align:center; font-size:11pt; margin-bottom:12px; color:#333; }

/* The worksheet table. */
.ws-table { width:100%; border-collapse:collapse; }
.ws-table th, .ws-table td { border:1px solid #111; padding:5px 6px; font-size:10pt; }
.ws-table th { background:#eee; text-align:center; }
.ws-table td { vertical-align:top; }
.ws-table .num { text-align:center; }
.ws-table td.blank { height:36px; }

/* Signature/footer block at the end of the worksheet. */
.sign-block { margin-top:40px; display:grid; grid-template-columns:1fr 1fr; column-gap:30px; }
.sign-item { text-align:center; }
.sign-line { display:inline-block; min-width:200px; border-bottom:1px solid #111; margin-top:40px; }
.sign-label { margin-top:4px; font-size:10pt; }

/* Print rules: strip the toolbar and expand the sheet to full A4. */
@media print {
	@page { size:A4 portrait; margin:8mm; }
	body { background:#fff; }
	.toolbar { display:none !important; }
	.sheet { margin:0; padding:0; box-shadow:none; width:auto; }
}

/* Footer image anchored at the bottom of the sheet, full width. */
.footer-img { text-align:center; margin-top:8px; }
.footer-img img { width:100%; max-width:210mm; height:auto; display:block; margin:0 auto; }

</style>
</head>
<body>

<!-- On-screen action toolbar: Print / Save PDF and Close (hidden on print). -->
<div class="toolbar">
	<button class="btn-print" type="button" onclick="window.print()"><i class="fas fa-print"></i> Print / Save PDF</button>
	<button class="btn-close" type="button" onclick="window.close()"><i class="fas fa-times"></i> Close</button>
</div>

<!-- The A4 sheet with the worksheet content. -->
<div class="sheet">

	<!-- Department letterhead. -->
	<div class="header">
		<img src="image/logo.png"><br>
		Republic of the Philippines<br>
		Department of Education
	</div>

	<!-- Worksheet title and sub-title. -->
	<div class="report-title">PHYSICAL COUNT OF INVENTORIES</div>
	<div class="report-sub">Date of count: <?= e($printDate) ?></div>

	<!-- The count worksheet table, with blank columns for hand-writing. -->
	<table class="ws-table">
		<thead>
			<tr>
				<th style="width:10%">Stock No.</th>
				<th style="width:28%">Description</th>
				<th style="width:8%">Unit</th>
				<th style="width:10%">Recorded<br>Qty</th>
				<th style="width:12%">Actual<br>Count</th>
				<th style="width:10%">Variance</th>
				<th style="width:22%">Remarks</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($items as $item): ?>
				<tr>
					<td class="num"><?= e($item['stock_no']) ?></td>
					<td><?= e($item['description']) ?></td>
					<td class="num"><?= e($item['unit']) ?></td>
					<td class="num"><?= (int) $item['quantity'] ?></td>
					<td class="blank">&nbsp;</td>
					<td class="blank">&nbsp;</td>
					<td class="blank">&nbsp;</td>
				</tr>
			<?php endforeach; ?>
			<?php if (!$items): ?>
				<tr><td colspan="7" style="text-align:center;color:#777;">No stock items available.</td></tr>
			<?php endif; ?>
		</tbody>
	</table>

	<!-- Signature block: counted by and verified by. -->
	<div class="sign-block">
		<div class="sign-item">
			<div class="sign-line">&nbsp;</div>
			<div class="sign-label">Counted by (Supply / Property Custodian)</div>
		</div>
		<div class="sign-item">
			<div class="sign-line">&nbsp;</div>
			<div class="sign-label">Verified by (Chief Administrative Officer / Representative)</div>
		</div>
	</div>

	<!-- Footer image anchored at the bottom of the page. -->
	<div class="footer-img">
		<img src="image/footer.png" alt="Footer Image">
	</div>

</div>

</body>
</html>