<?php
if (!$isPis) {
    require_once(root() . '/modules/error/403.php');
    return;
}
require_once(__DIR__ . '/helpers.php');

$search = trim($_GET['q'] ?? '');
$sql = "SELECT p.*, e.name AS employee, i.stock_no, i.description, i.unit,
               s.name AS supplier,
               (SELECT COUNT(*) FROM iar a WHERE a.pr_id = p.id) AS iar_count
        FROM purchase_requests p
        JOIN employees e ON e.id = p.employee_id
        JOIN items i ON i.id = p.item_id
        LEFT JOIN suppliers s ON s.id = p.supplier_id
        WHERE 1";
$params = [];
if ($search !== '') {
    $sql .= ' AND (p.pr_no LIKE ? OR i.stock_no LIKE ? OR i.description LIKE ?)';
    $params = ["%$search%", "%$search%", "%$search%"];
}
$sql .= ' ORDER BY p.created_at DESC';
$stmt = connection()->prepare($sql);
$stmt->execute($params);
$prs = $stmt->fetchAll();
?>
<div class="card">
    <div class="card-header">
        <h2>Purchase Requests</h2>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <form method="get" action="">
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search PR no. or item"
                       style="padding:8px 12px;border:1px solid var(--gray-200);border-radius:var(--radius);">
            </form>
            <a class="btn btn-primary" href="<?= customUri('pis', 'New Purchase Request') ?>">New Purchase Request</a>
        </div>
    </div>
    <div class="card-body">
        <?php if ($prs): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>PR No.</th>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Unit Cost</th>
                        <th>Total Cost</th>
                        <th>Supplier</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prs as $p): ?>
                    <tr>
                        <td><?= e($p['pr_no']) ?></td>
                        <td><?= e($p['description']) ?> <span class="muted">(<?= e($p['stock_no']) ?>)</span></td>
                        <td><?= (int)$p['quantity'] ?> <?= e($p['unit']) ?></td>
                        <td>₱ <?= imsMoney($p['unit_cost']) ?></td>
                        <td>₱ <?= imsMoney($p['total_cost']) ?></td>
                        <td><?= e($p['supplier'] ?: '—') ?></td>
                        <td><span class="badge badge-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
                        <td style="white-space:nowrap;">
                            <a class="btn btn-secondary btn-sm" href="<?= customUri('pis', 'View Purchase Request', $p['id']) ?>">View</a>
                            <a class="btn btn-info btn-sm" href="<?= customUri('pis', 'Purchase Order') ?>">PO Report</a>
                            <?php if ($p['status'] === 'pending'): ?>
                                <a class="btn btn-success btn-sm" href="<?= customUri('pis', 'Mark Purchase Request Arrived', $p['id']) ?>"
                                   onclick="return confirm('Mark this purchase request as arrived?');">Arrived</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p class="text-muted">No purchase requests found.</p>
        <?php endif; ?>
    </div>
</div>
