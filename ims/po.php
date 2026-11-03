<?php
require_once(__DIR__ . '/navigation.php');

$pdo = connection();
$prId = (int) ($_GET['pr_id'] ?? 0);
$purchaseRequests = $pdo->query(
    "SELECT p.id, p.pr_no, p.po_no, p.po_date, p.quantity, p.unit_cost, p.total_cost, p.purpose,
            p.status, i.stock_no, i.description, i.unit, s.name AS supplier, s.address
     FROM purchase_requests p
     JOIN items i ON i.id = p.item_id
     LEFT JOIN suppliers s ON s.id = p.supplier_id
     ORDER BY p.created_at DESC"
)->fetchAll();

$pr = null;
foreach ($purchaseRequests as $request) {
    if ((int) $request['id'] === $prId) {
        $pr = $request;
        break;
    }
}

contentTitle('Purchase Order');
imsNav('pr');
?>
<div class="po-toolbar no-print">
    <form method="get" class="po-select-form">
        <input type="hidden" name="v" value="<?= e(cipher('Purchase Order')) ?>">
        <label for="po-pr">Purchase Request</label>
        <select id="po-pr" name="pr_id" required onchange="this.form.submit()">
            <option value="">Select purchase request</option>
            <?php foreach ($purchaseRequests as $request): ?>
                <option value="<?= (int) $request['id'] ?>" <?= $prId === (int) $request['id'] ? 'selected' : '' ?>><?= e($request['pr_no'] . ' - ' . $request['description'] . ' (' . $request['quantity'] . ' ' . $request['unit'] . ')') ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (!$pr): ?><button class="btn btn-primary" type="submit"><i class="fas fa-file-pdf"></i> Preview PO</button><?php endif; ?>
    </form>
</div>

<?php if ($pr): ?>
<div class="po-actions no-print">
    <form method="post" class="d-inline">
        <?= csrf_field(); ?>
        <input type="hidden" name="pr_id" value="<?= e(cipher((string) $pr['id'])) ?>">
        <button class="btn btn-primary" type="submit" name="generate-po"><i class="fas fa-file-pdf"></i> Generate PO (Save number)</button>
    </form>
    <a class="btn btn-secondary" href="<?= uri() . '/ims/po-print.php?pr_id=' . encode((string) $pr['id']) ?>" target="_blank"><i class="fas fa-print"></i> Print / Save PDF</a>
</div>
<div class="po-sheet">
    <div class="appendix">Appendix 61</div>
    <h1>PURCHASE ORDER</h1>
    <div class="entity-name">DEPARTMENT OF EDUCATION<br><span>Schools Division of Dipolog City</span></div>

    <table class="po-table po-header">
        <tr>
            <td><strong>Supplier:</strong> <?= e($pr['supplier'] ?: '____________________________________________') ?><br>
                <strong>Address:</strong> <?= e($pr['address'] ?: '____________________________________________') ?><br>
                <strong>TIN:</strong> ____________________________________________</td>
            <td><strong>P.O. No.:</strong> <?= e($pr['po_no'] ?: $pr['pr_no']) ?><br>
                <strong>Date:</strong> <?= $pr['po_date'] ? e(date('F d, Y', strtotime($pr['po_date']))) : '____________________________' ?><br>
                <strong>Mode of Procurement:</strong> __________________________</td>
        </tr>
        <tr><td colspan="2" class="gentlemen"><strong>Gentlemen:</strong><br><span>Please furnish this Office the following articles subject to the terms and conditions contained herein:</span></td></tr>
        <tr>
            <td><strong>Place of Delivery:</strong> ______________________________<br><strong>Date of Delivery:</strong> ______________________________</td>
            <td><strong>Delivery Term:</strong> ______________________________<br><strong>Payment Term:</strong> ______________________________</td>
        </tr>
    </table>

    <table class="po-table po-items">
        <thead><tr><th>Stock /<br>Property No.</th><th>Unit</th><th>Description</th><th>Quantity</th><th>Unit Cost</th><th>Amount</th></tr></thead>
        <tbody><tr><td><?= e($pr['stock_no']) ?></td><td><?= e($pr['unit']) ?></td><td><?= e($pr['description']) ?><br><small><?= e($pr['purpose'] ?: '') ?></small></td><td><?= (int) $pr['quantity'] ?></td><td>&#8369; <?= number_format((float) $pr['unit_cost'], 2) ?></td><td>&#8369; <?= number_format((float) $pr['total_cost'], 2) ?></td></tr><tr class="blank-row"><td></td><td></td><td></td><td></td><td></td><td></td></tr></tbody>
        <tfoot><tr><td colspan="6"><strong>(Total Amount in Words)</strong> <?= e(poNumberToWords((float) $pr['total_cost'])) ?></td></tr></tfoot>
    </table>

    <p class="penalty">In case of failure to make the full delivery within the time specified above, a penalty of one-tenth (1/10) of one percent for every day of delay shall be imposed on the undelivered item/s.</p>
    <div class="signatures"><div><strong>Conforme:</strong><br><br><span>_____________________________</span><br>Signature over Printed Name of Supplier<br><br><span>________________</span><br>Date</div><div><strong>Very truly yours,</strong><br><br><span>_____________________________</span><br>Signature over Printed Name of Authorized Official<br><br><span>________________</span><br>Designation</div></div>
    <table class="po-table funds"><tr><td><strong>Fund Cluster:</strong> __________________________<br><strong>Funds Available:</strong> _________________________<br><br><span>________________________________________</span><br>Signature over Printed Name of Chief Accountant/Head of Accounting Division/Unit</td><td><strong>ORS/BURS No.:</strong> ______________________<br><strong>Date of the ORS/BURS:</strong> _______________<br><br><strong>Amount:</strong> &#8369; <?= number_format((float) $pr['total_cost'], 2) ?></td></tr></table>
