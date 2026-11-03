<?php
require_once(__DIR__ . '/navigation.php');

$all = connection()->query(
    "SELECT r.id AS ris_id, r.ris_no, r.created_at, r.purpose,
            CONCAT(e.first_name, ' ', e.last_name) AS employee,
            d.id AS detail_id, d.item_id, d.quantity, d.has_stock, d.status,
            i.stock_no, i.description, i.unit
     FROM requisition_slips r
     LEFT JOIN employees e ON e.id = r.employee_id
     LEFT JOIN requisition_slip_items d ON d.ris_id = r.id
     LEFT JOIN items i ON i.id = d.item_id
     WHERE d.status IS NOT NULL AND LOWER(d.status) = 'disapproved'
     ORDER BY r.created_at DESC"
)->fetchAll();
$grouped = [];
foreach ($all as $row) {
    $risId = (int) $row['ris_id'];
    if (!isset($grouped[$risId])) {
        $grouped[$risId] = [
            'ris_id' => $risId,
            'ris_no' => $row['ris_no'],
            'created_at' => $row['created_at'],
            'employee' => $row['employee'] ?: 'Unknown',
            'items' => [],
        ];
    }
    if ($row['item_id'] ?? null) {
        $grouped[$risId]['items'][] = $row;
    }
}
messageAlert($showAlert ?? false, $message ?? '', $success ?? true);
contentTitle('Disapproved Requisition Slips');
imsNav('ris-disapproved');
?>
<div class="card shadow mb-4">
    <div class="card-header"><strong>Disapproved requisition slips</strong></div>
    <div class="card-body"><div class="table-responsive"><table class="table table-hover"><thead><tr><th>RIS No.</th><th>Employee</th><th>Item(s)</th><th>Purpose</th><th>Date</th></tr></thead><tbody>
    <?php if (empty($grouped)): ?><tr><td colspan="5" class="text-center text-muted">No disapproved requisition slips.</td></tr><?php endif; ?>
    <?php foreach ($grouped as $ris): ?><tr>
        <td><?= e($ris['ris_no']) ?></td>
        <td><?= e($ris['employee']) ?></td>
        <td>
            <?php foreach ($ris['items'] as $detail): ?>
                <div class="mb-1">
                    <span class="badge badge-danger"><?= e($detail['stock_no'] . ' - ' . $detail['description'] . ' (' . (int) $detail['quantity'] . ' ' . e($detail['unit']) . ')') ?></span>
                    <?php if (strtolower((string) $detail['status']) === 'disapproved'): ?>
                        <form method="post" class="d-inline" onsubmit="return confirm('Approve this item via MOOE?');">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="approve-mooe-ris-item" value="<?= e(cipher((string) $detail['detail_id'])) ?>">
                            <button class="btn btn-sm btn-success" type="submit"><i class="fas fa-check"></i> Approve via MOOE</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </td>
        <td><small class="text-muted"><?= e($ris['purpose'] ?? '') ?></small></td>
        <td><?= e($ris['created_at']) ?></td>
    </tr><?php endforeach; ?></tbody></table></div></div>
</div>
