<?php
require_once(__DIR__ . '/navigation.php');
contentTitle('Stock Card Report');
imsNav('stock-card');
$pdo = connection();
$stockCardFilter = trim($_GET['filter'] ?? '');
$items = $pdo->query('SELECT id, stock_no, description, unit FROM items ORDER BY description')->fetchAll();

$movementStatement = $pdo->query(
	"SELECT m.created_at AS transaction_date, m.reference_no, m.movement_type, m.quantity, m.personnel, m.office, m.remarks,
			i.stock_no, i.description, i.unit
	 FROM stock_movements m
	 JOIN items i ON i.id = m.item_id
	 ORDER BY m.created_at ASC"
)->fetchAll();

$rows = [];
foreach ($movementStatement as $mov) {
	$absQty = abs((int) $mov['quantity']);
	$rows[] = [
		'date' => $mov['transaction_date'],
		'reference' => $mov['reference_no'] ?: '—',
		'receipt' => $mov['movement_type'] === 'Receipt' ? $absQty : '—',
		'personnel' => $mov['personnel'] ?: 'Unknown',
		'office' => $mov['office'] ?: '—',
		'qty' => $absQty,
		'type' => $mov['movement_type'],
		'stock_no' => $mov['stock_no'],
		'description' => $mov['description'],
		'unit' => $mov['unit'],
		'remarks' => $mov['remarks'] ?? '',
	];
}
usort($rows, static fn (array $left, array $right): int => strcmp($left['date'], $right['date']));
$filterOptions = array_values(array_unique(array_merge(
	array_filter(array_column($rows, 'personnel')),
	array_filter(array_column($rows, 'office')),
	array_map(static fn (array $item): string => $item['stock_no'] . ' - ' . $item['description'], $items)
)));
$rows = array_values(array_filter($rows, static function (array $row) use ($stockCardFilter): bool {
	if ($stockCardFilter === '') {
		return true;
	}
	$itemLabel = $row['stock_no'] . ' - ' . $row['description'];
	return stripos($itemLabel, $stockCardFilter) !== false
		|| stripos($row['personnel'], $stockCardFilter) !== false
		|| stripos($row['office'], $stockCardFilter) !== false;
}));

?>
<div class="d-flex justify-content-between align-items-center mb-3 stock-card-toolbar">
	<form method="get" class="form-inline">
		<input type="hidden" name="v" value="<?= e(cipher('Stock Card')) ?>">
		<label class="mr-2" for="stock-card-filter">Search</label>
		<input class="form-control mr-2" id="stock-card-filter" name="filter" list="stock-card-filter-options" value="<?= e($stockCardFilter) ?>" placeholder="Name, office, stock no., or item" autocomplete="off">
		<datalist id="stock-card-filter-options">
			<?php foreach ($filterOptions as $option): ?><option value="<?= e($option) ?>"></option><?php endforeach; ?>
		</datalist>
		<button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Filter</button>
		<a class="btn btn-outline-secondary ml-2" href="<?= customUri('ims', 'Stock Card') ?>">Clear</a>
	</form>
	<button class="btn btn-secondary" type="button" onclick="window.open('<?= uri() . '/ims/stock-card-print.php?filter=' . urlencode($stockCardFilter) ?>', '_blank', 'resizable=1,scrollbars=1')"><i class="fas fa-print"></i> Print</button>
</div>

<div class="card shadow mb-4">
	<div class="card-header"><strong>Stock Card Transactions</strong></div>
	<div class="card-body">
		<div class="table-responsive">
			<table class="table table-bordered table-hover" id="stock-card-report">
				<thead><tr><th>Date</th><th>Reference</th><th>Receipt</th><th>Name of Personnel</th><th>Office Name</th><th>Qty</th><th>Remarks</th></tr></thead>
				<tbody>
				<?php foreach ($rows as $row): ?>
					<tr>
						<td><?= e(date('M d, Y', strtotime($row['date']))) ?></td>
						<td><?= e($row['reference']) ?><?php if ($row['type'] === 'Adjustment'): ?> <span class="badge badge-info">Adjustment</span><?php elseif ($row['type'] === 'Issue'): ?> <span class="badge badge-warning">Issue</span><?php endif; ?><br><small class="text-muted"><?= e($row['stock_no'] . ' - ' . $row['description']) ?></small></td>
						<td><?= e((string) $row['receipt']) ?></td>
						<td><?= e($row['personnel']) ?></td>
						<td><?= e($row['office']) ?></td>
						<td><?= (int) $row['qty'] . ' ' . e($row['unit']) ?></td>
						<td><small><?= e($row['remarks'] ?: '—') ?></small></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php if (!$rows): ?><p class="text-center text-muted mb-0">No stock-card transactions found.</p><?php endif; ?>
	</div>
</div>

<style>
@media print {
	.stock-card-toolbar, .nav-tabs, .sidebar, #accordionSidebar, #scroll-to-top, .navbar, footer { display: none !important; }
	#content-wrapper, #content, .container-fluid { margin: 0 !important; padding: 0 !important; width: 100% !important; }
	.card { border: 0 !important; box-shadow: none !important; }
	.card-header { text-align: center; border: 0; }
	#stock-card-report { font-size: 10pt; }
}
</style>
