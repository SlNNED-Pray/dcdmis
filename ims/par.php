<?php
require_once(__DIR__ . '/navigation.php');
contentTitle('Property Acknowledgement Receipt');
imsNav('par');

$pdo = connection();
$parFilter = trim($_GET['filter'] ?? '');

$items = query(
    "SELECT id, stock_no, description, unit, unit_cost, quantity, created_at
     FROM items
     WHERE unit_cost >= 50000
     ORDER BY description ASC"
);

$rows = [];
$sumAmount = 0.0;
foreach ($items ?: [] as $item) {
    $qty = (int) $item['quantity'];
    $unitCost = (float) $item['unit_cost'];
    $amount = round($unitCost * $qty, 2);
    $rows[] = [
        'stock_no' => $item['stock_no'],
        'description' => $item['description'],
        'unit' => $item['unit'],
        'qty' => $qty,
        'unit_cost' => $unitCost,
        'amount' => $amount,
        'date_acquired' => $item['created_at'] !== ''
            ? date('M d, Y', strtotime($item['created_at']))
            : '—',
    ];
    $sumAmount += $amount;
}
if ($parFilter !== '') {
    $rows = array_values(array_filter($rows, static function (array $row) use ($parFilter): bool {
        $label = $row['stock_no'] . ' - ' . $row['description'];
        return stripos($label, $parFilter) !== false
            || stripos($row['stock_no'], $parFilter) !== false
            || stripos($row['description'], $parFilter) !== false;
    }));
    $sumAmount = array_sum(array_map(static fn (array $row): float => $row['amount'], $rows));
}
$filterOptions = array_values(array_unique(array_filter(array_map(
    static fn (array $row): string => $row['stock_no'],
    $rows
))));
$parCount = count($rows);
?>
<div class="d-flex justify-content-between align-items-center mb-3 stock-card-toolbar">
	<form method="get" class="form-inline">
		<input type="hidden" name="v" value="<?= e(cipher('Property Acknowledgement Receipt')) ?>">
		<label class="mr-2" for="par-filter">Search</label>
		<input class="form-control mr-2" id="par-filter" name="filter" value="<?= e($parFilter) ?>" list="par-filter-options" placeholder="Stock no. or item" autocomplete="off">
		<datalist id="par-filter-options">
			<?php foreach ($filterOptions as $option): ?><option value="<?= e($option) ?>"></option><?php endforeach; ?>
		</datalist>
		<button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Filter</button>
		<a class="btn btn-outline-secondary ml-2" href="<?= customUri('ims', 'Property Acknowledgement Receipt') ?>">Clear</a>
	</form>
	<button class="btn btn-secondary" type="button" onclick="window.open('<?= uri() . '/ims/par-print.php?filter=' . urlencode($parFilter) ?>', '_blank', 'resizable=1,scrollbars=1')"><i class="fas fa-print"></i> Print PAR</button>
</div>

<div class="card shadow mb-4">
	<div class="card-header"><strong>High-Value Property Items</strong> <small class="text-muted">(unit cost ≥ P50,000 | <?= $parCount ?> item(s) | Total: P<?= number_format($sumAmount, 2) ?>)</small></div>
	<div class="card-body">
		<div class="table-responsive">
			<table class="table table-bordered table-hover" id="par-table">
				<thead>
					<tr>
						<th>Property No.</th>
						<th>Property Description</th>
						<th>Unit</th>
						<th>On-hand Qty</th>
						<th style="text-align:right">Unit Cost</th>
						<th style="text-align:right">Total Cost</th>
						<th>Date Acquired</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($rows as $row): ?>
						<tr>
							<td><?= e($row['stock_no']) ?></td>
							<td><?= e($row['description']) ?></td>
							<td><?= e(ucfirst((string) $row['unit'])) ?></td>
							<td style="text-align:right"><?= (int) $row['qty'] ?></td>
							<td style="text-align:right"><?= 'P' . number_format($row['unit_cost'], 2) ?></td>
							<td style="text-align:right"><?= 'P' . number_format($row['amount'], 2) ?></td>
							<td><?= e($row['date_acquired']) ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
				<?php if ($rows): ?>
				<tfoot>
					<tr class="font-weight-bold">
						<td colspan="5" style="text-align:right">Total Cost</td>
						<td style="text-align:right"><?= 'P' . number_format($sumAmount, 2) ?></td>
						<td></td>
					</tr>
				</tfoot>
				<?php endif; ?>
			</table>
		</div>
		<?php if (!$rows): ?>
			<p class="text-center text-muted mb-0">No items with a unit cost of P50,000 or more found.</p>
		<?php endif; ?>
	</div>
</div>

<style>
@media print {
	.stock-card-toolbar, .nav-tabs, .sidebar, #accordionSidebar, #scroll-to-top, .navbar, footer { display: none !important; }
	#content-wrapper, #content, .container-fluid { margin: 0 !important; padding: 0 !important; width: 100% !important; }
	.card { border: 0 !important; box-shadow: none !important; }
	.card-header { text-align: center; border: 0; }
	#par-table { font-size: 10pt; }
}
</style>