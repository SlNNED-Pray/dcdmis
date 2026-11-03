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

$items = $allItems;
if ($itemFilter > 0) {
    $items = array_values(array_filter($allItems, static fn (array $row): bool => (int) $row['id'] === $itemFilter));
} elseif ($groupFilter !== '' && isset($grouped[$groupFilter])) {
    $items = $grouped[$groupFilter];
}

$itemDropdownGroups = ($itemFilter === 0 && $groupFilter !== '' && isset($grouped[$groupFilter]))
    ? [$groupFilter => $grouped[$groupFilter]]
    : $grouped;

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
        <form method="get" class="form-row align-items-end mb-3">
            <input type="hidden" name="v" value="<?= e(cipher('Stock and Inventory')) ?>">
            <div class="form-group col-md-4 mb-2">
                <label for="ims-group-filter">Group</label>
                <select class="form-control" id="ims-group-filter" name="group" onchange="document.getElementById('ims-item-filter').value = ''; this.form.submit();">
                    <option value="">All groups</option>
                    <?php foreach ($grouped as $groupName => $groupItems): ?>
                        <option value="<?= e($groupName) ?>" <?= $groupFilter === $groupName ? 'selected' : '' ?>><?= e($groupName) ?> (<?= count($groupItems) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-6 mb-2">
                <label for="ims-item-filter">Item &amp; Specifications</label>
                <select class="form-control" id="ims-item-filter" name="item" onchange="this.form.submit()">
                    <option value="">All items<?= $groupFilter !== '' ? ' in this group' : '' ?></option>
                    <?php foreach ($itemDropdownGroups as $groupName => $groupItems): ?>
                        <optgroup label="<?= e($groupName) ?>">
                            <?php foreach ($groupItems as $groupItem): ?>
                                <option value="<?= (int) $groupItem['id'] ?>" <?= $itemFilter === (int) $groupItem['id'] ? 'selected' : '' ?>><?= e($groupItem['stock_no']) ?> — <?= e($groupItem['description']) ?> (<?= e($groupItem['unit']) ?>)</option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-2 mb-2">
                <button class="btn btn-outline-primary btn-block" type="submit"><i class="fas fa-search"></i> Filter</button>
            </div>
            <?php if ($groupFilter !== '' || $itemFilter > 0): ?>
                <div class="form-group col-12 mb-2">
                    <a class="btn btn-sm btn-outline-secondary" href="<?= customUri('ims', 'Stock and Inventory') ?>"><i class="fas fa-times"></i> Clear filter</a>
                    <span class="text-muted ml-2">Showing <?= count($items) ?> of <?= count($allItems) ?> item(s).</span>
                </div>
            <?php endif; ?>
        </form>
        <div class="table-responsive">
            <table class="table table-hover" id="ims-items-table">
                <thead><tr><th>Stock No.</th><th>Description</th><th>Group</th><th>Unit</th><th>Total Units</th><th>On Hand</th><th>Minimum</th><th>Status</th><th>Remarks</th><th>Action</th></tr></thead>
                <tbody>
                <?php if (!$items): ?>
                    <tr><td colspan="10" class="text-center text-muted my-3">No items match the selected filter.</td></tr>
                <?php endif; ?>
                <?php foreach ($items as $item): ?>
                    <?php $low = (int) $item['quantity'] <= (int) $item['min_qty']; ?>
                    <tr>
                        <?php $unitsUnit = !empty($item['number_units_unit']) ? $item['number_units_unit'] : $item['unit']; ?>
                        <td><?= e($item['stock_no']) ?></td>
                        <td><?= e($item['description']) ?></td>
                        <td><small><?= e($item['item_group'] ?: 'Ungrouped') ?></small></td>
                        <td><?= e($item['unit']) ?></td>
                        <td><?php $tu = (int) $item['total_units']; ?><?= $tu ? $tu . (!empty($unitsUnit) ? ' ' . e(ucfirst($unitsUnit)) : '') : '—' ?></td>
                        <td><?= (int) $item['quantity'] ?></td>
                        <td><?= (int) $item['min_qty'] ?></td>
                        <td><span class="badge badge-<?= $low ? 'warning' : 'success' ?>"><?= $low ? 'Low stock' : 'Available' ?></span></td>
                        <td><small><?= e($item['remarks'] ?: '—') ?></small></td>
                        <td>
                            <a class="btn btn-info btn-sm" href="<?= customUri('ims', 'Create RIS', $item['id']) ?>"><i class="fas fa-file-invoice"></i> RIS</a>
                            <a class="btn btn-warning btn-sm" href="<?= customUri('ims', 'Edit Stock Item', $item['id']) ?>"><i class="fas fa-edit"></i> Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once(__DIR__ . '/item-create.php'); ?>
