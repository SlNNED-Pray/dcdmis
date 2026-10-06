<?php
require_once(__DIR__ . '/navigation.php');
contentTitle('Inventory Custodian Slip');
imsNav('ics');

$pdo = connection();
$stockCardFilter = trim($_GET['filter'] ?? '');

$items = query(
    "SELECT id, stock_no, description, unit, unit_cost, quantity, min_qty, remarks,
            personnel, office, item_status, transfer_to
     FROM items
     WHERE unit_cost >= 30000
     ORDER BY description ASC"
);

$latestMovement = [];
if ($items) {
    $ids = implode(',', array_map('intval', array_column($items, 'id')));
    $movs = query(
        "SELECT item_id, personnel, office, created_at
         FROM stock_movements
         WHERE item_id IN ($ids)
         ORDER BY created_at ASC"
    );
    foreach ($movs as $mov) {
        $latestMovement[(int) $mov['item_id']] = [
            'personnel' => trim((string) ($mov['personnel'] ?? '')),
            'office' => trim((string) ($mov['office'] ?? '')),
        ];
    }
}

$rows = [];
foreach ($items as $item) {
    $qty = (int) $item['quantity'];
    $unitCost = (float) $item['unit_cost'];
    $assign = $latestMovement[$item['id']] ?? ['personnel' => '', 'office' => ''];
    $personnel = trim((string) ($item['personnel'] ?? ''));
    $office = trim((string) ($item['office'] ?? ''));
    if ($personnel === '') { $personnel = $assign['personnel']; }
    if ($office === '') { $office = $assign['office']; }
    $rows[] = [
        'item_id' => $item['id'],
        'stock_no' => $item['stock_no'],
        'description' => $item['description'],
        'unit' => $item['unit'],
        'unit_cost' => $unitCost,
        'qty' => $qty,
        'amount' => round($unitCost * $qty, 2),
        'personnel' => $personnel,
        'office' => $office,
        'item_status' => trim((string) ($item['item_status'] ?? 'Functional')),
        'transfer_to' => trim((string) ($item['transfer_to'] ?? '')),
    ];
}
if ($stockCardFilter !== '') {
    $rows = array_values(array_filter($rows, static function (array $row) use ($stockCardFilter): bool {
        $label = $row['stock_no'] . ' - ' . $row['description'];
        return stripos($label, $stockCardFilter) !== false
            || stripos($row['stock_no'], $stockCardFilter) !== false
            || stripos($row['personnel'], $stockCardFilter) !== false
            || stripos($row['office'], $stockCardFilter) !== false;
    }));
}
$filterOptions = array_values(array_unique(array_merge(
    array_filter(array_column($rows, 'personnel')),
    array_filter(array_column($rows, 'office')),
    array_filter(array_column($rows, 'stock_no'))
)));
$icsCount = count($rows);
$sumAmount = array_sum(array_map(static fn (array $row): float => $row['amount'], $rows));
?>
<div class="d-flex justify-content-between align-items-center mb-3 stock-card-toolbar">
	<form method="get" class="form-inline">
		<input type="hidden" name="v" value="<?= e(cipher('Inventory Custodian Slip')) ?>">
		<label class="mr-2" for="ics-filter">Search</label>
		<input class="form-control mr-2" id="ics-filter" name="filter" value="<?= e($stockCardFilter) ?>" list="ics-filter-options" placeholder="Personnel, office, stock no., or item" autocomplete="off">
		<datalist id="ics-filter-options">
			<?php foreach ($filterOptions as $option): ?><option value="<?= e($option) ?>"></option><?php endforeach; ?>
		</datalist>
		<button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Filter</button>
		<a class="btn btn-outline-secondary ml-2" href="<?= customUri('ims', 'Inventory Custodian Slip') ?>">Clear</a>
	</form>
	<button class="btn btn-secondary" type="button" onclick="window.open('<?= uri() . '/ims/ics-report-print.php?filter=' . urlencode($stockCardFilter) ?>', '_blank', 'resizable=1,scrollbars=1')"><i class="fas fa-print"></i> Print ICS</button>
</div>

<div class="card shadow mb-4">
	<div class="card-header"><strong>High-Value Inventory Items</strong> <small class="text-muted">(unit cost ≥ P30,000 | <?= $icsCount ?> item(s) | Total: P<?= number_format($sumAmount, 2) ?>)</small></div>
	<div class="card-body">
		<div class="table-responsive">
			<table class="table table-bordered table-hover" id="ics-report-table">
				<thead>
					<tr>
						<th rowspan="2">Stock No.</th>
						<th rowspan="2">Description</th>
						<th rowspan="2">Unit</th>
						<th rowspan="2">On-hand Qty</th>
						<th colspan="2">Amount</th>
						<th rowspan="2">Personnel</th>
						<th rowspan="2">Office</th>
						<th rowspan="2">Status</th>
					</tr>
					<tr>
						<th style="text-align:right">Unit Cost</th>
						<th style="text-align:right">Total Cost</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($rows as $row): ?>
						<?php $statusLabel = strtolower($row['item_status']) === 'transferred' ? 'Transferred to: ' . $row['transfer_to'] : 'Functional'; ?>
						<tr>
							<td><?= e($row['stock_no']) ?></td>
							<td><?= e($row['description']) ?></td>
							<td><?= e(ucfirst((string) $row['unit'])) ?></td>
							<td style="text-align:right"><?= (int) $row['qty'] ?></td>
							<td style="text-align:right"><?= 'P' . number_format($row['unit_cost'], 2) ?></td>
							<td style="text-align:right"><?= 'P' . number_format($row['amount'], 2) ?></td>
							<td><?= e($row['personnel'] ?: '—') ?></td>
							<td><?= e($row['office'] ?: '—') ?></td>
							<td><?= e($statusLabel) ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
				<?php if ($rows): ?>
				<tfoot>
					<tr class="font-weight-bold">
						<td colspan="5" style="text-align:right">Total Cost</td>
						<td style="text-align:right"><?= 'P' . number_format($sumAmount, 2) ?></td>
						<td colspan="3"></td>
					</tr>
				</tfoot>
				<?php endif; ?>
			</table>
		</div>
		<?php if (!$rows): ?>
			<p class="text-center text-muted mb-0">No items with a unit cost of P30,000 or more found.</p>
		<?php endif; ?>
	</div>
</div>

<style>
@media print {
	.stock-card-toolbar, .nav-tabs, .sidebar, #accordionSidebar, #scroll-to-top, .navbar, footer { display: none !important; }
	#content-wrapper, #content, .container-fluid { margin: 0 !important; padding: 0 !important; width: 100% !important; }
	.card { border: 0 !important; box-shadow: none !important; }
	.card-header { text-align: center; border: 0; }
	#ics-report-table { font-size: 10pt; }
}
</style>