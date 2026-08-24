<?php
// modules/users/active-users.php
if (!$isDmis) {
    require_once(root() . '/modules/error/403.php');
    return;
}

messageAlert($showAlert, $message, $success);
?>

<div class="d-flex align-items-center justify-content-between flex-row mt-2 mb-3">
    <nav class="d-flex align-items-center flex-row m-0">
        <ol class="breadcrumb m-0 p-0 bg-transparent">
            <li class="breadcrumb-item"><a href="<?= uri() . '/' . $activeApp ?>">Dashboard</a></li>
            <li class="breadcrumb-item active">Users</li>
        </ol>
    </nav>
</div>

<div class="card border-left-primary shadow mb-4">
    <div class="card-header py-3">
        <?php contentTitleWithLink('Users', customUri('dmis', 'Active Employees'), 'Assign', 'fa-user-plus') ?>
    </div>

    <div class="card-body">
        <?php if ($isDmis) { ?>
            <div class="d-sm-flex align-items-center flex-row-reverse mb-2">
                <div class="d-inline-block">
                    <?php linkButtonSplit(customUri('export', 'users'), 'Export', 'fa-file-excel', 'Export as Excel file', 'success') ?>
                </div>
            </div>
        <?php } ?>

        <?php require(root() . '/modules/employees/employee-filter-bar.php'); ?>

        <div class="table-responsive">
            <table class="table table-hover mb-0 text-center" id="data-table" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th class="align-middle" width="5%">Photo</th>
                        <th class="align-middle" width="20%">Name</th>
                        <th class="align-middle" width="15%">Email Address</th>
                        <th class="align-middle" width="20%">Position</th>
                        <th class="align-middle" width="25%">Station</th>
                        <th class="align-middle" width="10%">Status</th>
                        <th class="align-middle" width="5%">Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    $query = users();
                    foreach ($query as $row):
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
                            <td class="align-middle text-left">
                                <?php modalItem(uri() . '/modules/users/edit-user-dialog.php?id=' . cipher($row['id']), $employeeName) ?>
                            </td>
                            <td class="align-middle text-lowercase"><?= e($row['email_address']) ?></td>
                            <td class="align-middle"><?= positions($row['position_id'])['official_title'] ?></td>
                            <td class="align-middle">
                                <?php linkItem(customUri($activeApp, 'School Information', $row['station_id']), schoolById($row['station_id'])['name']) ?>
                            </td>
                            <td class="align-middle">
                                <?php
                                $status = strtolower($row['status']);
                                roundPill($status);
                                ?>
                            </td>
                            <td class="align-middle text-capitalize">
                                <div class="dropdown no-arrow">
                                    <?php dropdownEllipsis() ?>
                                    <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in">
                                        <?php
                                        linkDropdownItem(customUri('dmis', 'Activity Log', $row['id']), 'Activity', 'fa-list', 'User Activity Log');
                                        ?>
                                        <div class="dropdown-divider"></div>
                                        <?php
                                        modalDropdownItem(uri() . '/modules/users/edit-user-dialog.php?id=' . cipher($row['id']), 'Edit', 'fa-edit', 'Edit User');
                                        modalDropdownItem(uri() . '/modules/users/reset-user-dialog.php?id=' . cipher($row['id']), 'Reset', 'fa-undo-alt', 'Reset User');
                                        ?>
                                        <div class="dropdown-divider"></div>
                                        <?php modalDropdownItem(uri() . '/modules/users/remove-user-dialog.php?id=' . cipher($row['id']), 'Remove', 'fa-trash', 'Remove User') ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>

                <tfoot>
                    <tr>
                        <th class="align-middle" width="5%">Photo</th>
                        <th class="align-middle" width="20%">Name</th>
                        <th class="align-middle" width="15%">Email Address</th>
                        <th class="align-middle" width="20%">Position</th>
                        <th class="align-middle" width="25%">Station</th>
                        <th class="align-middle" width="10%">Status</th>
                        <th class="align-middle" width="5%">Action</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>