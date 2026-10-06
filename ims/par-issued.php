<?php
require_once(__DIR__ . '/navigation.php');

$all = connection()->query(
    "SELECT p.id AS par_id, p.par_no, p.end_user, p.end_user_position, p.fund_cluster, p.remarks,
            p.total_cost, p.created_by, p.created_at,
            d.id AS detail_id, d.item_id, d.stock_no, d.description, d.unit, d.qty, d.amount
     FROM issued_par p
     LEFT JOIN issued_par_items d ON d.issued_par_id = p.id
     ORDER BY p.created_at DESC, d.id ASC"
)->fetchAll();
$grouped = [];
foreach ($all as $row) {
    $parId = (int) $row['par_id'];
    if (!isset($grouped[$parId])) {
        $grouped[$parId] = [
            'par_id' => $parId,
            'par_no' => $row['par_no'],
            'end_user' => $row['end_user'],
            'end_user_position' => $row['end_user_position'],
            'fund_cluster' => $row['fund_cluster'],
            'remarks' => $row['remarks'],
            'total_cost' => (float) $row['total_cost'],
            'created_by' => $row['created_by'],
            'created_at' => $row['created_at'],
            'items' => [],
        ];
    }
    if ($row['item_id'] ?? null) {
        $grouped[$parId]['items'][] = $row;
    }
}
messageAlert($showAlert ?? false, $message ?? '', $success ?? true);
contentTitle('Issued PAR');
imsNav('par-issued');
?>
<div class="card shadow mb-4">
    <div class="card-header"><strong>Issued Property Acknowledgement Receipts</strong></div>
    <div class="card-body"><div class="table-responsive"><table class="table table-hover"><thead><tr><th>PAR No.</th><th>End User</th><th>Fund Cluster</th><th>Item(s)</th><th>Total Cost</th><th>Date Issued</th></tr></thead><tbody>
    <?php foreach ($grouped as $row): ?><tr>
        <td><?= e($row['par_no']) ?> <a class="btn btn-outline-secondary btn-sm ml-1" title="Print / View" target="_blank" href="<?= uri() . '/ims/par-print.php?issued_id=' . encode((string) $row['par_id']) ?>"><i class="fas fa-print"></i></a></td>
        <td><?= e($row['end_user'] ?: '—') ?><?= $row['end_user_position'] ? ' <small class="text-muted">(' . e($row['end_user_position']) . ')</small>' : '' ?></td>
        <td><?= e($row['fund_cluster'] ?: '—') ?></td>
        <td>
            <?php if (empty($row['items'])): ?>—<?php endif; ?>
            <?php foreach ($row['items'] as $detail): ?>
                <div class="mb-1"><span class="badge badge-light border"><?= e($detail['stock_no'] . ' - ' . $detail['description'] . ' (' . (int) $detail['qty'] . ' ' . e($detail['unit']) . ', P' . number_format((float) $detail['amount'], 2) . ')') ?></span></div>
            <?php endforeach; ?>
            <?php if ($row['remarks'] !== '' && $row['remarks'] !== null): ?>
                <div class="mt-1"><small class="text-muted"><i class="fas fa-sticky-note"></i> <?= e((string) $row['remarks']) ?></small></div>
            <?php endif; ?>
        </td>
        <td><?= e('P' . number_format($row['total_cost'], 2)) ?></td>
        <td><?= e($row['created_at']) ?></td>
    </tr><?php endforeach; ?></tbody></table>
    <?php if (!$grouped): ?><p class="text-center text-muted my-3">No issued PAR records yet. Print a Property Acknowledgement Receipt to issue one.</p><?php endif; ?>
    </div></div>
</div>