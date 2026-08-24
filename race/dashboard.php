<?php
// race/dashboard.php
$nominatorOnly = isNominatorOnly($userId);
$countSchedules = number_format(count(awardSchedules()));
$countNominees = number_format(count(allNominees()));
$countAwards = number_format(count(recognitionAwardsWithCategory()));
$countWinners = number_format(count(awardWinners()));
$countRankAwards = number_format(count(awardsWithNominees()));

messageAlert($showAlert, $message, $success);
contentTitleWithModal('Dashboard', uri() . '/modules/race/nominate-dialog.php', 'Nominate', 'fa-user-plus');
?>

<div class="row mt-4">
    <?php
    if ($isICT || !$nominatorOnly):
        card('Schedule', customUri('race', 'Event Schedules'), 'fa-calendar-plus', 'primary', $countSchedules);
    endif;
    card('Nominees', customUri('race', 'Nominees List'), 'fa-user', 'warning', $countNominees);
    card('Awards', customUri('race', 'Awards List'), 'fa-award', 'danger', $countAwards);
    if ($isICT || !$nominatorOnly):
        card('Ranking', customUri('race', 'Ranking'), 'fa-chart-bar', 'info', $countRankAwards);
        card('Winners', customUri('race', 'Winners Lookup'), 'fa-trophy', 'success', $countWinners);
    endif;
    ?>
</div>

