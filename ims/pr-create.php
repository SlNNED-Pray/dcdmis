<?php
require_once(__DIR__ . '/navigation.php');

$pdo = connection();
$employees = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) AS name FROM employees ORDER BY last_name, first_name")->fetchAll();
$items = $pdo->query('SELECT id, stock_no, description, unit, unit_cost FROM items ORDER BY description')->fetchAll();
$suppliers = $pdo->query('SELECT id, name FROM suppliers ORDER BY name')->fetchAll();
messageAlert($showAlert ?? false, $message ?? '', $success ?? true);
contentTitle('Create Purchase Request');
imsNav('pr');
?>
<div class="card shadow mb-4" style="max-width:820px;"><div class="card-header"><strong>New purchase request</strong></div><div class="card-body"><form method="post">
<?= csrf_field(); ?>
<div class="form-row"><div class="form-group col-md-6"><label>Requesting employee</label><select class="form-control" name="employee_id" required><option value="">Select employee</option><?php foreach ($employees as $employee): ?><option value="<?= (int) $employee['id'] ?>"><?= e($employee['name']) ?></option><?php endforeach; ?></select></div><div class="form-group col-md-6"><label>Item</label><select class="form-control" name="item_id" required><?php foreach ($items as $item): ?><option value="<?= (int) $item['id'] ?>"><?= e($item['stock_no'] . ' - ' . $item['description'] . ' (' . $item['unit'] . ')') ?></option><?php endforeach; ?></select></div></div>
<div class="form-row"><div class="form-group col-md-4"><label>Quantity</label><input class="form-control" type="number" name="quantity" min="1" required></div><div class="form-group col-md-4"><label>Unit cost</label><input class="form-control" type="number" name="unit_cost" min="0" step="0.01" value="0"></div><div class="form-group col-md-4"><label>Supplier</label><select class="form-control" name="supplier_id"><option value="">Not assigned</option><?php foreach ($suppliers as $supplier): ?><option value="<?= (int) $supplier['id'] ?>"><?= e($supplier['name']) ?></option><?php endforeach; ?></select></div></div>
<div class="form-group"><label>Purpose</label><textarea class="form-control" name="purpose" rows="3"></textarea></div>
<button class="btn btn-primary" type="submit" name="save-pr"><i class="fas fa-save"></i> Save PR</button> <a class="btn btn-secondary" href="<?= customUri('ims', 'Purchase Requests') ?>">Cancel</a>
</form></div></div>
