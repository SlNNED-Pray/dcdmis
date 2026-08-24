<?php
// modules/employees/non-regular-employees.php
if (!$isHrmis && !$isHrtdms && !$isDmis) {
    require_once(root() . '/modules/error/403.php');
    return;
}

$schoolId = isset($_GET['id']) ? sanitize(decode($_GET['id'])) : null;
$school = schoolById($schoolId);
$schoolName = $alias = $address = $district = $head = $telephone = $email = $website = $fbPage = null;
$personnel = 0;

messageAlert($showAlert, $message, $success);
$selectedType = isset($_GET['type']) ? sanitize($_GET['type']) : '';
?>

<div class="d-flex align-items-center justify-content-between flex-row mt-2 mb-3">
    <nav class="d-flex align-items-center flex-row m-0">
        <ol class="breadcrumb m-0 p-0 bg-transparent">
            <li class="breadcrumb-item"><a href="<?= uri() . '/' . $activeApp ?>">Dashboard</a></li>
            <li class="breadcrumb-item active">Non-Regular Employees</li>
        </ol>
    </nav>

    <?php if ($isHrmis): ?>
        <div class="d-inline-block">
            <?php
            $modalStationParam = isset($_GET['s']) ? '?s=' . e($_GET['s']) : (isset($_GET['station']) ? '?station=' . e($_GET['station']) : '');
            modalButtonSplit(uri() . '/modules/employees/save-non-regular-employee-dialog.php' . $modalStationParam, 'Add', 'fa-user-plus');
            ?>
        </div>
    <?php endif ?>
</div>

<div class="card border-left-primary shadow mb-4">
    <div class="card-header py-3 d-flex align-items-center justify-content-between">
        <?php contentTitle('Non-Regular Employees') ?>
    </div>

    <div class="card-body">
        <?php require(root() . '/modules/employees/employee-filter-bar.php'); ?>

        <div class="table-responsive">
            <table class="table table-hover mb-0 text-center" id="data-table" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th class="align-middle" width="5%">Photo</th>
                        <th class="align-middle" width="35%">Name / Category</th>
                        <th class="align-middle" width="10%">Status</th>
                        <th class="align-middle" width="20%">Position / Fund Source</th>
                        <th class="align-middle" width="30%">Station</th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    $nonRegulars = nonRegularEmployees($selectedType);
                    foreach ($nonRegulars as $row):
                        $employeeName = toName($row['last_name'], $row['first_name'], $row['middle_name'], $row['name_extension']);
                        $photo = file_exists(root() . '/' . $row['profile_picture']) ? uri() . '/' . $row['profile_picture'] : uri() . '/assets/img/user.png';
                        $posData = positions($row['position_id']);
                        $pos = $posData['official_title'] ?? null;
                        $stnData = schoolById($row['station_id']);
                        $stn = $stnData['name'] ?? null;
                        ?>
                        <tr class="text-uppercase" data-gender="<?= e($row['sex']) ?>"
                            data-position-id="<?= e($row['position_id']) ?>" data-station-id="<?= e($row['station_id']) ?>">
                            <td class="align-middle">
                                <div class="image-container">
                                    <span
                                        class="d-flex justify-content-center align-middle employee-photo rounded-circle overflow-hidden">
                                        <img height="100%" src="<?= e($photo) ?>">
                                    </span>
                                    <div class="sex-sign"><?php sex($row['sex']) ?>
                                    </div>
                                </div>
                            </td>
                            <td class="align-middle text-left font-weight-bold">
                                <div><?= e(strtoupper($employeeName)) ?></div>
                                <span class="small">
                                    <?= e($row['employment_type']) ?>
                                </span>
                            </td>
                            <td class="align-middle">
                                <?php
                                $status = strtolower($row['status']);
                                roundPill($status);
                                ?>
                            </td>
                            <td class="align-middle text-uppercase">
                                <div>
                                    <?= e($pos ?: 'N/A') ?>
                                </div>
                                <span class="small"><?= e($row['fund_source'] ?: 'N/A') ?>
                                </span>
                            </td>
                            <td class="align-middle text-uppercase">
                                <?= e($stn ?: 'N/A') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>

                <tfoot>
                    <tr class="small">
                        <th class="align-middle" width="5%">Photo</th>
                        <th class="align-middle" width="35%">Name / Category</th>
                        <th class="align-middle" width="10%">Status</th>
                        <th class="align-middle" width="20%">Position / Fund Source</th>
                        <th class="align-middle" width="30%">Station</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>