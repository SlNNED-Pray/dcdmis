<?php
require_once(__DIR__ . '/navigation.php');

$pdo = connection();
$employees = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) AS name FROM employees ORDER BY last_name, first_name")->fetchAll();
$items = $pdo->query('SELECT id, stock_no, description, unit, unit_cost FROM items ORDER BY description')->fetchAll();
$suppliers = $pdo->query('SELECT id, name FROM suppliers ORDER BY name')->fetchAll();
$selectedEmployee = (int) ($_POST['employee_id'] ?? 0);
$selectedItem = (int) ($_POST['item_id'] ?? 0);
$selectedQuantity = (int) ($_POST['quantity'] ?? 0);
$selectedUnitCost = (float) ($_POST['unit_cost'] ?? 0);
$selectedSupplier = (int) ($_POST['supplier_id'] ?? 0);
$selectedPurpose = trim($_POST['purpose'] ?? '');
$openModal = isset($_POST['save-pr']) || $url === 'Create Purchase Request';
?>
<div class="modal fade" id="prModal" tabindex="-1" role="dialog" aria-labelledby="prModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<form method="post">
				<?= csrf_field(); ?>
				<div class="modal-header">
					<h5 class="modal-title" id="prModalLabel"><i class="fas fa-file-invoice-dollar"></i> New purchase request</h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				</div>
				<div class="modal-body">
<div class="form-row"><div class="form-group col-md-6"><label>Requesting employee</label><select class="form-control" name="employee_id" required><option value="">Select employee</option><?php foreach ($employees as $employee): ?><option value="<?= (int) $employee['id'] ?>" <?= (int) $employee['id'] === $selectedEmployee ? 'selected' : '' ?>><?= e($employee['name']) ?></option><?php endforeach; ?></select></div><div class="form-group col-md-6"><label>Item</label><select class="form-control" name="item_id" required><?php foreach ($items as $item): ?><option value="<?= (int) $item['id'] ?>" <?= (int) $item['id'] === $selectedItem ? 'selected' : '' ?>><?= e($item['stock_no'] . ' - ' . $item['description'] . ' (' . $item['unit'] . ')') ?></option><?php endforeach; ?></select></div></div>
<div class="form-row"><div class="form-group col-md-4"><label>Quantity</label><input class="form-control" type="number" name="quantity" min="1" required value="<?= $selectedQuantity > 0 ? (int) $selectedQuantity : '' ?>"></div><div class="form-group col-md-4"><label>Unit cost</label><input class="form-control" type="number" name="unit_cost" min="0" step="0.01" value="<?= $selectedUnitCost > 0 ? number_format($selectedUnitCost, 2, '.', '') : '0' ?>"></div><div class="form-group col-md-4"><label>Supplier</label><select class="form-control" name="supplier_id"><option value="">Not assigned</option><?php foreach ($suppliers as $supplier): ?><option value="<?= (int) $supplier['id'] ?>" <?= (int) $supplier['id'] === $selectedSupplier ? 'selected' : '' ?>><?= e($supplier['name']) ?></option><?php endforeach; ?></select></div></div>
<div class="form-group"><label>Purpose</label><textarea class="form-control" name="purpose" rows="3"><?= e($selectedPurpose) ?></textarea></div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
					<button class="btn btn-primary" type="submit" name="save-pr"><i class="fas fa-save"></i> Save PR</button>
				</div>
			</form>
		</div>
	</div>
</div>
<?php if ($openModal): ?>
<script>document.addEventListener('DOMContentLoaded', function () { if (window.jQuery && jQuery.fn.modal) { jQuery('#prModal').modal('show'); } });</script>
<?php endif; ?>