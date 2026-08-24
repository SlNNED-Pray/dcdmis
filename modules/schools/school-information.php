<?php
// modules/schools/school-information.php
if (!$isHrmis && !$isHrtdms && !$isDmis) {
    require_once(root() . '/modules/error/403.php');
    return;
}

$schoolId = isset($_GET['id']) ? sanitize(decode($_GET['id'])) : null;
$school = schoolById($schoolId);
$schoolName = $alias = $address = $district = $head = $telephone = $email = $website = $fbPage = null;
$personnel = 0;

messageAlert($showAlert, $message, $success);

if ($school) {
    $schoolName = $school['name'];
    $alias = $school['alias'];
    $address = $school['address'];
    $districts = district($school['district_id']);
    $district = $districts > 0 ? $districts['name'] : '';
    $category = $school['category'];
    $head = $school['head_id'];
    $telephone = $school['telephone'];
    $email = $school['email'];
    $website = $school['website'];
    $fbPage = $school['fb_page'];
    $count = schoolEmployeeCount($schoolId);
    $personnel = $count > 0 ? $count['total'] : 0;
    $logo = !empty($school['logo']) ? uri() . '/' . $school['logo'] : uri() . '/uploads/division/division.png';
} else {
    require_once(root() . '/modules/error/no-results-found.php');
    return;
}
?>

<div class="d-flex align-items-center justify-content-between flex-row mt-2 mb-3">
    <nav class="d-flex align-items-center flex-row m-0">
        <ol class="breadcrumb m-0 p-0 bg-transparent">
            <li class="breadcrumb-item"><a href="<?= uri() . '/' . $activeApp ?>">Dashboard</a></li>
            <?php if ($schoolId === DIVISION_ID): ?>
                <li class="breadcrumb-item active"><?= e($schoolName) ?></li>
            <?php else: ?>
                <li class="breadcrumb-item"><a href="<?= customUri($activeApp, 'Schools') ?>">Schools</a></li>
                <li class="breadcrumb-item active"><?= e($schoolName) ?></li>
            <?php endif; ?>
        </ol>
    </nav>
</div>

