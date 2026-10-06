<?php
// modules/districts/district-information.php
if (!$isHrmis && !$isHrtdms && !$isDmis) {
    require_once(root() . '/modules/error/403.php');
    return;
}

$districtId = isset($_GET['id']) ? sanitize(decode($_GET['id'])) : null;
$district = district($districtId);
$districtName = $psds = null;

messageAlert($showAlert, $message, $success);

if ($district) {
    $districtName = $district['name'];
    $psds = $district['supervisor_id'];
} else {
    require_once(root() . '/modules/error/no-results-found.php');
    return;
}
?>

<div class="d-flex align-items-center justify-content-between flex-row mt-2 mb-3">
    <nav class="d-flex align-items-center flex-row m-0">
        <ol class="breadcrumb m-0 p-0 bg-transparent">
            <li class="breadcrumb-item"><a href="<?= "{$baseUri}/{$activeApp}" ?>">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= customUri($activeApp, 'Districts') ?>">Districts</a></li>
            <li class="breadcrumb-item active"><?= e($districtName) ?></li>
        </ol>
    </nav>
</div>

<div class="card border-left-primary shadow mb-4">
    <div class="card-header py-3">
        <?php if ($isDmis) {
            contentTitleWithModal('District Information: ' . strtoupper($districtName), uri() . '/modules/districts/save-district-dialog.php?id=' . cipher($districtId), 'Edit', 'fa-edit');
        } else {
            contentTitle('District Information: ' . strtoupper($districtName));
        } ?>
    </div>

    <div class="card-body">
        <div class="table-responsive pb-3">
            <table cellspacing="0">
                <tr>
                    <th class="pr-5 align-top" scope="row">District</th>
                    <td class="text-uppercase"><?= e($districtName) ?></td>
                </tr>
                <tr>
                    <th class="pr-5 align-top" scope="row">Supervisor</th>
                    <td class="text-uppercase">
                        <div>
                            <?php if ($isHrmis) {
                                linkItem(customUri('hrmis', 'Employee Information', $psds), userName($psds));
                            } else {
                                echo userName($psds);
                            } ?>
                        </div>
                        <div class="small"><?= position($psds)['official_title'] ?></div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0 text-center" id="data-table" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th class="align-middle" width="5%">Logo</th>
                        <th class="align-middle" width="25%">School Name / Alias / ID / Address</th>
                        <th class="align-middle" width="15%">District</th>
                        <th class="align-middle" width="15%">Category</th>
                        <th class="align-middle" width="20%">Head of Office</th>
                        <th class="align-middle text-mars" width="5%"><i class="fa fa-user fw"></i> Male</th>
                        <th class="align-middle text-venus" width="5%"><i class="fa fa-user fw"></i> Female</th>
                        <th class="align-middle" width="5%"><i class="fa fa-user-friends fw"></i> Total</th>
                        <th class="align-middle" width="5%">Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    $query = districtSchools($districtId);
                    foreach ($query as $row):
                        $logo = "{$baseUri}/" . $row['logo'];
                        $schoolName = $row['name'];
                        ?>
                        <tr class="text-uppercase">
                            <td class="align-middle">
                                <div class="image-container">
                                    <span
                                        class="d-flex justify-content-center align-middle employee-photo rounded-circle overflow-hidden">
                                        <img height="100%" src="<?= e($logo) ?>" alt="<?= e($schoolName) ?>">
                                    </span>
                                </div>
                            </td>
                            <td class="align-middle text-left">
                                <div>
                                    <?php linkItem(customUri($activeApp, 'School Information', $row['id']), $schoolName . ' (' . $row['alias'] . ')') ?>
                                </div>
                                <div class="small"><?= $row['id'] . ' | ' . $row['address'] ?></div>
                            </td>
                            <td class="align-middle">
                                <?php
                                $district = district($row['district_id']);
                                if ($district) {
                                    linkItem(customUri($activeApp, 'District Information', $district['id']), $district['name']);
                                } ?>
                            </td>
                            <td class="align-middle"><?= e($row['category']) ?></td>
                            <td class="align-middle">
                                <div>
                                    <?php if ($isHrmis) {
                                        linkItem(customUri('hrmis', 'Employee Information', $row['head_id']), userName($row['head_id']));
                                    } else {
                                        modalItem(uri() . '/modules/users/user-info-dialog.php?id=' . cipher($row['head_id']), userName($row['head_id']));
                                    } ?>
                                </div>
                                <?php
                                $position = position($row['head_id']);
                                echo $position ? '<div class="small">' . $position['official_title'] . '</div>' : '';
                                ?>
                            </td>

                            <?php
                            $count = schoolEmployeeCount($row['id']);
                            $male = $female = $total = 0;

                            if ($count) {
                                $male = $count['male'];
                                $female = $count['female'];
                                $total = $count['total'];
                            }
                            ?>

                            <td class="align-middle"><?= e($male) ?></td>
                            <td class="align-middle"><?= e($female) ?></td>
                            <td class="align-middle"><?= e($total) ?></td>
                            <td class="align-middle text-capitalize">
                                <div class="dropdown no-arrow">
                                    <?php dropdownEllipsis() ?>
                                    <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in">
                                        <?php linkDropdownItem(customUri($activeApp, 'School Information', $row['id']), 'View', 'fa-eye', 'View School') ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>

                <tfoot>
                    <tr>
                        <th class="align-middle" width="5%">Logo</th>
                        <th class="align-middle" width="25%">School Name / Alias / ID / Address</th>
                        <th class="align-middle" width="15%">District</th>
                        <th class="align-middle" width="15%">Category</th>
                        <th class="align-middle" width="20%">Head of Office</th>
                        <th class="align-middle text-mars" width="5%"><i class="fa fa-user fw"></i> Male</th>
                        <th class="align-middle text-venus" width="5%"><i class="fa fa-user fw"></i> Female</th>
                        <th class="align-middle" width="5%"><i class="fa fa-user-friends fw"></i> Total</th>
                        <th class="align-middle" width="5%">Action</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>