</div>
<?php else: ?><div class="alert alert-info">Select a purchase request to generate the Purchase Order.</div><?php endif; ?>

<style>
.po-toolbar form { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
.po-toolbar select { min-width:360px; padding:8px; }
.po-actions { display:flex; gap:10px; align-items:center; justify-content:center; margin:20px auto 0; max-width:210mm; }
.po-sheet { box-sizing:border-box; width:210mm; height:297mm; margin:20px auto; padding:12mm 10mm; color:#111; background:#fff; font:11pt "Times New Roman",serif; overflow:hidden; }
.po-sheet h1 { text-align:center; font-size:19pt; margin:15px 0 8px; letter-spacing:1px; }
.appendix { text-align:right; font-style:italic; font-size:12pt; }.entity-name { text-align:center; font-weight:bold; font-size:12pt; text-decoration:underline; }.entity-name span { text-decoration:none; font-weight:normal; }
.po-table { width:100%; border-collapse:collapse; }.po-table td,.po-table th { border:1.5px solid #111; padding:6px; vertical-align:top; }.po-header td { height:68px; line-height:1.65; }.gentlemen { height:57px; }.gentlemen span { display:block; margin:5px 0 0 28px; }.po-items { margin-top:0; }.po-items th { text-align:center; vertical-align:middle; height:42px; }.po-items td { height:35px; }.po-items .blank-row td { height:245px; }.po-items tfoot td { height:27px; }.penalty { border:1.5px solid #111; border-top:0; margin:0; padding:27px 15px; text-align:center; }.signatures { display:grid; grid-template-columns:1fr 1fr; border:1.5px solid #111; border-top:0; padding:35px 20px 25px; text-align:center; min-height:145px; }.signatures div:first-child { text-align:left; }.signatures span { display:inline-block; min-width:190px; border-bottom:1px solid #111; }.funds td { height:105px; line-height:1.8; }.funds td:first-child { text-align:center; }.funds td:first-child span { text-decoration:underline; }
@media print { @page { size:A4 portrait; margin:0; } body { background:#fff; } .no-print, .po-actions, .nav-tabs, #accordionSidebar, .sidebar, .navbar, footer { display:none !important; } .container-fluid { margin:0 !important; padding:0 !important; } .po-sheet { box-sizing:border-box; margin:0; width:210mm; height:297mm; padding:9mm 7mm; overflow:hidden; } }
</style>
