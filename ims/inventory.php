<?php
require_once(__DIR__ . '/navigation.php');

$allItems = connection()->query("SELECT * FROM items ORDER BY (item_group IS NULL OR item_group = ''), item_group ASC, description ASC")->fetchAll();

$grouped = [];
foreach ($allItems as $row) {
    $groupName = trim((string) ($row['item_group'] ?? ''));
    if ($groupName === '') {
        $groupName = 'Ungrouped';
    }
    $grouped[$groupName][] = $row;
}
uksort($grouped, 'strnatcasecmp');

$groupFilter = trim((string) ($_GET['group'] ?? ''));
$itemFilter = (int) ($_GET['item'] ?? 0);
$itemQuery = trim((string) ($_GET['item_q'] ?? ''));
$stockFilter = trim((string) ($_GET['stock'] ?? ''));
if (!in_array($stockFilter, ['available', 'out'], true)) {
    $stockFilter = '';
}

$itemLabel = static function (array $row): string {
    $label = trim((string) ($row['stock_no'] ?? ''));
    $description = trim((string) ($row['description'] ?? ''));
    if ($description !== '') {
        $label .= ($label !== '' ? ' — ' : '') . $description;
    }
    if (trim((string) ($row['unit'] ?? '')) !== '') {
        $label .= ' (' . trim((string) $row['unit']) . ')';
    }
    return $label;
};

$itemTokens = $itemQuery !== ''
    ? array_values(array_filter(preg_split('/\s+/', mb_strtolower($itemQuery)) ?: [], static fn (string $token): bool => $token !== ''))
    : [];

$items = $allItems;
if ($itemFilter > 0) {
    $items = array_values(array_filter($allItems, static fn (array $row): bool => (int) $row['id'] === $itemFilter));
} elseif ($itemTokens) {
    $itemSource = $groupFilter !== '' && isset($grouped[$groupFilter]) ? $grouped[$groupFilter] : $allItems;
    $items = array_values(array_filter($itemSource, static function (array $row) use ($itemTokens): bool {
        $haystack = mb_strtolower(trim((string) ($row['stock_no'] ?? '')) . ' ' . trim((string) ($row['description'] ?? '')));
        foreach ($itemTokens as $token) {
            if (mb_strpos($haystack, $token) === false) {
                return false;
            }
        }
        return true;
    }));
} elseif ($groupFilter !== '' && isset($grouped[$groupFilter])) {
    $items = $grouped[$groupFilter];
}
if ($stockFilter === 'available') {
    $items = array_values(array_filter($items, static fn (array $row): bool => (int) $row['quantity'] > 0));
} elseif ($stockFilter === 'out') {
    $items = array_values(array_filter($items, static fn (array $row): bool => (int) $row['quantity'] <= 0));
}

$itemDropdownGroups = ($itemFilter === 0 && $groupFilter !== '' && isset($grouped[$groupFilter]))
    ? [$groupFilter => $grouped[$groupFilter]]
    : $grouped;

$itemOptions = [];
foreach ($itemDropdownGroups as $groupItems) {
    foreach ($groupItems as $groupItem) {
        $itemOptions[] = $groupItem;
    }
}

$itemLookup = [];
foreach ($itemOptions as $optionItem) {
    $itemLookup[$itemLabel($optionItem)] = (int) $optionItem['id'];
}

$selectedItemLabel = '';
if ($itemFilter > 0) {
    foreach ($itemOptions as $optionItem) {
        if ((int) $optionItem['id'] === $itemFilter) {
            $selectedItemLabel = $itemLabel($optionItem);
            break;
        }
    }
}
if ($selectedItemLabel === '' && $itemQuery !== '') {
    $selectedItemLabel = $itemQuery;
}

