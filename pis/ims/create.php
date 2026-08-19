<?php
if (!$isPis) {
    require_once(root() . '/modules/error/403.php');
    return;
}
require_once(__DIR__ . '/helpers.php');

$pdo = connection();
$employees = $pdo->query('SELECT * FROM employees ORDER BY name ASC')->fetchAll();
$items     = $pdo->query('SELECT * FROM items ORDER BY stock_no ASC')->fetchAll();
$suppliers = $pdo->query('SELECT * FROM suppliers ORDER BY name ASC')->fetchAll();

$errors = [];
$pr = [
    'employee_id' => (int)($_POST['employee_id'] ?? 0),
    'item_id'     => (int)($_POST['item_id'] ?? $_GET['item_id'] ?? 0),
    'quantity'    => (int)($_POST['quantity'] ?? 0),
    'unit_cost'   => (float)($_POST['unit_cost'] ?? 0),
    'purpose'     => trim($_POST['purpose'] ?? ''),
    'supplier_id' => (int)($_POST['supplier_id'] ?? 0) ?: null,
    'po_no'       => trim($_POST['po_no'] ?? ''),
    'po_date'     => trim($_POST['po_date'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($pr['employee_id'] <= 0) $errors[] = 'Select an employee.';
    if ($pr['item_id'] <= 0)     $errors[] = 'Select an item.';
    if ($pr['quantity'] <= 0)    $errors[] = 'Quantity must be greater than zero.';
    if ($pr['unit_cost'] < 0)    $errors[] = 'Unit cost cannot be negative.';

    if (!$errors) {
        $total = $pr['quantity'] * $pr['unit_cost'];
        $stmt = connection()->prepare(
            'INSERT INTO purchase_requests
             (pr_no, employee_id, item_id, quantity, unit_cost, total_cost, purpose, supplier_id, po_no, po_date)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            imsNextDocumentNumber('PR', 'purchase_requests', 'pr_no'),
            $pr['employee_id'], $pr['item_id'], $pr['quantity'],
            $pr['unit_cost'], $total, $pr['purpose'],
            $pr['supplier_id'], $pr['po_no'], $pr['po_date'] !== '' ? $pr['po_date'] : null,
        ]);
        $showAlert = true;
        $success = true;
        $message = 'Purchase request saved successfully.';
        redirect(customUri('pis', 'Purchase Requests'));
    }
}

?>
<div class="card" style="max-width:760px;">
    <div class="card-header">
        <h2>Purchase Request</h2>
        <span class="muted">Created when stock is insufficient (per RIS flow).</span>
    </div>
    <div class="card-body">
        <?php if ($errors): ?>
            <?php messageAlert(true, implode('<br>', array_map('e', $errors)), false); ?>
        <?php endif; ?>
        <form method="post" action="">
            <div class="form-grid">
                <div class="form-group">
                    <label>Name</label>
                    <select name="employee_id" required>
                        <option value="">-- Select employee --</option>
                        <?php foreach ($employees as $emp): ?>
                        <option value="<?= (int)$emp['id'] ?>" <?= $pr['employee_id'] === (int)$emp['id'] ? 'selected' : '' ?>>
                            <?= e($emp['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Designation</label>
                    <input type="text" value="" disabled placeholder="Auto from employee">
                </div>
                <div class="form-group" style="grid-column:1 / -1;">
                    <label>Stock / Property No. &amp; Item Description</label>
                    <input type="hidden" name="item_id" id="item-id"
                           value="<?= $pr['item_id'] ?>">
                    <div class="item-search-wrap" style="position:relative;">
                        <input type="text" id="item-search" autocomplete="off"
                               placeholder="Type to search by stock no. or description…"
                               value="<?php
                                   if ($pr['item_id'] > 0) {
                                       foreach ($items as $it) {
                                           if ((int)$it['id'] === $pr['item_id']) {
                                               echo e($it['stock_no'] . ' — ' . $it['description'] . ' (' . $it['unit'] . ')');
                                               break;
                                           }
                                       }
                                   }
                               ?>">
                        <div id="item-results" style="display:none;position:absolute;z-index:50;left:0;right:0;top:100%;background:#fff;border:1px solid var(--blue-100);border-radius:6px;margin-top:4px;box-shadow:0 6px 18px rgba(15,45,77,.15);max-height:280px;overflow-y:auto;"></div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Unit</label>
                    <input type="text" id="item-unit" value="" disabled>
                </div>
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="quantity" id="qty" min="1" step="1" required value="<?= $pr['quantity'] ?>">
                </div>
                <div class="form-group">
                    <label>Unit Cost (₱)</label>
                    <input type="number" name="unit_cost" id="unit-cost" min="0" step="0.01" required
                           value="<?= $pr['unit_cost'] ? e($pr['unit_cost']) : '' ?>">
                </div>
                <div class="form-group">
                    <label>Total Cost (₱)</label>
                    <input type="text" id="total-cost" value="0.00" disabled>
                </div>
                <div class="form-group" style="grid-column:1 / -1;">
                    <label>Purpose</label>
                    <input type="text" name="purpose" value="<?= e($pr['purpose']) ?>">
                </div>
                <div class="form-group">
                    <label>Supplier</label>
                    <select name="supplier_id">
                        <option value="">-- Select supplier --</option>
                        <?php foreach ($suppliers as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= $pr['supplier_id'] === (int)$s['id'] ? 'selected' : '' ?>>
                            <?= e($s['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>PO No.</label>
                    <input type="text" name="po_no" value="<?= e($pr['po_no']) ?>">
                </div>
                <div class="form-group">
                    <label>PO Date</label>
                    <input type="date" name="po_date" value="<?= e($pr['po_date']) ?>">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Purchase Request</button>
                <a class="btn btn-secondary" href="<?= customUri('pis', 'Purchase Requests') ?>">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var hiddenEl = document.getElementById('item-id');
    var searchEl = document.getElementById('item-search');
    var resultsEl = document.getElementById('item-results');

    var items = <?= json_encode(array_map(function ($it) {
        return [
            'id'        => (int)$it['id'],
            'stock_no'  => $it['stock_no'],
            'description' => $it['description'],
            'unit'      => $it['unit'],
            'unit_cost' => $it['unit_cost'],
            'label'     => $it['stock_no'] . ' — ' . $it['description'] . ' (' . $it['unit'] . ')',
        ];
    }, $items)) ?>;

    var unitEl  = document.getElementById('item-unit');
    var qtyEl   = document.getElementById('qty');
    var costEl  = document.getElementById('unit-cost');
    var totalEl = document.getElementById('total-cost');

    var selected = null;
    var highlight = -1;

    function refreshTotals() {
        var cost = costEl.value ? parseFloat(costEl.value) : 0;
        var qty  = qtyEl.value ? parseInt(qtyEl.value, 10) : 0;
        totalEl.value = (cost * qty).toFixed(2);
    }

    function fill(id) {
        for (var i = 0; i < items.length; i++) {
            if (items[i].id === id) {
                selected = items[i];
                hiddenEl.value = id;
                searchEl.value = selected.label;
                unitEl.value = selected.unit;
                costEl.value = selected.unit_cost;
                refreshTotals();
                closeResults();
                return;
            }
        }
    }

    function openResults() {
        resultsEl.style.display = 'block';
        resultsEl.scrollTop = 0;
    }
    function closeResults() {
        resultsEl.style.display = 'none';
        highlight = -1;
    }

    function renderResults() {
        var q = searchEl.value.trim().toLowerCase();
        var list = [];
        for (var i = 0; i < items.length; i++) {
            if (!q ||
                items[i].stock_no.toLowerCase().indexOf(q) !== -1 ||
                items[i].description.toLowerCase().indexOf(q) !== -1) {
                list.push(items[i]);
            }
            if (list.length >= 50) break;
        }
        if (list.length === 0) {
            resultsEl.innerHTML = '<div style="padding:10px 12px;color:#888;font-size:13px;">No matching items</div>';
            openResults();
            return;
        }
        highlight = -1;
        var html = '';
        for (var i = 0; i < list.length; i++) {
            var it = list[i];
            html += '<div class="item-result" data-id="' + it.id + '" style="padding:8px 12px;cursor:pointer;font-size:13px;border-bottom:1px solid var(--blue-50);">'
                  + '<strong style="color:var(--blue-700);">' + esc(it.stock_no) + '</strong>'
                  + ' <span style="color:#555;">— ' + esc(it.description) + '</span>'
                  + ' <span style="color:#888;">(' + esc(it.unit) + ')</span>'
                  + '</div>';
        }
        resultsEl.innerHTML = html;
        var divs = resultsEl.querySelectorAll('.item-result');
        Array.prototype.forEach.call(divs, function (div) {
            div.addEventListener('mousedown', function (e) {
                e.preventDefault();
                fill(parseInt(div.getAttribute('data-id'), 10));
            });
        });
        openResults();
    }

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function arrowMove(dir) {
        var divs = resultsEl.querySelectorAll('.item-result');
        if (divs.length === 0) return;
        highlight = Math.min(Math.max(highlight + dir, 0), divs.length - 1);
        Array.prototype.forEach.call(divs, function (div, i) {
            div.style.background = i === highlight ? 'var(--blue-100)' : '';
        });
    }

    searchEl.addEventListener('input', function () {
        if (selected && searchEl.value !== selected.label) {
            selected = null;
            hiddenEl.value = '';
            unitEl.value = '';
        }
        renderResults();
    });
    searchEl.addEventListener('focus', function () {
        if (searchEl.value.trim() === '') renderResults();
    });
    searchEl.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') { e.preventDefault(); arrowMove(1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); arrowMove(-1); }
        else if (e.key === 'Enter') {
            if (resultsEl.style.display === 'block') {
                var divs = resultsEl.querySelectorAll('.item-result');
                if (highlight >= 0 && divs[highlight]) {
                    e.preventDefault();
                    fill(parseInt(divs[highlight].getAttribute('data-id'), 10));
                }
            }
        }
        else if (e.key === 'Escape') closeResults();
    });
    document.addEventListener('mousedown', function (e) {
        if (!searchEl.contains(e.target) && !resultsEl.contains(e.target)) closeResults();
    });

    qtyEl.addEventListener('input', refreshTotals);
    costEl.addEventListener('input', refreshTotals);
})();
</script>
