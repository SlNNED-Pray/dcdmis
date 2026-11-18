<?php
require_once(__DIR__ . '/navigation.php');

if (!empty($_SESSION["{$prefix}ims_denied"])) {
    unset($_SESSION["{$prefix}ims_denied"]);
}

$canManage = imsIsStaff();
$risStatement = $canManage
    ? connection()->query(
        "SELECT r.id AS ris_id, r.ris_no, r.created_at, r.purpose, r.division, r.office, r.employee_id,
                CONCAT(e.first_name, ' ', e.last_name) AS employee,
                d.id AS detail_id, d.item_id, d.quantity, d.has_stock, d.status, d.unit, d.pcs_per_unit,
                i.stock_no, i.description, i.unit AS item_unit
         FROM requisition_slips r
         LEFT JOIN employees e ON e.id = r.employee_id
         LEFT JOIN requisition_slip_items d ON d.ris_id = r.id
         LEFT JOIN items i ON i.id = d.item_id
         WHERE (d.status IS NULL OR LOWER(d.status) <> 'disapproved')
         ORDER BY r.created_at DESC"
    )
    : (function () use ($userId): PDOStatement {
        $statement = connection()->prepare(
            "SELECT r.id AS ris_id, r.ris_no, r.created_at, r.purpose, r.division, r.office, r.employee_id,
                    CONCAT(e.first_name, ' ', e.last_name) AS employee,
                    d.id AS detail_id, d.item_id, d.quantity, d.has_stock, d.status, d.unit, d.pcs_per_unit,
                    i.stock_no, i.description, i.unit AS item_unit
             FROM requisition_slips r
             LEFT JOIN employees e ON e.id = r.employee_id
             LEFT JOIN requisition_slip_items d ON d.ris_id = r.id
             LEFT JOIN items i ON i.id = d.item_id
             WHERE (d.status IS NULL OR LOWER(d.status) <> 'disapproved')
               AND r.employee_id = ?
             ORDER BY r.created_at DESC",
        );
        $statement->execute([(int) $userId]);
        return $statement;
    })();
$all = $risStatement->fetchAll();
$grouped = [];
foreach ($all as $row) {
    $risId = (int) $row['ris_id'];
    if (!isset($grouped[$risId])) {
        $grouped[$risId] = [
            'ris_id' => $risId,
            'ris_no' => $row['ris_no'],
            'created_at' => $row['created_at'],
            'employee' => $row['employee'] ?: 'Unknown',
            'employee_id' => (int) $row['employee_id'],
            'division' => $row['division'],
            'office' => $row['office'],
            'items' => [],
        ];
    }
    if ($row['item_id'] ?? null) {
        $grouped[$risId]['items'][] = $row;
    }
}

$risQuery = trim((string) ($_GET['ris_q'] ?? ''));
$itemQuery = trim((string) ($_GET['item_q'] ?? ''));
$employeeQuery = trim((string) ($_GET['employee_q'] ?? ''));

$tokenize = static function (string $value): array {
    if (trim($value) === '') {
        return [];
    }
    return array_values(array_filter(
        preg_split('/\s+/', mb_strtolower($value)) ?: [],
        static fn (string $token): bool => $token !== ''
    ));
};

$matchesTokens = static function (string $haystack, array $tokens): bool {
    $haystack = mb_strtolower($haystack);
    foreach ($tokens as $token) {
        if (mb_strpos($haystack, $token) === false) {
            return false;
        }
    }
    return true;
};

$risTokens = $tokenize($risQuery);
$itemTokens = $tokenize($itemQuery);
$employeeTokens = $tokenize($employeeQuery);
$hasRisFilter = $risTokens || $itemTokens || $employeeTokens;

// Suggestion sources are taken from the slips this user can see, so every
// suggestion is guaranteed to return a result.
$risNumberOptions = [];
$itemOptionLabels = [];
$employeeOptions = [];
foreach ($grouped as $ris) {
    $risNumberOptions[(string) $ris['ris_no']] = (string) $ris['ris_no'];
    $employeeOptions[(string) $ris['employee']] = (string) $ris['employee'];
    foreach ($ris['items'] as $detail) {
        $itemOptionLabel = trim((string) ($detail['stock_no'] ?? ''));
        $itemOptionDescription = trim((string) ($detail['description'] ?? ''));
        if ($itemOptionDescription !== '') {
            $itemOptionLabel .= ($itemOptionLabel !== '' ? ' - ' : '') . $itemOptionDescription;
        }
        $itemOptionUnit = trim((string) ($detail['unit'] ?: ($detail['item_unit'] ?? '')));
        if ($itemOptionLabel !== '' && $itemOptionUnit !== '') {
            $itemOptionLabel .= ' (' . $itemOptionUnit . ')';
        }
        if ($itemOptionLabel !== '') {
            $itemOptionLabels[$itemOptionLabel] = $itemOptionLabel;
        }
    }
}
$risNumberOptions = array_values($risNumberOptions);
$itemOptionLabels = array_values($itemOptionLabels);
$employeeOptions = array_values($employeeOptions);
sort($risNumberOptions, SORT_NATURAL | SORT_FLAG_CASE);
sort($itemOptionLabels, SORT_NATURAL | SORT_FLAG_CASE);
sort($employeeOptions, SORT_NATURAL | SORT_FLAG_CASE);

