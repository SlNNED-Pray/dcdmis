<?php
if (!$isPis) {
    require_once(root() . '/modules/error/403.php');
    return;
}
require_once(__DIR__ . '/helpers.php');

$pdo = connection();

// Get all pending PRs grouped by supplier
$sql = "SELECT p.*, e.name AS employee, e.designation, e.office,
               i.stock_no, i.description, i.unit, s.id AS supplier_id, s.name AS supplier
        FROM purchase_requests p
        JOIN employees e ON e.id = p.employee_id
        JOIN items i ON i.id = p.item_id
        LEFT JOIN suppliers s ON s.id = p.supplier_id
        WHERE p.status = 'pending'
        ORDER BY s.id, p.created_at ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$all_prs = $stmt->fetchAll();

// Get filter parameters
$supplier_id = (int)($_GET['supplier_id'] ?? 0);
$po_date = trim($_GET['po_date'] ?? '');
$po_no = trim($_GET['po_no'] ?? '');

// Group by supplier
$grouped = [];
$suppliers_list = [];
foreach ($all_prs as $pr) {
    $sid = (int)($pr['supplier_id'] ?? 0);
    if (!isset($grouped[$sid])) {
        $grouped[$sid] = [];
    }
    $grouped[$sid][] = $pr;
    if (!in_array($pr['supplier'], $suppliers_list) && $pr['supplier']) {
        $suppliers_list[] = $pr['supplier'];
    }
}

// Filter by selected supplier
$filtered_prs = [];
if ($supplier_id > 0 && isset($grouped[$supplier_id])) {
    $filtered_prs = $grouped[$supplier_id];
} elseif ($supplier_id === 0 && count($grouped) > 0) {
    // Show first supplier by default
    $first_key = key($grouped);
    $filtered_prs = $grouped[$first_key];
    $supplier_id = $first_key;
}

// Get supplier info
$current_supplier = null;
if ($supplier_id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM suppliers WHERE id = ?');
    $stmt->execute([$supplier_id]);
    $current_supplier = $stmt->fetch();
}

// Calculate totals
$total_amount = 0;
foreach ($filtered_prs as $pr) {
    $total_amount += (float)$pr['total_cost'];
}

?>
<div class="no-print" style="margin-bottom:18px;">
    <div class="card">
        <div class="card-header"><h2>Purchase Order (Appendix 61)</h2></div>
        <div class="card-body">
            <form method="get" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Supplier</label>
                        <select name="supplier_id">
                            <option value="">-- Select Supplier --</option>
                            <?php
                            $stmt = $pdo->query('SELECT DISTINCT id, name FROM suppliers ORDER BY name ASC');
                            foreach ($stmt->fetchAll() as $sup):
                                $count = isset($grouped[(int)$sup['id']]) ? count($grouped[(int)$sup['id']]) : 0;
                                if ($count > 0):
                            ?>
                            <option value="<?= (int)$sup['id'] ?>" <?= $supplier_id === (int)$sup['id'] ? 'selected' : '' ?>>
                                <?= e($sup['name']) ?> (<?= $count ?> items)
                            </option>
                            <?php endif; endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>PO Number</label>
                        <input type="text" name="po_no" value="<?= e($po_no) ?>" placeholder="e.g., PO-2026-001">
                    </div>
                    <div class="form-group">
                        <label>PO Date</label>
                        <input type="date" name="po_date" value="<?= e($po_date) ?>">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Generate Purchase Order</button>
                    <?php if ($filtered_prs): ?>
                        <button type="button" class="btn btn-secondary" onclick="window.print()">Print / Save as PDF</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>



