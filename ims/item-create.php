<?php
require_once(__DIR__ . '/navigation.php');
require_once(root() . '/ims/helpers.php');

$errors = [];
$item = [
    'stock_no' => trim($_POST['stock_no'] ?? ''),
    'description' => trim($_POST['description'] ?? ''),
    'unit' => trim($_POST['unit'] ?? ''),
    'pcs_per_unit' => 0,
    'number_of_units' => 0,
    'number_units_unit' => '',
    'total_units' => (int) ($_POST['total_units'] ?? 0),
    'quantity' => (int) ($_POST['quantity'] ?? 0),
    'min_qty' => (int) ($_POST['min_qty'] ?? 0),
    'unit_cost' => (float) ($_POST['unit_cost'] ?? 0),
    'personnel' => trim($_POST['personnel'] ?? ''),
    'office' => trim($_POST['office'] ?? ''),
    'item_status' => in_array($_POST['item_status'] ?? 'Functional', ['Functional', 'Transferred'], true) ? $_POST['item_status'] : 'Functional',
    'transfer_to' => trim($_POST['transfer_to'] ?? ''),
    'remarks' => trim($_POST['remarks'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete-item'])) {
    if ($item['stock_no'] === '') $errors[] = 'Stock number is required.';
    if ($item['description'] === '') $errors[] = 'Description is required.';
    if ($item['unit'] === '') $errors[] = 'Unit is required.';
    if ($item['quantity'] < 0 || $item['min_qty'] < 0) $errors[] = 'Quantities cannot be negative.';
    if ($item['total_units'] < 0) $errors[] = 'Total units cannot be negative.';
    if ($item['unit_cost'] < 0) $errors[] = 'Unit cost cannot be negative.';
    if ($item['item_status'] === 'Transferred' && $item['transfer_to'] === '') $errors[] = 'Transferred-to personnel name is required when status is Transferred.';

    if (!$errors) {
        $duplicate = connection()->prepare('SELECT id FROM items WHERE stock_no = ?');
        $duplicate->execute([$item['stock_no']]);
        if ($duplicate->fetch()) $errors[] = 'Stock number already exists.';
    }

    if (!$errors) {
        $result = insert('items', $item);
        if ($result !== false) {
            createSystemLog($stationId, $userId, 'Created stock item', $result, clientIp());
            imsRecordCostHistory((int) $result, (float) $item['unit_cost'], 0.0, 'Opening', 'Initial unit cost on creation', null, (int) $userId);
            $_SESSION["{$prefix}ims_flash"] = ['success' => true, 'message' => 'Stock item created successfully.'];
            redirect(customUri('ims', 'Stock and Inventory'));
        }
        $errors[] = 'The stock item could not be saved.';
    }
}

messageAlert(false, '', true);
contentTitle('Create Stock Item');
imsNav('inventory');
?>

<div class="card shadow mb-4" style="max-width:940px;">
    <div class="card-header"><strong>New stock item</strong></div>
    <div class="card-body">
        <?php if ($errors): ?>
            <?php messageAlert(true, implode('<br>', array_map('e', $errors)), false); ?>
        <?php endif; ?>
        <form method="post">
            <?= csrf_field(); ?>
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
            <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Save item</button>
            <a class="btn btn-secondary" href="<?= customUri('ims', 'Stock and Inventory') ?>">Cancel</a>
        </form>
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

