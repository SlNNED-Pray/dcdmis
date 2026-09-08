<?php
require_once(__DIR__ . '/../includes/function.php');
require_once(root() . '/includes/string.php');
require_once(root() . '/includes/database/database.php');
require_once(root() . '/includes/database/account.php');
require_once(root() . '/includes/database/employee.php');
require_once(root() . '/includes/database/utility.php');
require_once(root() . '/ims/helpers.php');

$pdo = connection();
$prId = (int) (decode($_GET['pr_id'] ?? '') ?: 0);
$pr = $prId > 0 ? find(
    "SELECT p.id, p.pr_no, p.po_no, p.po_date, p.quantity, p.unit_cost, p.total_cost, p.purpose,
            p.status, i.stock_no, i.description, i.unit, s.name AS supplier, s.address
     FROM purchase_requests p
     JOIN items i ON i.id = p.item_id
     LEFT JOIN suppliers s ON s.id = p.supplier_id
     WHERE p.id = ?",
    [$prId]
) : null;

if (!$pr) {
?><!DOCTYPE html><html><head><meta charset="utf-8"><title>PO Not Found</title></head><body style="font-family:sans-serif;text-align:center;padding:60px;"><p>Purchase Order not found.</p><p><a href="javascript:window.close()">Close window</a></p></body></html><?php
	exit;
}
$backUrl = uri() . '/ims?v=' . encode('Purchase Order');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PO <?= e($pr['po_no'] ?: $pr['pr_no']) ?> — Appendix 61</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font:11pt "Times New Roman", Times, serif; color:#111; background:#f0f0f0; }
.toolbar { text-align:center; padding:14px; background:#fff; border-bottom:1px solid #ccc; }
.toolbar button { padding:8px 20px; font-size:11pt; cursor:pointer; border:1px solid #333; border-radius:4px; margin:0 6px; }
.toolbar .btn-print { background:#007bff; color:#fff; border-color:#007bff; }
.toolbar .btn-close { background:#6c757d; color:#fff; border-color:#6c757d; }
.sheet { box-sizing:border-box; width:210mm; height:297mm; margin:20px auto; padding:12mm 10mm; background:#fff; font:11pt "Times New Roman",serif; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.15); }
.sheet h1 { text-align:center; font-size:19pt; margin:15px 0 8px; letter-spacing:1px; }
.appendix { text-align:right; font-style:italic; font-size:12pt; }
.entity-name { text-align:center; font-weight:bold; font-size:12pt; text-decoration:underline; }
.entity-name span { text-decoration:none; font-weight:normal; }
.po-table { width:100%; border-collapse:collapse; }
.po-table td, .po-table th { border:1.5px solid #111; padding:5px; vertical-align:top; }
.po-header td { height:15px; line-height:1.65; }
.gentlemen { height:40px; }
.gentlemen span { display:block; margin:5px 0 0 15px; }
.po-items { margin-top:0; }
.po-items th { text-align:center; vertical-align:middle; height:42px; }
.po-items td { height:35px; }
.po-items .blank-row td { height:10px; }
.po-items tfoot td { height:27px; }
.penalty { border:1.5px solid #111; border-top:0; margin:0; padding:10px 5px; text-align:center; }
.signatures { display:grid; grid-template-columns:1fr 1fr; border:1.5px solid #111; border-top:0; padding:15px 20px 10px; text-align:center; min-height:145px; }
.signatures div:first-child { text-align:left; }
.signatures span { display:inline-block; min-width:190px; border-bottom:1px solid #111; }
.funds td { height:90px; line-height:1.8; }
.funds td:first-child { text-align:Left; }
.funds td:first-child span { text-decoration:underline; }
@media print {
	@page { size:A4 portrait; margin:0.2inch; }
	body { background:#fff; }
	.toolbar { display:none !important; }
	.sheet { margin:0; padding:5mm 5mm; box-shadow:none; }

	/* Department letterhead header: logo styling + Old English title font. */


}
.header img { background-repeat:no-repeat; height:2.17cm; width:auto; }
.header { font-family:"Old English Text MT", Arial, sans-serif; text-align:center; font-size:0.35278cm; margin-bottom:1px; }
.header2 { font-family:"Trajan Pro", Arial, sans-serif; text-align:center; font-size:0.3175cm; margin-bottom:1px; }

/* Footer image anchored at the bottom of the sheet, full width. */
.footer-img { text-align:center; margin-top:8px;   }
.footer-img img { width:100%; max-width:210mm; height:auto; display:block; margin:0 auto; }

</style>
</head>
<body>

<div class="toolbar">
	<button class="btn-print" type="button" onclick="window.print()"><i class="fas fa-print"></i> Print / Save PDF</button>
	<button class="btn-close" type="button" onclick="window.close()"><i class="fas fa-times"></i> Close</button>
</div>

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


	<div class="appendix">Appendix 61</div>
	<h1>PURCHASE ORDER</h1>
	<div class="entity-name">DEPARTMENT OF EDUCATION<br><span>Schools Division of Dipolog City</span></div>

	<table class="po-table po-header">
		<tr>
			<td><strong>Supplier:</strong> <?= e($pr['supplier'] ?: '____________________________________________') ?><br>
				<strong>Address:</strong> <?= e($pr['address'] ?: '____________________________________________') ?><br>
				<strong>TIN:</strong> ____________________________________________</td>
			<td><strong>P.O. No.:</strong> <?= e($pr['po_no'] ?: $pr['pr_no']) ?><br>
				<strong>Date:</strong> <?= $pr['po_date'] ? e(date('F d, Y', strtotime($pr['po_date']))) : '____________________________' ?><br>
				<strong>Mode of Procurement:</strong> __________________________</td>
		</tr>
		<tr><td colspan="2" class="gentlemen"><strong>Gentlemen:</strong><br><span>Please furnish this Office the following articles subject to the terms and conditions contained herein:</span></td></tr>
		<tr>
			<td><strong>Place of Delivery:</strong> ______________________________<br><strong>Date of Delivery:</strong> ______________________________</td>
			<td><strong>Delivery Term:</strong> ______________________________<br><strong>Payment Term:</strong> ______________________________</td>
		</tr>
	</table>

	<table class="po-table po-items">
		<thead><tr><th>Stock /<br>Property No.</th><th>Unit</th><th>Description</th><th>Quantity</th><th>Unit Cost</th><th>Amount</th></tr></thead>
		<tbody>
			<tr>
				<td><?= e($pr['stock_no']) ?></td>
				<td><?= e($pr['unit']) ?></td>
				<td><?= e($pr['description']) ?><br><small><?= e($pr['purpose'] ?: '') ?></small></td>
				<td><?= (int) $pr['quantity'] ?></td>
				<td>&#8369; <?= number_format((float) $pr['unit_cost'], 2) ?></td>
				<td>&#8369; <?= number_format((float) $pr['total_cost'], 2) ?></td>
				
			</tr>
			<tr class="blank-row">
				<td></td><td></td><td></td><td></td><td></td><td></td>
		</tbody>
		<tfoot><tr><td colspan="6"><strong>(Total Amount in Words)</strong> <?= e(poNumberToWords((float) $pr['total_cost'])) ?></tfoot>
	</table>

	<p class="penalty">In case of failure to make the full delivery within the time specified above, a penalty of one-tenth (1/10) of one percent for every day of delay shall be imposed on the undelivered item/s.</p>
	<div class="signatures">
		<div><strong>Conforme:</strong></br><span> </span><br>Signature over Printed Name of Supplier<br><br><span> </span><br>Date</div>
		<div><strong>Very truly yours,</strong><br><br><span> </span><br>Signature over Printed Name of Authorized Official<br><br><span> </span><br>Designation</div>
	</div>
	<table class="po-table funds">
		<tr>
			<td><strong>Fund Cluster:</strong><span> </span>  <br><strong>Funds Available:</strong>  <br><span> </span><br>Signature over Printed Name of Chief Accountant/Head of Accounting Division/Unit</td>
			<td><strong>ORS/BURS No.:</strong><span> </span>  <br><strong>Date of the ORS/BURS:</strong> <br><strong>Amount:</strong> &#8369; <?= number_format((float) $pr['total_cost'], 2) ?></td>
		</tr>
	</table>
	<!-- Footer image anchored at the bottom of the page. -->
	<div class="footer-img">
		<img src="image/footer.png" alt="Footer Image">
	</div>
</div>
	

</body>
</html>
