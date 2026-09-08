<?php
// modules/vacancies/applicants.php

if (!$isHrmis) {
    require_once root() . '/modules/error/403.php';
    return;
}

$publicationId = isset($_GET['id']) ? sanitize(decipher($_GET['id'])) : null;
$code = $title = null;

if ($publicationId) {
    $publication = publication($publicationId);
    if (count($publication) > 0) {
        $code = $publication['code'];
        $title = $publication['title'];
    }
} else {
    require_once(root() . '/modules/error/no-results-found.php');
    return;
}

messageAlert($showAlert, $message, $success);

// Handle filter inputs
$selectedPositionId = isset($_GET['position_id']) ? sanitize($_GET['position_id']) : 'all';
$selectedStatus = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';

$positionIdParam = ($selectedPositionId !== 'all' && $selectedPositionId !== '') ? sanitize(decipher($selectedPositionId)) : null;
$statusParam = ($selectedStatus !== 'all' && $selectedStatus !== '') ? $selectedStatus : null;

// Fetch filtered applicants
$apps = applicantsListByPublication($publicationId, $positionIdParam, $statusParam);

// Get positions for dropdown
$items = publicationItems($publicationId);
$pubPositions = [];
foreach ($items as $item) {
    $pubPositions[$item['position_id']] = $item['official_title'];
}
?>

<div class="d-flex align-items-center justify-content-between flex-row mt-2 mb-3">
    <nav class="d-flex align-items-center flex-row m-0">
        <ol class="breadcrumb m-0 p-0 bg-transparent">
            <li class="breadcrumb-item"><a href="<?= uri() . '/' . $activeApp ?>">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= customUri('hrmis', 'Call for Applications') ?>">Call for
                    Applications</a></li>
            <li class="breadcrumb-item">
                <a href="<?= customUri('hrmis', 'Call for Application Details', $publicationId) ?>"><?= e($code) ?></a>
            </li>
            <li class="breadcrumb-item active">Applicants</li>
        </ol>
    </nav>
</div>

