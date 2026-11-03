<?php
// bsa/dashboard.php — Schedule Overview bar graph (view: Dashboard)
if (!function_exists('uri')) {
    http_response_code(403);
    exit('Forbidden');
}

messageAlert($showAlert, $message, $success);
?>

<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between flex-wrap">
                <h6 class="m-0 font-weight-bold text-primary">Schedule Overview</h6>
                <div class="mb-0 d-flex align-items-center flex-wrap">
                    <label class="mb-0 mr-2 small font-weight-bold text-gray-600" for="schedulePeriod">Show by</label>
                    <select id="schedulePeriod" class="form-control form-control-sm mr-3" style="width:auto;">
                        <option value="day">Day</option>
                        <option value="week">Week</option>
                        <option value="month" selected>Month</option>
                        <option value="year">Year</option>
                    </select>
                    <label class="mb-0 mr-2 small font-weight-bold text-gray-600" for="scheduleCategory">Category</label>
                    <select id="scheduleCategory" class="form-control form-control-sm" style="width:auto;">
                        <option value="all" selected>Show All</option>
                        <option value="joborder">Job Order Request</option>
                        <option value="vehicle">Vehicle Booking</option>
                        <option value="dtc">DTC Booking</option>
                    </select>
                </div>
            </div>
            <div class="card-body">
                <p class="mb-3 font-weight-bold text-primary" id="scheduleChartSummary">Total schedules: loading...</p>
                <div class="chart-bar" style="height:20rem;">
                    <canvas id="scheduleBarChart"></canvas>
                </div>
                <p class="mb-0 text-danger small" id="scheduleChartError" style="display:none;"></p>
            </div>
        </div>
    </div>
</div>

<script src="<?= uri() ?>/assets/vendor/chart.js/Chart.min.js?v=<?= VERSION ?>"></script>
<script>
(function () {
    var canvas = document.getElementById('scheduleBarChart');
    var summaryEl = document.getElementById('scheduleChartSummary');
    var errorEl = document.getElementById('scheduleChartError');
    var periodEl = document.getElementById('schedulePeriod');
    var categoryEl = document.getElementById('scheduleCategory');

    if (!canvas || !summaryEl || !errorEl || !periodEl || !categoryEl) {
        return;
    }

    if (typeof Chart === 'undefined') {
        summaryEl.textContent = 'Total schedules: unavailable';
        errorEl.textContent = 'Bar chart failed to initialize: Chart.js did not load.';
        errorEl.style.display = 'block';
        return;
    }

    var CATEGORY_META = {
        dtc:      { label: 'DTC Booking', color: '#4e73df' },
        vehicle:  { label: 'Vehicle Booking', color: '#fd7e14' },
        joborder: { label: 'Job Order Request', color: '#f6c23e' }
    };
    var CATEGORY_ORDER = ['dtc', 'vehicle', 'joborder'];

    var chart = new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: { labels: [], datasets: [] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: { display: true, position: 'top' },
            tooltips: {
                mode: 'index',
                intersect: false,
                backgroundColor: 'rgb(255,255,255)',
                titleFontColor: '#6e707e',
                bodyFontColor: '#858796',
                borderColor: '#dddfeb',
                borderWidth: 1
            },
            scales: {
                xAxes: [{
                    stacked: true,
                    gridLines: { display: false, drawBorder: false }
                }],
                yAxes: [{
                    stacked: true,
                    beginAtZero: true,
                    ticks: { beginAtZero: true, precision: 0 },
                    gridLines: { color: '#e3e6f0' }
                }]
            }
        }
    });

    function buildDatasets(payload) {
        var selected = categoryEl.value || 'all';
        var keys = selected === 'all'
            ? CATEGORY_ORDER
            : CATEGORY_ORDER.filter(function (key) { return key === selected; });

        return keys.map(function (key) {
            return {
                label: CATEGORY_META[key].label,
                backgroundColor: CATEGORY_META[key].color,
                borderColor: CATEGORY_META[key].color,
                data: (payload.buckets || []).map(function (bucket) {
                    return Number(bucket[key]) || 0;
                })
            };
        });
    }

    function render(payload) {
        chart.data.labels = (payload.buckets || []).map(function (bucket) {
            return bucket.label;
        });
        chart.data.datasets = buildDatasets(payload);
        chart.update();

        var selected = categoryEl.value || 'all';
        var periodText = periodEl.options[periodEl.selectedIndex].text;
        var total = selected === 'all'
            ? Number(payload.totals && payload.totals.total) || 0
            : Number(payload.totals && payload.totals[selected]) || 0;
        var categoryText = selected === 'all'
            ? 'Show All'
            : CATEGORY_META[selected].label;

        summaryEl.textContent = 'Total schedules: ' + total +
            ' (' + periodText + ' \u00b7 ' + categoryText + ')';
        errorEl.style.display = 'none';
    }

    function fail(message) {
        summaryEl.textContent = 'Total schedules: unavailable';
        errorEl.textContent = message;
        errorEl.style.display = 'block';
    }

    function load() {
        summaryEl.textContent = 'Total schedules: loading...';
        var url = '<?= uri() ?>/bsa/api/schedule-stats.php?period=' +
            encodeURIComponent(periodEl.value) + '&_=' + Date.now();

        fetch(url, { credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Request failed with status ' + response.status + '.');
                }
                return response.json();
            })
            .then(function (payload) {
                if (!payload || payload.success !== true) {
                    throw new Error((payload && payload.message) || 'Unable to load schedule counts.');
                }
                render(payload);
            })
            .catch(function (err) {
                fail(err && err.message ? err.message : 'Unable to load schedule counts.');
            });
    }

    periodEl.addEventListener('change', load);
    categoryEl.addEventListener('change', load);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', load);
    } else {
        load();
    }
})();
</script>
