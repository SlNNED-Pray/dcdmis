<?php
require_once(__DIR__ . '/navigation.php');
require_once(root() . '/ims/helpers.php');

$item = [
    'stock_no' => trim($_POST['stock_no'] ?? ''),
    'description' => trim($_POST['description'] ?? ''),
    'unit' => trim($_POST['unit'] ?? ''),
    'total_units' => (int) ($_POST['total_units'] ?? 0),
    'quantity' => (int) ($_POST['quantity'] ?? 0),
    'min_qty' => (int) ($_POST['min_qty'] ?? 0),
    'unit_cost' => (float) ($_POST['unit_cost'] ?? 0),
    'personnel' => trim($_POST['personnel'] ?? ''),
    'office' => trim($_POST['office'] ?? ''),
    'item_status' => in_array($submittedStatus = trim($_POST['item_status'] ?? ''), ['Functional', 'Transferred'], true) ? $submittedStatus : 'Functional',
    'transfer_to' => trim($_POST['transfer_to'] ?? ''),
    'remarks' => trim($_POST['remarks'] ?? ''),
];

$openModal = isset($_POST['save-item']) || $url === 'Create Stock Item';
?>
<div class="modal fade" id="itemModal" tabindex="-1" role="dialog" aria-labelledby="itemModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<form method="post">
				<?= csrf_field(); ?>
				<div class="modal-header">
					<h5 class="modal-title" id="itemModalLabel"><i class="fas fa-boxes"></i> New stock item</h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				</div>
				<div class="modal-body">
					<div class="form-row">
						<div class="form-group col-md-6"><label>Stock No. <span class="text-danger">*</span></label><input class="form-control" name="stock_no" required value="<?= e($item['stock_no']) ?>"></div>
						<div class="form-group col-md-6"><label>Unit <span class="text-danger">*</span></label>
							<select class="form-control" name="unit" required>
								<option value="">Select unit</option>
								<?php foreach (['bottle','gallon','piece','unit','pack','can','pouch','bundle','roll','tube','pad','box','ream','book','cart','license','ticket','lot','set'] as $u): ?>
									<option value="<?= e($u) ?>" <?= strtolower($item['unit']) === $u ? 'selected' : '' ?>><?= e(ucfirst($u)) ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
					<div class="form-group"><label>Description <span class="text-danger">*</span></label><input class="form-control" name="description" required value="<?= e($item['description']) ?>"></div>
					<div class="form-row">
						<div class="form-group col-md-3"><label>Total units</label><input class="form-control" type="number" min="0" name="total_units" value="<?= $item['total_units'] ?: '' ?>"></div>
						<div class="form-group col-md-3"><label>Unit cost</label><input class="form-control" type="number" min="0" step="0.01" name="unit_cost" value="<?= e($item['unit_cost']) ?>"></div>
					</div>
					<div class="form-row">
						<div class="form-group col-md-4"><label>Personnel / Custodian</label><input class="form-control" name="personnel" value="<?= e($item['personnel']) ?>"></div>
						<div class="form-group col-md-4"><label>Office</label><input class="form-control" name="office" value="<?= e($item['office']) ?>"></div>
						<div class="form-group col-md-4"><label>Status</label>
							<select class="form-control" name="item_status" id="item-status">
								<option value="Functional" <?= $item['item_status'] === 'Functional' ? 'selected' : '' ?>>Functional</option>
								<option value="Transferred" <?= $item['item_status'] === 'Transferred' ? 'selected' : '' ?>>Transferred</option>
							</select>
						</div>
					</div>
					<div class="form-row" id="transfer-row" style="<?= $item['item_status'] === 'Transferred' ? '' : 'display:none;' ?>">
						<div class="form-group col-md-6"><label>Transferred to (personnel) <span class="text-danger">*</span></label><input class="form-control" name="transfer_to" id="transfer-to" value="<?= e($item['transfer_to']) ?>"></div>
					</div>
					<div class="form-row">
						<div class="form-group col-md-6"><label>Opening quantity</label><input class="form-control" type="number" min="0" name="quantity" value="<?= $item['quantity'] ?>"></div>
						<div class="form-group col-md-6"><label>Minimum quantity</label><input class="form-control" type="number" min="0" name="min_qty" value="<?= $item['min_qty'] ?>"></div>
					</div>
					<div class="form-group"><label>Remarks</label><textarea class="form-control" name="remarks" rows="2"><?= e($item['remarks']) ?></textarea></div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
					<button class="btn btn-primary" type="submit" name="save-item"><i class="fas fa-save"></i> Save item</button>
				</div>
			</form>
		</div>
	</div>
</div>
<script>
(function () {
    var sel = document.getElementById('item-status');
    var row = document.getElementById('transfer-row');
    var inp = document.getElementById('transfer-to');
    function toggle() {
        var on = sel && sel.value === 'Transferred';
        if (row) { row.style.display = on ? '' : 'none'; }
        if (inp) { inp.required = on; }
    }
    if (sel) { sel.addEventListener('change', toggle); toggle(); }
})();
</script>
<?php if ($openModal): ?>
<script>document.addEventListener('DOMContentLoaded', function () { if (window.jQuery && jQuery.fn.modal) { jQuery('#itemModal').modal('show'); } });</script>
<?php endif; ?>