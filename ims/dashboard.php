<?php
require_once(__DIR__ . '/navigation.php');

$pdo = connection();
$itemCountResult = query('SELECT COUNT(*) AS total FROM items');
$lowStockResult = query('SELECT COUNT(*) AS total FROM items WHERE quantity <= min_qty');
$pendingRisResult = query("SELECT COUNT(*) AS total FROM requisition_slip_items WHERE status IN ('Pending', 'Approved')");
$pendingPrResult = query("SELECT COUNT(*) AS total FROM purchase_requests WHERE status = 'pending'");
$itemCount = (int) ($itemCountResult[0]['total'] ?? 0);
$lowStockCount = (int) ($lowStockResult[0]['total'] ?? 0);
$pendingRis = (int) ($pendingRisResult[0]['total'] ?? 0);
$pendingPr = (int) ($pendingPrResult[0]['total'] ?? 0);

messageAlert($showAlert ?? false, $message ?? '', $success ?? true);
contentTitle('Inventory Management System');
imsNav('inventory');
?>

<div class="row mb-4">
    <?php
    card('Stock Items', customUri('ims', 'Stock and Inventory'), 'fa-boxes', 'primary', $itemCount);
    card('Low Stock', customUri('ims', 'Stock and Inventory'), 'fa-exclamation-triangle', $lowStockCount ? 'warning' : 'success', $lowStockCount);
    card('Pending RIS', customUri('ims', 'Requisition Slips'), 'fa-file-invoice', 'info', $pendingRis);
    card('Pending Purchase Requests', customUri('ims', 'Purchase Requests'), 'fa-file-invoice-dollar', 'secondary', $pendingPr);
    ?>
</div>

<div class="card shadow mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Inventory workflow</strong>
        <a class="btn btn-primary btn-sm" href="<?= customUri('ims', 'Create Stock Item') ?>"><i class="fas fa-plus"></i> Add item</a>
    </div>
    <div class="card-body">
        <p class="mb-0">Maintain stock, receive requisition slips, create purchase requests for insufficient stock, and record inspection and acceptance before issuing supplies.</p>
    </div>
</div>