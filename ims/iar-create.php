<?php
require_once(__DIR__ . '/navigation.php');
$risRows = connection()->query(
    "SELECT r.id, r.ris_no, r.created_at, d.id AS detail_id, d.item_id, d.quantity, d.status,
            i.stock_no, i.description, i.unit
     FROM requisition_slips r
     JOIN requisition_slip_items d ON d.ris_id = r.id
     JOIN items i ON i.id = d.item_id
     WHERE d.status = 'Pending'
     ORDER BY r.created_at DESC"
)->fetchAll();
$grouped = [];
foreach ($risRows as $row) {
    $risId = (int) $row['id'];
    if (!isset($grouped[$risId])) {
        $grouped[$risId] = ['id' => $risId, 'ris_no' => $row['ris_no'], 'created_at' => $row['created_at'], 'items' => []];
    }
    $grouped[$risId]['items'][] = [
        'detail_id' => (int) $row['detail_id'],
        'item_id' => (int) $row['item_id'],
        'quantity' => (int) $row['quantity'],
        'description' => $row['stock_no'] . ' - ' . $row['description'] . ' (' . $row['quantity'] . ' ' . $row['unit'] . ')',
    ];
}
contentTitle('Create Inspection and Acceptance Report');
imsNav('iar');
?>
<div class="card shadow mb-4" style="max-width:860px;"><div class="card-header"><strong>Accept stock for a pending RIS</strong></div><div class="card-body">
<?php if (!empty($message) && empty($success)): ?>
	<?php messageAlert(true, e($message), false); ?>
<?php endif; ?>
<form method="post">
<?= csrf_field(); ?>
<div class="form-group"><label>Pending requisition</label>
	<select class="form-control" name="ris_id" id="iar-ris-select" required>
		<option value="">Select RIS</option>
		<?php foreach ($grouped as $ris): ?>
		<option value="<?= e(cipher((string) $ris['id'])) ?>" data-items="<?= htmlspecialchars(json_encode($ris['items']), ENT_QUOTES) ?>"><?= e($ris['ris_no']) ?> (<?= e(date('M d, Y', strtotime($ris['created_at']))) ?>)</option>
		<?php endforeach; ?>
	</select>
</div>
<div id="iar-items"></div>
<div class="form-row">
	<div class="form-group col-md-6"><label>Inspector name</label><input class="form-control" name="inspector_name" required></div>
	<div class="form-group col-md-6"><label>Supplier</label><input class="form-control" name="supplier"></div>
</div>
<div class="form-row">
	<div class="form-group col-md-4"><label>PO No.</label><input class="form-control" name="po_no"></div>
	<div class="form-group col-md-4"><label>PO Date</label><input class="form-control" type="date" name="po_date"></div>
	<div class="form-group col-md-4"><label>Responsibility Center Code</label><input class="form-control" name="responsibility_center_code"></div>
</div>
<div class="form-row">
	<div class="form-group col-md-6"><label>Office / Dept.</label><input class="form-control" name="office_dept"></div>
	<div class="form-group col-md-6"><label>Invoice No.</label><input class="form-control" name="invoice_no"></div>
</div>
<button class="btn btn-primary" type="submit" name="save-iar"><i class="fas fa-check"></i> Accept and issue</button>
<a class="btn btn-secondary" href="<?= customUri('ims', 'IAR') ?>">Cancel</a>
</form>
</div></div>
<script>
document.getElementById('iar-ris-select').addEventListener('change', function () {
	var option = this.options[this.selectedIndex];
	var container = document.getElementById('iar-items');
	container.innerHTML = '';
	var items = [];
	try { items = option.dataset.items ? JSON.parse(option.dataset.items) : []; } catch (e) {}
	if (items.length === 0 || !option.value) { return; }
	var html = '<div class="table-responsive mb-3"><table class="table table-sm table-bordered"><thead><tr><th>Item</th><th>Qty Accepted</th></tr></thead><tbody>';
	items.forEach(function (item, index) {
		html += '<tr><td>' + item.description + '<input type="hidden" name="items[' + index + '][detail_id]" value="' + item.detail_id + '"><input type="hidden" name="items[' + index + '][item_id]" value="' + item.item_id + '"></td>' +
			'<td><input type="number" class="form-control" name="items[' + index + '][quantity]" min="1" required value="' + item.quantity + '"></td></tr>';
	});
	html += '</tbody></table></div>';
	container.innerHTML = html;
});
</script>
