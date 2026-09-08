<?php
require_once(__DIR__ . '/navigation.php');

$all = connection()->query(
    "SELECT r.id AS ris_id, r.ris_no, r.created_at, r.purpose, r.division, r.office,
            CONCAT(e.first_name, ' ', e.last_name) AS employee,
            d.id AS detail_id, d.item_id, d.quantity, d.has_stock, d.status, d.unit, d.pcs_per_unit,
            i.stock_no, i.description, i.unit AS item_unit
     FROM requisition_slips r
     LEFT JOIN employees e ON e.id = r.employee_id
     LEFT JOIN requisition_slip_items d ON d.ris_id = r.id
     LEFT JOIN items i ON i.id = d.item_id
     WHERE (d.status IS NULL OR LOWER(d.status) <> 'disapproved')
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
            'division' => $row['division'],
            'office' => $row['office'],
            'items' => [],
        ];
    }
    if ($row['item_id'] ?? null) {
        $grouped[$risId]['items'][] = $row;
    }
}
messageAlert($showAlert ?? false, $message ?? '', $success ?? true);
contentTitle('Requisition Slips');
imsNav('ris');
?>
<div class="card shadow mb-4">
    <div class="card-header d-flex justify-content-between align-items-center"><strong>Requisition slips</strong><a class="btn btn-primary btn-sm" href="<?= customUri('ims', 'Create RIS') ?>"><i class="fas fa-plus"></i> Create RIS</a></div>
    <div class="card-body"><div class="table-responsive"><table class="table table-hover"><thead><tr><th>RIS No.</th><th>Employee</th><th>Office</th><th>Division</th><th>Item(s)</th><th>Status</th><th>Date</th></tr></thead><tbody>
    <?php foreach ($grouped as $ris): ?><tr>
        <td><?= e($ris['ris_no']) ?> <a class="btn btn-outline-secondary btn-sm ml-1" title="Print RIS" target="_blank" href="<?= uri() . '/ims/ris-print.php?id=' . encode((string) $ris['ris_id']) ?>"><i class="fas fa-print"></i></a></td>
        <td><?= e($ris['employee']) ?></td>
        <td><small class="text-muted"><?= e($ris['office'] ?: '—') ?></small></td>
        <td><small class="text-muted"><?= e($ris['division'] ?: '—') ?></small></td>
        <td>
            <?php if (empty($ris['items'])): ?>—<?php endif; ?>
            <?php foreach ($ris['items'] as $detail): ?>
                <div class="mb-1">
                    <span class="badge badge-<?= $detail['has_stock'] ? 'success' : 'warning' ?>"><?= e($detail['stock_no'] . ' - ' . $detail['description'] . ' (' . (int) $detail['quantity'] . ' ' . e($detail['unit'] ?: $detail['item_unit']) . ')') ?></span>
                    <?php if ($detail['has_stock']): ?>
                        <?php if (strtolower((string) $detail['status']) === 'pending'): ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('Issue this item and deduct from stock?');">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="issue-ris-item" value="<?= e(cipher((string) $detail['detail_id'])) ?>">
                                <button class="btn btn-sm btn-success" type="submit"><i class="fas fa-check"></i> Issued</button>
                            </form>
                        <?php elseif (strtolower((string) $detail['status']) === 'issued'): ?>
                            <span class="badge badge-success"><i class="fas fa-box-open"></i> Issued</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if (strtolower((string) $detail['status']) === 'pending'): ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('Disapprove this item?');">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="disapprove-ris-item" value="<?= e(cipher((string) $detail['detail_id'])) ?>">
                                <button class="btn btn-sm btn-danger" type="submit"><i class="fas fa-times"></i> Disapprove</button>
                            </form>
                        <?php elseif (strtolower((string) $detail['status']) === 'approved'): ?>
                            <span class="badge badge-info"><i class="fas fa-check-circle"></i> Approved</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </td>
        <td>
            <?php $statuses = array_column($ris['items'], 'status'); ?>
            <?php if ($statuses && count(array_unique(array_map('strtolower', $statuses))) === 1): ?>
                <span class="badge badge-<?= $statuses[0] === 'Issued' ? 'success' : ($statuses[0] === 'Approved' ? 'info' : 'secondary') ?>"><?= implode('', $statuses) ?></span>
            <?php elseif ($statuses): ?>
                <span class="badge badge-secondary">Mixed</span>
            <?php endif; ?>
        </td>
        <td><?= e($ris['created_at']) ?></td>
    </tr><?php endforeach; ?></tbody></table></div></div>
</div>