<?php if ($filtered_prs && $current_supplier): ?>
<div class="print-sheet po-report-sheet" style="position: relative; overflow: hidden; background: transparent;">
    <div style="position: relative; z-index: 1;">

    <!-- Form Header -->
    <div class="sheet-head" style="text-align:center;margin-bottom:16px;border-bottom:2px solid #000;padding-bottom:12px;">
        <div style="font-size:11px;letter-spacing:1px;margin-bottom:4px;">Appendix 61</div>
        <h2 style="margin:8px 0;font-size:16px;letter-spacing:2px;">PURCHASE ORDER</h2>
    </div>

    <!-- PO Info Section -->
    <table style="width:100%;border-collapse:collapse;margin-bottom:16px;font-size:12px;">
        <tr>
            <td style="width:50%;vertical-align:top;padding:4px 0;border:1px solid #000;">
                <div style="padding:6px 8px;">
                    <strong>Entity Name:</strong><br>
                    <span style="margin-left:12px;"><?= e($_SESSION['full_name'] ?? 'Procurement System') ?></span>
                </div>
            </td>
            <td style="width:50%;vertical-align:top;padding:4px 0;border:1px solid #000;">
                <div style="padding:6px 8px;">
                    <strong>PO No.:</strong> <?= e($po_no) ?><br>
                    <strong>Date:</strong> <?= $po_date ? e(date('M d, Y', strtotime($po_date))) : date('M d, Y') ?>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="padding:8px;border:1px solid #000;border-top:none;">
                <div style="margin-bottom:4px;"><strong>Supplier:</strong></div>
                <div style="margin-left:12px;line-height:1.6;">
                    <strong><?= e($current_supplier['name']) ?></strong><br>
                    <?php if ($current_supplier['address']): ?>
                        Address: <?= e($current_supplier['address']) ?><br>
                    <?php endif; ?>
                    <?php if ($current_supplier['contact']): ?>
                        Contact: <?= e($current_supplier['contact']) ?><br>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    </table>

    <!-- Items Table -->
    <table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:11px;">
        <thead>
            <tr style="background:#f5f5f5;border:1px solid #000;">
                <th style="width:50px;border:1px solid #000;padding:6px 4px;text-align:center;">Line</th>
                <th style="width:70px;border:1px solid #000;padding:6px 4px;text-align:center;">Stock No.</th>
                <th style="min-width:180px;border:1px solid #000;padding:6px 4px;text-align:left;">Item Description</th>
                <th style="width:50px;border:1px solid #000;padding:6px 4px;text-align:center;">Unit</th>
                <th style="width:50px;border:1px solid #000;padding:6px 4px;text-align:center;">Qty.</th>
                <th style="width:70px;border:1px solid #000;padding:6px 4px;text-align:right;">Unit Cost</th>
                <th style="width:80px;border:1px solid #000;padding:6px 4px;text-align:right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php $line_no = 1; foreach ($filtered_prs as $pr): ?>
            <tr style="border:1px solid #000;">
                <td style="border:1px solid #000;padding:4px 2px;text-align:center;"><?= $line_no ?></td>
                <td style="border:1px solid #000;padding:4px 4px;text-align:center;"><?= e($pr['stock_no']) ?></td>
                <td style="border:1px solid #000;padding:4px 6px;"><?= e($pr['description']) ?></td>
                <td style="border:1px solid #000;padding:4px 4px;text-align:center;"><?= e($pr['unit']) ?></td>
                <td style="border:1px solid #000;padding:4px 2px;text-align:right;"><?= (int)$pr['quantity'] ?></td>
                <td style="border:1px solid #000;padding:4px 6px;text-align:right;">₱ <?= imsMoney($pr['unit_cost']) ?></td>
                <td style="border:1px solid #000;padding:4px 6px;text-align:right;">₱ <?= imsMoney($pr['total_cost']) ?></td>
            </tr>
            <?php $line_no++; endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background:#f5f5f5;border:1px solid #000;font-weight:bold;">
                <td colspan="6" style="border:1px solid #000;padding:6px 8px;text-align:right;">TOTAL AMOUNT:</td>
                <td style="border:1px solid #000;padding:6px 8px;text-align:right;">₱ <?= imsMoney($total_amount) ?></td>
            </tr>
        </tfoot>
    </table>

    <!-- Notes Section -->
    <div style="margin:16px 0;padding:8px;border:1px solid #000;min-height:60px;font-size:11px;">
        <strong>Terms & Conditions / Remarks:</strong><br>
        <div style="margin-top:8px;min-height:40px;"></div>
    </div>

    <!-- Signature Section -->
    <div style="margin-top:32px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;font-size:11px;">
        <div style="text-align:center;">
            <div style="border-bottom:1px solid #000;height:50px;margin-bottom:4px;"></div>
            <strong>Prepared by:</strong><br>
            <span style="font-size:10px;">Signature over Printed Name<br>Procurement Officer</span>
        </div>
        <div style="text-align:center;">
            <div style="border-bottom:1px solid #000;height:50px;margin-bottom:4px;"></div>
            <strong>Approved by:</strong><br>
            <span style="font-size:10px;">Signature over Printed Name<br>Head / Director</span>
        </div>
        <div style="text-align:center;">
            <div style="border-bottom:1px solid #000;height:50px;margin-bottom:4px;"></div>
            <strong>Received by:</strong><br>
            <span style="font-size:10px;">Signature over Printed Name<br>Supplier Representative</span>
        </div>
    </div>
    </div>
</div>

<?php elseif (!$filtered_prs): ?>
<div class="card">
    <div class="card-body">
        <p class="text-muted">No pending purchase requests for the selected supplier.</p>
    </div>
</div>
<?php endif; ?>
