<?php
/*
 * iar-print.php
 * Renders a printable Inspection and Acceptance Report (IAR) as a standalone
 * A4 HTML page (Appendix 62 format) for the specified IAR record.
 * This page is opened in a new window/tab and provides Print / Save to PDF.
 */

// Load shared helpers, database layer, and IMS-specific utilities.
require_once(__DIR__ . '/../includes/function.php');
require_once(root() . '/includes/string.php');
require_once(root() . '/includes/database/database.php');
require_once(root() . '/includes/database/account.php');
require_once(root() . '/includes/database/employee.php');
require_once(root() . '/includes/database/utility.php');
require_once(root() . '/ims/helpers.php');

// Open the shared database connection.
$pdo = connection();

// Read and decode the IAR id passed through the URL (?id=<encoded>).
// resolve() makes a safe integer; 0 means no/invalid id.
$decodedId = (int) (decode($_GET['id'] ?? '') ?: 0);

// Fetch the full IAR record; if it does not exist show a "not found" page and stop.
$iar = $decodedId > 0 ? find('SELECT * FROM iar WHERE id = ?', [$decodedId]) : null;
if (!$iar) {
?><!DOCTYPE html><html><head><meta charset="utf-8"><title>IAR Not Found</title></head><body style="font-family:sans-serif;text-align:center;padding:60px;"><p>Inspection and Acceptance Report not found.</p><p><a href="javascript:window.close()">Close window</a></p></body></html><?php
	exit;
}

// Build the "back" URL that returns to the IAR listing inside the IMS module.
$backUrl = uri() . '/ims?v=' . encode('IAR');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>IAR <?= e($iar['iar_no']) ?> — Appendix 62</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
/* Reset default box model so measurements are predictable on the A4 sheet. */
* { box-sizing:border-box; margin:0; padding:0; }

