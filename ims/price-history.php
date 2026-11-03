<?php
require_once(__DIR__ . '/navigation.php');

$filter = trim($_GET['filter'] ?? '');
$where = '';
$params = [];
if ($filter !== '') {
    $where = ' WHERE i.stock_no LIKE ? OR i.description LIKE ?';
    $like = '%' . $filter . '%';
    $params = [$like, $like];
}

$rows = query(
    "SELECT h.id, h.item_id, h.price_date, h.old_cost, h.new_cost, h.reference, h.remarks,
            h.created_by, h.created_at,
            i.stock_no, i.description, i.unit, i.unit_cost AS current_cost,
            CONCAT(e.last_name, ', ', e.first_name) AS recorded_by
     FROM item_cost_history h
     LEFT JOIN items i ON i.id = h.item_id
     LEFT JOIN employees e ON e.id = h.created_by
     {$where}
     ORDER BY h.price_date DESC, h.id DESC"
, $params);

$current = 0;
$totalChanges = 0;
foreach ($rows as $row) {
    $current++;
    if (round((float) $row['old_cost'], 2) !== round((float) $row['new_cost'], 2)) {
        $totalChanges++;
    }
}

contentTitle('Price History');
imsNav('price-history');
messageAlert($showAlert ?? false, $message ?? '', $success ?? true);
?>
<div class="card shadow mb-4">
    <div class="card-header">
        <strong>Unit cost changes by date</strong>
        <span class="badge badge-light ml-2"><?= $current ?> record(s)</span>
        <?php if ($totalChanges): ?><span class="badge badge-info ml-1"><?= $totalChanges ?> change(s)</span><?php endif; ?>
    </div>
    <div class="card-body">
        <form method="get" class="form-row mb-3">
            <input type="hidden" name="type" value="<?= e($_GET['type'] ?? '') ?>">
            <div class="form-group col-md-5 mb-0"><input class="form-control" type="search" name="filter" placeholder="Search by stock no. or description" value="<?= e($filter) ?>"></div>
            <div class="form-group col-md-4 mb-0"><button class="btn btn-outline-primary" type="submit"><i class="fas fa-search"></i> Filter</button><?php if ($filter !== ''): ?> <a class="btn btn-outline-secondary" href="<?= customUri('ims', 'Price History') ?>">Clear</a><?php endif; ?></div>
        </form>
        <div class="table-responsive"><table class="table table-hover table-sm"><thead><tr><th>Date</th><th>Stock No.</th><th>Description</th><th>Previous Cost</th><th>New Cost</th><th>Change</th><th>Current Cost</th><th>Reference</th><th>Recorded By</th></tr></thead><tbody>
        <?php foreach ($rows as $row): ?>
            <?php $change = (float) $row['new_cost'] - (float) $row['old_cost']; ?>
            <tr>
                <td><?= e(date('M d, Y', strtotime((string) $row['price_date']))) ?></td>
                <td><?= e($row['stock_no'] ?? '—') ?></td>
                <td><?= $row['description'] !== null ? e($row['description']) : '<em class="text-muted">Item removed</em>' ?><?= $row['unit'] !== null ? ' <small class="text-muted">(' . e($row['unit']) . ')</small>' : '' ?></td>
                <td><?= 'P' . number_format((float) $row['old_cost'], 2) ?></td>
                <td><?= 'P' . number_format((float) $row['new_cost'], 2) ?></td>
                <td>
                    <?php if ($change > 0): ?><span class="text-danger">+<?= 'P' . number_format($change, 2) ?></span>
                    <?php elseif ($change < 0): ?><span class="text-success">-<?= 'P' . number_format(abs($change), 2) ?></span>
                    <?php else: ?><span class="text-muted">0</span><?php endif; ?>
                </td>
                <td><?= $row['stock_no'] !== null ? 'P' . number_format((float) $row['current_cost'], 2) : '—' ?></td>
                <td><?= e($row['reference'] ?? '—') ?><?= $row['remarks'] !== null && $row['remarks'] !== '' ? ' <small class="text-muted">(' . e($row['remarks']) . ')</small>' : '' ?></td>
                <td><?= e($row['recorded_by'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php if (!$rows): ?><p class="text-center text-muted my-3">No price changes recorded yet.</p><?php endif; ?>
    </div>
</div>