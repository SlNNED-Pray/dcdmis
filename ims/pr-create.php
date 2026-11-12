<?php
require_once(__DIR__ . '/navigation.php');

$pdo = connection();
$employees = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) AS name FROM employees ORDER BY last_name, first_name")->fetchAll();
$items = $pdo->query('SELECT id, stock_no, description, unit, unit_cost FROM items ORDER BY description')->fetchAll();
$suppliers = $pdo->query('SELECT id, name FROM suppliers ORDER BY name')->fetchAll();
$selectedEmployee = (int) ($_POST['employee_id'] ?? 0);
$selectedEmployeeName = '';
foreach ($employees as $employee) {
    if ((int) $employee['id'] === $selectedEmployee) {
        $selectedEmployeeName = (string) $employee['name'];
        break;
    }
}
$employeeJson = json_encode(array_map(static fn (array $employee): array => [
    'id' => (int) $employee['id'],
    'name' => (string) $employee['name'],
], $employees), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
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
<div class="form-row"><div class="form-group col-md-6" style="position:relative;"><label>Requesting employee</label><input class="form-control pr-emp-search" type="text" autocomplete="off" placeholder="Type name to search" value="<?= e($selectedEmployeeName) ?>" required><input type="hidden" name="employee_id" class="pr-emp-id" value="<?= $selectedEmployee ?>"><ul class="pr-emp-suggestions dropdown-menu" style="display:none;left:0;right:0;top:100%;position:absolute;max-height:220px;overflow:auto;z-index:1000;"></ul></div><div class="form-group col-md-6"><label>Item</label><select class="form-control" name="item_id" required><?php foreach ($items as $item): ?><option value="<?= (int) $item['id'] ?>" <?= (int) $item['id'] === $selectedItem ? 'selected' : '' ?>><?= e($item['stock_no'] . ' - ' . $item['description'] . ' (' . $item['unit'] . ')') ?></option><?php endforeach; ?></select></div></div>
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
<script>
(function () {
    var PR_EMPLOYEES = <?= $employeeJson ?: '[]' ?>;
    var input = document.querySelector('.pr-emp-search');
    var hidden = document.querySelector('.pr-emp-id');
    var list = document.querySelector('.pr-emp-suggestions');
    if (!input || !hidden || !list) return;

    function hideResults() { list.style.display = 'none'; }

    function showResults() {
        var q = input.value.trim().toLowerCase();
        if (!q) { hideResults(); return; }
        var results = PR_EMPLOYEES.filter(function (e) {
            return e.name.toLowerCase().indexOf(q) !== -1;
        }).slice(0, 20);
        if (!results.length) { hideResults(); return; }
        list.innerHTML = '';
        results.forEach(function (e) {
            var li = document.createElement('li');
            li.className = 'dropdown-item';
            li.setAttribute('role', 'option');
            li.textContent = e.name;
            li.addEventListener('mousedown', function (ev) {
                ev.preventDefault();
                input.value = e.name;
                hidden.value = e.id;
                input.setCustomValidity('');
                hideResults();
            });
            list.appendChild(li);
        });
        list.style.display = 'block';
    }

    input.addEventListener('input', function () {
        hidden.value = '';
        input.setCustomValidity('');
        showResults();
    });
    input.addEventListener('focus', function () { if (input.value) showResults(); });
    document.addEventListener('click', function (e) {
        if (input.parentNode && !input.parentNode.contains(e.target)) hideResults();
    });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') hideResults();
        if (e.key === 'Enter' && list.style.display === 'block' && list.children.length > 0) {
            e.preventDefault();
            list.children[0].dispatchEvent(new MouseEvent('mousedown'));
        }
    });
    document.addEventListener('click', function (e) {
        var trigger = e.target.closest ? e.target.closest('[data-target="#prModal"]') : null;
        if (!trigger) return;
        input.value = '';
        hidden.value = '';
        hideResults();
    });

    var form = input.closest('form');
    if (form) {
        form.addEventListener('submit', function (e) {
            if (!hidden.value || hidden.value === '0') {
                e.preventDefault();
                input.setCustomValidity('Please select an employee from the suggestions.');
                input.reportValidity();
                return;
            }
            input.setCustomValidity('');
        });
    }
})();
</script>
