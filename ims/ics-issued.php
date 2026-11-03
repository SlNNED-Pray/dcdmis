<?php
require_once(__DIR__ . '/navigation.php');

$all = connection()->query(
    "SELECT i.id AS ics_id, i.ics_no, i.ics_date, i.received_from, i.received_by,
            i.received_by_position, i.remarks, i.total_cost, i.created_by, i.created_at,
            d.id AS detail_id, d.item_id, d.stock_no, d.description, d.unit, d.qty, d.amount, d.status
     FROM issued_ics i
     LEFT JOIN issued_ics_items d ON d.issued_ics_id = i.id
     ORDER BY i.created_at DESC, d.id ASC"
)->fetchAll();
$grouped = [];
foreach ($all as $row) {
    $icsId = (int) $row['ics_id'];
    if (!isset($grouped[$icsId])) {
        $grouped[$icsId] = [
            'ics_id' => $icsId,
            'ics_no' => $row['ics_no'],
            'ics_date' => $row['ics_date'],
            'received_from' => $row['received_from'],
            'received_by' => $row['received_by'],
            'received_by_position' => $row['received_by_position'],
            'remarks' => $row['remarks'],
            'total_cost' => (float) $row['total_cost'],
            'created_by' => $row['created_by'],
            'created_at' => $row['created_at'],
            'items' => [],
        ];
    }
    if ($row['item_id'] ?? null) {
        $grouped[$icsId]['items'][] = $row;
    }
}
messageAlert($showAlert ?? false, $message ?? '', $success ?? true);
contentTitle('Issued ICS');
imsNav('ics-issued');
?>
<div class="card shadow mb-4">
    <div class="card-header"><strong>Issued Inventory Custodian Slips</strong></div>
    <div class="card-body"><div class="table-responsive"><table class="table table-hover"><thead><tr><th>ICS No.</th><th>Date</th><th>Received From</th><th>Received By</th><th>Item(s)</th><th>Total Cost</th><th>Date Issued</th></tr></thead><tbody>
    <?php foreach ($grouped as $row): ?><tr>
        <td><?= e($row['ics_no']) ?> <a class="btn btn-outline-secondary btn-sm ml-1" title="Print / View" target="_blank" href="<?= uri() . '/ims/ics-report-print.php?issued_id=' . encode((string) $row['ics_id']) ?>"><i class="fas fa-print"></i></a></td>
        <td><?= $row['ics_date'] ? e(date('M d, Y', strtotime((string) $row['ics_date']))) : '—' ?></td>
        <td><?= e($row['received_from'] ?: '—') ?></td>
        <td><?= e($row['received_by'] ?: '—') ?><?= $row['received_by_position'] ? ' <small class="text-muted">(' . e($row['received_by_position']) . ')</small>' : '' ?></td>
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
    <?php if (!$grouped): ?><p class="text-center text-muted my-3">No issued ICS records yet. Print an Inventory Custodian Slip to issue one.</p><?php endif; ?>
    </div></div>
</div>