$totalRisCount = count($grouped);
if ($hasRisFilter) {
    $filtered = [];
    foreach ($grouped as $risId => $ris) {
        if ($risTokens && !$matchesTokens((string) $ris['ris_no'], $risTokens)) {
            continue;
        }
        if ($employeeTokens && !$matchesTokens((string) $ris['employee'], $employeeTokens)) {
            continue;
        }
        if ($itemTokens) {
            $itemMatched = false;
            foreach ($ris['items'] as $detail) {
                $haystack = trim((string) ($detail['stock_no'] ?? '')) . ' ' . trim((string) ($detail['description'] ?? ''));
                if ($matchesTokens($haystack, $itemTokens)) {
                    $itemMatched = true;
                    break;
                }
            }
            if (!$itemMatched) {
                continue;
            }
        }
        $filtered[$risId] = $ris;
    }
    $grouped = $filtered;
}

messageAlert($showAlert ?? false, $message ?? '', $success ?? true);
contentTitle('Requisition Slips');
imsNav('ris');
?>
<div class="card shadow mb-4">
    <div class="card-header d-flex justify-content-between align-items-center"><strong>Requisition slips</strong><button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#risModal"><i class="fas fa-plus"></i> Create RIS</button></div>
    <div class="card-body">
        <form method="get" class="mb-3"><?= imsEmbedInput() ?>
            <input type="hidden" name="v" value="<?= e(cipher('Requisition Slips')) ?>">
            <div class="form-row align-items-end">
                <div class="form-group col-md-3 mb-2">
                    <label for="ris-filter-number">RIS Number</label>
                    <input class="form-control" id="ris-filter-number" name="ris_q" list="ris-number-options"
                        value="<?= e($risQuery) ?>" placeholder="Type RIS number" autocomplete="off">
                    <datalist id="ris-number-options">
                        <?php foreach ($risNumberOptions as $risNumberOption): ?>
                            <option value="<?= e($risNumberOption) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-group col-md-4 mb-2">
                    <label for="ris-filter-item">Item name</label>
                    <input class="form-control" id="ris-filter-item" name="item_q" list="ris-item-options"
                        value="<?= e($itemQuery) ?>" placeholder="Type stock no. or description" autocomplete="off">
                    <datalist id="ris-item-options">
                        <?php foreach ($itemOptionLabels as $itemOptionLabel): ?>
                            <option value="<?= e($itemOptionLabel) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-group col-md-3 mb-2">
                    <label for="ris-filter-employee">Employee</label>
                    <input class="form-control" id="ris-filter-employee" name="employee_q" list="ris-employee-options"
                        value="<?= e($employeeQuery) ?>" placeholder="Type employee name" autocomplete="off">
                    <datalist id="ris-employee-options">
                        <?php foreach ($employeeOptions as $employeeOption): ?>
                            <option value="<?= e($employeeOption) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-group col-md-2 mb-2">
                    <button class="btn btn-outline-primary btn-block" type="submit"><i class="fas fa-search"></i> Filter</button>
                </div>
            </div>
            <?php if ($hasRisFilter): ?>
                <div class="mb-2">
                    <a class="btn btn-sm btn-outline-secondary" href="<?= imsUri('Requisition Slips') ?>"><i class="fas fa-times"></i> Clear filter</a>
                    <span class="text-muted ml-2">Showing <?= count($grouped) ?> of <?= $totalRisCount ?> requisition slip(s)</span>
                </div>
            <?php endif; ?>
        </form>
        <div class="table-responsive"><table class="table table-hover"><thead><tr><th>RIS No.</th><th>Employee</th><th>Office</th><th>Division</th><th>Item(s)</th><th>Action</th><th>Date</th></tr></thead><tbody>
    <?php if (empty($grouped)): ?><tr><td colspan="7" class="text-center text-muted my-3"><?= $hasRisFilter ? 'No requisition slips match the selected filter.' : 'No requisition slips found.' ?></td></tr><?php endif; ?>
    <?php foreach ($grouped as $ris): ?><tr>
        <td><?= e($ris['ris_no']) ?><?php if ($canManage): ?> <a class="btn btn-outline-secondary btn-sm ml-1" title="Print RIS" target="_blank" href="<?= uri() . '/ims/ris-print.php?id=' . encode((string) $ris['ris_id']) ?>"><i class="fas fa-print"></i></a><?php endif; ?></td>
        <td><?= e($ris['employee']) ?></td>
        <td><small class="text-muted"><?= $ris['employee_id'] > 0 ? e(risDivisionOfficeFromEmployee($ris['employee_id'])['office'] ?: '—') : e($ris['office'] ?: '—') ?></small></td>
        <td><small class="text-muted"><?= e($ris['division'] ?: '—') ?></small></td>
        <td>
            <?php if (empty($ris['items'])): ?>—<?php endif; ?>
            <?php foreach ($ris['items'] as $detail): ?>
                <div class="mb-1">
                    <span class="badge badge-<?= $detail['has_stock'] ? 'success' : 'warning' ?>"><?= e($detail['stock_no'] . ' - ' . $detail['description'] . ' (' . (int) $detail['quantity'] . ' ' . e($detail['unit'] ?: $detail['item_unit']) . ')') ?></span>
                </div>
            <?php endforeach; ?>
        </td>
        <td>
            <?php if (empty($ris['items'])): ?>—<?php endif; ?>
            <?php foreach ($ris['items'] as $detail): ?>
                <?php
                $detailStatus = strtolower((string) $detail['status']);
                $statusLabel = in_array($detailStatus, ['issued', 'approved'], true)
                    ? 'Approved'
                    : ($detailStatus === 'disapproved' ? 'Disapproved' : ucfirst($detailStatus ?: 'pending'));
                ?>
                <div class="mb-1">
                    <?php if (!$canManage): ?>
                        <span class="text-muted small"><?= e($statusLabel) ?></span>
                    <?php elseif ($detailStatus === 'pending'): ?>
                        <?php if ($detail['has_stock']): ?>
                            <form method="post" class="d-inline mr-1" onsubmit="return confirm('Approve this item and deduct from stock?');">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="issue-ris-item" value="<?= e(cipher((string) $detail['detail_id'])) ?>">
                                <button class="btn btn-sm btn-success" style="min-width:105px" type="submit"><i class="fas fa-check"></i> Approved</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" class="d-inline" onsubmit="return confirm('Disapprove this item? It will be moved to Disapproved RIS.');">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="disapprove-ris-item" value="<?= e(cipher((string) $detail['detail_id'])) ?>">
                            <button class="btn btn-sm btn-danger" style="min-width:105px" type="submit"><i class="fas fa-times"></i> Disapproved</button>
                        </form>
                    <?php elseif (in_array($detailStatus, ['issued', 'approved'], true)): ?>
                        <button type="button" class="btn btn-sm btn-success" style="min-width:118px" disabled><i class="fas fa-check-circle"></i> Approved</button>
                    <?php elseif ($detailStatus === 'disapproved'): ?>
                        <button type="button" class="btn btn-sm btn-danger" style="min-width:118px" disabled><i class="fas fa-times-circle"></i> Disapproved</button>
                    <?php else: ?>
                        <button type="button" class="btn btn-sm btn-secondary" style="min-width:118px" disabled><?= e($statusLabel) ?></button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </td>
        <td><?= e($ris['created_at']) ?></td>
    </tr><?php endforeach; ?></tbody></table></div></div>
</div>
<script>
(function () {
    var filters = ['ris-filter-number', 'ris-filter-item', 'ris-filter-employee'];
    filters.forEach(function (id) {
        var input = document.getElementById(id);
        if (!input) {
            return;
        }
        var list = document.getElementById(input.getAttribute('list'));
        var form = input.form;
        if (!list || !form) {
            return;
        }
        input.addEventListener('change', function () {
            var value = input.value.trim().toLowerCase();
            if (value === '') {
                return;
            }
            var exact = Array.prototype.some.call(list.options, function (option) {
                return option.value.trim().toLowerCase() === value;
            });
            if (!exact) {
                return;
            }
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });
    });
})();
</script>

<?php require_once(__DIR__ . '/ris-create.php'); ?>
