<?php
require_once(__DIR__ . '/navigation.php');

$itemId = (int) (decode($_GET['id'] ?? '') ?: 0);
$itemRecord = $itemId > 0 ? find('SELECT * FROM items WHERE id = ?', [$itemId]) : null;

if (!$itemRecord) {
    messageAlert(true, 'Stock item not found.', false);
    contentTitle('Edit Stock Item');
    imsNav('inventory');
    echo '<div class="card shadow mb-4"><div class="card-body"><a class="btn btn-secondary" href="' . customUri('ims', 'Stock and Inventory') . '">Back</a></div></div>';
    return;
}
$itemUnits = query('SELECT * FROM item_units WHERE item_id = ? ORDER BY FIELD(unit, "bottle","gallon","piece","unit","pack","can","pouch","bundle","roll","tube","pad","box","ream","book","cart","license","ticket","lot","set"), unit', [$itemId]);

$priceHistory = query(
    "SELECT h.id, h.price_date, h.old_cost, h.new_cost, h.reference, h.remarks,
            CONCAT(e.first_name, ' ', e.last_name) AS recorded_by
     FROM item_cost_history h
     LEFT JOIN employees e ON e.id = h.created_by
     WHERE h.item_id = ?
     ORDER BY h.price_date DESC, h.id DESC",
    [$itemId]
);

$savedId = (int) sanitize(decipher($_POST['item_id'] ?? ''));
$submitting = isset($_POST['save-item']);
$item = [
    'id' => $itemId,
    'stock_no' => $submitting ? trim($_POST['stock_no'] ?? '') : $itemRecord['stock_no'],
    'description' => $submitting ? trim($_POST['description'] ?? '') : $itemRecord['description'],
    'unit' => $submitting ? trim($_POST['unit'] ?? '') : $itemRecord['unit'],
    'pcs_per_unit' => (int) $itemRecord['pcs_per_unit'],
    'number_of_units' => (int) $itemRecord['number_of_units'],
    'number_units_unit' => isset($itemRecord['number_units_unit']) ? strtolower(trim($itemRecord['number_units_unit'])) : '',
    'total_units' => $submitting ? (int) ($_POST['total_units'] ?? 0) : (int) $itemRecord['total_units'],
    'quantity' => $submitting ? (int) ($_POST['quantity'] ?? 0) : (int) $itemRecord['quantity'],
    'min_qty' => $submitting ? (int) ($_POST['min_qty'] ?? 0) : (int) $itemRecord['min_qty'],
    'unit_cost' => $submitting ? (float) ($_POST['unit_cost'] ?? 0) : (float) $itemRecord['unit_cost'],
    'personnel' => $submitting ? trim($_POST['personnel'] ?? '') : ($itemRecord['personnel'] ?? ''),
    'office' => $submitting ? trim($_POST['office'] ?? '') : ($itemRecord['office'] ?? ''),
    'item_status' => $submitting ? (in_array($_POST['item_status'] ?? 'Functional', ['Functional', 'Transferred'], true) ? $_POST['item_status'] : 'Functional') : ($itemRecord['item_status'] ?? 'Functional'),
    'transfer_to' => $submitting ? trim($_POST['transfer_to'] ?? '') : ($itemRecord['transfer_to'] ?? ''),
    'remarks' => $submitting ? trim($_POST['remarks'] ?? '') : ($itemRecord['remarks'] ?? ''),
];

contentTitle('Edit Stock Item');
imsNav('inventory');
messageAlert($showAlert ?? false, $message ?? '', $success ?? true);
?>
<div class="card shadow mb-4" style="max-width:940px;">
    <div class="card-header"><strong>Edit stock item</strong></div>
    <div class="card-body">
        <?php if (!empty($message) && empty($success)): ?>
            <?php messageAlert(true, e($message), false); ?>
        <?php endif; ?>
        <form method="post">
            <?= csrf_field(); ?>
            <input type="hidden" name="item_id" value="<?= e(cipher((string) $item['id'])) ?>">
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
            <button class="btn btn-primary" type="submit" name="save-item"><i class="fas fa-save"></i> Update item</button>
            <a class="btn btn-secondary" href="<?= customUri('ims', 'Stock and Inventory') ?>">Cancel</a>
        </form>
    </div>
