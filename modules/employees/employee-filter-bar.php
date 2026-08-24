<?php
// includes/employee-filter-bar.php
if (!function_exists('positions')) {
    require_once(root() . '/includes/database/position.php');
}

if (!function_exists('districts')) {
    require_once(root() . '/includes/database/school.php');
}

$allDistricts = districts();
$selectedGender = isset($_GET['gender']) ? sanitize($_GET['gender']) : '';
$selectedPosition = isset($_GET['position']) ? sanitize($_GET['position']) : '';
$selectedStation = isset($_GET['station']) ? sanitize($_GET['station']) : '';
?>

<div class="mb-2">
    <form id="employee-filter-form" class="mb-0" onsubmit="return false;">
        <div class="font-weight-bold">
            <i class="fas fa-filter mr-1"></i> Filter by:
        </div>

        <div class="row my-1">
            <div class="col-12 col-md-4 col-lg-2 form-group">
                <label for="filter-gender" class="mb-0 small">Gender</label>
                <select id="filter-gender" name="gender" class="form-control employee-filter">
                    <option value="">All</option>
                    <option value="Male" <?= ($selectedGender === 'Male') ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= ($selectedGender === 'Female') ? 'selected' : '' ?>>Female</option>
                </select>
            </div>

            <div class="col-12 col-md-8 col-lg-5 form-group">
                <label for="filter-position" class="mb-0 small">Position</label>
                <select id="filter-position" name="position" class="form-control employee-filter">
                    <option value="">All</option>
                    <?php
                    $positionCategories = positionCategories();
                    if (is_array($positionCategories)):
                        foreach ($positionCategories as $cat):
                            $catName = $cat['category'] ?? '';
                            $catPositions = positionsByCategory($catName);
                            if (empty($catPositions))
                                continue; ?>
                            <optgroup label="<?= e($catName) ?>">
                                <?php foreach ($catPositions as $pos): ?>
                                    <option value="<?= e($pos['id']) ?>" <?= ($selectedPosition == $pos['id']) ? 'selected' : '' ?>>
                                        <?= e($pos['official_title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="col-12 col-md-8 col-lg-5 form-group">
                <label for="filter-station" class="mb-0 small">Station</label>
                <select id="filter-station" name="station" class="form-control employee-filter">
                    <option value="">All</option>
                    <?php if (is_array($allDistricts)): ?>
                        <?php foreach ($allDistricts as $dist):
                            $districtSchools = schoolsByDistrict($dist['id']);
                            if (empty($districtSchools))
                                continue;
                            ?>
                            <optgroup label="<?= e($dist['name']) ?>">
                                <?php foreach ($districtSchools as $stn): ?>
                                    <option value="<?= e($stn['id']) ?>" <?= ($selectedStation == $stn['id']) ? 'selected' : '' ?>>
                                        <?= e($stn['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>
    </form>
</div>

<script>
    (function () {
        function initEmployeeFilter() {
            if (typeof jQuery === 'undefined') {
                return;
            }

            var $ = jQuery;

            if (!window.employeeFilterRegistered && typeof $.fn.dataTable !== 'undefined') {
                window.employeeFilterRegistered = true;

                $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
                    var rowNode = settings.aoData[dataIndex] ? settings.aoData[dataIndex].nTr : null;
                    if (!rowNode) return true;

                    var filterGender = ($('#filter-gender').val() || '').toString().toLowerCase().trim();
                    var filterPosId = ($('#filter-position').val() || '').toString().trim();
                    var filterPosText = ($('#filter-position option:selected').text() || '').toString().toLowerCase().trim();
                    if ($('#filter-position').val() === '') filterPosText = '';

                    var filterStnId = ($('#filter-station').val() || '').toString().trim();
                    var filterStnText = ($('#filter-station option:selected').text() || '').toString().toLowerCase().trim();
                    if ($('#filter-station').val() === '') filterStnText = '';

                    if (!filterGender && !filterPosId && !filterStnId) {
                        return true;
                    }

                    var $row = $(rowNode);
                    var rowGender = ($row.attr('data-gender') || $row.data('gender') || '').toString().toLowerCase().trim();
                    var rowPosId = ($row.attr('data-position-id') || $row.data('position-id') || '').toString().trim();
                    var rowStnId = ($row.attr('data-station-id') || $row.data('station-id') || '').toString().trim();
                    var rowText = $row.text().toLowerCase();

                    if (filterGender !== '') {
                        var matchGender = (rowGender !== '') ? (rowGender === filterGender) : (rowText.indexOf(filterGender) !== -1);
                        if (!matchGender) return false;
                    }

                    if (filterPosId !== '') {
                        var matchPos = false;
                        if (rowPosId !== '') {
                            matchPos = (rowPosId.toLowerCase() === filterPosId.toLowerCase());
                        } else if (filterPosText !== '') {
                            matchPos = (rowText.indexOf(filterPosText) !== -1);
                        } else {
                            matchPos = (rowText.indexOf(filterPosId.toLowerCase()) !== -1);
                        }
                        if (!matchPos) return false;
                    }

                    if (filterStnId !== '') {
                        var matchStn = false;
                        if (rowStnId !== '') {
                            matchStn = (rowStnId.toLowerCase() === filterStnId.toLowerCase());
                        } else if (filterStnText !== '') {
                            matchStn = (rowText.indexOf(filterStnText) !== -1);
                        } else {
                            matchStn = (rowText.indexOf(filterStnId.toLowerCase()) !== -1);
                        }
                        if (!matchStn) return false;
                    }

                    return true;
                });
            }

            function triggerFilterDraw() {
                if (typeof $.fn.dataTable !== 'undefined') {
                    $.fn.dataTable.tables({ api: true }).draw();
                }
            }

            $(document).off('change.empFilter', '.employee-filter').on('change.empFilter', '.employee-filter', function () {
                triggerFilterDraw();
            });

            if ($('#filter-gender').val() || $('#filter-position').val() || $('#filter-station').val()) {
                setTimeout(function () {
                    triggerFilterDraw();
                }, 100);
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initEmployeeFilter);
        } else {
            initEmployeeFilter();
        }
    })();
</script>