/* Base typography: the official DepEd IAR uses Times New Roman at 11pt. */
body { font:11pt "Times New Roman", Times, serif; color:#111; background:#f0f0f0; }

/* On-screen toolbar with Print and Close buttons (hidden when printing). */
.toolbar { text-align:center; padding:14px; background:#fff; border-bottom:1px solid #ccc; }
.toolbar button { padding:8px 20px; font-size:11pt; cursor:pointer; border:1px solid #333; border-radius:4px; margin:0 6px; }
.toolbar .btn-print { background:#007bff; color:#fff; border-color:#007bff; }
.toolbar .btn-close { background:#6c757d; color:#fff; border-color:#6c757d; }

/* The A4 "paper" / white sheet. width=210mm matches A4 portrait. */
.sheet { width:210mm; margin:5px auto; padding:5mm 10mm; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,.15); }

/* Flex column wrapper so the footer sticks to the bottom of the page
   while the header stays pinned to the top (they "do not move"). */
.sheet-inner { display:flex; flex-direction:column; min-height:190mm; }
.sheet-body { flex:1 0 auto; }

/* "Appendix 62" caption shown above the report title. */
.appendix { text-align:right; font-style:italic; font-size:12pt; margin-bottom:4px; }

/* Department letterhead header: logo styling + Old English title font. */
.header img { background-repeat:no-repeat; height:2.17cm; width:auto; }
.header { font-family:"Old English Text MT", Arial, sans-serif; text-align:center; font-size:0.35278cm; margin-bottom:1px; }
.header2 { font-family:"Trajan Pro", Arial, sans-serif; text-align:center; font-size:0.3175cm; margin-bottom:1px; }

/* Footer image anchored at the bottom of the sheet, full width. */
.footer-img { text-align:center; margin-top:8px;   }
.footer-img img { width:100%; max-width:210mm; height:auto; display:block; margin:0 auto; }

/* Report title. */
.sheet h1 { text-align:center; font-size:16pt; margin:8px 0 14px; letter-spacing:1px; }

/* Meta tables: entity/supplier/IAR number info block (no outer borders). */
.meta { width:100%; border-collapse:collapse; margin-bottom:2px; text-align:left; }
.meta td { padding:4px 6px; vertical-align:top; line-height:1.8; white-space:nowrap; }

/* Items table: bordered listing of stock/property, description, unit, qty. */
.items { width:100%; border-collapse:collapse; margin-top:8px; }
.items th, .items td { border:1.5px solid #111; padding:6px 8px; text-align:left; vertical-align:middle; }
.items th { text-align:center; height:40px; background:none; }
.items .blank td { height:28px; }

/* Signature/footer block table: Inspection and Acceptance columns. */
.footer { width:100%; border-collapse:collapse; margin-top:0; }
.footer td { border:1.5px solid #111; padding:8px 10px; vertical-align:top; line-height:1.7; }
.footer .label { text-align:center; font-weight:bold; height:28px; }
.footer .sig td { height:80px; padding-top:50px; text-align:center; }
.footer label { margin:0; font-weight:normal; }
.footer input[type="radio"] { margin-right:4px; }

/* Print-specific rules: remove screen chrome, expand sheet to full A4. */
@media print {
	@page { size:A4 portrait; margin:0.5inch; }
	body { background:#ffff; }
	.toolbar { display:none !important; }
	.sheet { margin:0; padding:1mm 1mm; box-shadow:none; width:210mm; }
}
</style>
</head>
<body>

<!-- On-screen action toolbar: Print / Save PDF and Close (hidden on print). -->
<div class="toolbar">
	<button class="btn-print" type="button" onclick="window.print()"><i class="fas fa-print"></i> Print / Save PDF</button>
	<button class="btn-close" type="button" onclick="window.close()"><i class="fas fa-times"></i> Close</button>
</div>

<!-- The A4 sheet: header at top, body content, footer image at the bottom. -->
<div class="sheet">

	<!-- Department letterhead (logo + agency name), kept at the top of the page. -->
	<div class="header">
		<img src="image/logo.png"> <br>
		Republic of the Philippines <br>
		Department of Education
	</div>
	<div class="header2"> Region IX – Zamboanga Peninsula <br>
		  SCHOOLS DIVISION OF DIPOLOG CITY
	</div>

	<!-- Flex container that fills the page so the footer stays at the bottom. -->
	<div class="sheet-inner">

	<!-- Main body of the report. -->
	<div class="sheet-body">

	<!-- Fiscal/Agency header info (Entity Name and Fund Cluster). -->
	<div class="appendix">Appendix 62</div>
	<h1>INSPECTION AND ACCEPTANCE REPORT</h1>

	<!-- First meta row: entity name and fund cluster. -->
	<table class="meta">
		<tr>
			<td colspan="2">Entity Name : <u>&emsp;DEPARTMENT OF EDUCATION, Schools Division of Dipolog City</u></td>
			<td>Fund Cluster :<u>&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;</u></td>
		</tr>
	</table>

	<!-- Supplier / document-number meta block (Supplier, IAR No., PO No., dates,
	     requisitioning office, invoice details, responsibility center). -->
	<table class="meta">
		<tr>
			<td>Supplier : <u>&emsp;<?= e($iar['supplier'] ?: '____________________________________________') ?>&emsp;</u></td>
			<td>IAR No. : <u>&emsp;<?= e($iar['iar_no']) ?>&emsp;</u></td>
		</tr>
		<tr>
			<td>PO No./Date : <u>&emsp;<?= e($iar['po_no'] ?: ' ') ?><?= $iar['po_date'] ? '  ' . e(date('m/d/Y', strtotime($iar['po_date']))) : '' ?>&emsp;</u></td>
			<td>Date : <u>&emsp;<?= e(date('m/d/Y', strtotime($iar['created_at']))) ?>&emsp;</u></td>
		</tr>
		<tr>
			<td>Requisitioning Office/Dept. : <u>&emsp;<?= e($iar['office_dept'] ?: '') ?>&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;</u></td>
			<td>Invoice No. : <u>&emsp;<?= e($iar['invoice_no'] ?: ' ') ?>&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;</u></td>
		</tr>
		<tr>
			<td>Responsibility Center Code : <u>&emsp;<?= e($iar['responsibility_center_code'] ?: ' ') ?>&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;</u></td>
			<td>Date : <u>&emsp;<?= $iar['invoice_date'] ? e(date('m/d/Y', strtotime($iar['invoice_date']))) : ' ' ?>&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;</u></td>
		</tr>
	</table>

	<!-- Items table: header row + one populated item row followed by blank rows
	     to fill the column height of the printed form. -->
	<table class="items">
		<thead>
			<tr>
				<th style="width:18%">Stock /<br>Property No.</th>
				<th>Description</th>
				<th style="width:10%">Unit</th>
				<th style="width:12%">Quantity</th>
			</tr>
		</thead>
		<tbody>
			<!-- The actual inspected/accepted item from the IAR record. -->
			<tr>
				<td><?= e($iar['stock_no']) ?></td>
				<td><?= e($iar['description']) ?></td>
				<td><?= e($iar['unit']) ?></td>
				<td><?= (int) $iar['quantity'] ?></td>
			</tr>
			<!-- Ten empty rows reserved for any additional line items on the form. -->
			<?php for ($i = 0; $i < 10; $i++): ?>
			<tr class="blank"><td></td><td></td><td></td><td></td></tr>
			<?php endfor; ?>
		</tbody>
	</table>

	<!-- Inspection / Acceptance block: status radios and signature lines.
	     Clean 2-column layout split into INSPECTION (left) and ACCEPTANCE (right). -->
	<table class="footer">
		<!-- Column headings: Inspection on the left, Acceptance on the right. -->
		<tr>
			<td class="label">INSPECTION</td>
			<td class="label">ACCEPTANCE</td>
		</tr>
		<!-- Inspection and acceptance dates (both default to the IAR date). -->
		<tr>
			<td>Date Inspected : <u>&emsp;<?= e(date('m/d/Y', strtotime($iar['created_at']))) ?>&emsp;</u></td>
			<td>Date Received : <u>&emsp;<?= e(date('m/d/Y', strtotime($iar['created_at']))) ?>&emsp;</u></td>
		</tr>
		<!-- Inspection remark (left) and Complete/Partial acceptance radio options (right). -->
		<tr>
			<td style="padding-top:12px; font-style:italic;">Inspected, verified and found in order as to quantity and specifications</td>
			<td><input type="radio" id="complete" name="status" checked disabled> <label for="complete">Complete</label></td>
		</tr>
		<tr>
			<td></td>
			<td><input type="radio" id="partial" name="status" disabled> <label for="partial">Partial (pls. specify quantity)</label></td>
		</tr>
		<!-- Signature lines: Inspector (left, under INSPECTION) and
		     Supply/Property Custodian (right, under ACCEPTANCE) aligned. -->
		<tr class="sig">
			<td>
				<u>&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;<?= e($iar['inspector_name']) ?>&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;</u><br>
				<small>Inspection Officer/Inspection Committee</small>
			</td>
			<td style="text-align:center;">
				<u>&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;</u><br>
				<small>Supply and/or Property Custodian</small>
			</td>
		</tr>
	</table>
	</div><!-- /sheet-body -->

	<!-- Footer image anchored at the bottom of the page. -->
	<div class="footer-img">
		<img src="image/footer.png" alt="Footer Image">
	</div>
	</div><!-- /sheet-inner -->
</div>

</body>
</html>
