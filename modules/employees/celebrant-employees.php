<?php
// modules/employees/celebrant-employees.php
if (!$isHrmis) {
    require_once(root() . '/modules/error/403.php');
    return;
}

$now = date('Y-m-d');

messageAlert($showAlert, $message, $success);
?>

<div class="d-flex align-items-center justify-content-between flex-row mt-2 mb-3">
    <nav class="d-flex align-items-center flex-row m-0">
        <ol class="breadcrumb m-0 p-0 bg-transparent">
            <li class="breadcrumb-item"><a href="<?php echo uri() . '/' . $activeApp ?>">Dashboard</a></li>
            <li class="breadcrumb-item active">Celebrants</li>
        </ol>
    </nav>

    <div class="d-inline-block">
        <?php modalButtonSplit(uri() . '/modules/employees/save-employee-dialog.php', 'Add Employee', 'fa-user-plus') ?>
    </div>
</div>

<div class="card border-left-primary shadow mb-4">
    <div class="card-header py-3">
        <?php contentTitleWithLink('Celebrant Employees', uri() . '/hrmis') ?>
    </div>

    <div class="card-body">
        <?php require(root() . '/modules/employees/employee-filter-bar.php'); ?>

        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <a class="nav-link text-secondary" href="#previous-month"
                    data-toggle="tab"><?php echo date('F Y', strtotime("{$now} - 1 month")) ?></a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary active" href="#current-month"
                    data-toggle="tab"><?php echo date('F Y') ?></a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary" href="#next-month"
                    data-toggle="tab"><?php echo date('F Y', strtotime("{$now} + 1 month")) ?></a>
            </li>
        </ul>

        <div class="tab-content">
            <?php
            $months = 0;
            while ($months < 3) {
                switch ($months) {
                    case 0:
                        $datetimeString = "{$now} - 1 month";
                        $tabID = 'previous-month';
                        $table = 'data-table-previous';
                        break;
                    case 2:
                        $datetimeString = "{$now} + 1 month";
                        $tabID = 'next-month';
                        $table = 'data-table-next';
                        break;
                    default:
                        $datetimeString = $now;
                        $tabID = 'current-month';
                        $table = 'data-table';
                        break;
                }
                ?>
                <div class="tab-pane fade <?php echo setActiveItem($months, 1, 'show active') ?>" id="<?php echo $tabID ?>">
                    <?php $bmonth = date('m', strtotime($datetimeString)) ?>
                    <div class="row">
                        <div class="col table-responsive">
                            <table class="table table-hover mb-0 text-center" id="<?php echo $table ?>" width="100%"
                                cellspacing="0">
                                <thead>
                                    <tr>
                                        <th class="align-middle" width="5%">Photo</th>
                                        <th class="align-middle" width="25%">Name</th>
                                        <th class="align-middle" width="15%">Date of Birth</th>
                                        <th class="align-middle" width="5%">Age</th>
                                        <th class="align-middle" width="20%">Position</th>
                                        <th class="align-middle" width="25%">Station</th>
                                        <th class="align-middle" width="5%">Action</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php
                                    $query = celebrantEmployees($bmonth);
                                    foreach ($query as $row):
                                        $employeeName = toName($row['last_name'], $row['first_name'], $row['middle_name'], $row['name_extension']);
                                        $photo = file_exists(root() . '/' . $row['profile_picture']) ? uri() . '/' . $row['profile_picture'] : uri() . '/assets/img/user.png';
                                        ?>
                                        <tr class="text-uppercase" data-gender="<?= e($row['sex']) ?>"
                                            data-position-id="<?= e($row['position_id']) ?>"
                                            data-station-id="<?= e($row['station_id']) ?>">
                                            <td class="align-middle">
                                                <div class="image-container">
                                                    <span
                                                        class="d-flex justify-content-center align-middle employee-photo rounded-circle overflow-hidden">
                                                        <img height="100%" src="<?php echo $photo ?>"
                                                            alt="<?php echo $employeeName ?>">
                                                    </span>
                                                    <div class="sex-sign"><?php sex($row['sex']) ?></div>
                                                </div>
                                            </td>
                                            <td class="align-middle text-left">
                                                <?php linkItem(customUri('hrmis', 'Employee Information', $row['id']), $employeeName) ?>
                                            </td>
                                            <td class="align-middle">
                                                <?php echo toDate($row['birthdate'], 'F j, Y') ?>
                                            </td>
                                            <td class="align-middle">
                                                <?php echo getDateDifference($row['birthdate']) ?>
                                            </td>
                                            <td class="align-middle">
                                                <?php echo positions($row['position_id'])['official_title'] ?>
                                            </td>
                                            <td class="align-middle">

                                                <?php linkItem(customUri($activeApp, 'School Information', $row['station_id']), schoolById($row['station_id'])['name']); ?>
                                            </td>
                                            <td class="align-middle text-capitalize">
                                                <div class="dropdown no-arrow">
                                                    <?php dropdownEllipsis() ?>
                                                    <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in">
                                                        <?php
                                                        linkDropdownItem(customUri('hrmis', 'Employee Information', $row['id']), 'Employee Information', 'fa-user', 'Employee Information');
                                                        linkDropdownItem(customUri('hrmis', 'Service Record', $row['id']), 'Service Record', 'fa-file-alt', 'Service Record');
                                                        linkDropdownItem(customUri('hrmis', '201 Files', $row['id']), '201 Files', 'fa-folder-open', '201 Files');
                                                        linkDropdownItem(customUri('hrmis', 'Trainings', $row['id']), 'Trainings', 'fa-chalkboard-teacher', 'Trainings'); ?>
                                                        <div class="dropdown-divider"></div>
                                                        <?php linkDropdownItem(customUri('hrmis', 'Edit History', $row['id']), 'Edit History', 'fa-history', 'Edit History'); ?>
                                                        <div class="dropdown-divider"></div>
                                                        <?php modalDropdownItem(uri() . '/modules/employees/reassign-employee-dialog.php?id=' . cipher($row['id']), 'Reassign', 'fa-share', 'Reassign Employee');
                                                        if ($isPersonnel || $isICT) {
                                                            modalDropdownItem(uri() . '/modules/employees/promote-employee-dialog.php?id=' . cipher($row['id']), 'Promote', 'fa-thumbs-up', 'Promote Employee');
                                                            modalDropdownItem(uri() . '/modules/employees/remove-employee-dialog.php?id=' . cipher($row['id']), 'Remove', 'fa-trash', 'Remove Employee');
                                                        } ?>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>

                                <tfoot>
                                    <tr>
                                        <th class="align-middle" width="5%">Photo</th>
                                        <th class="align-middle" width="25%">Name</th>
                                        <th class="align-middle" width="15%">Date of Birth</th>
                                        <th class="align-middle" width="5%">Age</th>
                                        <th class="align-middle" width="20%">Position</th>
                                        <th class="align-middle" width="25%">Station</th>
                                        <th class="align-middle" width="5%">Action</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                <?php
                $months++;
            } ?>
        </div>
    </div>
</div>