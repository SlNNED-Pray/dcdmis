<?php
require_once(__DIR__ . '/navigation.php');
contentTitle('Report of Supplies and Materials Issued');
imsNav('rsmi');
$pdo = connection();
$rsmiFilter = trim($_GET['filter'] ?? '');

$rows = [];
$totalQty = 0;
$totalAmount = 0.0;
$statement = $pdo->query(
	"SELECT r.ris_no, r.office, r.created_at,
	        i.stock_no, i.description, i.unit, i.unit_cost,
	        ri.quantity, ri.unit AS ris_unit
	 FROM requisition_slip_items ri
	 INNER JOIN requisition_slips r ON r.id = ri.ris_id
	 INNER JOIN items i ON i.id = ri.item_id
	 WHERE LOWER(ri.status) = 'issued'
	 ORDER BY r.ris_no ASC, ri.id ASC"
)->fetchAll();

foreach ($statement as $m) {
	$unit = trim((string) ($m['ris_unit'] ?: $m['unit']));
	$qty = (int) $m['quantity'];
	$cost = (float) ($m['unit_cost'] ?? 0);
	$amount = $qty * $cost;
	$rows[] = [
		'ris_no' => $m['ris_no'],
		'rc_code' => $m['office'] ?: '—',
		'stock_no' => $m['stock_no'],
		'description' => $m['description'],
		'unit' => $unit,
		'quantity' => $qty,
		'unit_cost' => $cost,
		'amount' => $amount,
	];
	$totalQty += $qty;
	$totalAmount += $amount;
}

$rows = array_values(array_filter($rows, static function (array $row) use ($rsmiFilter): bool {
	if ($rsmiFilter === '') {
		return true;
	}
	return stripos($row['ris_no'], $rsmiFilter) !== false
		|| stripos($row['rc_code'], $rsmiFilter) !== false
		|| stripos($row['stock_no'], $rsmiFilter) !== false
		|| stripos($row['description'], $rsmiFilter) !== false;
}));

$filterOptions = array_values(array_unique(array_merge(
	array_filter(array_column($rows, 'ris_no')),
	array_filter(array_column($rows, 'rc_code')),
	array_map(static fn (array $r): string => $r['stock_no'] . ' - ' . $r['description'], $rows)
)));

$filteredTotalAmount = array_sum(array_column($rows, 'amount'));
$filteredTotalQty = array_sum(array_column($rows, 'quantity'));
?>
<div class="d-flex justify-content-between align-items-center mb-3 rsmi-toolbar">
	<form method="get" class="form-inline">
		<input type="hidden" name="v" value="<?= e(cipher('Report of Supplies and Materials Issued')) ?>">
		<label class="mr-2" for="rsmi-filter">Search</label>
		<input class="form-control mr-2" id="rsmi-filter" name="filter" list="rsmi-filter-options" value="<?= e($rsmiFilter) ?>" placeholder="RIS no., center code, stock no., or item" autocomplete="off">
		<datalist id="rsmi-filter-options">
			<?php foreach ($filterOptions as $option): ?><option value="<?= e($option) ?>"></option><?php endforeach; ?>
		</datalist>
		<button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Filter</button>
		<a class="btn btn-outline-secondary ml-2" href="<?= customUri('ims', 'Report of Supplies and Materials Issued') ?>">Clear</a>
	</form>
	<button class="btn btn-secondary" type="button" onclick="window.open('<?= uri() . '/ims/rsmi-print.php' ?>', '_blank', 'resizable=1,scrollbars=1')"><i class="fas fa-print"></i> Print</button>
</div>

<div class="card shadow mb-4">
	<div class="card-header"><strong>Report of Supplies and Materials Issued</strong></div>
	<div class="card-body">
		<div class="table-responsive">
			<table class="table table-bordered table-hover" id="rsmi-report">
				<thead>
					<tr>
						<th>RIS No.</th>
						<th>Responsibility Center Code</th>
						<th>Stock No.</th>
						<th>Item</th>
						<th>Unit</th>
						<th>Quantity Issued</th>
						<th>Unit Cost</th>
						<th>Amount</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($rows as $row): ?>
					<tr>
						<td><?= e($row['ris_no']) ?></td>
						<td><?= e($row['rc_code']) ?></td>
						<td><?= e($row['stock_no']) ?></td>
						<td><?= e($row['description']) ?></td>
						<td><?= e(ucfirst($row['unit'])) ?></td>
						<td class="text-right"><?= (int) $row['quantity'] ?></td>
						<td class="text-right"><?= number_format($row['unit_cost'], 2) ?></td>
						<td class="text-right"><?= number_format($row['amount'], 2) ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
				<?php if ($rows): ?>
					<tfoot>
						<tr class="font-weight-bold">
							<td colspan="5">Total</td>
							<td class="text-right"><?= (int) $filteredTotalQty ?></td>
							<td></td>
							<td class="text-right"><?= number_format($filteredTotalAmount, 2) ?></td>
						</tr>
					</tfoot>
				<?php endif; ?>
			</table>
		</div>
		<?php if (!$rows): ?><p class="text-center text-muted mb-0">No issued items found.</p><?php endif; ?>
	</div>
</div>

<style>
@media print {
	.rsmi-toolbar, .nav-tabs, .sidebar, #accordionSidebar, #scroll-to-top, .navbar, footer { display: none !important; }
	#content-wrapper, #content, .container-fluid { margin: 0 !important; padding: 0 !important; width: 100% !important; }
	.card { border: 0 !important; box-shadow: none !important; }
	.card-header { text-align: center; border: 0; }
	#rsmi-report { font-size: 10pt; }
}
</style>