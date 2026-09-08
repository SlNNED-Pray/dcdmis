<?php
require_once(__DIR__ . '/navigation.php');
$rows = connection()->query(
    "SELECT i.id, i.iar_no, i.ris_id, i.inspector_name, i.supplier, i.stock_no, i.description, i.unit, i.quantity, i.status, i.created_at,
            r.ris_no
     FROM iar i
     LEFT JOIN requisition_slips r ON r.id = i.ris_id
     ORDER BY i.created_at DESC"
)->fetchAll();
contentTitle('Inspection and Acceptance Reports');
imsNav('iar');
?>
<div class="card shadow mb-4"><div class="card-header d-flex justify-content-between align-items-center"><strong>Inspection and Acceptance Reports</strong><a class="btn btn-primary btn-sm" href="<?= customUri('ims', 'Create IAR') ?>"><i class="fas fa-plus"></i> Create IAR</a></div><div class="card-body"><div class="table-responsive"><table class="table table-hover"><thead><tr><th>IAR No.</th><th>RIS ID</th><th>Description</th><th>Quantity</th><th>Inspector</th><th>Status</th><th>Date</th><th>Action</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><?= e($row['iar_no']) ?></td><td><?= e($row['ris_no'] ?: (int) $row['ris_id']) ?></td><td><?= e($row['stock_no'] . ' - ' . $row['description']) ?></td><td><?= (int) $row['quantity'] ?></td><td><?= e($row['inspector_name']) ?></td><td><?= e($row['status']) ?></td><td><?= e($row['created_at']) ?></td><td><a class="btn btn-info btn-sm" href="<?= uri() . '/ims/iar-print.php?id=' . encode((string) $row['id']) ?>" target="_blank"><i class="fas fa-print"></i> Print</a></td></tr><?php endforeach; ?></tbody></table></div></div></div>