</div>

<div class="card shadow mb-4" style="max-width:940px;">
    <div class="card-header"><strong>Applicable units</strong> <small class="text-muted">(used for requisition &amp; stock conversion; e.g. box = 500 pcs)</small></div>
    <div class="card-body">
        <?php if (empty($itemUnits)): ?><p class="text-muted">No additional units.</p><?php endif; ?>
        <table class="table table-sm">
            <thead><tr><th>Unit</th><th>Pieces per unit</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($itemUnits as $iu): ?>
                <tr>
                    <td><?= e(ucfirst($iu['unit'])) ?></td>
                    <td><?= (int) $iu['pcs_per_unit'] ?></td>
                    <td class="text-right">
                        <form method="post" class="d-inline" onsubmit="return confirm('Remove this unit?');">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="item_unit_id" value="<?= e(cipher((string) $iu['id'])) ?>">
                            <button class="btn btn-outline-danger btn-sm" type="submit" name="remove-item-unit"><i class="fas fa-trash"></i> Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <form method="post" class="form-row align-items-end">
            <?= csrf_field(); ?>
            <input type="hidden" name="item_id_id" value="<?= e(cipher((string) $item['id'])) ?>">
            <div class="form-group col-md-4 mb-0"><label>Unit</label>
                <select class="form-control" name="new_unit">
                    <option value="">Select unit</option>
                    <?php $existing = array_map(static fn ($u2) => strtolower($u2['unit']), $itemUnits); ?>
                    <?php foreach (['bottle','gallon','piece','unit','pack','can','pouch','bundle','roll','tube','pad','box','ream','book','cart','license','ticket','lot','set'] as $u2): ?>
                        <?php if (in_array($u2, $existing, true)) continue; ?>
                        <option value="<?= e($u2) ?>"><?= e(ucfirst($u2)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-3 mb-0"><label>Pieces per unit</label><input class="form-control" type="number" min="1" name="new_pcs" value="1"></div>
            <div class="form-group col-md-5 mb-0"><button class="btn btn-outline-primary" type="submit" name="add-item-unit"><i class="fas fa-plus"></i> Add unit</button></div>
        </form>
    </div>
</div>

<div class="card shadow mb-4" style="max-width:940px;">
    <div class="card-header"><strong>Price history</strong> <small class="text-muted">(all unit cost changes for this item)</small></div>
    <div class="card-body">
        <?php if (empty($priceHistory)): ?><p class="text-muted">No price changes recorded yet.</p><?php endif; ?>
        <table class="table table-sm">
            <thead><tr><th>Date</th><th>Previous</th><th>New</th><th>Change</th><th>Reference</th><th>Recorded By</th></tr></thead>
            <tbody>
            <?php foreach ($priceHistory as $ph): ?>
                <?php $phChange = (float) $ph['new_cost'] - (float) $ph['old_cost']; ?>
                <tr>
                    <td><?= e($ph['price_date']) ?></td>
                    <td><?= 'P' . number_format((float) $ph['old_cost'], 2) ?></td>
                    <td><?= 'P' . number_format((float) $ph['new_cost'], 2) ?></td>
                    <td>
                        <?php if ($phChange > 0): ?><span class="text-danger">+<?= 'P' . number_format($phChange, 2) ?></span>
                        <?php elseif ($phChange < 0): ?><span class="text-success">-<?= 'P' . number_format(abs($phChange), 2) ?></span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><?= e($ph['reference']) ?></td>
                    <td><?= e($ph['recorded_by'] ?: '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
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