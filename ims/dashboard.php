<?php
require_once(__DIR__ . '/navigation.php');

$pdo = connection();
$itemCountResult = query('SELECT COUNT(*) AS total FROM items');
$lowStockResult = query('SELECT COUNT(*) AS total FROM items WHERE quantity > 0 AND quantity <= min_qty');
$pendingRisResult = query("SELECT COUNT(*) AS total FROM requisition_slip_items WHERE status IN ('Pending', 'Approved')");
$pendingPrResult = query("SELECT COUNT(*) AS total FROM purchase_requests WHERE status = 'pending'");
$itemCount = (int) ($itemCountResult[0]['total'] ?? 0);
$lowStockCount = (int) ($lowStockResult[0]['total'] ?? 0);
$pendingRis = (int) ($pendingRisResult[0]['total'] ?? 0);
$pendingPr = (int) ($pendingPrResult[0]['total'] ?? 0);

$topItems = query('SELECT stock_no, description, quantity FROM items WHERE quantity > 0 ORDER BY quantity DESC, description ASC LIMIT 10');
$stockChart = [
    'labels' => array_map(static function (array $item): string {
        $description = (string) $item['description'];
        return mb_strlen($description) > 32 ? mb_substr($description, 0, 29) . '...' : $description;
    }, $topItems),
    'full' => array_map(static fn(array $item): string => (string) $item['description'], $topItems),
    'values' => array_map(static fn(array $item): int => (int) $item['quantity'], $topItems),
];

$categoryTotals = [];
foreach (query('SELECT stock_no, description, quantity, unit_cost FROM items') as $item) {
    $category = imsItemCategory((string) $item['description'], (string) $item['stock_no']);
    $value = (int) $item['quantity'] * (float) $item['unit_cost'];
    if ($value <= 0) {
        continue;
    }
    $categoryTotals[$category] = ($categoryTotals[$category] ?? 0) + $value;
}
arsort($categoryTotals);
$totalValue = array_sum($categoryTotals);
$valueChart = [
    'labels' => array_keys($categoryTotals),
    'values' => array_values($categoryTotals),
];

$months = [];
$cursor = new DateTime('first day of this month');
$cursor->modify('-5 months');
for ($i = 0; $i < 6; $i++) {
    $months[$cursor->format('Y-m')] = [
        'label' => $cursor->format('M Y'),
        'receipts' => 0,
        'dispatches' => 0,
    ];
    $cursor->modify('+1 month');
}
$flowRows = query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key,
    SUM(CASE WHEN movement_type = 'Receipt' THEN quantity ELSE 0 END) AS receipts,
    SUM(CASE WHEN movement_type = 'Issue' THEN ABS(quantity) ELSE 0 END) AS dispatches
    FROM stock_movements
    GROUP BY month_key");
foreach ($flowRows as $row) {
    if (!isset($months[$row['month_key']])) {
        continue;
    }
    $months[$row['month_key']]['receipts'] = (int) $row['receipts'];
    $months[$row['month_key']]['dispatches'] = (int) $row['dispatches'];
}
$flowChart = [
    'labels' => array_column($months, 'label'),
    'receipts' => array_column($months, 'receipts'),
    'dispatches' => array_column($months, 'dispatches'),
];

