<?php
// modules/vacancies/publication-details.php

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

$publicationId = isset($_GET['id']) ? sanitize(decipher($_GET['id'])) : null;
$code = $title = $description = null;

if ($publicationId) {
    $publication = publication($publicationId);
    if (count($publication) > 0) {
        $code = $publication['code'];
        $title = $publication['title'];
        $description = $publication['description'];
    }
} else {
    require_once(root() . '/modules/error/no-results-found.php');
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
            <li class="breadcrumb-item"><a href="<?= customUri($activeApp, 'Call for Applications') ?>">Call for
                    Applications</a></li>
            <li class="breadcrumb-item active">
                <?= e($code) ?>
            </li>
        </ol>
    </nav>
</div>

<div class="card border-left-primary shadow mb-4">
    <div class="card-header py-3">
        <?php if ($isHrmis && ($isPersonnel || $isICT)) {
            contentTitleWithLink('Details', customUri('hrmis', 'Edit Call for Application', $publicationId), 'Edit', 'fa-edit');
        } else {
            contentTitle('Details');
        } ?>
    </div>

    <div class="card-body">
        <h2 class="my-0"><?= e($title) ?></h2>
        <?php if (!empty(e($description))): ?>
            <p class="mt-1 mb-0"><?= e($description) ?></p>
        <?php endif ?>
    </div>
</div>


<div class="row">
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Status</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?= ucfirst($publication['status']) ?>
                        </div>
                        <div class="small text-muted mt-2">
                            <?= toLongDate($publication['open_date']) ?> -
                            <?= toLongDate($publication['close_date']) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calendar fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-0">
                            Total Applications</div>
                        <div class="h4 mb-0 font-weight-bold text-gray-800">
                            <a href="<?= customUri('hrmis', 'Call for Application Applicants', $publicationId) ?>"
                                class="text-dark">
                                <?= countApplicationsByPublication($publicationId) ?>
                            </a>
                        </div>
                        <div class="small text-muted mt-0">
                            <?php $applicantsCount = countApplicantsByPublication($publicationId);
                            echo "{$applicantsCount} applicant" . ($applicantsCount > 1 ? 's' : '') ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-md-12 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Public Link</div>
                        <div class="font-weight-bold text-gray-800 text-truncate">
                            <a href="<?= uri() . '/hrmis/apply?p=' . $code ?>" target="_blank">
                                <?= uri() . '/hrmis/apply?p=' . $code ?>
                            </a>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-link fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$positionChartData = applicantsCountByPosition($publicationId);
foreach ($positionChartData as &$item) {
    $item['link'] = customUri('hrmis', 'Call for Application Applicants', $publicationId) . '&position_id=' . encode($item['position_id']);
}
unset($item);

$empStatus = applicantEmploymentStatusCount($publicationId);
$employedCount = (int) ($empStatus['internal'] ?? 0);
$notEmployedCount = (int) ($empStatus['external'] ?? 0);

$employmentChartData = [
    [
        'name' => 'Internal',
        'count' => $employedCount,
        'link' => customUri('hrmis', 'Call for Application Applicants', $publicationId) . '&status=internal'
    ],
    [
        'name' => 'External',
        'count' => $notEmployedCount,
        'link' => customUri('hrmis', 'Call for Application Applicants', $publicationId) . '&status=external'
    ]
];
?>

<div class="row">
    <div class="col-xl-8 col-lg-7 mb-4">
        <div class="card shadow">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Applicants by Position</h6>
            </div>
            <div class="card-body">
                <?php if (count($positionChartData) > 0): ?>
                    <div class="chart-bar h-auto" style="position: relative; height: 350px;">
                        <canvas id="applicants-position-chart"></canvas>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-chart-bar fa-3x text-gray-300 mb-3"></i>
                        <p class="text-muted mb-0">No applicant data available for this call for application.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-lg-5 mb-4">
        <div class="card shadow">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Applicant Type</h6>
            </div>
            <div class="card-body">
                <?php if ($employedCount > 0 || $notEmployedCount > 0): ?>
                    <div class="chart-pie pt-4 pb-2" style="position: relative; height: 300px;">
                        <canvas id="employment-status-chart"></canvas>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-chart-pie fa-3x text-gray-300 mb-3"></i>
                        <p class="text-muted mb-0">No applicant data available.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="<?= uri() ?>/assets/vendor/chart.js/Chart.min.js"></script>
<script src="<?= uri() ?>/assets/vendor/chart.js/chartjs-plugin-datalabels.min.js"></script>
<script src="<?= uri() ?>/assets/js/chart-custom.js?v=<?= VERSION ?>"></script>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        <?php if (count($positionChartData) > 0): ?>
            const posData = <?= json_encode($positionChartData) ?>;
            const posColors = generateColorPallete(posData.length);
            generateBarChart(posData, posColors, 'applicants-position-chart', true);
        <?php endif; ?>

        <?php if ($employedCount > 0 || $notEmployedCount > 0): ?>
            const empData = <?= json_encode($employmentChartData) ?>;
            const empColors = ['#4e73df', '#1cc88a'];
            generateBarChart(empData, empColors, 'employment-status-chart', true);
        <?php endif; ?>
    });
