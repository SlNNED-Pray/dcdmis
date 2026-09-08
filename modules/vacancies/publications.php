<?php
// modules/vacancies/publications.php
$userPositionId = null;
if ($isPis) {
    if (!function_exists('position')) {
        require_once(root() . '/includes/database/position.php');
    }
    $userPosition = position($userId);
    $userPositionId = $userPosition['position_id'] ?? null;

    require_once(root() . '/includes/database/vacancy.php');
}
$isAllowedHigherPosition = $isPis && (in_array($userPositionId, $allowedMonitoringPositions, true) || $isICT);

if (!$isHrmis && !$isAllowedHigherPosition) {
    require_once root() . '/modules/error/403.php';
    return;
}

messageAlert($showAlert, $message, $success);
?>

<div class="d-flex align-items-center justify-content-between flex-row mt-2 mb-3">
    <nav class="d-flex align-items-center flex-row m-0">
        <ol class="breadcrumb m-0 p-0 bg-transparent">
            <li class="breadcrumb-item"><a href="<?= uri() . '/' . $activeApp ?>">Dashboard</a></li>
            <?php if ($isPis): ?>
                <li class="breadcrumb-item"><a href="<?= customUri('pis', 'System Overview') ?>">System Overview</a></li>
                <li class="breadcrumb-item"><a
                        href="<?= customUri('pis', 'Recruitment, Selection and Placement') ?>">Recruitment, Selection and
                        Placement</a></li>
            <?php endif ?>
            <li class="breadcrumb-item active">Call for Applications</li>
        </ol>
    </nav>
</div>

<div class="card border-left-primary shadow mb-4">
    <div class="card-header py-3">
        <?php
        if ($isHrmis && ($isICT || $isPersonnel)) {
            contentTitleWithLink('Call for Applications', customUri('hrmis', 'Create Call for Application'), 'Add', 'fa-plus');
        } else {
            contentTitle('Call for Applications');
        }
        ?>
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover mb-0 text-center" id="data-table" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th class="align-middle" width="35%">Title</th>
                        <th class="align-middle" width="10%">Vacancies</th>
                        <th class="align-middle" width="15%">Open Date</th>
                        <th class="align-middle" width="15%">Close Date</th>
                        <th class="align-middle" width="10%">Status</th>
                        <?php if ($isHrmis && ($isICT || $isPersonnel)): ?>
                            <th class="align-middle" width="5%">Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    $query = allPublications();
                    foreach ($query as $row):
                        $vacancyCount = countPublicationItems($row['id']);
                        ?>
                        <tr class="text-uppercase">
                            <td class="align-middle text-left">
                                <a href="<?= (($isHrmis && ($isICT || $isPersonnel)) || $isAllowedHigherPosition) ? customUri($activeApp, 'Call for Application Details', $row['id']) : uri() . '/hrmis/apply?p=' . $row['code'] ?>"
                                    target="<?= (($isHrmis && ($isICT || $isPersonnel)) || $isAllowedHigherPosition) ? '_self' : '_blank' ?>">
                                    <?= e($row['title']) ?>
                                </a>
                            </td>
                            <td class="align-middle">
                                <span class="badge badge-secondary badge-pill"><?= e($vacancyCount) ?> Items</span>
                            </td>
                            <td class="align-middle"><?= toLongDate($row['open_date']) ?></td>
                            <td class="align-middle"><?= toLongDate($row['close_date']) ?></td>
                            <td class="align-middle">
                                <?php if ($row['status'] === 'open'): ?>
                                    <span class="badge badge-success badge-pill">Open</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary badge-pill"><?= $row['status'] ?></span>
                                <?php endif; ?>
                            </td>
                            <?php if ($isHrmis && ($isICT || $isPersonnel)): ?>
                                <td class="align-middle text-capitalize">
                                    <div class="dropdown no-arrow">
                                        <?php dropdownEllipsis() ?>
                                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in">
                                            <?php
                                            linkDropdownItem(customUri('hrmis', 'Call for Application Details', $row['id']), 'View', 'fa-eye', 'View Call for Application Details');
                                            linkDropdownItem(customUri('hrmis', 'Edit Call for Application', $row['id']), 'Edit', 'fa-edit', 'Edit Call for Application'); ?>
                                            <div class="dropdown-divider"></div>
                                            <?php modalDropdownItem(uri() . '/modules/vacancies/delete-publication-dialog.php?id=' . cipher($row['id']), 'Delete', 'fa-trash-alt', 'Delete Call for Application'); ?>
                                        </div>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach ?>
                </tbody>

                <tfoot>
                    <tr>
                        <th class="align-middle" width="35%">Title</th>
                        <th class="align-middle" width="10%">Vacancies</th>
                        <th class="align-middle" width="15%">Open Date</th>
                        <th class="align-middle" width="15%">Close Date</th>
                        <th class="align-middle" width="10%">Status</th>
                        <?php if ($isHrmis && ($isICT || $isPersonnel)): ?>
                            <th class="align-middle" width="5%">Action</th>
                        <?php endif; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>