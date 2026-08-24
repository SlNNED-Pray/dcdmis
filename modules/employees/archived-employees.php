<?php
// modules/employees/active-employees.php
if (!$isHrmis && !$isDmis) {
    require_once(root() . '/modules/error/403.php');
    return;
}

messageAlert($showAlert, $message, $success);
?>

<div class="d-flex align-items-center justify-content-between flex-row mt-2 mb-3">
    <nav class="d-flex align-items-center flex-row m-0">
        <ol class="breadcrumb m-0 p-0 bg-transparent">
            <li class="breadcrumb-item"><a href="<?= uri() . '/' . $activeApp ?>">Dashboard</a></li>
            <?php if ($isDmis): ?>
                <li class="breadcrumb-item active"><a href="<?= customUri($activeApp, 'Employees') ?>">Employees</a></li>
            <?php endif ?>
            <li class="breadcrumb-item active">Archived</li>
        </ol>
    </nav>

    <?php if ($isHrmis): ?>
        <div class="d-inline-block">
            <?php modalButtonSplit(uri() . '/modules/employees/save-employee-dialog.php', 'Add Employee', 'fa-user-plus') ?>
        </div>
    <?php endif ?>
</div>

<div class="card border-left-primary shadow mb-4">
    <div class="card-header py-3">
        <?php contentTitleWithLink('Archived Employees', uri() . '/hrmis') ?>
    </div>

    <div class="card-body">
        <?php require(root() . '/modules/employees/employee-filter-bar.php'); ?>

        <div class="table-responsive">
            <table class="table table-hover mb-0 text-center" id="data-table" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th class="align-middle" width="5%">Photo</th>
                        <th class="align-middle" width="20%">Name</th>
                        <th class="align-mdille" width="10%">Status</th>
                        <th class="align-middle" width="15%">Date of Birth</th>
                        <th class="align-middle" width="15%">Position</th>
                        <th class="align-middle" width="20%">Station</th>
                        <th class="align-middle" width="10%">Progress</th>
                        <th class="align-middle" width="5%">Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    $query = archivedEmployees();
                    foreach ($query as $row):
                        $employeeName = toName($row['last_name'], $row['first_name'], $row['middle_name'], $row['name_extension']);
                        $photo = file_exists(root() . '/' . $row['profile_picture']) ? "{$baseUri}/" . $row['profile_picture'] : "{$baseUri}/assets/img/user.png";
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
                            <td class="align-middle text-left">
                                <?php linkItem(customUri('hrmis', 'Employee Information', $row['id']), $employeeName) ?>
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
                            <td class="align-middle"><?= positions($row['position_id'])['official_title'] ?></td>
                            <td class="align-middle">
                                <?php
                                $stationName = stationName($row['station_id']);

                                linkItem(customUri($activeApp, 'School Information', $row['station_id']), $stationName) ?>
                            </td>
                            <td class="align-middle"><?php progressBar(pdsProgress($row['id'])) ?></td>
                            <td class="align-middle text-capitalize">
                                <?php if ($isHrmis && $status !== 'duplicate'): ?>
                                    <div class="dropdown no-arrow">
                                        <?php dropdownEllipsis() ?>
                                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in">
                                            <?php linkDropdownItem(customUri('hrmis', 'Employee Information', $row['id']), 'Employee Information', 'fa-user', 'Employee Information');
                                            linkDropdownItem(customUri('hrmis', 'Service Record', $row['id']), 'Service Record', 'fa-file-alt', 'Service Record');
                                            linkDropdownItem(customUri('hrmis', '201 Files', $row['id']), '201 Files', 'fa-folder-open', '201 Files');
                                            linkDropdownItem(customUri('hrmis', 'Trainings', $row['id']), 'Trainings', 'fa-chalkboard-teacher', 'Trainings'); ?>
                                            <div class="dropdown-divider"></div>
                                            <?php
                                            linkDropdownItem(customUri('hrmis', 'Edit History', $row['id']), 'Edit History', 'fa-history', 'Edit History');

                                            if ($isPersonnel) {
                                                switch ($status) {
                                                    case 'resigned':
                                                    case 'transferred':
                                                    case 'dismissed':
                                                    case 'suspended': ?>
                                                        <div class="dropdown-divider"></div>
                                                        <?php
                                                        modalDropdownItem(uri() . '/modules/employees/reassign-employee-dialog.php?id=' . cipher($row['id']), 'Reassign', 'fa-share', 'Reassign Employee');
                                                        break;
                                                    default:
                                                        break;
                                                }
                                            }
                                            ?>
                                        </div>
                                    </div>
                                <?php endif;

                                if ($isDmis && $status === 'duplicate'): ?>
                                    <div class="dropdown no-arrow">
                                        <?php dropdownEllipsis() ?>
                                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in">
                                            <?php modalDropdownItem(uri() . '/modules/employees/delete-employee-dialog.php?id=' . cipher($row['id']), 'Delete', 'fa-trash', 'Delete Employee') ?>
                                        </div>
                                    <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>

                <tfoot>
                    <tr>
                        <th class="align-middle" width="5%">Photo</th>
                        <th class="align-middle" width="20%">Name</th>
                        <th class="align-mdille" width="10%">Status</th>
                        <th class="align-middle" width="15%">Date of Birth</th>
                        <th class="align-middle" width="15%">Position</th>
                        <th class="align-middle" width="20%">Station</th>
                        <th class="align-middle" width="10%">Progress</th>
                        <th class="align-middle" width="5%">Action</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>