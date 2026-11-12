<?php
// pis/page.php
messageAlert($showAlert, $message, $success);
contentTitle('Dashboard');
$hasImsRole = (bool) userRole($userId, 'ims');
$availableStockItems = $hasImsRole
    ? (query('SELECT stock_no, description, unit, quantity FROM items WHERE quantity > 0 ORDER BY quantity DESC, description ASC') ?: [])
    : [];

$inventoryChartItems = $hasImsRole
    ? (query(
        "SELECT id, stock_no, description, item_group, unit, quantity, created_at
         FROM items
         ORDER BY (item_group IS NULL OR item_group = ''), item_group ASC, description ASC"
    ) ?: [])
    : [];
$inventoryMovementsByItem = [];
if ($hasImsRole) {
    foreach (query('SELECT item_id, quantity, created_at FROM stock_movements ORDER BY created_at') ?: [] as $movementRow) {
        $inventoryMovementsByItem[(int) $movementRow['item_id']][] = [
            (string) $movementRow['created_at'],
            (int) $movementRow['quantity'],
        ];
    }
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
?>

<div class="row mt-4">
    <?php
    card('Employee Information', customUri('pis', 'Employee Information', $userId), 'fa-user-tie', 'primary');
    card('Service Record', customUri('pis', 'Service Record', $userId), 'fa-file-alt', 'success');
    card('201 Files', customUri('pis', '201 Files', $userId), 'fa-folder-open', 'info');
    card('Trainings', customUri('pis', 'Trainings', $userId), 'fa-chalkboard-teacher', 'warning');
    card('Payslips', customUri('pis', 'Payslips', $userId), 'fa-money-check', 'danger');
    if (!$isNonDivision) {
        card('IPCRF', customUri('pis', 'IPCRF', $userId), 'fa-chart-line', 'secondary');
    }
    if ($hasImsRole) {
        card('Stock and Inventory', customUri('ims', 'Stock and Inventory'), 'fa-warehouse', 'primary');
        card('Available Stocks', customUri('ims', 'Stock and Inventory') . '&stock=available', 'fa-boxes', 'success', count($availableStockItems));
        card('No Stocks', customUri('ims', 'Stock and Inventory') . '&stock=out', 'fa-boxes', 'danger');
    }
    ?>
</div>

<?php if ($isNonDivision || $schoolInfo): ?>
    <hr class="mt-0">
    <div class="row mt-4">
        <?php
        if ($isNonDivision) {
            card('Request Transfer', customUri('pis', 'Request Transfer'), 'fa-exchange-alt', 'primary');
        }
        if ($schoolInfo) {
            card('School Employees', customUri('pis', 'School Employees'), 'fa-users', $isNonDivision ? 'success' : 'primary');
        }
        ?>
    </div>
<?php endif; ?>

<?php if ($showMonitoringTools || $showOverview): ?>
    <hr class="mt-0">
    <div class="row mt-4">
        <?php
        if ($showMonitoringTools) {
            card('Monitoring Tools', customUri('pis', 'Monitoring Tools'), 'fa-binoculars', 'primary');
        }
        if ($showOverview) {
            card('System Overview', customUri('pis', 'System Overview'), 'fa-network-wired', $showMonitoringTools ? 'success' : 'primary');
        } ?>
    </div>
<?php endif; ?>

<?php
if ($hasImsRole):
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
    $stockChartHeight = max(260, count($availableStockItems) * 26 + 40);
    $stockJsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    ?>
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
                        <div id="pisAvailableStocksChartWrap" style="position:relative; height:<?= (int) $stockChartHeight ?>px;">
                            <canvas id="pisAvailableStocksChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <span class="small text-muted"><?= count($availableStockItems) ?> item<?= count($availableStockItems) === 1 ? '' : 's' ?> with stock</span>
                    <div>
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                        <a class="btn btn-success btn-sm" href="<?= e(customUri('ims', 'Stock and Inventory') . '&stock=available') ?>">
                            <i class="fas fa-list mr-1"></i>View inventory list
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
                        <div class="form-group col-md-5 mb-2">
                            <label class="small mb-1" for="siItem">Item &amp; Specifications</label>
                            <select class="form-control form-control-sm" id="siItem">
                                <option value="">All items</option>
                            </select>
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
                        <a class="btn btn-primary btn-sm" href="<?= e(customUri('ims', 'Stock and Inventory')) ?>">
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

        var availableLink = document.querySelector('a[href*="stock=available"]');
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
        var itemSelect = document.getElementById('siItem');
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

        function populateItems(preserveSelection) {
            var previous = itemSelect.value;
            var group = groupSelect.value;
            itemSelect.innerHTML = '<option value="">All items</option>';
            inventory.forEach(function (row) {
                if (group && row.group !== group) {
                    return;
                }
                var option = document.createElement('option');
                option.value = String(row.id);
                option.textContent = row.stock_no + ' \u2014 ' + row.description + (row.unit ? ' (' + row.unit + ')' : '');
                itemSelect.appendChild(option);
            });
            if (preserveSelection && previous) {
                var match = itemSelect.querySelector('option[value="' + previous + '"]');
                if (match) {
                    itemSelect.value = previous;
                }
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
            var item = itemSelect.value;
            var entries = [];

            inventory.forEach(function (row) {
                if (group && row.group !== group) {
                    return;
                }
                if (item && String(row.id) !== item) {
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
            populateItems(true);
            toggleDateFields();
            render();

            groupSelect.addEventListener('change', function () {
                populateItems(false);
                render();
            });
            itemSelect.addEventListener('change', render);
            dateModeSelect.addEventListener('change', function () {
                toggleDateFields();
                render();
            });
            [asOfInput, fromInput, toInput].forEach(function (input) {
                input.addEventListener('change', render);
            });
            document.getElementById('siReset').addEventListener('click', function () {
                groupSelect.value = '';
                populateItems(false);
                itemSelect.value = '';
                dateModeSelect.value = '';
                asOfInput.value = '';
                fromInput.value = '';
                toInput.value = '';
                toggleDateFields();
                render();
            });
        }

        var imsLinks = document.querySelectorAll('.card a[href*="/ims"]');
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
<?php endif; ?>