<?php
if (!$isPis) {
    require_once(root() . '/modules/error/403.php');
    return;
}
require_once(__DIR__ . '/helpers.php');

$id = (int)($_GET['id'] ?? 0);
$stmt = connection()->prepare(
    'SELECT p.*, e.name AS employee, e.designation, e.office,
            i.stock_no, i.description, i.unit, s.name AS supplier
     FROM purchase_requests p
     JOIN employees e ON e.id = p.employee_id
     JOIN items i ON i.id = p.item_id
     LEFT JOIN suppliers s ON s.id = p.supplier_id
     WHERE p.id = ?'
);
$stmt->execute([$id]);
$pr = $stmt->fetch();
if (!$pr) {
    messageAlert(true, 'Purchase request not found.', false);
    return;
}

$iar = connection()->prepare('SELECT * FROM iar WHERE pr_id = ? ORDER BY created_at DESC LIMIT 1');
$iar->execute([$id]);
$iar = $iar->fetch();

?>
<div class="print-sheet">
    <div class="sheet-head no-print" style="display:flex;justify-content:space-between;align-items:center;">
        <h3>Purchase Request</h3>
        <div style="display:flex;gap:8px;">
            <?php if ($pr['status'] === 'pending'): ?>
                <a class="btn btn-success btn-sm" href="<?= customUri('pis', 'Mark Purchase Request Arrived', $pr['id']) ?>"
                   onclick="return confirm('Mark this purchase request as arrived?');">Mark Arrived</a>
            <?php endif; ?>
            <a class="btn btn-secondary btn-sm" href="<?= customUri('pis', 'Purchase Requests') ?>">Back</a>
        </div>
    </div>

    <div class="sheet-head">
        <h3>Purchase Request</h3>
        <span>PR No.: <strong><?= e($pr['pr_no']) ?></strong></span>
    </div>

    <div class="sheet-meta">
        <span><strong>Name:</strong> <?= e($pr['employee']) ?></span>
        <span><strong>Status:</strong> <?= e($pr['status']) ?></span>
        <span><strong>Designation:</strong> <?= e($pr['designation']) ?></span>
        <span><strong>Office:</strong> <?= e($pr['office']) ?></span>
        <span><strong>Supplier:</strong> <?= e($pr['supplier'] ?: '—') ?></span>
        <span><strong>PO No. / Date:</strong> <?= e($pr['po_no']) ?> / <?= $pr['po_date'] ? e($pr['po_date']) : '—' ?></span>
        <span><strong>Purpose:</strong> <?= e($pr['purpose']) ?></span>
        <span><strong>Date:</strong> <?= e(date('M d, Y', strtotime($pr['created_at']))) ?></span>
    </div>

    <table class="sheet">
        <thead>
            <tr>
                <th>Stock / Property No.</th>
                <th>Item Description</th>
                <th>Unit</th>
                <th>Quantity</th>
                <th>Unit Cost</th>
                <th>Total Cost</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?= e($pr['stock_no']) ?></td>
                <td><?= e($pr['description']) ?></td>
                <td><?= e($pr['unit']) ?></td>
                <td><?= (int)$pr['quantity'] ?></td>
                <td>₱ <?= imsMoney($pr['unit_cost']) ?></td>
                <td>₱ <?= imsMoney($pr['total_cost']) ?></td>
            </tr>
        </tbody>
    </table>

    <?php if ($iar): ?>
    <p style="margin-top:18px;">
        Inspection &amp; Acceptance Report:
                <a href="<?= e(uri()) ?>/iar/view.php?id=<?= (int)$iar['id'] ?>" class="btn btn-secondary btn-sm"><?= e($iar['iar_no']) ?></a>
    </p>
    <?php endif; ?>

    <div class="sig">
        <div>Requested by<span class="line"><?= e($pr['employee']) ?></span></div>
        <div>Approved by<span class="line"><?= e($_SESSION['full_name'] ?? '') ?></span></div>
    </div>
</div>
