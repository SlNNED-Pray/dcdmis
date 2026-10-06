<?php
require_once(__DIR__ . '/../includes/function.php');
require_once(root() . '/includes/string.php');
require_once(root() . '/includes/database/database.php');
require_once(root() . '/includes/database/account.php');
require_once(root() . '/includes/database/employee.php');
require_once(root() . '/includes/database/school.php');
require_once(root() . '/includes/database/section.php');
require_once(root() . '/includes/database/position.php');
require_once(root() . '/includes/database/utility.php');
require_once(root() . '/ims/helpers.php');

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$pdo = connection();
$risId = (int) (decode($_GET['id'] ?? '') ?: 0);
$ris = $risId > 0 ? find(
    "SELECT r.id, r.ris_no, r.division, r.office, r.purpose, r.created_at, r.employee_id,
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

if (!imsIsStaff() && (int) ($ris['employee_id'] ?? 0) !== (int) ($userId ?? 0)) {
	imsDeny();
}

$lines = query(
    "SELECT d.id, d.item_id, d.quantity, d.unit, d.pcs_per_unit, d.has_stock, d.status,
            i.stock_no, i.description, i.unit AS item_unit
     FROM requisition_slip_items d
     JOIN items i ON i.id = d.item_id
     WHERE d.ris_id = ? AND LOWER(d.status) <> 'disapproved'
     ORDER BY d.id ASC",
    [$risId]
);

$designation = '';
if (!empty($ris['employee_id'])) {
    $posRec = find(
        "SELECT p.official_title FROM station_assignments sa
         INNER JOIN positions p ON p.id = sa.position_id
         WHERE sa.employee_id = ? ORDER BY sa.assignment_date DESC LIMIT 1",
        [(int) $ris['employee_id']]
    );
    $designation = $posRec ? (string) $posRec['official_title'] : '';
}

$approvedByHead = section('PSS')['head_id'] ?? null;
$approvedByName = !empty($approvedByHead) ? userName((int) $approvedByHead, true) : '';
$approvedByDesignation = !empty($approvedByHead)
    ? (string) (position((int) $approvedByHead)['official_title'] ?? 'Supply Office Administrative Officer V')
    : 'Supply Office Administrative Officer V';

$issuedByName = trim((string) ($_GET['issued_by'] ?? ''));
$issuedByDesignation = trim((string) ($_GET['issued_designation'] ?? ''));
$issuedByDate = trim((string) ($_GET['issued_date'] ?? date('Y-m-d')));
$receivedByName = trim((string) ($_GET['received_by'] ?? ''));
$receivedByDesignation = trim((string) ($_GET['received_designation'] ?? ''));
$receivedByDate = trim((string) ($_GET['received_date'] ?? date('Y-m-d')));
$showSigForm = ($issuedByName === '' && $receivedByName === '');
$issuedByDateDisplay = $issuedByDate !== '' ? date('F j, Y', strtotime($issuedByDate)) : '';
$receivedByDateDisplay = $receivedByDate !== '' ? date('F j, Y', strtotime($receivedByDate)) : '';

$sigEmployees = query(
    "SELECT e.id,
            CONCAT(e.first_name, ' ', IFNULL(CONCAT(e.middle_name, ' '), ''), e.last_name, IFNULL(CONCAT(', ', e.name_extension), '')) AS name,
            (SELECT p.official_title FROM station_assignments sa
               INNER JOIN positions p ON p.id = sa.position_id
              WHERE sa.employee_id = e.id
              ORDER BY sa.assignment_date DESC LIMIT 1) AS designation
     FROM employees e
     ORDER BY e.last_name, e.first_name"
);
$sigEmployeeJson = json_encode(array_map(static function ($e) {
    return ['id' => (int) $e['id'], 'name' => (string) $e['name'], 'designation' => (string) ($e['designation'] ?: '')];
}, $sigEmployees), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$entityName = 'DEPED DIVISION OF DIPOLOG CITY';
$divisionName = $ris['division'] ?: 'Dipolog City';
$officeName = !empty($ris['employee_id']) ? risDivisionOfficeFromEmployee((int) $ris['employee_id'])['office'] : ($ris['office'] ?: '');
$requestedName = $ris['employee'] ?: '____________________';
$requestedDesignation = $designation ?: '____________________';
$risDate = date('F j, Y', strtotime($ris['created_at']));

if (!$showSigForm && !empty($ris) && !empty($lines)) {
    $movementDate = $issuedByDate !== '' ? date('Y-m-d H:i:s', strtotime($issuedByDate)) : date('Y-m-d H:i:s');
    foreach ($lines as $line) {
        $dup = find(
            "SELECT id FROM stock_movements
             WHERE item_id = ? AND movement_type = 'Issue' AND reference_no = ?
             LIMIT 1",
            [(int) $line['item_id'], $ris['ris_no']]
        );
        if ($dup) {
            continue;
        }
        insert('stock_movements', [
            'item_id' => (int) $line['item_id'],
            'movement_type' => 'Issue',
            'quantity' => -(int) $line['quantity'],
            'reference_no' => $ris['ris_no'],
            'personnel' => $issuedByName !== '' ? $issuedByName : 'Unknown',
            'office' => $officeName !== '' ? $officeName : ($ris['office'] ?: ''),
            'remarks' => ($receivedByName !== '' ? 'Received by ' . $receivedByName : 'Issued')
                . ($ris['purpose'] !== '' ? ' — ' . $ris['purpose'] : ''),
            'created_at' => $movementDate,
        ]);
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>RIS <?= e($ris['ris_no']) ?> — Requisition and Issue Slip</title>
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
.ris-title { text-align:center; font-weight:bold; font-size:20pt; margin:14px 0 10px; letter-spacing:2px; }
.meta { width:100%; border-collapse:separate; border-spacing:0; margin-top:4px; }
.meta td { padding:3px 0; vertical-align:bottom; }
.meta .data { font-weight:bold; }
.items-table { width:100%; border-collapse:collapse; margin-top:6px; }
.items-table th, .items-table td { border:1px solid #111; padding:4px 6px; }
.items-table th { text-align:center; font-weight:bold; }
.items-table td { vertical-align:middle; text-align:center; }
.items-table td.desc { text-align:left; }
.empty-row { height:26px; }
.purpose-row { height:28px; }
.purpose-row td { text-align:left; }
.purpose-label { font-weight:bold; }
.sig-table { width:100%; border-collapse:collapse; margin-top:26px; table-layout:fixed; }
.sig-table td { border:1px solid #111; padding:6px 10px 14px; vertical-align:top; text-align:left; }
.sig-table .head { font-weight:bold; text-align:center; margin-bottom:12px; }
.sig-lines { width:100%; border-bottom:1px solid #111; margin:32px 0 2px; }
.sig-lines.small { margin-top:26px; }
.sheet-footer { position:absolute; left:9mm; right:9mm; bottom:8mm; }
.code { position:absolute; bottom:34mm; left:9mm; font-size:9pt; }
.sig-modal { position:fixed; inset:0; background:rgba(0,0,0,.55); display:flex; align-items:center; justify-content:center; z-index:9999; }
.sig-modal-box { background:#fff; border:1px solid #333; box-shadow:0 6px 24px rgba(0,0,0,.4); width:640px; max-width:94vw; max-height:92vh; overflow:auto; padding:22px 26px; font:12pt "Times New Roman",serif; }
.sig-modal-box h3 { text-align:center; margin:0 0 14px; }
.sig-field { margin-bottom:12px; position:relative; }
.sig-field label { display:block; font-weight:bold; margin-bottom:3px; }
.sig-field input[type=text], .sig-field input[type=date] { width:100%; padding:6px 8px; font:11pt "Times New Roman",serif; border:1px solid #999; box-sizing:border-box; }
.sig-field input[readonly] { background:#f4f4f4; }
.emp-suggestions { position:absolute; left:0; right:0; top:100%; z-index:2000; background:#fff; border:1px solid #999; list-style:none; margin:0; padding:0; max-height:200px; overflow:auto; }
.emp-suggestions li { padding:6px 10px; cursor:pointer; }
.emp-suggestions li:hover, .emp-suggestions li.active { background:#e8f0fe; }
.sig-modal .actions { text-align:center; margin-top:18px; }
.sig-modal .actions button, .sig-modal .actions a { display:inline-block; padding:7px 26px; margin:0 6px; font-size:11pt; cursor:pointer; text-decoration:none; border:1px solid #333; background:#fff; color:#111; }
.sig-modal .actions button.btn-go { background:#007bff; border-color:#007bff; color:#fff; }
@media print {
	@page { size:A4 portrait; margin:0.2inch; }
	body { background:#fff; }
	.toolbar { display:none !important; }
	.sig-modal { display:none !important; }
	.sheet { margin:0; padding:5mm 6mm; box-shadow:none; }
}
</style>
</head>
<body>

<div class="toolbar">
	<button class="btn-print" type="button" onclick="window.print()"><i class="fas fa-print"></i> Print / Save PDF</button>
	<button class="btn-close" type="button" onclick="window.close()"><i class="fas fa-times"></i> Close</button>
</div>

<div class="sheet">

	<div class="appendix">Appendix 63</div>

	<div class="header">
		<img src="image/logo.png"> <br>
		Republic of the Philippines <br>
		Department of Education
	</div>
	<div class="header2"> Region IX – Zamboanga Peninsula <br>
		SCHOOLS DIVISION OF DIPOLOG CITY
	</div>

	<div class="ris-title">REQUISITION AND ISSUE SLIP</div>

	<table class="meta">
		<tr>
			<td style="width:58%;">Entity Name : <span class="data"><?= e($entityName) ?></span></td>
			<td>Fund Cluster : ______________________</td>
		</tr>
		<tr>
			<td>Division : <span class="data"><?= e($divisionName) ?></span></td>
			<td>Responsibility Center Code : ______________________</td>
		</tr>
		<tr>
			<td>Office : <span class="data"><?= e($officeName) ?></span></td>
			<td>RIS No. : <span class="data"><?= e($ris['ris_no']) ?></span></td>
		</tr>
	</table>

	<table class="items-table">
		<thead>
			<tr>
				<th colspan="4">Requisition</th>
				<th colspan="2">Stock Available?</th>
				<th colspan="2">Issue</th>
			</tr>
			<tr>
				<th style="width:10%">Stock No.</th>
				<th style="width:10%">Unit</th>
				<th style="width:42%">Description</th>
				<th style="width:10%">Quantity</th>
				<th style="width:8%">Yes</th>
				<th style="width:8%">No</th>
				<th style="width:6%">Quantity</th>
				<th style="width:6%">Remarks</th>
			</tr>
		</thead>
		<tbody>
			<?php if (!$lines): ?>
				<tr><td colspan="8" style="text-align:center;color:#777;">No items.</td></tr>
			<?php endif; ?>
			<?php foreach ($lines as $line): ?>
				<tr>
					<td><?= e($line['stock_no']) ?></td>
					<td><?= e(ucfirst((string) ($line['unit'] ?: $line['item_unit']))) ?></td>
					<td class="desc"><?= e($line['description']) ?></td>
					<td><?= (int) $line['quantity'] ?></td>
<td><?= $line['has_stock'] ? '✓' : '' ?></td>
					<td><?= $line['has_stock'] ? '' : '✓' ?></td>
					<td><?= (int) $line['quantity'] ?></td>
					<td><?= e(ucfirst((string) $line['status'])) ?></td>
				</tr>
			<?php endforeach; ?>
			<tr class="purpose-row">
				<td colspan="8"><span class="purpose-label">Purpose:</span> <?= e($ris['purpose'] ?: '____________________') ?></td>
			</tr>
			<?php for ($i = count($lines); $i < 5; $i++): ?>
				<tr class="empty-row"><td colspan="8"></td></tr>
			<?php endfor; ?>
			
			
		</tbody>
	</table>

	<table class="sig-table">
		<tr>
			<td style="width:15%">
				<div> </div><br><br><br>
				<div>Signature:</div><br><br> 
				<div>Printed Name:</div><br><br> 
				<div>Designation:  </div><br><br>
				<div>Date: </div>
			</td>
			<td>
				<div class="head">Requested by:</div><br>
				<div class="sig-lines"> </div><br>
				<div>  <?= e($requestedName) ?></div><br><br>
				<div>  <?= e($requestedDesignation) ?></div><br>
				<div> <?= e($risDate) ?></div>
			</td>
			<td>
				<div class="head">Approved by:</div><br>
				<div class="sig-lines"> </div><br>
				<div> <?= e($approvedByName) ?></div><br>
				<div> <?= e($approvedByDesignation) ?></div><br>
				<div> <?= e($risDate) ?> </div>
			</td>
			<td>
				<div class="head">Issued by:</div><br>
				<div class="sig-lines"> </div><br>
				<div>  <?= e($issuedByName) ?></div><br> 
				<div>  <?= e($issuedByDesignation) ?></div><br>
				<div> <?= e($issuedByDateDisplay) ?> </div>
			</td>
			<td>
				<div class="head">Received by: </div><br>
				<div class="sig-lines"> </div><br>
				<div>  <?= e($receivedByName) ?></div><br>
				<div>  <?= e($receivedByDesignation) ?></div><br>
				<div> <?= e($receivedByDateDisplay) ?> </div>
			</td>
		</tr>
	</table>

	<div class="sheet-footer">
		<img src="image/footer.png" alt="Footer Image" style="width:100%; max-width:210mm; height:auto; display:block; margin:0 auto;">
	</div>

	 

</div>

<?php if ($showSigForm): ?>
<div class="sig-modal" id="sigModal">
	<div class="sig-modal-box">
		<h3>RIS Signatories</h3>
		<form method="get" action="">
			<input type="hidden" name="id" value="<?= e($_GET['id'] ?? cipher($risId)) ?>">
			<div class="sig-field">
				<label>Issued by — Name</label>
				<input type="text" name="issued_by" id="issued_by_input" autocomplete="off" placeholder="Type name to search" required>
				<ul class="emp-suggestions" id="issued_by_list" style="display:none;"></ul>
			</div>
			<div class="sig-field">
				<label>Issued by — Designation</label>
				<input type="text" name="issued_designation" id="issued_by_designation" readonly placeholder="Auto-filled">
			</div>
			<div class="sig-field">
				<label>Issued by — Date</label>
				<input type="date" name="issued_date" value="<?= date('Y-m-d') ?>" required>
			</div>
			<hr style="border:none;border-top:1px solid #ccc;">
			<div class="sig-field">
				<label>Received by — Name</label>
				<input type="text" name="received_by" id="received_by_input" autocomplete="off" placeholder="Type name to search" required>
				<ul class="emp-suggestions" id="received_by_list" style="display:none;"></ul>
			</div>
			<div class="sig-field">
				<label>Received by — Designation</label>
				<input type="text" name="received_designation" id="received_by_designation" readonly placeholder="Auto-filled">
			</div>
			<div class="sig-field">
				<label>Received by — Date</label>
				<input type="date" name="received_date" value="<?= date('Y-m-d') ?>" required>
			</div>
			<div class="actions">
				<button type="submit" class="btn-go">Apply</button>
				<a href="#" onclick="document.getElementById('sigModal').style.display='none';return false;">Skip</a>
			</div>
		</form>
	</div>
</div>
<script>
const SIG_EMPLOYEES = <?= $sigEmployeeJson ?: '[]' ?>;
function bindSignatorySuggest(inputId, listId, desigId) {
	var input = document.getElementById(inputId);
	var list = document.getElementById(listId);
	var desig = document.getElementById(desigId);
	if (!input || !list || !desig) return;
	function show() {
		var q = input.value.trim().toLowerCase();
		if (!q) { hide(); return; }
		var results = SIG_EMPLOYEES.filter(function (e) { return e.name.toLowerCase().indexOf(q) !== -1; }).slice(0, 50);
		if (!results.length) { hide(); return; }
		list.innerHTML = '';
		results.forEach(function (e, idx) {
			var li = document.createElement('li');
			li.textContent = e.name + (e.designation ? ' — ' + e.designation : '');
			li.className = idx === 0 ? 'active' : '';
			li.addEventListener('mousedown', function (ev) {
				ev.preventDefault();
				pick(e);
			});
			list.appendChild(li);
		});
		list.style.display = 'block';
	}
	function hide() { list.style.display = 'none'; }
	function pick(e) {
		input.value = e.name;
		desig.value = e.designation || '';
		hide();
	}
	input.addEventListener('input', function () { desig.value = ''; show(); });
	input.addEventListener('focus', function () { if (input.value) show(); });
	input.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') hide();
		if (e.key === 'Enter' && list.style.display === 'block' && list.children.length) {
			e.preventDefault();
			list.children[0].dispatchEvent(new MouseEvent('mousedown'));
		}
	});
	document.addEventListener('click', function (e) {
		if (!input.closest('.sig-field').contains(e.target)) hide();
	});
}
bindSignatorySuggest('issued_by_input', 'issued_by_list', 'issued_by_designation');
bindSignatorySuggest('received_by_input', 'received_by_list', 'received_by_designation');
</script>
<?php endif; ?>

</body>
</html>