<div class="card border-left-primary shadow mb-4">
    <div class="card-header py-3">
        <?php
        $titlePrefix = ($schoolId === DIVISION_ID) ? 'Division Office Information' : 'School Information';
        $backLink = ($schoolId === DIVISION_ID) ? uri() . '/' . $activeApp : customUri($activeApp, 'Schools');
        if ($activeApp === 'dmis') {
            contentTitleWithModal("$titlePrefix: " . strtoupper($schoolName), uri() . '/modules/schools/save-school-dialog.php?id=' . cipher($schoolId) . '&e=' . cipher($alias), 'Edit', 'fa-edit');
        } elseif ($activeApp === 'hrmis') {
            contentTitleWithModal("$titlePrefix: " . strtoupper($schoolName), uri() . '/modules/employees/save-employee-dialog.php?s=' . cipher($schoolId), 'Add', 'fa-user-plus');
        } else {
            contentTitleWithLink("$titlePrefix: " . strtoupper($schoolName), $backLink);
        } ?>
    </div>

    <div class="card-body">
        <div class="row">
            <div class="col-sm-12 col-md-12 col-lg-4 col-xl-2">
                <img src="<?= e($logo) ?>" width="100%">
            </div>
            <div class="col-sm-12 col-md-12 col-lg-8 col-xl-10">
                <div class="table-responsive">
                    <table cellspacing="0">
                        <tr>
                            <th class="pr-5 align-top" scoper="row">School ID</th>
                            <td class="text-uppercase"><?= e($schoolId) ?></td>
                        </tr>
                        <tr>
                            <th class="pr-5 align-top" scoper="row">Name</th>
                            <td class="text-uppercase"><?= "{$schoolName} ({$alias})" ?></td>
                        </tr>
                        <tr>
                            <th class="pr-5 align-top" scoper="row">Address</th>
                            <td class="text-uppercase"><?= e($address) ?></td>
                        </tr>
                        <tr>
                            <th class="pr-5 align-top" scoper="row">District</th>
                            <td class="text-uppercase"><?= e($district) ?></td>
                        </tr>
                        <tr>
                            <th class="pr-5 align-top" scoper="row">Category</th>
                            <td class="text-uppercase"><?= e($category) ?></td>
                        </tr>
                        <?php if (!empty($head)): ?>
                            <tr>
                                <th class="pr-5 align-top" scoper="row">Head of Office</th>
                                <td class="text-uppercase">
                                    <div>
                                        <?php if ($isHrmis) {
                                            linkItem(customUri('hrmis', 'Employee Information', $head), userName($head));
                                        } else {
                                            modalItem(uri() . '/modules/users/user-info-dialog.php?id=' . cipher($head), userName($head));
                                        } ?>
                                    </div>
                                    <?php
                                    $position = position($head);
                                    echo $position > 0 ? '<div class="small">' . $position['official_title'] . '</div>' : '';
                                    ?>
                                </td>
                            </tr>
                        <?php endif ?>
                        <?php if (!empty($telephone)): ?>
                            <tr>
                                <th class="pr-5 align-top" scoper="row">Telephone</th>
                                <td class="text-uppercase"><?= e($telephone) ?></td>
                            </tr>
                        <?php endif ?>
                        <?php if (!empty($email)): ?>
                            <tr>
                                <th class="pr-5 align-top" scoper="row">Email Address</th>
                                <td class="text-lowercase"><?= e($email) ?></td>
                            </tr>
                        <?php endif ?>
                        <?php if (!empty($website)): ?>
                            <tr>
                                <th class="pr-5 align-top" scoper="row">Website</th>
                                <td class="text-lowercase"><?= e($website) ?></td>
                            </tr>
                        <?php endif ?>
                        <?php if (!empty($fbPage)): ?>
                            <tr>
                                <th class="pr-5 align-top" scoper="row">Facebook Page</th>
                                <td class="text-lowercase"><?= e($fbPage) ?></td>
                            </tr>
                        <?php endif ?>
                        <tr>
                            <th class="pr-5 align-top" scoper="row">Personnel</th>
                            <td class="text-lowercase"><?= e($personnel) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-sm-flex align-items-center flex-row-reverse my-2">
            <div class="d-inline-block">
                <?php
                linkButtonSplit(customUri('export', 'active-employees', $schoolId), 'Export', 'fa-file-excel', 'Export as Excel file', 'success');
                if ($isHrmis) {
                    linkButtonSplit(customUri($activeApp, 'Non-Regular Employees', $schoolId), 'Non-Regular Staff', 'fa-users-cog', 'View Contract of Service, Job Order & Casual employees', 'info');
                } ?>
            </div>
        </div>

        <?php require(root() . '/modules/employees/employee-filter-bar.php'); ?>

        <div class="table-responsive mt-3">
            <table class="table table-hover mb-0 text-center" id="data-table" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th class="align-middle" width="5%">Photo</th>
                        <th class="align-middle" width="5%">Employee Number</th>
                        <th class="align-middle" width="25%">Name</th>
                        <th class="align-middle" width="15%">Date of Birth</th>
                        <th class="align-middle" width="5%">Age</th>
                        <th class="align-middle" width="20%">Position</th>
                        <?php if (!$isHrtdms): ?>
                            <th class="align-middle" width="15%">Email Address</th>
                        <?php else: ?>
                            <th class="align-middle" width="15%">Attended Trainings</th>
                        <?php endif ?>
                        <?php if ($isHrmis): ?>
                            <th class="align-middel" width="10%">Progress</th>
                        <?php else: ?>
                            <?php if (!$isHrtdms): ?>
                                <th class="align-middle" width="10%">Contact #</th>
                            <?php endif ?>
                        <?php endif ?>
                        <th class="align-middle" width="5%">Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    $query = activeEmployees($schoolId);
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
                            <td class="align-middle"><?= toHandleNull($row['agency_id'], 'N/A') ?></td>
                            <td class="align-middle text-left">
                                <?php if ($isHrmis) {
                                    linkItem(customUri('hrmis', 'Employee Information', $row['id']), $employeeName, true);
                                } else {
                                    modalItem(uri() . '/modules/users/user-info-dialog.php?id=' . cipher($row['id']), $employeeName);
                                } ?>
                            </td>
                            <td class="align-middle">
                                <?= toDate($row['birthdate'], 'F j, Y') ?>
                            </td>
                            <td class="align-middle">
                                <?= getDateDifference($row['birthdate']) ?>
                            </td>
                            <td class="align-middle"><?= positions($row['position_id'])['official_title'] ?></td>
                            <?php if (!$isHrtdms): ?>
                                <td class="align-middle text-lowercase"><?= e($row['email_address']) ?></td>
                            <?php else: ?>
                                <td class="align-middle text-lowercase">
                                    <?php
                                    $count = count(attendedTrainings($row['id']));
                                    if ($count > 0) {
                                        echo $count;
                                    } else { ?>
                                        <span class="text-danger font-weight-bold"><?= e($count) ?></span>
                                    <?php } ?>
                                </td>
                            <?php endif ?>
                            <?php if ($isHrmis) { ?>
                                <td class="align-middle"><?php progressBar(pdsProgress($row['id'])) ?></td>
                            <?php } else { ?>
                                <?php if (!$isHrtdms): ?>
                                    <td class="align-middle"><?= e($row['mobile_number']) ?></td>
                                <?php endif ?>
                            <?php } ?>
                            <td class="align-middle text-capitalize">
                                <div class="dropdown no-arrow">
                                    <?php dropdownEllipsis() ?>
                                    <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in">
                                        <?php if (($isHrmis && $isPersonnel) || ($isHrmis && $isICT)) { ?>
                                            <?php modalDropdownItem(uri() . '/modules/schools/set-school-head-dialog.php?e=' . cipher($schoolId) . '&id=' . cipher($row['id']), 'Set Head', 'fa-user-tie', 'Set School Head') ?>
                                            <div class="dropdown-divider"></div>
                                        <?php }
                                        if ($isHrmis) {
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
                                            }
                                        } elseif ($isDmis) {
                                            linkDropdownItem(customUri('dmis', 'Activity Log', $row['id']), 'Activity', 'fa-list', 'User Activity Log'); ?>
                                            <div class="dropdown-divider"></div>
                                            <?php
                                            modalDropdownItem(uri() . '/modules/users/edit-user-dialog.php?id=' . cipher($row['id']), 'Edit', 'fa-edit', 'Edit User');
                                            modalDropdownItem(uri() . '/modules/users/reset-user-dialog.php?id=' . cipher($row['id']), 'Reset', 'fa-undo-alt', 'Reset User');
                                        } elseif ($isHrtdms) { ?>
                                            <div class="dropdown-divider"></div>
                                            <?php linkDropdownItem(customUri('hrtdms', 'Trainings', $row['id']), 'Trainings', 'fa-chalkboard-teacher');
                                        } else { ?>
                                            <div class="dropdown-divider"></div>
                                            <?php modalDropdownItem(uri() . '/modules/users/user-info-dialog.php?id=' . cipher($row['id']), 'View', 'fa-eye', 'View Employee');
                                        } ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>

                <tfoot>
                    <tr>
                        <th class="align-middle" width="5%">Photo</th>
                        <th class="align-middle" width="5%">Employee Number</th>
                        <th class="align-middle" width="25%">Name</th>
                        <th class="align-middle" width="15%">Date of Birth</th>
                        <th class="align-middle" width="5%">Age</th>
                        <th class="align-middle" width="20%">Position</th>
                        <?php if (!$isHrtdms): ?>
                            <th class="align-middle" width="15%">Email Address</th>
                        <?php else: ?>
                            <th class="align-middle" width="15%">Attended Trainings</th>
                        <?php endif ?>
                        <?php if ($isHrmis): ?>
                            <th class="align-middel" width="10%">Progress</th>
                        <?php else: ?>
                            <?php if (!$isHrtdms): ?>
                                <th class="align-middle" width="10%">Contact #</th>
                            <?php endif ?>
                        <?php endif ?>
                        <th class="align-middle" width="5%">Action</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>