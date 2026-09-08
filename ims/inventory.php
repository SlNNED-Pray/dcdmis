<?php
require_once(__DIR__ . '/navigation.php');

$items = connection()->query('SELECT * FROM items ORDER BY description ASC')->fetchAll();
messageAlert($showAlert ?? false, $message ?? '', $success ?? true);
contentTitle('Stock and Inventory');
imsNav('inventory');
?>

<div class="card shadow mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Stock items</strong>
        <a class="btn btn-primary btn-sm" href="<?= customUri('ims', 'Create Stock Item') ?>"><i class="fas fa-plus"></i> Add item</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="ims-items-table">
                <thead><tr><th>Stock No.</th><th>Description</th><th>Unit</th><th>Total Units</th><th>On Hand</th><th>Minimum</th><th>Status</th><th>Remarks</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <?php $low = (int) $item['quantity'] <= (int) $item['min_qty']; ?>
                    <tr>
                        <?php $unitsUnit = !empty($item['number_units_unit']) ? $item['number_units_unit'] : $item['unit']; ?>
                        <td><?= e($item['stock_no']) ?></td>
                        <td><?= e($item['description']) ?></td>
                        <td><?= e($item['unit']) ?></td>
                        <td><?php $tu = (int) $item['total_units']; ?><?= $tu ? $tu . (!empty($unitsUnit) ? ' ' . e(ucfirst($unitsUnit)) : '') : '—' ?></td>
                        <td><?= (int) $item['quantity'] ?></td>
                        <td><?= (int) $item['min_qty'] ?></td>
                        <td><span class="badge badge-<?= $low ? 'warning' : 'success' ?>"><?= $low ? 'Low stock' : 'Available' ?></span></td>
                        <td><small><?= e($item['remarks'] ?: '—') ?></small></td>
                        <td>
                            <a class="btn btn-info btn-sm" href="<?= customUri('ims', 'Create RIS', $item['id']) ?>"><i class="fas fa-file-invoice"></i> RIS</a>
                            <a class="btn btn-warning btn-sm" href="<?= customUri('ims', 'Edit Stock Item', $item['id']) ?>"><i class="fas fa-edit"></i> Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