<div class="card border-left-primary shadow mb-4">
    <div class="card-header py-3">
        <?php contentTitleWithLink('Applicants', customUri('hrmis', 'Call for Application Details', $publicationId), 'Back', 'fa-arrow-circle-left') ?>
    </div>

    <div class="card-body">
        <h5 class="my-0"><?= e($title) ?></h5>
        <?php if (!empty($publication['description'])): ?>
            <p class="mt-1 mb-1">
                <?= e($publication['description']) ?>
            </p>
        <?php endif ?>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <?php contentTitleWithLink(count($apps) . ' application' . (count($apps) !== 1 ? 's' : ''), uri() . '/export?v=' . encode('applicants') . '&id=' . encode($publicationId) . '&position_id=' . urlencode($selectedPositionId) . '&status=' . urlencode($selectedStatus), 'Export', 'fa-download', 'success'); ?>
    </div>

    <div class="card-body">
        <form method="GET" action="">
            <input type="hidden" name="v" value="<?= e($_GET['v'] ?? '') ?>">
            <input type="hidden" name="id" value="<?= e($_GET['id'] ?? '') ?>">
            <div class="row">
                <div class="col-md-5 mb-2">
                    <label for="filter-position" class="font-weight-bold text-gray-800 small mb-0">Position
                        Applied</label>
                    <select id="filter-position" name="position_id" class="form-control">
                        <option value="all" <?= ($selectedPositionId === 'all') ? 'selected' : '' ?>>All</option>
                        <?php foreach ($pubPositions as $posId => $posTitle):
                            $encodedPosId = cipher($posId); ?>
                            <option value="<?= e($encodedPosId) ?>" <?= ($selectedPositionId !== 'all' && decipher($selectedPositionId) == $posId) ? 'selected' : '' ?>>
                                <?= e($posTitle) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-2">
                    <label for="filter-status" class="font-weight-bold text-gray-800 small mb-0">Applicant
                        Type</label>
                    <select id="filter-status" name="status" class="form-control">
                        <option value="all" <?= ($selectedStatus === 'all') ? 'selected' : '' ?>>All</option>
                        <option value="internal" <?= ($selectedStatus === 'internal') ? 'selected' : '' ?>><i class=""></i>
                            Internal
                        </option>
                        <option value="external" <?= ($selectedStatus === 'external') ? 'selected' : '' ?>>External
                        </option>
                    </select>
                </div>
                <div class="col-md-3 mb-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-filter mr-1"></i> Apply Filters
                    </button>
                </div>
            </div>
        </form>

        <div class="table-responsive mt-4">
            <table class="table table-hover text-center" width="100%" cellspacing="0" id="data-table">
                <thead>
                    <tr>
                        <th width="15%" class="align-middle">Applied on</th>
                        <th width="30%" class="align-middle">Applicant Name</th>
                        <th width="25%" class="align-middle">Position Applied</th>
                        <th width="10%" class="align-middle">Status</th>
                        <th width="10%" class="align-middle">Attachment</th>
                        <?php if ($isHrmis && ($isPersonnel || $isICT) && $publication['status'] === 'open'): ?>
                            <th width="5%" class="align-middle">Action</th>
                        <?php endif ?>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($apps as $app): ?>
                        <tr class="text-uppercase">
                            <td class="align-middle small">
                                <?= toDatetime($app['created_at']) ?>
                            </td>
                            <td class="align-middle text-left">
                                <?php
                                $applicantId = $app['application_code_id'] ?? applicantId($app['application_code']);
                                $applicantNameStr = applicantName($app['application_code']);
                                ?>
                                <div class="font-weight-bold">
                                    <?php if ($applicantId): ?>
                                        <a href="<?= e(customUri('hrmis', 'Applicant Information', $applicantId)) ?>"><?= e($applicantNameStr) ?></a>
                                    <?php else: ?>
                                        <?= e($applicantNameStr) ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ($app['is_employed']): ?>
                                    <span class="badge badge-primary py-1 px-2">Internal</span>
                                <?php else: ?>
                                    <span class="badge badge-success py-1 px-2">External</span>
                                <?php endif ?>
                            </td>
                            <td class="align-middle text-left">
                                <?= e($app['official_title']) ?>
                            </td>
                            <td class="align-middle">
                                <?php
                                $statusClass = 'secondary';
                                if ($app['status'] === 'Qualified') {
                                    $statusClass = 'success';
                                } elseif ($app['status'] === 'Disqualified') {
                                    $statusClass = 'danger';
                                } elseif ($app['status'] === 'For Review') {
                                    $statusClass = 'info';
                                }
                                ?>
                                <span class="badge badge-<?= $statusClass ?> py-1 px-2"><?= e($app['status']) ?></span>
                            </td>
                            <td class="align-middle">
                                <?php
                                $applicantDocument = applicantDocument($publicationId, applicantId($app['application_code']));
                                $documentUri = root() . "/{$applicantDocument}";
                                if (!empty($applicantDocument) && file_exists($documentUri)) {
                                    linkButtonSplit("{$baseUri}/{$applicantDocument}", 'Document', 'fa-paperclip', "View Document Attachment", 'secondary', true);
                                } else { ?>
                                    <div class="small text-muted">No document attachment</div>
                                <?php } ?>
                            </td>
                            <?php if ($isHrmis && ($isPersonnel || $isICT) && $publication['status'] === 'open'): ?>
                                <td class="align-middle text-capitalize">
                                    <div class="dropdown no-arrow">
                                        <?php dropdownEllipsis() ?>
                                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in">
                                            <?php if ($app['status'] === 'For Review') {
                                                modalDropdownItem(uri() . '/modules/vacancies/qualify-application-dialog.php?id=' . cipher($app['id']), 'Qualify', 'fa-thumbs-up', 'Qualify Application');
                                                modalDropdownItem(uri() . '/modules/vacancies/disqualify-application-dialog.php?id=' . cipher($app['id']), 'Disqualify', 'fa-thumbs-down', 'Disqualify Application');
                                            } else {
                                                modalDropdownItem(uri() . '/modules/vacancies/for-review-application-dialog.php?id=' . cipher($app['id']), 'For Initial Screening', 'fa-redo', 'Mark Application For Initial Screening');
                                                if ($app['status'] === 'Qualified') {
                                                    modalDropdownItem(uri() . '/modules/vacancies/disqualify-application-dialog.php?id=' . cipher($app['id']), 'Disqualify', 'fa-thumbs-down', 'Disqualify Application');
                                                } else {
                                                    modalDropdownItem(uri() . '/modules/vacancies/qualify-application-dialog.php?id=' . cipher($app['id']), 'Qualify', 'fa-thumbs-up', 'Qualify Application');
                                                }
                                            } ?>
                                        </div>
                                    </div>
                                </td>
                            <?php endif ?>
                        </tr>
                    <?php endforeach ?>
                </tbody>

                <tfoot>
                    <tr>
                        <th width="10%" class="align-middle">Applied on</th>
                        <th width="35%" class="align-middle">Applicant Name</th>
                        <th width="25%" class="align-middle">Position Applied</th>
                        <th width="10%" class="align-middle">Status</th>
                        <th width="15%" class="align-middle">Attachment</th>
                        <?php if ($isHrmis && ($isPersonnel || $isICT) && $publication['status'] === 'open'): ?>
                            <th width="5%" class="align-middle">Action</th>
                        <?php endif ?>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>