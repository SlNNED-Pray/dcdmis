<?php
require_once(__DIR__ . '/navigation.php');
$items = connection()->query('SELECT id, stock_no, description, unit, quantity, min_qty FROM items ORDER BY description')->fetchAll();
contentTitle('Physical Count of Inventories');
imsNav('physical-count');
messageAlert($showAlert ?? false, $message ?? '', $success ?? true);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
	<button class="btn btn-secondary" type="button" onclick="window.open('<?= uri() . '/ims/physical-count-print.php' ?>', '_blank', 'resizable=1,scrollbars=1')"><i class="fas fa-print"></i> Print worksheet</button>
</div>

<form method="post" id="physical-count-form">
<?= csrf_field(); ?>
<div class="card shadow mb-4"><div class="card-header"><strong>Physical count worksheet</strong></div><div class="card-body"><p class="text-muted">Enter the actual count in the "Actual count" column. Variance is computed automatically (Recorded − Actual). Save adjustments only after the count has been reviewed.</p><div class="table-responsive"><table class="table table-hover"><thead><tr><th>Stock No.</th><th>Description</th><th>Unit</th><th>Recorded</th><th>Actual count</th><th>Variance</th><th>Remarks</th></tr></thead><tbody>
<?php foreach ($items as $item): ?>
	<tr>
		<td><?= e($item['stock_no']) ?></td>
		<td><?= e($item['description']) ?></td>
		<td><?= e($item['unit']) ?></td>
		<td class="recorded-qty"><?= (int) $item['quantity'] ?></td>
		<td><input class="form-control form-control-sm actual-count" type="number" min="0" name="actual_count[<?= (int) $item['id'] ?>]" value="<?= (int) $item['quantity'] ?>" aria-label="Actual count for <?= e($item['description']) ?>"></td>
		<td class="variance text-muted">0</td>
		<td><input class="form-control form-control-sm" type="text" name="remarks[<?= (int) $item['id'] ?>]" placeholder="Optional note"></td>
	</tr>
<?php endforeach; ?>
</tbody></table></div>
<button class="btn btn-primary" type="submit" name="save-physical-count"><i class="fas fa-save"></i> Save adjustments</button>
</div></div>
</form>

<script>
// Auto-calculate variance (Recorded - Actual) for every row as the user types.
document.addEventListener('DOMContentLoaded', function () {
	document.querySelectorAll('tr').forEach(function (row) {
		var input = row.querySelector('input.actual-count');
		var recordedCell = row.querySelector('.recorded-qty');
		var varianceCell = row.querySelector('.variance');
		if (!input || !recordedCell || !varianceCell) return;

		var recorded = parseInt(recordedCell.textContent, 10) || 0;
		function updateVariance() {
			var actual = parseInt(input.value, 10) || 0;
			var variance = recorded - actual;
			varianceCell.textContent = variance;
			varianceCell.classList.remove('text-muted');
			varianceCell.classList.toggle('text-success', variance === 0);
			varianceCell.classList.toggle('text-warning', variance >= 1);
			varianceCell.classList.toggle('text-danger', variance <= -1);
		}
		input.addEventListener('input', updateVariance);
		updateVariance();
	});
});
</script>

<style>
@media print {
	.btn, .nav-tabs, .sidebar, #accordionSidebar, #scroll-to-top, .navbar, footer, form button { display: none !important; }
	#content-wrapper, #content, .container-fluid { margin: 0 !important; padding: 0 !important; width: 100% !important; }
	.card { border: 0 !important; box-shadow: none !important; }
	.card-header { text-align: center; border: 0; }
	.table { font-size: 10pt; }
}
</style>