</script>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <div class="d-sm-flex align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold">Applications for Initial Screening</h6>
            <div>
                <?php linkButtonSplit(customUri('hrmis', 'Qualified Applicants', $publicationId), 'Qualified', 'fa-thumbs-up', 'View Qualified Applicants', 'success');
                linkButtonSplit(customUri('hrmis', 'Disqualified Applicants', $publicationId), 'Disqualified', 'fa-thumbs-down', 'View Disqualified Applicants', 'danger');
                linkButtonSplit(uri() . '/export?v=' . encode('publication-applicants-details') . '&id=' . encode($publicationId), 'Export Details', 'fa-file-excel', 'Export Applicant Details', 'info'); ?>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover text-center" width="100%" cellspacing="0" id="data-table">
                <thead>
                    <tr>
                        <th width="15%" class="align-middle">Applied on</th>
                        <th width="40%" class="align-middle">Applicant</th>
                        <th width="30%" class="align-middle">Position Applied</th>
                        <th width="15%" class="align-middle">Attachment</th>
                        <?php if ($isHrmis && ($isPersonnel || $isICT) && $publication['status'] === 'open'): ?>
                            <th width="5%" class="align-middle">Action</th>
                        <?php endif ?>
                    </tr>
                </thead>

                <tbody>
                    <?php $apps = applicantsForReviewByPublication($publicationId);
                    foreach ($apps as $app): ?>
                        <tr class="text-uppercase">
                            <td class="align-middle">
                                <?= toDatetime($app['created_at']) ?>
                            </td>
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
                                <div>
                                    <?= e($app['official_title']) ?>
                                </div>
                            </td>
                            <td class="align-middle">
                                <?php
                                $applicantDocument = applicantDocument($publicationId, applicantId($app['application_code']));
                                $documentUri = root() . "/{$applicantDocument}";
                                if (!empty($applicantDocument) && file_exists($documentUri)) {
                                    linkButtonSplit("{$baseUri}/{$applicantDocument}", 'Document', 'fa-paperclip', "View Document Attachment", 'secondary', true);
                                } else { ?>
                                    <div class="small text-uppercase">No document attachment</div>
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
                        <th width="15%" class="align-middle">Applied on</th>
                        <th width="40%" class="align-middle">Applicant</th>
                        <th width="30%" class="align-middle">Position Applied</th>
                        <th width="15%" class="align-middle">Attachment</th>
                        <?php if ($isHrmis && ($isPersonnel || $isICT) && $publication['status'] === 'open'): ?>
                            <th width="5%" class="align-middle">Action</th>
                        <?php endif ?>
                    </tr>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-sm-flex align-items-center justify-content-between">
        <h6 class="m-0 font-weight-bold">Included Vacancies</h6>
        <?php
        $isClosed = ($publication['status'] === 'closed');
        $items = publicationItems($publicationId);
        $hasEligibleToReadd = false;
        if ($isClosed && $isHrmis && ($isPersonnel || $isICT)) {
            foreach ($items as $chkItem) {
                if ($chkItem['status'] !== 'filled' && countQualifiedApplicants($publicationId, $chkItem['position_id']) === 0 && !isVacancyReadded($chkItem['plantilla_item_id'])) {
                    $hasEligibleToReadd = true;
                    break;
                }
            }
        }
        if ($hasEligibleToReadd): ?>
            <div>
                <?php modalButtonSplit(uri() . '/modules/vacancies/readd-all-vacancies-dialog.php?id=' . cipher($publicationId), 'Re-add Unfilled Items to Vacancies', 'fa-redo', 'Re-add all unfilled positions with no qualified applicants to vacant plantilla positions', 'primary'); ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover mb-0 text-center" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th class="align-middle" width="30%">Position / Salary Grade</th>
                        <th class="align-middle" width="20%">Item Number</th>
                        <th class="align-middle" width="35%">Station</th>
                        <?php if ($isHrmis && ($isPersonnel || $isICT)): ?>
                            <th class="align-middle" width="15%">Status / Action</th>
                        <?php endif ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($items as $item): ?>
                        <tr class="text-uppercase">
                            <td class="align-middle">
                                <?= e($item['official_title']) . ' (' . e($item['salary_grade']) . ')' ?>
                            </td>
                            <td class="align-middle"><?= e($item['item_number']) ?></td>
                            <td class="align-middle">
                                <?php $school = schoolById($item['station_id']);
                                echo $school ? $school['name'] : 'N/A'; ?>
                            </td>
                            <?php if ($isHrmis && ($isPersonnel || $isICT)): ?>
                                <td class="align-middle text-capitalize">
                                    <?php if ($item['status'] === 'filled') { ?>
                                        <span class="badge badge-success py-1 px-2 text-uppercase small">Filled</span>
                                    <?php } elseif ($publication['status'] === 'open') { ?>
                                        <?php if (countQualifiedApplicants($publicationId, $item['position_id']) === 0) { ?>
                                            <span class="badge badge-secondary py-1 px-2 text-uppercase small">No Qualified
                                                Applicants</span>
                                        <?php } elseif (countQualifiedApplicants($publicationId, $item['position_id']) !== countAssessedQualifiedApplicants($publicationId, $item['position_id'])) { ?>
                                            <span class="badge badge-secondary py-1 px-2 text-uppercase small">Pending Assessment</span>
                                        <?php } else {
                                            modalButtonSplit(uri() . '/modules/vacancies/fill-vacancy-dialog.php?id=' . cipher($item['vacancy_id']), 'Fill Position', 'fa-user-plus', 'Fill Position Item');
                                        } ?>
                                    <?php } elseif ($publication['status'] === 'closed') { ?>
                                        <?php
                                        $qualifiedCount = countQualifiedApplicants($publicationId, $item['position_id']);
                                        $alreadyReadded = isVacancyReadded($item['plantilla_item_id']);
                                        if ($qualifiedCount > 0) { ?>
                                            <span class="badge badge-info py-1 px-2 text-uppercase small">Has Qualified
                                                Applicants</span>
                                        <?php } elseif ($alreadyReadded) { ?>
                                            <span class="badge badge-primary py-1 px-2 text-uppercase small"
                                                title="Item already re-added to vacant positions">Vacant</span>
                                        <?php } else {
                                            modalButtonSplit(uri() . '/modules/vacancies/readd-vacancy-dialog.php?pub_id=' . cipher($publicationId) . '&vacancy_id=' . cipher($item['vacancy_id']), 'Re-add to Vacancies', 'fa-plus-circle', 'Re-add this vacant plantilla item to vacancies for new Call for Applications', 'primary');
                                        } ?>
                                    <?php } else { ?>
                                        <span
                                            class="badge badge-secondary py-1 px-2 text-uppercase small"><?= e($publication['status']) ?></span>
                                    <?php } ?>
                                </td>
                            <?php endif ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th class="align-middle" width="30%">Position / Salary Grade</th>
                        <th class="align-middle" width="20%">Item Number</th>
                        <th class="align-middle" width="35%">Station</th>
                        <?php if ($isHrmis && ($isPersonnel || $isICT)): ?>
                            <th class="align-middle" width="15%">Status / Action</th>
                        <?php endif ?>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>