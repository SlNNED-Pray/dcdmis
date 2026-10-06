<?php
require_once(__DIR__ . '/navigation.php');

$suppliers = connection()->query(
    "SELECT s.id, s.name, s.address, s.contact, s.created_at,
            (SELECT COUNT(*) FROM purchase_requests pr WHERE pr.supplier_id = s.id) AS pr_count
     FROM suppliers s
     ORDER BY s.name ASC"
)->fetchAll();
$supplierCount = count($suppliers);
messageAlert($showAlert ?? false, $message ?? '', $success ?? true);
contentTitle('Suppliers');
imsNav('suppliers');
?>

<div class="card shadow mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Suppliers</strong>
        <button class="btn btn-primary btn-sm" type="button" data-toggle="modal" data-target="#addSupplierModal"><i class="fas fa-plus"></i> Add supplier</button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Name</th><th>Address</th><th>Contact</th><th>Purchase Requests</th><th>Date Added</th><th>Action</th></tr></thead>
                <tbody>
                <?php if (!$suppliers): ?>
                    <tr><td colspan="6" class="text-center text-muted">No suppliers yet. Add one to use it on purchase requests.</td></tr>
                <?php endif; ?>
                <?php foreach ($suppliers as $supplier): ?>
                    <tr>
                        <td><?= e($supplier['name']) ?></td>
                        <td><?= e($supplier['address'] ?: '—') ?></td>
                        <td><?= e($supplier['contact'] ?: '—') ?></td>
                        <td><?= (int) $supplier['pr_count'] ?></td>
                        <td><?= e(date('M d, Y', strtotime($supplier['created_at']))) ?></td>
                        <td>
                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this supplier?');">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="verifier" value="<?= e(cipher((string) $supplier['id'])) ?>">
                                <button class="btn btn-danger btn-sm" type="submit" name="delete-supplier"><i class="fas fa-trash"></i> Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addSupplierModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="post" class="modal-content">
            <?= csrf_field(); ?>
            <div class="modal-header"><h5 class="modal-title">Add supplier</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body">
                <div class="form-group"><label>Name *</label><input class="form-control" name="name" required></div>
                <div class="form-group"><label>Address</label><input class="form-control" name="address"></div>
                <div class="form-group"><label>Contact</label><input class="form-control" name="contact"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" name="save-supplier"><i class="fas fa-check"></i> Save</button>
            </div>
        </form>
    </div>
</div>