$dateMode = trim((string) ($_GET['date_mode'] ?? ''));
if (!in_array($dateMode, ['as_of', 'range'], true)) {
    $dateMode = '';
}
$asOfDate = trim((string) ($_GET['as_of'] ?? ''));
$fromDate = trim((string) ($_GET['from_date'] ?? ''));
$toDate = trim((string) ($_GET['to_date'] ?? ''));
$isValidDate = static function (string $value): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }
    [$year, $month, $day] = array_map('intval', explode('-', $value));
    return checkdate($month, $day, $year);
};
if (!$isValidDate($asOfDate)) {
    $asOfDate = '';
}
if (!$isValidDate($fromDate)) {
    $fromDate = '';
}
if (!$isValidDate($toDate)) {
    $toDate = '';
}
if ($dateMode === 'as_of' && $asOfDate === '') {
    $dateMode = '';
}
if ($dateMode === 'range' && $fromDate === '' && $toDate === '') {
    $dateMode = '';
}
if ($dateMode === 'range' && $fromDate !== '' && $toDate !== '' && $fromDate > $toDate) {
    [$fromDate, $toDate] = [$toDate, $fromDate];
}

$dateInfo = [];
if ($dateMode !== '') {
    $movementsByItem = [];
    $movementRows = connection()->query('SELECT item_id, quantity, created_at FROM stock_movements ORDER BY created_at')->fetchAll();
    foreach ($movementRows as $movementRow) {
        $movementsByItem[(int) $movementRow['item_id']][] = $movementRow;
    }

    $asOfEnd = $asOfDate !== '' ? $asOfDate . ' 23:59:59' : '';
    $rangeStart = $fromDate !== '' ? $fromDate . ' 00:00:00' : '0000-00-00 00:00:00';
    $rangeEnd = $toDate !== '' ? $toDate . ' 23:59:59' : '9999-12-31 23:59:59';

    foreach ($allItems as $row) {
        $itemId = (int) $row['id'];
        $movements = $movementsByItem[$itemId] ?? [];
        $firstEntry = (string) ($row['created_at'] ?? '');
        foreach ($movements as $movement) {
            if ($firstEntry === '' || $movement['created_at'] < $firstEntry) {
                $firstEntry = (string) $movement['created_at'];
            }
        }

        $info = [
            'first_entry' => $firstEntry,
            'as_of_qty' => (int) $row['quantity'],
            'range_qty' => 0,
            'range_count' => 0,
        ];
        $afterQty = 0;
        foreach ($movements as $movement) {
            $movementDate = (string) $movement['created_at'];
            $endBoundary = $dateMode === 'as_of' ? $asOfEnd : $rangeEnd;
            if ($movementDate > $endBoundary) {
                $afterQty += (int) $movement['quantity'];
            }
            if ($dateMode === 'range' && $movementDate >= $rangeStart && $movementDate <= $rangeEnd) {
                $info['range_qty'] += (int) $movement['quantity'];
                $info['range_count']++;
            }
        }
        $info['as_of_qty'] = (int) $row['quantity'] - $afterQty;
        $dateInfo[$itemId] = $info;
    }

    if ($dateMode === 'as_of') {
        $asOfEnd = $asOfDate . ' 23:59:59';
        $items = array_values(array_filter($items, static function (array $row) use ($dateInfo, $asOfEnd): bool {
            $info = $dateInfo[(int) $row['id']] ?? null;
            return $info !== null && $info['first_entry'] !== '' && $info['first_entry'] <= $asOfEnd;
        }));
    } else {
        $items = array_values(array_filter($items, static function (array $row) use ($dateInfo): bool {
            return ($dateInfo[(int) $row['id']]['range_count'] ?? 0) > 0;
        }));
    }
}

$asOfLabel = $asOfDate !== '' ? date('M d, Y', strtotime($asOfDate)) : '';
$rangeLabel = ($fromDate !== '' ? date('M d, Y', strtotime($fromDate)) : 'First entry')
    . ' – '
    . ($toDate !== '' ? date('M d, Y', strtotime($toDate)) : 'Today');

messageAlert($showAlert ?? false, $message ?? '', $success ?? true);
contentTitle('Stock and Inventory');
imsNav('inventory');
?>