$summaryItems = query('SELECT id, stock_no, description, quantity, min_qty FROM items
    ORDER BY (quantity <= 0) DESC, (quantity <= min_qty) DESC, quantity ASC, description ASC');

$kpiStock = query('SELECT COUNT(*) AS item_count,
    SUM(GREATEST(quantity, 0)) AS total_quantity,
    SUM(GREATEST(quantity, 0) * unit_cost) AS total_value
    FROM items')[0] ?? [];
$kpiItemCount = (int) ($kpiStock['item_count'] ?? 0);
$kpiTotalQuantity = (int) ($kpiStock['total_quantity'] ?? 0);
$kpiTotalValue = (float) ($kpiStock['total_value'] ?? 0);

$reservedByItem = [];
foreach (query("SELECT item_id, SUM(quantity) AS reserved FROM requisition_slip_items
    WHERE status IN ('Pending', 'Approved') GROUP BY item_id") as $reservedRow) {
    $reservedByItem[(int) $reservedRow['item_id']] = (int) $reservedRow['reserved'];
}
$kpiAvailable = 0;
$kpiReservedApplied = 0;
foreach (query('SELECT id, quantity FROM items') as $stockRow) {
    $onHand = max((int) $stockRow['quantity'], 0);
    $reserved = $reservedByItem[(int) $stockRow['id']] ?? 0;
    $applied = min($reserved, $onHand);
    $kpiReservedApplied += $applied;
    $kpiAvailable += $onHand - $applied;
}

$kpiWindowDays = 90;
$kpiMovement = query("SELECT SUM(ABS(sm.quantity)) AS issued_units,
        SUM(ABS(sm.quantity) * i.unit_cost) AS cost_of_issues
    FROM stock_movements sm
    JOIN items i ON i.id = sm.item_id
    WHERE sm.movement_type = 'Issue'
      AND sm.created_at >= NOW() - INTERVAL {$kpiWindowDays} DAY")[0] ?? [];
$kpiIssuedUnits = (int) ($kpiMovement['issued_units'] ?? 0);
$kpiCostOfIssues = (float) ($kpiMovement['cost_of_issues'] ?? 0);
$kpiDailyCost = $kpiCostOfIssues / $kpiWindowDays;
$kpiDaysOnHand = $kpiDailyCost > 0 ? $kpiTotalValue / $kpiDailyCost : null;
$kpiTurnover = $kpiTotalValue > 0 ? $kpiCostOfIssues / $kpiTotalValue : null;
$kpiTurnoverAnnualized = $kpiTurnover !== null ? $kpiTurnover * (365 / $kpiWindowDays) : null;

$reorderAlerts = query('SELECT stock_no, description, quantity, min_qty FROM items
    WHERE quantity <= 0 OR (min_qty > 0 AND quantity <= min_qty)
    ORDER BY (quantity <= 0) DESC, (quantity - min_qty) ASC, description ASC') ?: [];
$reorderOutOfStock = 0;
$reorderBelowMinimum = 0;
foreach ($reorderAlerts as $reorderItem) {
    if ((int) $reorderItem['quantity'] <= 0) {
        $reorderOutOfStock++;
    } else {
        $reorderBelowMinimum++;
    }
}
$reorderPreviewLimit = 25;
$reorderPreview = array_slice($reorderAlerts, 0, $reorderPreviewLimit);

$agedBuckets = [
    '0-30 days' => ['items' => 0, 'value' => 0.0],
    '31-60 days' => ['items' => 0, 'value' => 0.0],
    '61-90 days' => ['items' => 0, 'value' => 0.0],
    '91-180 days' => ['items' => 0, 'value' => 0.0],
    'Over 180 days' => ['items' => 0, 'value' => 0.0],
];
$agedItems = [];
foreach (query("SELECT i.stock_no, i.description, i.quantity, i.unit_cost, i.created_at,
        (SELECT MAX(sm.created_at) FROM stock_movements sm WHERE sm.item_id = i.id) AS last_movement
    FROM items i WHERE i.quantity > 0") as $agedRow) {
    $basis = $agedRow['last_movement'] ?: $agedRow['created_at'];
    $timestamp = $basis ? strtotime((string) $basis) : false;
    $daysIdle = $timestamp ? (int) floor((time() - $timestamp) / 86400) : 9999;
    $bucket = $daysIdle <= 30 ? '0-30 days'
        : ($daysIdle <= 60 ? '31-60 days'
            : ($daysIdle <= 90 ? '61-90 days'
                : ($daysIdle <= 180 ? '91-180 days' : 'Over 180 days')));
    $agedValue = (int) $agedRow['quantity'] * (float) $agedRow['unit_cost'];
    $agedBuckets[$bucket]['items']++;
    $agedBuckets[$bucket]['value'] += $agedValue;
    $agedItems[] = [
        'stock_no' => (string) $agedRow['stock_no'],
        'description' => (string) $agedRow['description'],
        'quantity' => (int) $agedRow['quantity'],
        'value' => $agedValue,
        'days' => $daysIdle,
    ];
}
$agedChart = [
    'labels' => array_keys($agedBuckets),
    'values' => array_values(array_map(static fn(array $bucket): float => (float) $bucket['value'], $agedBuckets)),
    'counts' => array_values(array_map(static fn(array $bucket): int => (int) $bucket['items'], $agedBuckets)),
];
$agedValue = $agedBuckets['91-180 days']['value'] + $agedBuckets['Over 180 days']['value'];
$agedCount = $agedBuckets['91-180 days']['items'] + $agedBuckets['Over 180 days']['items'];
$agedShare = $kpiTotalValue > 0 ? ($agedValue / $kpiTotalValue) * 100 : 0.0;
$slowMovingValue = $agedBuckets['61-90 days']['value'] + $agedValue;
$slowMovingCount = $agedBuckets['61-90 days']['items'] + $agedCount;
$slowMovingShare = $kpiTotalValue > 0 ? ($slowMovingValue / $kpiTotalValue) * 100 : 0.0;

$agedSlowItems = array_values(array_filter($agedItems, static fn(array $agedRow): bool => $agedRow['days'] > 60));
usort($agedSlowItems, static fn(array $first, array $second): int => $second['value'] <=> $first['value']);
$agedSlowItems = array_slice($agedSlowItems, 0, 8);

$trendMonths = 12;
$trendItems = [];
foreach (query('SELECT id, quantity, unit_cost FROM items') as $trendItem) {
    $trendItems[(int) $trendItem['id']] = [
        'quantity' => (int) $trendItem['quantity'],
        'cost' => (float) $trendItem['unit_cost'],
        'net' => 0,
        'months' => [],
    ];
}
foreach (query('SELECT item_id, SUM(quantity) AS net FROM stock_movements GROUP BY item_id') as $trendNet) {
    $trendItemId = (int) $trendNet['item_id'];
    if (isset($trendItems[$trendItemId])) {
        $trendItems[$trendItemId]['net'] = (int) $trendNet['net'];
    }
}
foreach (query("SELECT item_id, DATE_FORMAT(created_at, '%Y-%m') AS month_key, SUM(quantity) AS net
    FROM stock_movements GROUP BY item_id, month_key") as $trendMonth) {
    $trendItemId = (int) $trendMonth['item_id'];
    if (isset($trendItems[$trendItemId])) {
        $trendItems[$trendItemId]['months'][(string) $trendMonth['month_key']] = (int) $trendMonth['net'];
    }
}
$trendSeries = [];
$trendCursor = new DateTime('first day of this month');
$trendCursor->modify('-' . ($trendMonths - 1) . ' months');
for ($trendIndex = 0; $trendIndex < $trendMonths; $trendIndex++) {
    $monthKey = $trendCursor->format('Y-m');
    $trendUnits = 0;
    $trendValue = 0.0;
    foreach ($trendItems as $trendItem) {
        $cumulative = 0;
        foreach ($trendItem['months'] as $trendMonthKey => $trendMonthNet) {
            if ($trendMonthKey <= $monthKey) {
                $cumulative += $trendMonthNet;
            }
        }
        $onHand = max($trendItem['quantity'] - ($trendItem['net'] - $cumulative), 0);
        $trendUnits += $onHand;
        $trendValue += $onHand * $trendItem['cost'];
    }
    $trendSeries[] = [
        'label' => $trendCursor->format('M Y'),
        'units' => $trendUnits,
        'value' => round($trendValue, 2),
    ];
    $trendCursor->modify('+1 month');
}
$trendChart = [
    'labels' => array_column($trendSeries, 'label'),
    'units' => array_column($trendSeries, 'units'),
    'values' => array_column($trendSeries, 'value'),
];
$trendLedgerStart = (string) ((query('SELECT MIN(created_at) AS first_movement FROM stock_movements')[0]['first_movement'] ?? ''));
$trendLedgerStartLabel = $trendLedgerStart !== '' ? date('M Y', strtotime($trendLedgerStart)) : null;

$availableStockItems = query('SELECT stock_no, description, unit, quantity FROM items WHERE quantity > 0 ORDER BY quantity DESC, description ASC') ?: [];
$inventoryChartItems = query(
    "SELECT id, stock_no, description, item_group, unit, quantity, created_at
     FROM items
     ORDER BY (item_group IS NULL OR item_group = ''), item_group ASC, description ASC"
) ?: [];
$inventoryMovementsByItem = [];
foreach (query('SELECT item_id, quantity, created_at FROM stock_movements ORDER BY created_at') ?: [] as $movementRow) {
    $inventoryMovementsByItem[(int) $movementRow['item_id']][] = [
        (string) $movementRow['created_at'],
        (int) $movementRow['quantity'],
    ];
}
$inventoryChartData = [];
foreach ($inventoryChartItems as $inventoryChartItem) {
    $inventoryGroup = trim((string) ($inventoryChartItem['item_group'] ?? ''));
    $inventoryChartData[] = [
        'id' => (int) $inventoryChartItem['id'],
        'stock_no' => (string) $inventoryChartItem['stock_no'],
        'description' => (string) $inventoryChartItem['description'],
        'group' => $inventoryGroup !== '' ? $inventoryGroup : 'Ungrouped',
        'unit' => (string) $inventoryChartItem['unit'],
        'quantity' => (int) $inventoryChartItem['quantity'],
        'created_at' => (string) $inventoryChartItem['created_at'],
        'movements' => $inventoryMovementsByItem[(int) $inventoryChartItem['id']] ?? [],
    ];
}

messageAlert($showAlert ?? false, $message ?? '', $success ?? true);
contentTitle('Property & Inventory Management Information System');
imsNav('inventory');
?>

<div class="row mb-4">
    <?php
    card('Stock Items', imsUri('Stock and Inventory'), 'fa-boxes', 'primary', $itemCount);
    card('Low Stock', imsUri('Stock and Inventory'), 'fa-exclamation-triangle', $lowStockCount ? 'warning' : 'success', $lowStockCount);
    card('Pending RIS', imsUri('Requisition Slips'), 'fa-file-invoice', 'info', $pendingRis);
    card('Pending Purchase Requests', imsUri('Purchase Requests'), 'fa-file-invoice-dollar', 'secondary', $pendingPr);
    ?>
</div>

<div class="row mb-4" id="pimisStockCards">
    <?php
    card('Stock and Inventory', imsUri('Stock and Inventory'), 'fa-warehouse', 'primary');
    card('Available Stocks', imsUri('Stock and Inventory') . '&stock=available', 'fa-boxes', 'success', count($availableStockItems));
    card('No Stocks', imsUri('Stock and Inventory') . '&stock=out', 'fa-boxes', 'danger');
    ?>
</div>

<div class="d-sm-flex justify-content-between align-items-center mb-3">
    <h6 class="m-0 font-weight-bold text-primary">Core Key Performance Indicators (KPI)</h6>
    <small class="text-muted">Consumption and turnover basis: last <?= $kpiWindowDays ?> days of issued stock</small>
</div>

<div class="row mb-4">
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-3">
                        <div class="text-xs font-weight-bold text-uppercase text-primary mb-1">Total Stock Quantity</div>
                        <div class="h4 mb-0 font-weight-bold text-gray-800">
                            <?= number_format($kpiTotalQuantity) ?> <small class="text-muted font-weight-normal">units</small>
                        </div>
                        <div class="small text-muted mt-1">Across <?= number_format($kpiItemCount) ?> stock items</div>
                    </div>
                    <div class="col-auto"><i class="fas fa-boxes fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-3">
                        <div class="text-xs font-weight-bold text-uppercase text-success mb-1">Total Stock Value</div>
                        <div class="h4 mb-0 font-weight-bold text-gray-800">&#8369;<?= number_format($kpiTotalValue, 2) ?></div>
                        <div class="small text-muted mt-1">Valued at current unit cost</div>
                    </div>
                    <div class="col-auto"><i class="fas fa-money-bill-wave fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-3">
                        <div class="text-xs font-weight-bold text-uppercase text-info mb-1">Physical Available</div>
                        <div class="h4 mb-0 font-weight-bold text-gray-800">
                            <?= number_format($kpiAvailable) ?> <small class="text-muted font-weight-normal">units</small>
                        </div>
                        <div class="small text-muted mt-1"><?= number_format($kpiReservedApplied) ?> units soft-reserved on pending/approved RIS</div>
                    </div>
                    <div class="col-auto"><i class="fas fa-check-circle fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-3">
                        <div class="text-xs font-weight-bold text-uppercase text-warning mb-1">Days on Hand (DOH)</div>
                        <div class="h4 mb-0 font-weight-bold text-gray-800">
                            <?= $kpiDaysOnHand !== null ? number_format($kpiDaysOnHand, 1) . ' <small class="text-muted font-weight-normal">days</small>' : '&mdash;' ?>
                        </div>
                        <div class="small text-muted mt-1">At &#8369;<?= number_format($kpiDailyCost, 2) ?> daily cost of issues</div>
                    </div>
                    <div class="col-auto"><i class="fas fa-calendar-day fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-secondary shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-3">
                        <div class="text-xs font-weight-bold text-uppercase text-secondary mb-1">Inventory Turnover Ratio</div>
                        <div class="h4 mb-0 font-weight-bold text-gray-800">
                            <?= $kpiTurnover !== null ? number_format($kpiTurnover, 2) . '&times;' : '&mdash;' ?>
                        </div>
                        <div class="small text-muted mt-1">
                            Annualized <?= $kpiTurnoverAnnualized !== null ? number_format($kpiTurnoverAnnualized, 2) . '&times;' : '&mdash;' ?>
                            &middot; <?= number_format($kpiIssuedUnits) ?> units issued
                        </div>
                    </div>
                    <div class="col-auto"><i class="fas fa-sync-alt fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-danger shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-3">
                        <div class="text-xs font-weight-bold text-uppercase text-danger mb-1">Aged / Dead Stock %</div>
                        <div class="h4 mb-0 font-weight-bold text-gray-800">
                            <?= number_format($agedShare, 1) ?>% <small class="text-muted font-weight-normal">of value</small>
                        </div>
                        <div class="small text-muted mt-1">
                            Over 90 days: &#8369;<?= number_format($agedValue, 2) ?> &middot; <?= number_format($slowMovingShare, 1) ?>% slow-moving (>60d)
                        </div>
                    </div>
                    <div class="col-auto"><i class="fas fa-hourglass-end fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-xl-7">
        <div class="card shadow h-100">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Quantity on hand by product</h6>
            </div>
            <div class="card-body">
                <?php if ($stockChart['labels']): ?>
                    <div class="chart-bar" style="height:20rem;">
                        <canvas id="imsStockChart"></canvas>
                    </div>
                    <p class="mb-0 text-muted small">Top 10 products by quantity on hand.</p>
                <?php else: ?>
                    <p class="mb-0 text-muted">No stock is currently on hand.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card shadow h-100">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Inventory value by category</h6>
                <span class="small font-weight-bold text-gray-600">Total: &#8369;<?= number_format($totalValue, 2) ?></span>
            </div>
            <div class="card-body">
                <?php if ($valueChart['labels']): ?>
                    <div class="chart-pie" style="height:20rem;">
                        <canvas id="imsValueChart"></canvas>
                    </div>
                <?php else: ?>
                    <p class="mb-0 text-muted">No stock item has a recorded unit cost yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Stock flow (last 6 months)</h6>
            </div>
            <div class="card-body">
                <div class="chart-bar" style="height:20rem;">
                    <canvas id="imsFlowChart"></canvas>
                </div>
                <p class="mb-0 text-muted small">Receipts from accepted deliveries versus dispatches from issued requisitions.</p>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-xl-6">
        <div class="card shadow h-100">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Items Needing Restock / Reorder Alerts</h6>
                <span class="small font-weight-bold text-gray-600 text-right ml-2">
                    <?= number_format(count($reorderAlerts)) ?> flagged
                    &middot; <span class="text-danger"><?= number_format($reorderOutOfStock) ?> out of stock</span>
                    &middot; <span class="text-warning"><?= number_format($reorderBelowMinimum) ?> below minimum</span>
                </span>
            </div>
            <div class="card-body">
                <?php if ($reorderAlerts): ?>
                    <div class="table-responsive" style="max-height:20rem; overflow-y:auto;">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th style="position:sticky; top:0; z-index:1; background-color:#eaecf4;">Item</th>
                                    <th class="text-right" style="position:sticky; top:0; z-index:1; background-color:#eaecf4;">On Hand</th>
                                    <th class="text-right" style="position:sticky; top:0; z-index:1; background-color:#eaecf4;">Reorder Level</th>
                                    <th style="position:sticky; top:0; z-index:1; background-color:#eaecf4;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reorderPreview as $reorderAlert): ?>
                                    <?php
                                    $alertQuantity = (int) $reorderAlert['quantity'];
                                    $alertReorderLevel = (int) $reorderAlert['min_qty'];
                                    $alertIsOut = $alertQuantity <= 0;
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="font-weight-bold text-gray-800"><?= e($reorderAlert['description']) ?></div>
                                            <div class="small text-muted"><?= e($reorderAlert['stock_no']) ?></div>
                                        </td>
                                        <td class="text-right"><?= number_format($alertQuantity) ?></td>
                                        <td class="text-right"><?= $alertReorderLevel > 0 ? number_format($alertReorderLevel) : '&mdash;' ?></td>
                                        <td>
                                            <span class="badge badge-<?= $alertIsOut ? 'danger' : 'warning' ?>">
                                                <?= $alertIsOut ? 'Out of stock' : 'Low stock' ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="mb-0 mt-2 text-muted small">
                        Out-of-stock SKUs and items at or below their reorder level, most critical first.
                        <?php if (count($reorderAlerts) > count($reorderPreview)): ?>
                            Showing <?= number_format(count($reorderPreview)) ?> of <?= number_format(count($reorderAlerts)) ?>
                            &middot; <a href="<?= e(imsUri('Stock and Inventory') . '&stock=out') ?>">view out-of-stock items</a>.
                        <?php endif; ?>
                    </p>
                <?php else: ?>
                    <p class="mb-0 text-muted">No items are out of stock or below their reorder level.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card shadow h-100">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Aged Inventory / Dead Stock</h6>
                <span class="small font-weight-bold text-gray-600">
                    Over 90 days: &#8369;<?= number_format($agedValue, 2) ?> (<?= number_format($agedShare, 1) ?>%)
                </span>
            </div>
            <div class="card-body">
                <div class="chart-bar" style="height:20rem;">
                    <canvas id="imsAgedChart"></canvas>
                </div>
                <p class="mb-0 text-muted small">
                    Inventory value tied up by days since last stock movement.
                    Slow-moving (>60 days): &#8369;<?= number_format($slowMovingValue, 2) ?> (<?= number_format($slowMovingShare, 1) ?>%) &middot; <?= $slowMovingCount ?> SKUs &middot; Dead stock (>90d): <?= $agedCount ?> SKUs.
                </p>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Stock Availability Trends</h6>
                <span class="small font-weight-bold text-gray-600">
                    Last 12 months<?= $trendLedgerStartLabel ? ' &middot; Movement history from ' . $trendLedgerStartLabel : '' ?>
                </span>
            </div>
            <div class="card-body">
                <div class="chart-bar" style="height:20rem;">
                    <canvas id="imsTrendChart"></canvas>
                </div>
                <p class="mb-0 text-muted small">Reconstructed from stock_movements (backward from current on-hand). Units shown on the left axis, inventory value on the right axis.</p>
            </div>
        </div>
    </div>
</div>

<?php if ($agedSlowItems): ?>
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Top Slow-Moving / Aged Stock (>60 days)</h6>
        <span class="small font-weight-bold text-gray-600">By tied-up value</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Product ID</th>
                        <th>Item Name</th>
                        <th class="text-right">Quantity</th>
                        <th class="text-right">Tied-up Value</th>
                        <th class="text-right">Days Idle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($agedSlowItems as $slowItem): ?>
                        <tr>
                            <td class="text-nowrap"><?= e($slowItem['stock_no']) ?></td>
                            <td><?= e($slowItem['description']) ?></td>
                            <td class="text-right"><?= number_format((int) $slowItem['quantity']) ?></td>
                            <td class="text-right">&#8369;<?= number_format((float) $slowItem['value'], 2) ?></td>
                            <td class="text-right"><?= number_format((int) $slowItem['days']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Stock summary</h6>
        <a class="btn btn-primary btn-sm" href="<?= imsUri('Stock and Inventory') ?>">View stock</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive" style="max-height:24rem; overflow-y:auto;">
            <table class="table table-sm table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th style="position:sticky; top:0; z-index:1; background-color:#eaecf4;">Product ID</th>
                        <th style="position:sticky; top:0; z-index:1; background-color:#eaecf4;">Item Name</th>
                        <th class="text-right" style="position:sticky; top:0; z-index:1; background-color:#eaecf4;">Current Stock</th>
                        <th class="text-right" style="position:sticky; top:0; z-index:1; background-color:#eaecf4;">Reorder Level</th>
                        <th style="position:sticky; top:0; z-index:1; background-color:#eaecf4;">Stock Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($summaryItems as $item): ?>
                        <?php
                        $quantity = (int) $item['quantity'];
                        $reorderLevel = (int) $item['min_qty'];
                        if ($quantity <= 0) {
                            $stockStatus = 'Out of stock';
                            $stockBadge = 'danger';
                        } elseif ($quantity <= $reorderLevel) {
                            $stockStatus = 'Low stock';
                            $stockBadge = 'warning';
                        } else {
                            $stockStatus = 'Available';
                            $stockBadge = 'success';
                        }
                        ?>
                        <tr>
                            <td class="text-nowrap"><?= e($item['stock_no']) ?></td>
                            <td><?= e($item['description']) ?></td>
                            <td class="text-right"><?= $quantity ?></td>
                            <td class="text-right"><?= $reorderLevel ?></td>
                            <td><span class="badge badge-<?= $stockBadge ?>"><?= $stockStatus ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Inventory workflow</strong>
        <a class="btn btn-primary btn-sm" href="<?= imsUri('Create Stock Item') ?>"><i class="fas fa-plus"></i> Add item</a>
    </div>
    <div class="card-body">
        <p class="mb-0">Maintain stock, receive requisition slips, create purchase requests for insufficient stock, and record inspection and acceptance before issuing supplies.</p>
    </div>
</div>

<div class="modal fade" id="availableStocksModal" tabindex="-1" role="dialog" aria-labelledby="availableStocksModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="availableStocksModalLabel">
                    <i class="fas fa-boxes text-success mr-2"></i>Available Stocks
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Items with stock on hand greater than zero, highest quantity first.</p>
                <div id="pisAvailableStocksEmpty" class="text-center text-muted py-5" style="display:none;">
                    <i class="fas fa-box-open fa-2x d-block mb-2"></i>
                    No available stocks recorded.
                </div>
                <div class="table-responsive" style="max-height:65vh; overflow-y:auto;">
                    <div id="pisAvailableStocksChartWrap" style="position:relative; height:<?= max(260, count($availableStockItems) * 26 + 40) ?>px;">
                        <canvas id="pisAvailableStocksChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <span class="small text-muted"><?= count($availableStockItems) ?> item<?= count($availableStockItems) === 1 ? '' : 's' ?> with stock</span>
                <div>
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                    <a class="btn btn-success btn-sm" href="<?= e(imsUri('Stock and Inventory') . '&stock=available') ?>">
                        <i class="fas fa-list mr-1"></i>View inventory list
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
#stockInventoryModal .si-autocomplete { position: relative; }
#stockInventoryModal .si-suggestions {
    display: none;
    left: 0;
    right: 0;
    top: 100%;
    margin: 0;
    position: absolute;
    max-height: 240px;
    overflow: auto;
    z-index: 1000;
}
#stockInventoryModal .si-suggestions .dropdown-item { white-space: normal; cursor: pointer; }
#stockInventoryModal .si-suggestions .si-sub { display: block; font-size: .72rem; color: #6c757d; }
#stockInventoryModal .si-suggestions .dropdown-item.active,
#stockInventoryModal .si-suggestions .dropdown-item:hover { background-color: #eef2ff; }
</style>
<div class="modal fade" id="stockInventoryModal" tabindex="-1" role="dialog" aria-labelledby="stockInventoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="stockInventoryModalLabel">
                    <i class="fas fa-warehouse text-primary mr-2"></i>Stock and Inventory
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-row align-items-end">
                    <div class="form-group col-md-3 mb-2">
                        <label class="small mb-1" for="siGroup">Group</label>
                        <select class="form-control form-control-sm" id="siGroup">
                            <option value="">All groups</option>
                        </select>
                    </div>
                    <div class="form-group col-md-5 mb-2 si-autocomplete">
                        <label class="small mb-1" for="siItem">Item &amp; Specifications</label>
                        <input class="form-control form-control-sm" type="text" id="siItem" autocomplete="off"
                            placeholder="Type stock no. or description" aria-autocomplete="list"
                            aria-controls="siItemSuggestions" aria-expanded="false">
                        <ul class="si-suggestions dropdown-menu" id="siItemSuggestions" role="listbox"></ul>
                    </div>
                    <div class="form-group col-md-2 mb-2">
                        <label class="small mb-1" for="siDateMode">Date filter</label>
                        <select class="form-control form-control-sm" id="siDateMode">
                            <option value="">All dates</option>
                            <option value="as_of">As of date</option>
                            <option value="range">Date range</option>
                        </select>
                    </div>
                    <div class="form-group col-md-2 mb-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm btn-block" id="siReset">
                            <i class="fas fa-undo mr-1"></i>Reset
                        </button>
                    </div>
                </div>
                <div class="form-row align-items-end" id="siDateFields" style="display:none;">
                    <div class="form-group col-md-3 mb-2 si-date-asof" style="display:none;">
                        <label class="small mb-1" for="siAsOf">As of date</label>
                        <input class="form-control form-control-sm" type="date" id="siAsOf">
                    </div>
                    <div class="form-group col-md-3 mb-2 si-date-range" style="display:none;">
                        <label class="small mb-1" for="siFrom">From date</label>
                        <input class="form-control form-control-sm" type="date" id="siFrom">
                    </div>
                    <div class="form-group col-md-3 mb-2 si-date-range" style="display:none;">
                        <label class="small mb-1" for="siTo">To date</label>
                        <input class="form-control form-control-sm" type="date" id="siTo">
                    </div>
                </div>
                <p class="small text-muted mb-2" id="siSummary"></p>
                <div id="siEmpty" class="text-center text-muted py-5" style="display:none;">
                    <i class="fas fa-box-open fa-2x d-block mb-2"></i>
                    No items match the selected filters.
                </div>
                <div class="table-responsive" style="max-height:60vh; overflow-y:auto;">
                    <div id="siChartWrap" style="position:relative; height:300px;">
                        <canvas id="siChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <span class="small text-muted">Filter by group, item or date to narrow the graph.</span>
                <div>
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                    <a class="btn btn-primary btn-sm" href="<?= e(imsUri('Stock and Inventory')) ?>">
                        <i class="fas fa-list mr-1"></i>View inventory list
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= uri() ?>/assets/vendor/chart.js/Chart.min.js?v=<?= VERSION ?>"></script>
<script>
(function () {
    if (typeof Chart === 'undefined') {
        return;
    }

    var palette = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796', '#5a5c69', '#fd7e14'];

    function peso(value) {
        return '\u20b1' + Number(value || 0).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    var stockCanvas = document.getElementById('imsStockChart');
    if (stockCanvas) {
        var stockData = <?= json_encode($stockChart, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        new Chart(stockCanvas.getContext('2d'), {
            type: 'horizontalBar',
            data: {
                labels: stockData.labels,
                datasets: [{
                    label: 'On hand',
                    data: stockData.values,
                    backgroundColor: '#4e73df',
                    borderColor: '#4e73df',
                    borderWidth: 1
                }]
            },
            options: {
                maintainAspectRatio: false,
                legend: { display: false },
                tooltips: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        title: function (items) {
                            return stockData.full[items[0].index];
                        },
                        label: function (item) {
                            return 'On hand: ' + Number(item.xLabel).toLocaleString('en-PH');
                        }
                    }
                },
                scales: {
                    xAxes: [{
                        ticks: { beginAtZero: true, precision: 0 },
                        gridLines: { color: '#e3e6f0' }
                    }],
                    yAxes: [{
                        gridLines: { display: false, drawBorder: false }
                    }]
                }
            }
        });
    }

    var valueCanvas = document.getElementById('imsValueChart');
    if (valueCanvas) {
        var valueData = <?= json_encode($valueChart, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        new Chart(valueCanvas.getContext('2d'), {
            type: 'pie',
            data: {
                labels: valueData.labels,
                datasets: [{
                    data: valueData.values,
                    backgroundColor: valueData.labels.map(function (label, index) {
                        return palette[index % palette.length];
                    })
                }]
            },
            options: {
                maintainAspectRatio: false,
                legend: {
                    position: 'bottom',
                    labels: { usePointStyle: true }
                },
                tooltips: {
                    callbacks: {
                        label: function (item, data) {
                            var values = data.datasets[item.datasetIndex].data;
                            var total = values.reduce(function (sum, value) {
                                return sum + Number(value);
                            }, 0);
                            var percent = total > 0 ? (Number(item.value) / total * 100) : 0;
                            return item.label + ': ' + peso(item.value) + ' (' + percent.toFixed(1) + '%)';
                        }
                    }
                }
            }
        });
    }

    var flowCanvas = document.getElementById('imsFlowChart');
    if (flowCanvas) {
        var flowData = <?= json_encode($flowChart, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        new Chart(flowCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: flowData.labels,
                datasets: [
                    {
                        label: 'Receipts',
                        data: flowData.receipts,
                        backgroundColor: '#1cc88a',
                        borderColor: '#1cc88a',
                        borderWidth: 1
                    },
                    {
                        label: 'Dispatches',
                        data: flowData.dispatches,
                        backgroundColor: '#e74a3b',
                        borderColor: '#e74a3b',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                maintainAspectRatio: false,
                legend: { position: 'top' },
                tooltips: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function (item, data) {
                            return data.datasets[item.datasetIndex].label + ': ' + Number(item.yLabel).toLocaleString('en-PH');
                        }
                    }
                },
                scales: {
                    xAxes: [{
                        gridLines: { display: false, drawBorder: false }
                    }],
                    yAxes: [{
                        ticks: { beginAtZero: true, precision: 0 },
                        gridLines: { color: '#e3e6f0' }
                    }]
                }
            }
        });
    }

    var trendCanvas = document.getElementById('imsTrendChart');
    if (trendCanvas) {
        var trendData = <?= json_encode($trendChart, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        new Chart(trendCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: trendData.labels,
                datasets: [
                    {
                        label: 'On hand (units)',
                        data: trendData.units,
                        borderColor: '#4e73df',
                        backgroundColor: 'rgba(78, 115, 223, 0.12)',
                        borderWidth: 2,
                        pointRadius: 3,
                        pointBackgroundColor: '#4e73df',
                        fill: true,
                        yAxisID: 'units'
                    },
                    {
                        label: 'Stock value',
                        data: trendData.values,
                        borderColor: '#1cc88a',
                        backgroundColor: 'rgba(28, 200, 138, 0.12)',
                        borderWidth: 2,
                        borderDash: [5, 4],
                        pointRadius: 3,
                        pointBackgroundColor: '#1cc88a',
                        fill: false,
                        yAxisID: 'value'
                    }
                ]
            },
            options: {
                maintainAspectRatio: false,
                legend: { position: 'top' },
                tooltips: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function (item, data) {
                            if (item.datasetIndex === 0) {
                                return 'On hand: ' + Number(item.yLabel).toLocaleString('en-PH') + ' units';
                            }
                            return 'Stock value: ' + peso(item.yLabel);
                        }
                    }
                },
                scales: {
                    xAxes: [{
                        gridLines: { display: false, drawBorder: false }
                    }],
                    yAxes: [
                        {
                            id: 'units',
                            position: 'left',
                            ticks: { beginAtZero: true, precision: 0 },
                            gridLines: { color: '#e3e6f0' }
                        },
                        {
                            id: 'value',
                            position: 'right',
                            ticks: {
                                beginAtZero: true,
                                callback: function (value) {
                                    return '\u20b1' + Number(value).toLocaleString('en-PH');
                                }
                            },
                            gridLines: { display: false }
                        }
                    ]
                }
            }
        });
    }

    var agedCanvas = document.getElementById('imsAgedChart');
    if (agedCanvas) {
        var agedData = <?= json_encode($agedChart, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        new Chart(agedCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: agedData.labels,
                datasets: [
                    {
                        label: 'Inventory value',
                        data: agedData.values,
                        backgroundColor: '#4e73df',
                        borderColor: '#4e73df',
                        borderWidth: 1,
                        yAxisID: 'value'
                    },
                    {
                        label: 'Items',
                        data: agedData.counts,
                        backgroundColor: '#fd7e14',
                        borderColor: '#fd7e14',
                        borderWidth: 1,
                        yAxisID: 'count'
                    }
                ]
            },
            options: {
                maintainAspectRatio: false,
                legend: { position: 'top' },
                tooltips: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function (item, data) {
                            if (item.datasetIndex === 0) {
                                var total = data.datasets[0].data.reduce(function (sum, value) {
                                    return sum + Number(value);
                                }, 0);
                                var percent = total > 0 ? (Number(item.yLabel) / total * 100) : 0;
                                return 'Inventory value: ' + peso(item.yLabel) + ' (' + percent.toFixed(1) + '%)';
                            }
                            return 'Items: ' + Number(item.yLabel).toLocaleString('en-PH');
                        }
                    }
                },
                scales: {
                    xAxes: [{
                        gridLines: { display: false, drawBorder: false }
                    }],
                    yAxes: [
                        {
                            id: 'value',
                            position: 'left',
                            ticks: {
                                beginAtZero: true,
                                callback: function (value) {
                                    return '\u20b1' + Number(value).toLocaleString('en-PH', { maximumFractionDigits: 0 });
                                }
                            },
                            gridLines: { color: '#e3e6f0' }
                        },
                        {
                            id: 'count',
                            position: 'right',
                            ticks: { beginAtZero: true, precision: 0 },
                            gridLines: { display: false }
                        }
                    ]
                }
            }
        });
    }
})();
</script>
<?php
$stockLabels = [];
$stockFullLabels = [];
$stockValues = [];
$stockUnits = [];
foreach ($availableStockItems as $stockItem) {
    $stockDescription = trim((string) ($stockItem['description'] ?? ''));
    if ($stockDescription === '') {
        $stockDescription = trim((string) ($stockItem['stock_no'] ?? ''));
    }
    if ($stockDescription === '') {
        $stockDescription = 'Item';
    }
    $stockFullLabels[] = $stockDescription;
    if (function_exists('mb_strimwidth')) {
        $stockLabels[] = mb_strimwidth($stockDescription, 0, 52, '…', 'UTF-8');
    } else {
        $stockLabels[] = strlen($stockDescription) > 52 ? substr($stockDescription, 0, 49) . '...' : $stockDescription;
    }
    $stockValues[] = (int) ($stockItem['quantity'] ?? 0);
    $stockUnits[] = trim((string) ($stockItem['unit'] ?? ''));
}
$stockJsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<script>
(function () {
    var modal = document.getElementById('availableStocksModal');
    if (!modal) {
        return;
    }

    var labels = <?= json_encode($stockLabels, $stockJsonFlags) ?: '[]' ?>;
    var fullLabels = <?= json_encode($stockFullLabels, $stockJsonFlags) ?: '[]' ?>;
    var values = <?= json_encode($stockValues, $stockJsonFlags) ?: '[]' ?>;
    var units = <?= json_encode($stockUnits, $stockJsonFlags) ?: '[]' ?>;
    var canvas = document.getElementById('pisAvailableStocksChart');
    var wrap = document.getElementById('pisAvailableStocksChartWrap');
    var empty = document.getElementById('pisAvailableStocksEmpty');
    var chart = null;

    function renderChart() {
        if (labels.length === 0) {
            if (wrap) {
                wrap.style.display = 'none';
            }
            if (empty) {
                empty.style.display = 'block';
            }
            return;
        }
        if (chart || !canvas || typeof Chart === 'undefined') {
            return;
        }

        chart = new Chart(canvas.getContext('2d'), {
            type: 'horizontalBar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Quantity on hand',
                    data: values,
                    backgroundColor: 'rgba(28, 200, 138, 0.75)',
                    borderColor: 'rgba(28, 200, 138, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { display: false },
                tooltips: {
                    callbacks: {
                        title: function (items) {
                            return fullLabels[items[0].index] || '';
                        },
                        label: function (item) {
                            var unit = units[item.index] || '';
                            return ' ' + Number(item.xLabel || 0).toLocaleString('en-PH') + (unit ? ' ' + unit : '');
                        }
                    }
                },
                scales: {
                    xAxes: [{
                        ticks: {
                            beginAtZero: true,
                            precision: 0,
                            callback: function (value) {
                                return Number(value).toLocaleString('en-PH');
                            }
                        },
                        gridLines: { color: 'rgba(0, 0, 0, 0.05)' }
                    }],
                    yAxes: [{
                        ticks: { autoSkip: false, fontSize: 11 },
                        gridLines: { display: false }
                    }]
                }
            }
        });
    }

    var availableLink = document.querySelector('#pimisStockCards a[href*="stock=available"]');
    var availableCard = availableLink ? availableLink.closest('.card') : null;
    if (availableCard) {
        availableCard.style.cursor = 'pointer';
        availableCard.setAttribute('role', 'button');
        availableCard.setAttribute('tabindex', '0');
        availableCard.addEventListener('click', function (event) {
            event.preventDefault();
            if (window.jQuery) {
                window.jQuery(modal).one('shown.bs.modal', renderChart).modal('show');
            }
        });
        availableCard.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                if (window.jQuery) {
                    window.jQuery(modal).one('shown.bs.modal', renderChart).modal('show');
                }
            }
        });
    }

    if (window.jQuery) {
        window.jQuery(modal).on('shown.bs.modal', renderChart);
    } else {
        document.addEventListener('DOMContentLoaded', function () {
            if (window.jQuery) {
                window.jQuery(modal).on('shown.bs.modal', renderChart);
            }
        });
    }
})();
</script>
<script>
(function () {
    var modal = document.getElementById('stockInventoryModal');
    if (!modal) {
        return;
    }

    var inventory = <?= json_encode($inventoryChartData, $stockJsonFlags) ?: '[]' ?>;
    var groupSelect = document.getElementById('siGroup');
    var itemInput = document.getElementById('siItem');
    var itemSuggestions = document.getElementById('siItemSuggestions');
    var selectedItemId = '';
    var activeSuggestion = -1;
    var dateModeSelect = document.getElementById('siDateMode');
    var asOfInput = document.getElementById('siAsOf');
    var fromInput = document.getElementById('siFrom');
    var toInput = document.getElementById('siTo');
    var dateFields = document.getElementById('siDateFields');
    var summary = document.getElementById('siSummary');
    var empty = document.getElementById('siEmpty');
    var wrap = document.getElementById('siChartWrap');
    var canvas = document.getElementById('siChart');
    var chart = null;
    var currentRows = [];
    var currentFull = [];
    var initialised = false;

    function truncate(text, max) {
        return text.length > max ? text.substring(0, max - 1) + '\u2026' : text;
    }

    function fmtDate(value) {
        var parts = String(value || '').split('-');
        if (parts.length !== 3) {
            return value;
        }
        var date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        return date.toLocaleDateString('en-PH', { month: 'short', day: '2-digit', year: 'numeric' });
    }

    function populateGroups() {
        var seen = {};
        var groups = [];
        inventory.forEach(function (row) {
            if (!seen[row.group]) {
                seen[row.group] = true;
                groups.push(row.group);
            }
        });
        groups.sort(function (a, b) {
            return a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' });
        });
        groups.forEach(function (group) {
            var option = document.createElement('option');
            option.value = group;
            option.textContent = group;
            groupSelect.appendChild(option);
        });
    }

    function itemLabel(row) {
        return row.stock_no + ' \u2014 ' + row.description + (row.unit ? ' (' + row.unit + ')' : '');
    }

    function itemTokens() {
        return itemInput.value.trim().toLowerCase().split(/\s+/).filter(function (token) {
            return token !== '';
        });
    }

    function itemMatches(row, tokens) {
        var haystack = (row.stock_no + ' ' + row.description + ' ' + row.unit).toLowerCase();
        return tokens.every(function (token) {
            return haystack.indexOf(token) !== -1;
        });
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
        });
    }

    function hideSuggestions() {
        itemSuggestions.style.display = 'none';
        itemSuggestions.innerHTML = '';
        itemInput.setAttribute('aria-expanded', 'false');
        activeSuggestion = -1;
    }

    function renderSuggestions() {
        var tokens = itemTokens();
        if (!tokens.length) {
            hideSuggestions();
            return;
        }
        var group = groupSelect.value;
        var results = inventory.filter(function (row) {
            return (!group || row.group === group) && itemMatches(row, tokens);
        }).slice(0, 25);
        if (!results.length) {
            hideSuggestions();
            return;
        }
        itemSuggestions.innerHTML = '';
        activeSuggestion = -1;
        results.forEach(function (row) {
            var li = document.createElement('li');
            li.className = 'dropdown-item';
            li.setAttribute('role', 'option');
            li.innerHTML = escapeHtml(row.stock_no + ' \u2014 ' + row.description)
                + '<span class="si-sub">' + escapeHtml((row.group || 'Ungrouped') + (row.unit ? ' \u00b7 ' + row.unit : '')) + '</span>';
            li.addEventListener('mousedown', function (event) {
                event.preventDefault();
                chooseItem(row);
            });
            itemSuggestions.appendChild(li);
        });
        itemSuggestions.style.display = 'block';
        itemInput.setAttribute('aria-expanded', 'true');
    }

    function chooseItem(row) {
        selectedItemId = String(row.id);
        itemInput.value = itemLabel(row);
        hideSuggestions();
        render();
    }

    function highlightSuggestion() {
        var options = itemSuggestions.querySelectorAll('.dropdown-item');
        Array.prototype.forEach.call(options, function (option, index) {
            if (index === activeSuggestion) {
                option.classList.add('active');
            } else {
                option.classList.remove('active');
            }
        });
        if (options[activeSuggestion]) {
            options[activeSuggestion].scrollIntoView({ block: 'nearest' });
        }
    }

    function toggleDateFields() {
        var mode = dateModeSelect.value;
        dateFields.style.display = mode === '' ? 'none' : '';
        document.querySelectorAll('.si-date-asof').forEach(function (element) {
            element.style.display = mode === 'as_of' ? '' : 'none';
        });
        document.querySelectorAll('.si-date-range').forEach(function (element) {
            element.style.display = mode === 'range' ? '' : 'none';
        });
    }

    function getBounds() {
        if (dateModeSelect.value === 'as_of' && asOfInput.value !== '') {
            return { mode: 'as_of', start: null, end: asOfInput.value + ' 23:59:59' };
        }
        if (dateModeSelect.value === 'range' && (fromInput.value !== '' || toInput.value !== '')) {
            return {
                mode: 'range',
                start: fromInput.value !== '' ? fromInput.value + ' 00:00:00' : '0000-00-00 00:00:00',
                end: toInput.value !== '' ? toInput.value + ' 23:59:59' : '9999-12-31 23:59:59'
            };
        }
        return null;
    }

    function buildRows() {
        var bounds = getBounds();
        var group = groupSelect.value;
        var tokens = itemTokens();
        var entries = [];

        inventory.forEach(function (row) {
            if (group && row.group !== group) {
                return;
            }
            if (selectedItemId !== '' && String(row.id) !== selectedItemId) {
                return;
            }
            if (selectedItemId === '' && tokens.length && !itemMatches(row, tokens)) {
                return;
            }

            var value = row.quantity;
            var metric = 'on hand';
            if (bounds) {
                var firstEntry = row.created_at || '';
                row.movements.forEach(function (movement) {
                    if (firstEntry === '' || movement[0] < firstEntry) {
                        firstEntry = movement[0];
                    }
                });
                if (bounds.mode === 'as_of') {
                    var after = 0;
                    row.movements.forEach(function (movement) {
                        if (movement[0] > bounds.end) {
                            after += movement[1];
                        }
                    });
                    if (firstEntry === '' || firstEntry > bounds.end) {
                        return;
                    }
                    value = row.quantity - after;
                    metric = 'on hand as of ' + fmtDate(asOfInput.value);
                } else {
                    var moved = 0;
                    var count = 0;
                    row.movements.forEach(function (movement) {
                        if (movement[0] >= bounds.start && movement[0] <= bounds.end) {
                            moved += movement[1];
                            count++;
                        }
                    });
                    if (count === 0) {
                        return;
                    }
                    value = moved;
                    metric = 'net moved ' + (fromInput.value ? fmtDate(fromInput.value) : 'first entry') + ' \u2013 ' + (toInput.value ? fmtDate(toInput.value) : 'today');
                }
            }

            entries.push({ row: row, value: value, metric: metric });
        });

        entries.sort(function (a, b) {
            return b.value - a.value || a.row.description.localeCompare(b.row.description);
        });

        return entries;
    }

    function render() {
        var entries = buildRows();
        currentRows = entries.map(function (entry) { return entry.row; });
        currentFull = entries.map(function (entry) {
            return entry.row.description !== '' ? entry.row.description : entry.row.stock_no;
        });

        if (!entries.length) {
            if (chart) {
                chart.destroy();
                chart = null;
            }
            wrap.style.display = 'none';
            empty.style.display = 'block';
            summary.textContent = 'No items match the selected filters.';
            return;
        }

        empty.style.display = 'none';
        wrap.style.display = '';
        wrap.style.height = Math.max(260, entries.length * 24 + 40) + 'px';

        var values = entries.map(function (entry) { return entry.value; });
        var labels = currentFull.map(function (text) { return truncate(text, 52); });
        var total = values.reduce(function (sum, value) { return sum + value; }, 0);
        summary.textContent = 'Showing ' + entries.length + ' item' + (entries.length === 1 ? '' : 's')
            + ' \u00b7 total ' + total.toLocaleString('en-PH') + ' ' + entries[0].metric + '.';

        if (!chart) {
            chart = new Chart(canvas.getContext('2d'), {
                type: 'horizontalBar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Quantity',
                        data: values,
                        backgroundColor: 'rgba(78, 115, 223, 0.75)',
                        borderColor: 'rgba(78, 115, 223, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: { display: false },
                    tooltips: {
                        callbacks: {
                            title: function (items) {
                                return currentFull[items[0].index] || '';
                            },
                            label: function (item) {
                                var row = currentRows[item.index] || {};
                                return ' ' + Number(item.xLabel || 0).toLocaleString('en-PH')
                                    + (row.unit ? ' ' + row.unit : '')
                                    + (row.stock_no ? ' \u00b7 ' + row.stock_no : '');
                            }
                        }
                    },
                    scales: {
                        xAxes: [{
                            ticks: {
                                beginAtZero: true,
                                precision: 0,
                                callback: function (value) {
                                    return Number(value).toLocaleString('en-PH');
                                }
                            },
                            gridLines: { color: 'rgba(0, 0, 0, 0.05)' }
                        }],
                        yAxes: [{
                            ticks: { autoSkip: false, fontSize: 10 },
                            gridLines: { display: false }
                        }]
                    }
                }
            });
        } else {
            chart.data.labels = labels;
            chart.data.datasets[0].data = values;
            chart.resize();
            chart.update();
        }
    }

    function initialise() {
        if (initialised) {
            return;
        }
        initialised = true;
        populateGroups();
        toggleDateFields();
        render();

        groupSelect.addEventListener('change', function () {
            selectedItemId = '';
            itemInput.value = '';
            hideSuggestions();
            render();
        });
        itemInput.addEventListener('input', function () {
            selectedItemId = '';
            renderSuggestions();
            render();
        });
        itemInput.addEventListener('focus', function () {
            if (itemInput.value.trim() !== '') {
                renderSuggestions();
            }
        });
        itemInput.addEventListener('keydown', function (event) {
            var options = itemSuggestions.querySelectorAll('.dropdown-item');
            if (event.key === 'Escape') {
                hideSuggestions();
                return;
            }
            if (!options.length) {
                return;
            }
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                activeSuggestion = (activeSuggestion + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
                highlightSuggestion();
            } else if (event.key === 'Enter') {
                if (activeSuggestion >= 0) {
                    event.preventDefault();
                    var option = options[activeSuggestion];
                    if (option) {
                        option.dispatchEvent(new MouseEvent('mousedown'));
                    }
                } else {
                    hideSuggestions();
                }
            }
        });
        document.addEventListener('click', function (event) {
            if (!itemInput.parentNode.contains(event.target)) {
                hideSuggestions();
            }
        });
        dateModeSelect.addEventListener('change', function () {
            toggleDateFields();
            render();
        });
        [asOfInput, fromInput, toInput].forEach(function (input) {
            input.addEventListener('change', render);
        });
        document.getElementById('siReset').addEventListener('click', function () {
            groupSelect.value = '';
            selectedItemId = '';
            itemInput.value = '';
            hideSuggestions();
            dateModeSelect.value = '';
            asOfInput.value = '';
            fromInput.value = '';
            toInput.value = '';
            toggleDateFields();
            render();
        });
    }

    var imsLinks = document.querySelectorAll('#pimisStockCards a[href*="/ims"]');
    var stockLink = null;
    Array.prototype.forEach.call(imsLinks, function (link) {
        if (!stockLink && !/[?&]stock=/.test(link.getAttribute('href') || '')) {
            stockLink = link;
        }
    });
    var stockCard = stockLink ? stockLink.closest('.card') : null;
    if (stockCard) {
        stockCard.style.cursor = 'pointer';
        stockCard.setAttribute('role', 'button');
        stockCard.setAttribute('tabindex', '0');
        stockCard.addEventListener('click', function (event) {
            event.preventDefault();
            if (window.jQuery) {
                window.jQuery(modal).one('shown.bs.modal', initialise).modal('show');
            }
        });
        stockCard.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                if (window.jQuery) {
                    window.jQuery(modal).one('shown.bs.modal', initialise).modal('show');
                }
            }
        });
    }
})();
</script>
