<?php
// modules/overview/lnd.php
$userPositionId = null;
if ($isPis) {
    if (!function_exists('position')) {
        require_once(root() . '/includes/database/position.php');
    }
    $userPosition = position($userId);
    $userPositionId = $userPosition['position_id'] ?? null;
}
$isAllowedHigherPosition = $isPis && (in_array($userPositionId, $allowedMonitoringPositions, true) || $isICT);

if (!$isHrtdms && !$isAllowedHigherPosition) {
    require_once(root() . '/modules/error/403.php');
    return;
}

require_once(root() . '/includes/database/learning-development.php');

$countScheduled = number_format(count(scheduledTrainings()));
?>

<div class="d-flex align-items-center justify-content-between flex-row mt-2 mb-3">
    <nav class="d-flex align-items-center flex-row m-0">
        <ol class="breadcrumb m-0 p-0 bg-transparent">
            <li class="breadcrumb-item"><a href="<?= "{$baseUri}/{$activeApp}" ?>">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= customUri('pis', 'System Overview') ?>">System Overview</a></li>
            <li class="breadcrumb-item active">Learning and Development</li>
        </ol>
    </nav>
</div>

<div class="row mt-4">
    <?php
    card('Scheduled Trainings', customUri('pis', 'Scheduled Trainings'), 'fa-calendar', 'primary', $countScheduled);
    card('Conducted Trainings', customUri('pis', 'Conducted Trainings'), 'fa-chalkboard-teacher', 'success');
    card('Programs', customUri('pis', 'Programs'), 'fa-cogs', 'info');
    ?>
</div>

<hr class="mt-0">

<script src="<?= uri() ?>/assets/vendor/chart.js/Chart.min.js"></script>
<script src="<?= uri() ?>/assets/vendor/chart.js/chartjs-plugin-datalabels.min.js"></script>
<script src="<?= uri() ?>/assets/js/chart-custom.js?v=<?= VERSION ?>"></script>

<div class="row">
    <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary text-uppercase">Conducted Trainings Per Year</h6>
            </div>
            <div class="card-body">
                <div class="chart-bar h-auto">
                    <canvas id="conducted-trainings-bar-chart"></canvas>
                    <script>
                        <?php
                        $conductedTrainingByYear = array_map(function ($item) {
                            $item['link'] = customUri('pis', 'Conducted Trainings') . '&from=' . $item['name'] . '-01-01&to=' . $item['name'] . '-12-31';
                            return $item;
                        }, conductedTrainingsByYear(10));
                        ?>
                        generateBarChart(<?= json_encode($conductedTrainingByYear) ?>, generateColorPallete(<?= count($conductedTrainingByYear) ?>), 'conducted-trainings-bar-chart');
                    </script>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary text-uppercase">Trained Employees Per Year</h6>
            </div>
            <div class="card-body">
                <div class="chart-bar h-auto">
                    <canvas id="trained-employees-bar-chart"></canvas>
                    <script>
                        <?php
                        $trainedEmployeesByYear = array_map(function ($item) {
                            $item['link'] = customUri('pis', 'Trained Employees') . '&year=' . $item['name'];
                            return $item;
                        }, trainedEmployeesByYear());
                        ?>
                        generateBarChart(<?= json_encode($trainedEmployeesByYear) ?>, generateColorPallete(<?= count($trainedEmployeesByYear) ?>), 'trained-employees-bar-chart');
                    </script>
                </div>
            </div>
        </div>
    </div>
</div>