<div class="card shadow mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Stock items</strong>
        <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#itemModal"><i class="fas fa-plus"></i> Add item</button>
    </div>
    <div class="card-body">
        <form method="get" class="mb-3"><?= imsEmbedInput() ?>
            <input type="hidden" name="v" value="<?= e(cipher('Stock and Inventory')) ?>">
            <?php if ($stockFilter !== ''): ?>
                <input type="hidden" name="stock" value="<?= e($stockFilter) ?>">
            <?php endif; ?>
            <div class="form-row align-items-end">
                <div class="form-group col-md-3 mb-2">
                    <label for="ims-group-filter">Group</label>
                    <select class="form-control" id="ims-group-filter" name="group" onchange="document.getElementById('ims-item-filter').value = ''; document.getElementById('ims-item-id').value = ''; this.form.submit();">
                        <option value="">All groups</option>
                        <?php foreach ($grouped as $groupName => $groupItems): ?>
                            <option value="<?= e($groupName) ?>" <?= $groupFilter === $groupName ? 'selected' : '' ?>><?= e($groupName) ?> (<?= count($groupItems) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-4 mb-2">
                    <label for="ims-item-filter">Item &amp; Specifications</label>
                    <input class="form-control" id="ims-item-filter" name="item_q" list="ims-item-options"
                        value="<?= e($selectedItemLabel) ?>" placeholder="Type stock no. or description" autocomplete="off">
                    <datalist id="ims-item-options">
                        <?php foreach ($itemOptions as $optionItem): ?>
                            <option value="<?= e($itemLabel($optionItem)) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <input type="hidden" name="item" id="ims-item-id" value="<?= $itemFilter > 0 ? (int) $itemFilter : '' ?>">
                </div>
                <div class="form-group col-md-3 mb-2">
                    <label for="ims-date-mode">Date filter</label>
                    <select class="form-control" id="ims-date-mode" name="date_mode">
                        <option value="">All dates</option>
                        <option value="as_of" <?= $dateMode === 'as_of' ? 'selected' : '' ?>>As of date</option>
                        <option value="range" <?= $dateMode === 'range' ? 'selected' : '' ?>>Date range</option>
                    </select>
                </div>
                <div class="form-group col-md-2 mb-2">
                    <button class="btn btn-outline-primary btn-block" type="submit"><i class="fas fa-search"></i> Filter</button>
                </div>
            </div>
            <div class="form-row align-items-end" id="ims-date-fields" style="<?= $dateMode === '' ? 'display:none;' : '' ?>">
                <div class="form-group col-md-3 mb-2 ims-date-asof" style="<?= $dateMode === 'as_of' ? '' : 'display:none;' ?>">
                    <label for="ims-as-of">As of date</label>
                    <input class="form-control" type="date" id="ims-as-of" name="as_of" value="<?= e($asOfDate) ?>">
                </div>
                <div class="form-group col-md-3 mb-2 ims-date-range" style="<?= $dateMode === 'range' ? '' : 'display:none;' ?>">
                    <label for="ims-from-date">From date</label>
                    <input class="form-control" type="date" id="ims-from-date" name="from_date" value="<?= e($fromDate) ?>">
                </div>
                <div class="form-group col-md-3 mb-2 ims-date-range" style="<?= $dateMode === 'range' ? '' : 'display:none;' ?>">
                    <label for="ims-to-date">To date</label>
                    <input class="form-control" type="date" id="ims-to-date" name="to_date" value="<?= e($toDate) ?>">
                </div>
            </div>
            <?php if ($groupFilter !== '' || $itemFilter > 0 || $itemQuery !== '' || $dateMode !== '' || $stockFilter !== ''): ?>
                <div class="mb-2">
                    <a class="btn btn-sm btn-outline-secondary" href="<?= imsUri('Stock and Inventory') ?>"><i class="fas fa-times"></i> Clear filter</a>
                    <span class="text-muted ml-2">
                        Showing <?= count($items) ?> of <?= count($allItems) ?> item(s)
                        <?php if ($stockFilter === 'available'): ?> · with available stock<?php elseif ($stockFilter === 'out'): ?> · out of stock<?php endif; ?>
                        <?php if ($dateMode === 'as_of'): ?> · as of <?= e($asOfLabel) ?><?php elseif ($dateMode === 'range'): ?> · transactions <?= e($rangeLabel) ?><?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>
        </form>
        <?php
        $inventoryColWidths = $dateMode !== ''
            ? [9, 21, 10, 5, 7, 8, 6, 8, 11, 15]
            : [10, 24, 11, 6, 8, 7, 9, 12, 13];
        ?>
        <div class="table-responsive inventory-table-responsive">
            <table class="table table-hover inventory-table" id="ims-items-table">
                <colgroup>
                    <?php foreach ($inventoryColWidths as $inventoryColWidth): ?>
                        <col style="width:<?= (int) $inventoryColWidth ?>%">
                    <?php endforeach; ?>
                </colgroup>
                <thead><tr><th>Stock No.</th><th>Description</th><th>Group</th><th>Unit</th><th>Total</th><?php if ($dateMode === 'as_of'): ?><th>On Hand as of <?= e($asOfLabel) ?></th><?php elseif ($dateMode === 'range'): ?><th>Qty moved (<?= e($rangeLabel) ?>)</th><?php endif; ?><th>Minimum</th><th>Status</th><th>Remarks</th><th>Action</th></tr></thead>
                <tbody>
                <?php if (!$items): ?>
                    <tr><td colspan="<?= $dateMode !== '' ? 10 : 9 ?>" class="text-center text-muted my-3">No items match the selected filter.</td></tr>
                <?php endif; ?>
                <?php foreach ($items as $item): ?>
                    <?php $dateRow = $dateInfo[(int) $item['id']] ?? ['as_of_qty' => (int) $item['quantity'], 'range_qty' => 0, 'range_count' => 0]; ?>
                    <?php $statusQty = $dateMode === 'as_of' ? (int) $dateRow['as_of_qty'] : (int) $item['quantity']; ?>
                    <?php $noStock = $statusQty <= 0; ?>
                    <?php $low = !$noStock && $statusQty <= (int) $item['min_qty']; ?>
                    <tr>
                        <?php $unitsUnit = !empty($item['number_units_unit']) ? $item['number_units_unit'] : $item['unit']; ?>
                        <td><?= e($item['stock_no']) ?></td>
                        <td><?= e($item['description']) ?></td>
                        <td><small><?= e($item['item_group'] ?: 'Ungrouped') ?></small></td>
                        <td><?= e($item['unit']) ?></td>
                        <td><?php $tu = (int) $item['total_units']; ?><?= $tu ? $tu . (!empty($unitsUnit) ? ' ' . e(ucfirst($unitsUnit)) : '') : '—' ?></td>
                        <?php if ($dateMode === 'as_of'): ?>
                            <td><?= (int) $dateRow['as_of_qty'] ?></td>
                        <?php elseif ($dateMode === 'range'): ?>
                            <td>
                                <?= ((int) $dateRow['range_qty'] > 0 ? '+' : '') . (int) $dateRow['range_qty'] ?>
                                <small class="text-muted">(<?= (int) $dateRow['range_count'] ?> txn)</small>
                            </td>
                        <?php endif; ?>
                        <td><?= (int) $item['min_qty'] ?></td>
                        <td><span class="badge badge-<?= $noStock ? 'danger' : ($low ? 'warning' : 'success') ?>"><?= $noStock ? 'No Stock' : ($low ? 'Low Stock' : 'Available') ?></span></td>
                        <td><small><?= e($item['remarks'] ?: '—') ?></small></td>
                        <td>
                            <?php if ($statusQty <= (int) $item['min_qty']): ?>
                                <button type="button" class="btn btn-danger btn-sm pr-quick-btn" title="Create purchase request" data-toggle="modal" data-target="#prModal" data-item-id="<?= (int) $item['id'] ?>" data-item-qty="<?= max(1, (int) $item['min_qty'] - $statusQty) ?>" data-item-cost="<?= number_format((float) $item['unit_cost'], 2, '.', '') ?>"><i class="fas fa-file-invoice-dollar"></i> PR</button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-info btn-sm ris-quick-btn" data-toggle="modal" data-target="#risModal" data-item-id="<?= (int) $item['id'] ?>"><i class="fas fa-file-invoice"></i> RIS</button>
                            <a class="btn btn-warning btn-sm" href="<?= imsUri('Edit Stock Item', $item['id']) ?>"><i class="fas fa-edit"></i> Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<style>
.inventory-table-responsive { overflow: visible !important; }
.inventory-table { width: 100%; table-layout: fixed; }
.inventory-table th, .inventory-table td { vertical-align: top; overflow-wrap: anywhere; word-break: break-word; }
.inventory-table th { white-space: normal; }
.inventory-table .badge { display: inline-block; max-width: 100%; white-space: normal; }
.inventory-table .btn { white-space: normal; }
</style>

<?php require_once(__DIR__ . '/item-create.php'); ?>
<?php require_once(__DIR__ . '/ris-create.php'); ?>
<?php require_once(__DIR__ . '/pr-create.php'); ?>
<script>
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.ris-quick-btn');
    if (!btn) return;
    if (typeof ITEMS === 'undefined' || typeof selectItem !== 'function') return;
    var itemId = btn.getAttribute('data-item-id');
    var item = ITEMS.filter(function (it) { return String(it.id) === String(itemId); })[0];
    if (!item) return;
    if (typeof resetRisForm === 'function') resetRisForm();
    var container = document.getElementById('ris-items');
    if (!container) return;
    var row = container.querySelector('.ris-item-row');
    if (!row) return;
    selectItem(row, item);
    var qty = row.querySelector('[name="quantity[]"]');
    if (qty) { qty.value = 1; refreshStock(row); }
});
</script>
<script>
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.pr-quick-btn');
    if (!btn) return;
    var form = document.querySelector('#prModal form');
    if (!form) return;
    var itemSelect = form.querySelector('[name="item_id"]');
    var qtyInput = form.querySelector('[name="quantity"]');
    var costInput = form.querySelector('[name="unit_cost"]');
    if (itemSelect) { itemSelect.value = btn.getAttribute('data-item-id') || ''; }
    if (qtyInput) { qtyInput.value = btn.getAttribute('data-item-qty') || '1'; }
    if (costInput) { costInput.value = btn.getAttribute('data-item-cost') || '0'; }
});
</script>
<script>
(function () {
    var mode = document.getElementById('ims-date-mode');
    var fields = document.getElementById('ims-date-fields');
    if (!mode || !fields) return;
    var asOfGroups = document.querySelectorAll('.ims-date-asof');
    var rangeGroups = document.querySelectorAll('.ims-date-range');
    function toggleGroup(groups, show) {
        groups.forEach(function (group) {
            group.style.display = show ? '' : 'none';
            group.querySelectorAll('input, select').forEach(function (input) {
                input.disabled = !show;
            });
        });
    }
    function update() {
        var value = mode.value;
        fields.style.display = value ? '' : 'none';
        toggleGroup(asOfGroups, value === 'as_of');
        toggleGroup(rangeGroups, value === 'range');
    }
    mode.addEventListener('change', update);
    update();
})();
</script>
<script>
(function () {
    var input = document.getElementById('ims-item-filter');
    var hidden = document.getElementById('ims-item-id');
    var form = input ? input.form : null;
    if (!input || !hidden || !form) return;
    var lookup = <?= json_encode($itemLookup, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}' ?>;

    function syncId() {
        hidden.value = Object.prototype.hasOwnProperty.call(lookup, input.value)
            ? String(lookup[input.value])
            : '';
    }

    input.addEventListener('input', syncId);
    input.addEventListener('change', function () {
        syncId();
        if (hidden.value !== '') {
            form.submit();
        }
    });
    syncId();
})();
</script>
