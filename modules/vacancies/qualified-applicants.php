<?php
// modules/vacancies/qualified-applicants.php

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

$apps = applicantsByPublication($publicationId);
$qualifiedApps = array_filter($apps, function ($app) {
    return $app['status'] === 'Qualified';
});
?>

<div class="d-flex align-items-center justify-content-between flex-row mt-2 mb-3">
    <nav class="d-flex align-items-center flex-row m-0">
        <ol class="breadcrumb m-0 p-0 bg-transparent">
            <li class="breadcrumb-item"><a href="<?= uri() . '/' . $activeApp ?>">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= customUri('hrmis', 'Call for Applications') ?>">Call for
                    Applications</a>
            </li>
            <li class="breadcrumb-item"><a
                    href="<?= customUri('hrmis', 'Call for Application Details', $publicationId) ?>">
                    <?= e($code) ?>
                </a></li>
            <li class="breadcrumb-item active">Qualified Applicants</li>
        </ol>
    </nav>
</div>

<div class="card border-left-success shadow mb-4">
    <div class="card-header py-3">
        <?php contentTitleWithLink('Qualified Applicants', customUri('hrmis', 'Call for Application Details', $publicationId), 'Back', 'fa-arrow-circle-left') ?>
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
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold">Qualified Applicants</h6>
        <?php if (count($qualifiedApps) > 0): ?>
            <div>
                <?php linkButtonSplit(customUri('hrmis', 'Comparative Assessment Results', $publicationId), 'Comparative Assessment Results', 'fa-list-ol', 'View Comparative Assessment Results', 'primary', true); ?>
                <?php linkButtonSplit(uri() . '/export?v=' . encode('qualified-applicants') . '&id=' . encode($publicationId), 'Export', 'fa-download', 'Export Qualified Applicants', 'success'); ?>
            </div>
        <?php endif ?>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover text-center" width="100%" cellspacing="0" id="data-table">
                <thead>
                    <tr>
                        <th width="15%" class="align-middle">Applied on</th>
                        <th width="30%" class="align-middle">Applicant</th>
                        <th width="25%" class="align-middle">Position Applied</th>
                        <th width="10%" class="align-middle">Score</th>
                        <th width="10%" class="align-middle">Document</th>
                        <?php if ($isHrmis && ($isPersonnel || $isICT) && $publication['status'] === 'open'): ?>
                            <th width="10%" class="align-middle">Action</th>
                        <?php endif ?>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    if (count($qualifiedApps) > 0) {
                        foreach ($qualifiedApps as $app): ?>
                            <tr class="text-uppercase">
                                <td class="align-middle"><?= toDatetime($app['created_at']) ?></td>
                                <td class="align-middle font-weight-bold">
                                    <?php
                                    $applicantId = $app['application_code_id'] ?? applicantId($app['application_code']);
                                    $applicantNameStr = applicantName($app['application_code']);
                                    if ($applicantId): ?>
                                        <a href="<?= e(customUri('hrmis', 'Applicant Information', $applicantId)) ?>"><?= e($applicantNameStr) ?></a>
                                    <?php else: ?>
                                        <?= e($applicantNameStr) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="align-middle">
                                    <div><?= e($app['official_title']) ?></div>
                                </td>
                                <td class="align-middle">
                                    <?php if (isset($app['total_accumulated_score']) && $app['total_accumulated_score'] !== null): ?>
                                        <div class="font-weight-bold text-primary">
                                            <?= number_format($app['total_accumulated_score'], 3) ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-muted font-italic small text-uppercase">Not assessed</div>
                                    <?php endif; ?>
                                </td>
                                <td class="align-middle text-capitalize">
                                    <?php
                                    $applicantDocument = applicantDocument($publicationId, applicantId($app['application_code']));
                                    $documentUri = root() . "/{$applicantDocument}";
                                    if (!empty($applicantDocument) && file_exists($documentUri)) {
                                        linkButtonSplit("{$baseUri}/{$applicantDocument}", 'Attachment', 'fa-paperclip', "View Applicant Document", 'secondary', true);
                                    } else { ?>
                                        <div class="text-muted font-italic small text-uppercase">No document attachment</div>
                                    <?php } ?>
                                </td>
                                <?php if ($isHrmis && ($isPersonnel || $isICT) && $publication['status'] === 'open'): ?>
                                    <td class="align-middle text-capitalize">
                                        <div class="dropdown no-arrow">
                                            <?php dropdownEllipsis() ?>
                                            <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in">
                                                <?php
                                                linkDropdownItem(customUri('hrmis', 'Assess Applicant', $app['id']), 'Assess', 'fa-poll', 'Assess Applicant', true); ?>
                                                <div class="dropdown-divider"></div>
                                                <?php
                                                modalDropdownItem(uri() . '/modules/vacancies/for-review-application-dialog.php?id=' . cipher($app['id']), 'For Initial Screening', 'fa-redo', 'Mark Application For Initial Screening');
                                                modalDropdownItem(uri() . '/modules/vacancies/disqualify-application-dialog.php?id=' . cipher($app['id']), 'Disqualify', 'fa-thumbs-down', 'Disqualify Application');
                                                ?>
                                            </div>
                                        </div>
                                    </td>
                                <?php endif ?>
                            </tr>
                        <?php endforeach;
                    } ?>
                </tbody>

                <tfoot>
                    <tr>
                        <th width="15%" class="align-middle">Applied on</th>
                        <th width="30%" class="align-middle">Applicant</th>
                        <th width="25%" class="align-middle">Position Applied</th>
                        <th width="10%" class="align-middle">Score</th>
                        <th width="10%" class="align-middle">Document</th>
                        <?php if ($isHrmis && ($isPersonnel || $isICT) && $publication['status'] === 'open'): ?>
                            <th width="10%" class="align-middle">Action</th>
                        <?php endif ?>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>