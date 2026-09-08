<?php
require_once(__DIR__ . '/navigation.php');

$rows = connection()->query(
    "SELECT p.*, i.stock_no, i.description, i.unit, s.name AS supplier
     FROM purchase_requests p JOIN items i ON i.id = p.item_id
     LEFT JOIN suppliers s ON s.id = p.supplier_id ORDER BY p.created_at DESC"
)->fetchAll();
messageAlert($showAlert ?? false, $message ?? '', $success ?? true);
contentTitle('Purchase Requests');
imsNav('pr');
?>
<div class="card shadow mb-4"><div class="card-header d-flex justify-content-between align-items-center"><strong>Purchase requests</strong><a class="btn btn-primary btn-sm" href="<?= customUri('ims', 'Create Purchase Request') ?>"><i class="fas fa-plus"></i> Create PR</a></div><div class="card-body"><div class="table-responsive"><table class="table table-hover"><thead><tr><th>PR No.</th><th>Item</th><th>Qty</th><th>Total</th><th>Supplier</th><th>Source of Fund</th><th>Status</th><th>Date</th><th>Action</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><?= e($row['pr_no']) ?></td><td><?= e($row['stock_no'] . ' - ' . $row['description']) ?></td><td><?= (int) $row['quantity'] . ' ' . e($row['unit']) ?></td><td>&#8369; <?= number_format((float) $row['total_cost'], 2) ?></td><td><?= e($row['supplier'] ?: 'Not assigned') ?></td><td><span class="badge badge-info"><?= e($row['source_of_funds'] ?: '—') ?></span></td><td><?= e(ucfirst($row['status'])) ?></td><td><?= e($row['created_at']) ?></td><td><a class="btn btn-sm btn-secondary" href="<?= customUri('ims', 'Purchase Order') ?>&pr_id=<?= (int) $row['id'] ?>"><i class="fas fa-file-pdf"></i> PO</a></td></tr><?php endforeach; ?></tbody></table></div></div></div>
