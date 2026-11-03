<?php
require_once(__DIR__ . '/navigation.php');
contentTitle('Inspection and Acceptance Report');
imsNav('iar-report');
messageAlert($showAlert ?? false, $message ?? '', $success ?? true);

$filter = trim($_GET['filter'] ?? '');

$orders = query(
    "SELECT p.id, p.pr_no, p.po_no, p.po_date, p.quantity, p.unit_cost, p.total_cost, p.purpose, p.status, p.delivery_status,
            i.stock_no, i.description, i.unit, s.name AS supplier
     FROM purchase_requests p
     JOIN items i ON i.id = p.item_id
     LEFT JOIN suppliers s ON s.id = p.supplier_id
     WHERE p.po_no IS NOT NULL AND TRIM(p.po_no) <> ''
     ORDER BY p.po_date DESC, p.id DESC"
) ?: [];

if ($filter !== '') {
    $orders = array_values(array_filter($orders, static function (array $row) use ($filter): bool {
        $haystack = implode(' ', [
            (string) $row['po_no'],
            (string) $row['pr_no'],
            (string) $row['supplier'],
            (string) $row['stock_no'],
            (string) $row['description'],
        ]);
        return stripos($haystack, $filter) !== false;
    }));
}

$filterOptions = array_values(array_unique(array_filter(array_merge(
    array_column($orders, 'po_no'),
    array_column($orders, 'stock_no')
))));
$orderCount = count($orders);

$iarByPo = [];
$poNumbers = array_values(array_filter(array_unique(array_column($orders, 'po_no')), static fn ($v) => (string) $v !== ''));
if ($poNumbers) {
    $placeholders = implode(',', array_fill(0, count($poNumbers), '?'));
    foreach (query("SELECT po_no, MAX(id) AS iar_id FROM issued_iar WHERE po_no IN ($placeholders) GROUP BY po_no", $poNumbers) ?: [] as $iap) {
        $iarByPo[(string) $iap['po_no']] = (int) $iap['iar_id'];
    }
}
?>
<div class="d-flex justify-content-between align-items-center mb-3 stock-card-toolbar">
	<form method="get" class="form-inline">
		<input type="hidden" name="v" value="<?= e(cipher('Inspection and Acceptance Report')) ?>">
		<label class="mr-2" for="iar-filter">Search</label>
		<input class="form-control mr-2" id="iar-filter" name="filter" value="<?= e($filter) ?>" list="iar-filter-options" placeholder="PO no., supplier, or item" autocomplete="off">
		<datalist id="iar-filter-options">
			<?php foreach ($filterOptions as $option): ?><option value="<?= e($option) ?>"></option><?php endforeach; ?>
		</datalist>
		<button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Filter</button>
		<a class="btn btn-outline-secondary ml-2" href="<?= customUri('ims', 'Inspection and Acceptance Report') ?>">Clear</a>
	</form>
</div>

<div class="card shadow mb-4">
	<div class="card-header"><strong>Purchase Orders for Inspection and Acceptance</strong> <small class="text-muted">(<?= $orderCount ?> order(s))</small></div>
	<div class="card-body">
		<div class="table-responsive">
			<table class="table table-bordered table-hover" id="iar-report-table">
				<thead>
					<tr>
						<th>PO No.</th>
						<th>PO Date</th>
						<th>Supplier</th>
						<th>Item</th>
						<th>Unit</th>
						<th style="text-align:right">Quantity</th>
						<th style="text-align:right">Unit Cost</th>
						<th>Status</th>
						<th>Action</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($orders as $row): $status = (string) ($row['delivery_status'] ?: 'Pending'); ?>
						<tr>
							<td><?= e($row['po_no']) ?></td>
							<td><?= $row['po_date'] ? e(date('M d, Y', strtotime($row['po_date']))) : '—' ?></td>
							<td><?= e($row['supplier'] ?: '—') ?></td>
							<td><?= e($row['stock_no'] . ' - ' . $row['description']) ?></td>
							<td><?= e(ucfirst((string) $row['unit'])) ?></td>
							<td style="text-align:right"><?= (int) $row['quantity'] ?></td>
							<td style="text-align:right"><?= 'P' . number_format((float) $row['unit_cost'], 2) ?></td>
							<td>
								<?php if ($status === 'Delivered'): ?><span class="badge badge-success">Delivered</span>
								<?php elseif ($status === 'Partial'): ?><span class="badge badge-warning">Partial</span>
								<?php else: ?><span class="badge badge-secondary">Pending</span><?php endif; ?>
							</td>
							<td class="text-nowrap">
								<?php $iarLink = (int) ($iarByPo[(string) $row['po_no']] ?? 0); ?>
								<?php if ($status === 'Delivered'): ?>
									<?php if ($iarLink): ?><a class="btn btn-outline-success btn-sm mb-1 mr-1" target="_blank" title="Print the saved IAR" href="<?= uri() . '/ims/iar-report-print.php?issued_id=' . encode((string) $iarLink) ?>"><i class="fas fa-print"></i> Print IAR</a>
									<?php else: ?><a class="btn btn-outline-success btn-sm mb-1 mr-1" target="_blank" title="Print the saved data of this delivered purchase order" href="<?= uri() . '/ims/iar-report-print.php?po_id=' . encode((string) $row['id']) ?>"><i class="fas fa-print"></i> Print IAR</a><?php endif; ?>
								<?php elseif ($status === 'Partial'): ?><a class="btn btn-warning btn-sm mb-1 mr-1" target="_blank" title="Edit and update the saved IAR data" href="<?= uri() . '/ims/iar-report-print.php?po_id=' . encode((string) $row['id']) . '&edit=1' ?>"><i class="fas fa-edit"></i> Update IAR</a>
								<?php else: ?><a class="btn btn-info btn-sm mb-1 mr-1" target="_blank" href="<?= uri() . '/ims/iar-report-print.php?po_id=' . encode((string) $row['id']) ?>"><i class="fas fa-print"></i> Create IAR</a><?php endif; ?>
								</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php if (!$orders): ?>
			<p class="text-center text-muted mb-0">No purchase orders found. Generate a PO from a purchase request first.</p>
		<?php endif; ?>
	</div>
</div>
