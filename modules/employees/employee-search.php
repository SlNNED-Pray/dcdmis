<?php
// modules/employees/employee-search.php
if (!$isHrmis) {
    require_once(root() . '/modules/error/403.php');
    return;
}

$search = isset($_GET['id']) ? sanitize(decode($_GET['id'])) : null;
$employees = employeeSearch($search);

messageAlert($showAlert, $message, $success);
$isHrmis = $activeApp === 'hrmis';

if (!$employees) {
    require_once(root() . '/modules/error/no-results-found.php');
    return;
}
?>

<div class="card border-left-primary shadow mb-4">
    <div class="card-header py-3">
        <?php contentTitleWithLink("Employee Search : \"{$search}\"", uri() . '/hrmis') ?>
    </div>
    <div class="card-body">
        <?php require(root() . '/modules/employees/employee-filter-bar.php'); ?>

        <div class="table-responsive">
            <table class="table table-hover mb-0 text-center" id="data-table" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th class="align-middle" width="5%">Photo</th>
                        <th class="align-middle" width="5%">Employee Number</th>
                        <th class="align-middle" width="20%">Name</th>
                        <th class="align-middle" width="10%">Status</th>
                        <th class="align-middle" width="15%">Date of Birth</th>
                        <th class="align-middle" width="5%">Age</th>
                        <th class="align-middle" width="15%">Position</th>
                        <th class="align-middle" width="20%">Station</th>
                        <th class="align-middle" width="5%">Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    foreach ($employees as $row):
                        $employeeName = toName($row['last_name'], $row['first_name'], $row['middle_name'], $row['name_extension']);
                        $photo = file_exists(root() . '/' . $row['profile_picture']) ? uri() . '/' . $row['profile_picture'] : uri() . '/assets/img/user.png';
                        ?>
                        <tr class="text-uppercase" data-gender="<?= e($row['sex']) ?>"
                            data-position-id="<?= e($row['position_id']) ?>" data-station-id="<?= e($row['station_id']) ?>">
                            <td class="align-middle">
                                <div class="image-container">
                                    <span
                                        class="d-flex justify-content-center align-middle employee-photo rounded-circle overflow-hidden">
                                        <img height="100%" src="<?= e($photo) ?>" alt="<?= e($employeeName) ?>">
                                    </span>
                                    <div class="sex-sign"><?php sex($row['sex']) ?></div>
                                </div>
                            </td>
                            <td class="align-middle"><?= toHandleNull($row['agency_id'], 'N/A') ?></td>
                            <td class="align-middle text-left">
                                <?php if ($isHrmis) {
                                    linkItem(customUri('hrmis', 'Employee Information', $row['id']), $employeeName);
                                } else {
                                    echo $employeeName;
                                } ?>
                            </td>
                            <td class="align-middle">
                                <?php
                                $status = strtolower($row['status']);
                                roundPill($status);
                                ?>
                            </td>
                            <td class="align-middle">
                                <?= toDate($row['birthdate'], 'F j, Y') ?>
                            </td>
                            <td class="align-middle">


                                <?= getDateDifference($row['birthdate']) ?>
                            </td>
                            <td class="align-middle"><?= positions($row['position_id'])['official_title'] ?></td>
                            <td class="align-middle">
                                <?php linkItem(customUri($activeApp, 'School Information', $row['station_id']), schoolById($row['station_id'])['name']) ?>
                            </td>
                            <td class="align-middle text-capitalize">
                                <?php if ($status !== 'duplicate'): ?>
                                    <div class="dropdown no-arrow">
                                        <?php dropdownEllipsis() ?>
                                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in">
                                            <?php
                                            linkDropdownItem(customUri('hrmis', 'Employee Information', $row['id']), 'Employee Information', 'fa-user', 'Employee Information');
                                            linkDropdownItem(customUri('hrmis', 'Service Record', $row['id']), 'Service Record', 'fa-file-alt', 'Service Record');
                                            linkDropdownItem(customUri('hrmis', '201 Files', $row['id']), '201 Files', 'fa-folder-open', '201 Files');
                                            linkDropdownItem(customUri('hrmis', 'Trainings', $row['id']), 'Trainings', 'fa-chalkboard-teacher', 'Trainings'); ?>
                                            <div class="dropdown-divider"></div>
                                            <?php linkDropdownItem(customUri('hrmis', 'Edit History', $row['id']), 'Edit History', 'fa-history', 'Edit History') ?>
                                            <?php if ($status === 'active'): ?>
                                                <div class="dropdown-divider"></div>
                                                <?php modalDropdownItem(uri() . '/modules/employees/reassign-employee-dialog.php?id=' . cipher($row['id']), 'Reassign', 'fa-share', 'Reassign Employee');
                                                if ($isPersonnel || $isICT) {
                                                    modalDropdownItem(uri() . '/modules/employees/promote-employee-dialog.php?id=' . cipher($row['id']), 'Promote', 'fa-thumbs-up', 'Promote Employee');
                                                    modalDropdownItem(uri() . '/modules/employees/remove-employee-dialog.php?id=' . cipher($row['id']), 'Remove', 'fa-trash', 'Remove Employee');
                                                }
                                            endif ?>
                                        </div>
                                    </div>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>

                <tfoot>
                    <tr>
                        <th class="align-middle" width="5%">Photo</th>
                        <th class="align-middle" width="5%">Employee Number</th>
                        <th class="align-middle" width="20%">Name</th>
                        <th class="align-mdille" width="10%">Status</th>
                        <th class="align-middle" width="15%">Date of Birth</th>
                        <th class="align-middle" width="5%">Age</th>
                        <th class="align-middle" width="15%">Position</th>
                        <th class="align-middle" width="20%">Station</th>
                        <th class="align-middle" width="5%">Action</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>