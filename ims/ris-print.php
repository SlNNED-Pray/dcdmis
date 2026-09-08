<?php
require_once(__DIR__ . '/../includes/function.php');
require_once(root() . '/includes/string.php');
require_once(root() . '/includes/database/database.php');
require_once(root() . '/includes/database/account.php');
require_once(root() . '/includes/database/employee.php');
require_once(root() . '/includes/database/utility.php');
require_once(root() . '/ims/helpers.php');

$pdo = connection();
$risId = (int) (decode($_GET['id'] ?? '') ?: 0);
$ris = $risId > 0 ? find(
    "SELECT r.id, r.ris_no, r.division, r.office, r.purpose, r.created_at,
            CONCAT(e.first_name, ' ', e.last_name) AS employee
     FROM requisition_slips r
     LEFT JOIN employees e ON e.id = r.employee_id
     WHERE r.id = ?",
    [$risId]
) : null;

if (!$ris) {
?><!DOCTYPE html><html><head><meta charset="utf-8"><title>RIS Not Found</title></head><body style="font-family:sans-serif;text-align:center;padding:60px;"><p>Requisition Slip not found.</p><p><a href="javascript:window.close()">Close window</a></p></body></html><?php
	exit;
}

$lines = query(
    "SELECT d.quantity, d.unit, d.pcs_per_unit, d.has_stock, d.status,
            i.stock_no, i.description, i.unit AS item_unit
     FROM requisition_slip_items d
     JOIN items i ON i.id = d.item_id
     WHERE d.ris_id = ? AND LOWER(d.status) <> 'disapproved'
     ORDER BY d.id ASC",
    [$risId]
);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>RIS <?= e($ris['ris_no']) ?> — Requisition Slip</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font:11pt "Times New Roman", Times, serif; color:#111; background:#f0f0f0; }
.toolbar { text-align:center; padding:14px; background:#fff; border-bottom:1px solid #ccc; }
.toolbar button { padding:8px 20px; font-size:11pt; cursor:pointer; border:1px solid #333; border-radius:4px; margin:0 6px; }
.toolbar .btn-print { background:#007bff; color:#fff; border-color:#007bff; }
.toolbar .btn-close { background:#6c757d; color:#fff; border-color:#6c757d; }
.sheet { box-sizing:border-box; width:210mm; height:297mm; margin:20px auto; padding:12mm 10mm; background:#fff; font:11pt "Times New Roman",serif; box-shadow:0 2px 8px rgba(0,0,0,.15); display:flex; flex-direction:column; }
.header { font-family:"Old English Text MT", Arial, sans-serif; text-align:center; font-size:0.35278cm; margin-bottom:1px; }
.header img { height:2.17cm; width:auto; }
.header2 { font-family:"Trajan Pro", Arial, sans-serif; text-align:center; font-size:0.3175cm; margin-bottom:1px; }
.req-title { text-align:center; font-weight:bold; font-size:18pt; margin:25px 0 4px; letter-spacing:1px; }
.entity-name { text-align:center; font-weight:bold; font-size:12pt; text-decoration:underline; }
.entity-name span { text-decoration:none; font-weight:normal; }
.ris-meta { width:100%; border-collapse:collapse; margin-top:18px; }
.ris-meta td { border:1.5px solid #111; padding:6px 8px; vertical-align:top; }
.ris-meta .label { width:26%; font-weight:bold; }
.items-table { width:100%; border-collapse:collapse; margin-top:14px; }
.items-table th, .items-table td { border:1.5px solid #111; padding:6px; }
.items-table th { text-align:center; background:#eee; }
.items-table td { vertical-align:top; text-align:center; }
.items-table td.desc { text-align:left; }
.sig-box { display:flex; gap:20px; margin-top:60px; }
.sig { flex:1; text-align:center; }
.sig .line { display:inline-block; min-width:190px; border-bottom:1px solid #111; margin-top:46px; }
.purpose { margin-top:14px; border:1.5px solid #111; padding:10px; }
.purpose .label { font-weight:bold; }
.footer-img { width:100%; height:auto; text-align:center; margin-top:auto; padding-top:40px; }
.footer-img img { width:100%; max-width:210mm; height:auto; display:block; margin:0 auto; }
@media print {
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
		<img src="image/logo.png"> <br>
		Republic of the Philippines <br>
		Department of Education
	</div>
	<div class="header2"> Region IX – Zamboanga Peninsula <br>
		SCHOOLS DIVISION OF DIPOLOG CITY
	</div>

	<div class="req-title">REQUISITION SLIP</div>
	<div class="entity-name">Department of Education — <?= e($ris['division'] ?: 'Schools Division of Dipolog City') ?></div>

	<table class="ris-meta">
		<tr>
			<td class="label">RIS No.</td>
			<td><?= e($ris['ris_no']) ?></td>
			<td class="label">Date</td>
			<td><?= e(date('F d, Y', strtotime($ris['created_at']))) ?></td>
		</tr>
		<tr>
			<td class="label">Division</td>
			<td><?= e($ris['division'] ?: 'Schools Division of Dipolog City') ?></td>
			<td class="label">Office/Department</td>
			<td><?= e($ris['office'] ?: '____________________') ?></td>
		</tr>
		<tr>
			<td class="label">Requested by</td>
			<td><?= e($ris['employee'] ?: '____________________') ?></td>
			<td class="label">Entity Name</td>
			<td><?= e($ris['division'] ?: '____________________') ?></td>
		</tr>
	</table>

	<table class="items-table">
		<thead>
			<tr>
				<th style="width:8%">Qty</th>
				<th style="width:12%">Unit</th>
				<th style="width:10%">Stock No.</th>
				<th class="desc">Description</th>
			</tr>
		</thead>
		<tbody>
			<?php if (!$lines): ?>
				<tr><td colspan="4" style="text-align:center;color:#777;">No items.</td></tr>
			<?php endif; ?>
			<?php foreach ($lines as $line): ?>
				<tr>
					<td><?= (int) $line['quantity'] ?></td>
					<td><?= e(ucfirst((string) ($line['unit'] ?: $line['item_unit']))) ?></td>
					<td><?= e($line['stock_no']) ?></td>
					<td class="desc"><?= e($line['description']) ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<div class="purpose">
		<span class="label">Purpose:</span> <?= e($ris['purpose'] ?: '____________________') ?>
	</div>

	<div class="sig-box">
		<div class="sig"><span class="line"><?= e($ris['employee'] ?: '____________________') ?></span><br><strong>Requested By</strong></div>
		<div class="sig"><span class="line"></span><br><strong>Approved By</strong></div>
	</div>

	<div class="footer-img"><img src="image/footer.png" alt="Footer Image"></div>

</div>

</body>
</html>