<?php if ($isICT || !$nominatorOnly): ?>
    <?php
    $analyticsYear = isset($_GET['an_year']) ? sanitize($_GET['an_year']) : '';
    $analyticsCategoryId = isset($_GET['an_category']) ? sanitize($_GET['an_category']) : '';
    $analyticsStationId = isset($_GET['an_school']) ? sanitize($_GET['an_school']) : '';
    $analyticsEmployeeName = isset($_GET['an_employee']) ? sanitize($_GET['an_employee']) : '';

    $analyticsYears = raceAwardYears();
    $analyticsCategories = recognitionCategories();
    $analyticsSchools = schools();

    $byYear = raceAwardsByYear($analyticsCategoryId ?: null, $analyticsStationId ?: null, $analyticsEmployeeName ?: null);
    $bySex = raceAwardeesBySex($analyticsYear ?: null, $analyticsCategoryId ?: null, $analyticsStationId ?: null, $analyticsEmployeeName ?: null);
    $byGeneration = raceAwardeesByGeneration($analyticsYear ?: null, $analyticsCategoryId ?: null, $analyticsStationId ?: null, $analyticsEmployeeName ?: null);
    $byCategory = raceAwardsByCategory($analyticsYear ?: null, $analyticsStationId ?: null, $analyticsEmployeeName ?: null);
    $topAwardees = raceTopAwardees($analyticsYear ?: null, $analyticsCategoryId ?: null, $analyticsStationId ?: null, $analyticsEmployeeName ?: null, 10);

    $totalAwardees = 0;
    foreach ($bySex as $row) {
        $totalAwardees += (int) $row['count'];
    }
    ?>

    <div class="card shadow mt-4 mb-4">
        <div class="card-header py-3 d-flex align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-dark text-uppercase">
                <i class="fas fa-chart-pie fa-fw mr-1"></i> Rewards and Recognition Analytics
            </h6>
        </div>
        <div class="card-body">
            <form action="" method="GET" class="row align-items-end mb-4">
                <input type="hidden" name="v" value="<?= isset($_GET['v']) ? e($_GET['v']) : '' ?>">
                <input type="hidden" name="id" value="<?= isset($_GET['id']) ? e($_GET['id']) : '' ?>">

                <div class="col-md-3 mb-2 mb-md-0">
                    <label class="small font-weight-bold text-uppercase text-dark mb-1">School</label>
                    <select name="an_school" class="form-control form-control-sm">
                        <option value="">All Schools</option>
                        <?php foreach ($analyticsSchools as $sch): ?>
                            <option value="<?= e($sch['id']) ?>" <?= setOptionSelected($sch['id'], $analyticsStationId) ?>>
                                <?= e($sch['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3 mb-2 mb-md-0">
                    <label class="small font-weight-bold text-uppercase text-dark mb-1">Category</label>
                    <select name="an_category" class="form-control form-control-sm">
                        <option value="">All Categories</option>
                        <?php foreach ($analyticsCategories as $cat): ?>
                            <option value="<?= e($cat['id']) ?>" <?= setOptionSelected($cat['id'], $analyticsCategoryId) ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2 mb-2 mb-md-0">
                    <label class="small font-weight-bold text-uppercase text-dark mb-1">Year</label>
                    <select name="an_year" class="form-control form-control-sm">
                        <option value="">All Years</option>
                        <?php foreach ($analyticsYears as $yr): ?>
                            <option value="<?= e($yr['year']) ?>" <?= setOptionSelected($yr['year'], $analyticsYear) ?>>
                                <?= e($yr['year']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3 mb-2 mb-md-0">
                    <label class="small font-weight-bold text-uppercase text-dark mb-1">Employee Name</label>
                    <input type="text" name="an_employee" class="form-control form-control-sm" placeholder="Search employee..."
                        value="<?= e($analyticsEmployeeName) ?>">
                </div>

                <div class="col-md-1">
                    <div class="d-flex">
                        <button class="btn btn-primary btn-sm mr-1" type="submit" title="Filter">
                            <i class="fas fa-filter"></i>
                        </button>
                        <a href="<?= uri() . '/' . $activeApp ?>" class="btn btn-secondary btn-sm" title="Reset Filters">
                            <i class="fas fa-redo"></i>
                        </a>
                    </div>
                </div>
            </form>

            <div class="row">
                <div class="col-xl-4 col-lg-6 mb-4">
                    <div class="card border h-100">
                        <div class="card-header py-2 bg-light">
                            <h6 class="m-0 font-weight-bold text-dark small text-uppercase">No. of Awards Given by Year</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-bar h-auto">
                                <canvas id="chart-by-year"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 col-lg-6 mb-4">
                    <div class="card border h-100">
                        <div class="card-header py-2 bg-light">
                            <h6 class="m-0 font-weight-bold text-dark small text-uppercase">No. of Awardees by Sex</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-pie py-2">
                                <canvas id="chart-by-sex"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 col-lg-6 mb-4">
                    <div class="card border h-100">
                        <div class="card-header py-2 bg-light">
                            <h6 class="m-0 font-weight-bold text-dark small text-uppercase">No. of Awardees by Generational Label</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-bar h-auto">
                                <canvas id="chart-by-generation"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-5 col-lg-12 mb-4">
                    <div class="card border h-100">
                        <div class="card-header py-2 bg-light">
                            <h6 class="m-0 font-weight-bold text-dark small text-uppercase">No. of Awards Given by Category</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-bar h-auto">
                                <canvas id="chart-by-category"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-7 col-lg-12 mb-4">
                    <div class="card border h-100">
                        <div class="card-header py-2 bg-light">
                            <h6 class="m-0 font-weight-bold text-dark small text-uppercase">Top Awardees</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 text-center" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th class="align-middle">Employee Name</th>
                                            <th class="align-middle">Sex</th>
                                            <th class="align-middle">Generational Label</th>
                                            <th class="align-middle">School</th>
                                            <th class="align-middle">No. of Awards</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($topAwardees)): ?>
                                            <?php foreach ($topAwardees as $ta): ?>
                                                <tr>
                                                    <td class="align-middle text-left text-uppercase font-weight-bold">
                                                        <?= e(toName($ta['last_name'], $ta['first_name'], $ta['middle_name'], $ta['name_extension'])) ?>
                                                    </td>
                                                    <td class="align-middle"><?= e($ta['sex']) ?></td>
                                                    <td class="align-middle small"><?= e(raceGenerationLabel($ta['birthdate'])) ?></td>
                                                    <td class="align-middle small text-uppercase"><?= e($ta['school_name'] ?: '—') ?></td>
                                                    <td class="align-middle">
                                                        <span class="badge badge-pill badge-success font-weight-bold px-2 py-1">
                                                            <?= e($ta['award_count']) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-4">No awardees found for the selected filters.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= uri() ?>/assets/vendor/chart.js/Chart.min.js"></script>
    <script src="<?= uri() ?>/assets/vendor/chart.js/chartjs-plugin-datalabels.min.js"></script>
    <script src="<?= uri() ?>/assets/js/chart-custom.js?v=1.3"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const byYear = <?= json_encode($byYear) ?>;
            const bySex = <?= json_encode($bySex) ?>;
            const byGeneration = <?= json_encode($byGeneration) ?>;
            const byCategory = <?= json_encode($byCategory) ?>;

            const yearData = byYear.map(item => ({ name: String(item.name || 'N/A'), count: parseInt(item.count || 0) }));
            generateBarChart(yearData, generateColorPallete(yearData.length), 'chart-by-year', false);

            const sexData = bySex.map(item => ({ name: item.name || 'Not Specified', count: parseInt(item.count || 0) }));
            const sexColors = sexData.map(item => {
                const n = item.name.toLowerCase();
                if (n === 'male') return '#02a3fe';
                if (n === 'female') return '#ec49a6';
                return '#858796';
            });
            const totalAwardees = <?= json_encode((int)$totalAwardees) ?>;

            Chart.pluginService.register({
                afterDatasetsDraw: function(chart) {
                    if (chart.canvas && chart.canvas.id === 'chart-by-sex') {
                        var ctx = chart.ctx;
                        var centerX, centerY;

                        var meta = chart.getDatasetMeta(0);
                        if (meta && meta.data && meta.data.length > 0 && meta.data[0]._model) {
                            centerX = meta.data[0]._model.x;
                            centerY = meta.data[0]._model.y;
                        } else if (chart.chartArea) {
                            centerX = (chart.chartArea.left + chart.chartArea.right) / 2;
                            centerY = (chart.chartArea.top + chart.chartArea.bottom) / 2;
                        } else {
                            centerX = chart.width / 2;
                            centerY = chart.height / 2;
                        }

                        ctx.save();
                        ctx.font = "bold 1.5rem 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.fillStyle = '#5a5c69';
                        ctx.fillText(totalAwardees, centerX, centerY);
                        ctx.restore();
                    }
                }
            });

            generateDoughnutChart(sexData, sexColors, 'chart-by-sex', false);

            const genData = byGeneration.map(item => ({ name: item.name || 'Not Specified', count: parseInt(item.count || 0) }));
            generateBarChart(genData, generateColorPallete(genData.length), 'chart-by-generation', false);

            const catData = byCategory.map(item => ({ name: item.name || 'Uncategorized', count: parseInt(item.count || 0) }));
            generateBarChart(catData, generateColorPallete(catData.length), 'chart-by-category', false);
        });
    </script>
<?php endif; ?>