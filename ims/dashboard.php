<?php
require_once(__DIR__ . '/navigation.php');

$pdo = connection();
$itemCountResult = query('SELECT COUNT(*) AS total FROM items');
$lowStockResult = query('SELECT COUNT(*) AS total FROM items WHERE quantity <= min_qty');
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
    WHERE min_qty > 0 AND quantity <= min_qty
    ORDER BY (quantity - min_qty) ASC, description ASC');
$reorderTracked = (int) (query('SELECT COUNT(*) AS total FROM items WHERE min_qty > 0')[0]['total'] ?? 0);
$reorderChart = [
    'labels' => array_map(static function (array $item): string {
        $description = (string) $item['description'];
        return mb_strlen($description) > 28 ? mb_substr($description, 0, 25) . '...' : $description;
    }, $reorderAlerts),
    'full' => array_map(static fn(array $item): string => (string) $item['description'], $reorderAlerts),
    'current' => array_map(static fn(array $item): int => (int) $item['quantity'], $reorderAlerts),
    'reorder' => array_map(static fn(array $item): int => (int) $item['min_qty'], $reorderAlerts),
];

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

messageAlert($showAlert ?? false, $message ?? '', $success ?? true);
contentTitle('Inventory Management System');
imsNav('inventory');
?>

<div class="row mb-4">
    <?php
    card('Stock Items', customUri('ims', 'Stock and Inventory'), 'fa-boxes', 'primary', $itemCount);
    card('Low Stock', customUri('ims', 'Stock and Inventory'), 'fa-exclamation-triangle', $lowStockCount ? 'warning' : 'success', $lowStockCount);
    card('Pending RIS', customUri('ims', 'Requisition Slips'), 'fa-file-invoice', 'info', $pendingRis);
    card('Pending Purchase Requests', customUri('ims', 'Purchase Requests'), 'fa-file-invoice-dollar', 'secondary', $pendingPr);
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
                <span class="small font-weight-bold text-gray-600"><?= count($reorderAlerts) ?> of <?= $reorderTracked ?> SKUs</span>
            </div>
            <div class="card-body">
                <?php if ($reorderChart['labels']): ?>
                    <div class="chart-bar" style="height:20rem;">
                        <canvas id="imsReorderChart"></canvas>
                    </div>
                    <p class="mb-0 text-muted small">SKUs at or below their designated reorder point.</p>
                <?php else: ?>
                    <p class="mb-0 text-muted">Every SKU with a reorder level is above its reorder point.</p>
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
        <a class="btn btn-primary btn-sm" href="<?= customUri('ims', 'Stock and Inventory') ?>">View stock</a>
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
        <a class="btn btn-primary btn-sm" href="<?= customUri('ims', 'Create Stock Item') ?>"><i class="fas fa-plus"></i> Add item</a>
    </div>
    <div class="card-body">
        <p class="mb-0">Maintain stock, receive requisition slips, create purchase requests for insufficient stock, and record inspection and acceptance before issuing supplies.</p>
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

    var reorderCanvas = document.getElementById('imsReorderChart');
    if (reorderCanvas) {
        var reorderData = <?= json_encode($reorderChart, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        new Chart(reorderCanvas.getContext('2d'), {
            type: 'horizontalBar',
            data: {
                labels: reorderData.labels,
                datasets: [
                    {
                        label: 'Current stock',
                        data: reorderData.current,
                        backgroundColor: '#e74a3b',
                        borderColor: '#e74a3b',
                        borderWidth: 1
                    },
                    {
                        label: 'Reorder level',
                        data: reorderData.reorder,
                        backgroundColor: '#f6c23e',
                        borderColor: '#f6c23e',
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
                        title: function (items) {
                            return reorderData.full[items[0].index];
                        },
                        label: function (item, data) {
                            return data.datasets[item.datasetIndex].label + ': ' + Number(item.xLabel).toLocaleString('en-PH');
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
