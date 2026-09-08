<?php
// modules/vacancies/comparative-assessment-results.php

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

// Fetch unique positions in this publication and determine their categories
$pubItems = publicationItems($publicationId);
$positions = [];
foreach ($pubItems as $item) {
    $posDetails = find("SELECT `category` FROM `positions` WHERE `id` = ?", [$item['position_id']]);
    $category = $posDetails ? $posDetails['category'] : 'N/A';

    $positions[$item['position_id']] = [
        'id' => $item['position_id'],
        'official_title' => $item['official_title'],
        'salary_grade' => $item['salary_grade'],
        'category' => $category
    ];
}

// Sort positions alphabetically by official_title
uasort($positions, function ($a, $b) {
    return strcmp($a['official_title'], $b['official_title']);
});

// Fetch qualified applicants and their assessment results
$results = qualifiedApplicantsAssessmentResults($publicationId);
$resultsByPosition = [];
if ($results) {
    foreach ($results as $res) {
        $resultsByPosition[$res['position_id']][] = $res;
    }
}

// Load scoring weight criteria categories
$weightsData = query("SELECT * FROM `scoring_criteria_weights`");
$weightsByCategory = [];
if ($weightsData) {
    foreach ($weightsData as $w) {
        $weightsByCategory[$w['scoring_category']] = [
            'education' => (float) $w['education_max_points'],
            'training' => (float) $w['training_max_points'],
            'experience' => (float) $w['experience_max_points'],
            'performance' => (float) $w['performance_max_points'],
            'accomplishments' => (float) $w['accomplishments_max_points'],
            'app_edu' => (float) $w['application_education_max_points'],
            'app_ld' => (float) $w['application_ld_max_points'],
            'potential' => (float) $w['potential_max_points'],
            'total' => (float) $w['total_max_points'],
        ];
    }
}

function getScoringCategoryLabelForResults($salaryGrade, $category, $title = '')
{
    $isPrincipal = stripos($title, 'Principal') !== false;
    if ($isPrincipal && $salaryGrade >= 17 && $salaryGrade <= 22) {
        return 'SG 17-22 (School Administration Positions)';
    } elseif ($salaryGrade >= 1 && $salaryGrade <= 9) {
        return stripos($category, 'general service') !== false
            ? 'SG 1-9 (General Services)'
            : 'SG 1-9 (Non-General Services)';
    } elseif ($salaryGrade >= 10 && $salaryGrade <= 22) {
        return 'SG 10-22';
    } elseif ($salaryGrade == 24) {
        return 'SG 24 (Chief Positions)';
    } else {
        return 'SG 10-22';
    }
}

messageAlert($showAlert, $message, $success);
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
            <li class="breadcrumb-item"><a
                    href="<?= customUri('hrmis', 'Qualified Applicants', $publicationId) ?>">Qualified Applicants</a>
            </li>
            <li class="breadcrumb-item active">Comparative Assessment Results</li>
        </ol>
    </nav>
</div>

<div class="card border-left-success shadow mb-4">
    <div class="card-header py-3">
        <?php contentTitleWithLink('Comparative Assessment Results', customUri('hrmis', 'Qualified Applicants', $publicationId), 'Back', 'fa-arrow-circle-left') ?>
    </div>
    <div class="card-body">
        <h5 class="my-0 font-weight-bold text-gray-800"><?= e($title) ?></h5>
        <?php if (!empty($publication['description'])): ?>
            <p class="mt-2 mb-1 text-gray-600 small">
                <?= e($publication['description']) ?>
            </p>
        <?php endif ?>
        <small class="text-muted">Publication Code: <?= e($code) ?></small>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3 bg-light">
        <h6 class="m-0 font-weight-bold">Positions Comparative Assessment</h6>
    </div>
    <div class="card-body">
        <?php if (count($positions) > 0): ?>
            <!-- Tabs Navigation -->
            <ul class="nav nav-tabs mb-4" id="positionTab" role="tablist">
                <?php
                $isFirst = true;
                foreach ($positions as $posId => $pos):
                    $tabId = "pos-" . $posId;
                    $count = isset($resultsByPosition[$posId]) ? count($resultsByPosition[$posId]) : 0;
                    ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $isFirst ? 'active font-weight-bold text-primary' : 'text-secondary' ?>"
                            id="<?= $tabId ?>-tab" data-toggle="tab" href="#<?= $tabId ?>" role="tab"
                            aria-controls="<?= $tabId ?>" aria-selected="<?= $isFirst ? 'true' : 'false' ?>">
                            <?= e($pos['official_title']) ?>
                            <span class="badge badge-secondary ml-1"><?= $count ?></span>
                        </a>
                    </li>
                    <?php
                    $isFirst = false;
                endforeach;
                ?>
            </ul>

            <!-- Tabs Content -->
            <div class="tab-content" id="positionTabContent">
                <?php
                $isFirst = true;
                foreach ($positions as $posId => $pos):
                    $tabId = "pos-" . $posId;
                    $posResults = $resultsByPosition[$posId] ?? [];

                    // Determine weights for this position
                    $sgLabel = getScoringCategoryLabelForResults($pos['salary_grade'], $pos['category'], $pos['official_title']);
                    $weights = $weightsByCategory[$sgLabel] ?? [
                        'education' => 5,
                        'training' => 10,
                        'experience' => 15,
                        'performance' => 20,
                        'accomplishments' => 10,
                        'app_edu' => 10,
                        'app_ld' => 10,
                        'potential' => 20,
                        'total' => 100,
                    ];
                    ?>
                    <div class="tab-pane fade <?= $isFirst ? 'show active' : '' ?> px-3" id="<?= $tabId ?>" role="tabpanel"
                        aria-labelledby="<?= $tabId ?>-tab">

                        <?php
                        $hasAssessed = false;
                        foreach ($posResults as $res) {
                            if ($res['total_accumulated_score'] !== null) {
                                $hasAssessed = true;
                                break;
                            }
                        }
                        ?>

                        <div class="d-flex justify-content-between align-items-center mb-3 flex-column flex-sm-row">
                            <?php if ($hasAssessed): ?>
                                <h6 class="font-weight-bold text-gray-800 my-1">Rank List</h6>
                                <?php linkButtonSplit(uri() . '/export?v=' . encode('comparative-assessment-results') . '&id=' . encode($publicationId) . '&pos=' . encode($posId), 'Export', 'fa-download', 'Export Comparative Assessment Results for ' . e($pos['official_title']), 'success'); ?>
                            <?php endif; ?>
                        </div>

                        <?php if (count($posResults) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover text-center position-results-table" width="100%" cellspacing="0">
                                    <thead class="font-weight-bold text-gray-800">
                                        <tr>
                                            <th class="align-middle" width="5%">Rank</th>
                                            <th class="align-middle" width="20%">Applicant</th>
                                            <th class="align-middle" style="font-size: 0.8rem;">Education<br><span
                                                    class="text-muted font-weight-normal small">Max:
                                                    <?= $weights['education'] ?></span></th>
                                            <th class="align-middle" style="font-size: 0.8rem;">Training<br><span
                                                    class="text-muted font-weight-normal small">Max:
                                                    <?= $weights['training'] ?></span></th>
                                            <th class="align-middle" style="font-size: 0.8rem;">Experience<br><span
                                                    class="text-muted font-weight-normal small">Max:
                                                    <?= $weights['experience'] ?></span></th>
                                            <th class="align-middle" style="font-size: 0.8rem;">Performance<br><span
                                                    class="text-muted font-weight-normal small">Max:
                                                    <?= $weights['performance'] ?></span></th>
                                            <th class="align-middle" style="font-size: 0.8rem;">Accomplishments<br><span
                                                    class="text-muted font-weight-normal small">Max:
                                                    <?= $weights['accomplishments'] ?></span></th>
                                            <th class="align-middle" style="font-size: 0.8rem;">App. of Edu.<br><span
                                                    class="text-muted font-weight-normal small">Max:
                                                    <?= $weights['app_edu'] ?></span></th>
                                            <th class="align-middle" style="font-size: 0.8rem;">App. of L&D<br><span
                                                    class="text-muted font-weight-normal small">Max:
                                                    <?= $weights['app_ld'] ?></span></th>
                                            <th class="align-middle" style="font-size: 0.8rem;">Potential<br><span
                                                    class="text-muted font-weight-normal small">Max:
                                                    <?= $weights['potential'] ?></span></th>
                                            <th class="align-middle" style="font-size: 0.85rem;">Total Score<br><span
                                                    class="text-muted font-weight-normal small">Max: <?= $weights['total'] ?></span>
                                            </th>
                                            <th class="align-middle" width="15%">Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if (count($posResults) > 0) {
                                            $ranks = [];
                                            $currentRank = 1;
                                            $prevScore = null;
                                            $itemCount = 0;
                                            foreach ($posResults as $index => $res) {
                                                $itemCount++;
                                                $score = $res['total_accumulated_score'];
                                                if ($score === null) {
                                                    $ranks[$index] = '-';
                                                } else {
                                                    if ($prevScore === null || (float) $score !== (float) $prevScore) {
                                                        $currentRank = $itemCount;
                                                    }
                                                    $ranks[$index] = $currentRank;
                                                    $prevScore = $score;
                                                }
                                            }
                                        }
                                        foreach ($posResults as $index => $res):
                                            $applicantName = applicantName($res['application_code']);
                                            $isAssessed = ($res['total_accumulated_score'] !== null);
                                            ?>
                                            <tr class="text-uppercase">
                                                <td class="align-middle font-weight-bold"><?= $ranks[$index] ?></td>
                                                <td class="align-middle text-left font-weight-bold">
                                                    <?php
                                                    $applicantId = $res['application_code_id'] ?? applicantId($res['application_code']);
                                                    if ($applicantId): ?>
                                                        <a href="<?= e(customUri('hrmis', 'Applicant Information', $applicantId)) ?>"><?= e($applicantName) ?></a>
                                                    <?php else: ?>
                                                        <?= e($applicantName) ?>
                                                    <?php endif; ?>
                                                    <div class="text-muted font-weight-normal small">
                                                        <?= e($res['application_code']) ?>
                                                    </div>
                                                </td>
                                                <td class="align-middle">
                                                    <?= $isAssessed ? number_format($res['education_score'], 3, '.', '') : '<span class="text-muted font-italic small">-</span>' ?>
                                                </td>
                                                <td class="align-middle">
                                                    <?= $isAssessed ? number_format($res['training_score'], 3, '.', '') : '<span class="text-muted font-italic small">-</span>' ?>
                                                </td>
                                                <td class="align-middle">
                                                    <?= $isAssessed ? number_format($res['experience_score'], 3, '.', '') : '<span class="text-muted font-italic small">-</span>' ?>
                                                </td>
                                                <td class="align-middle">
                                                    <?= $isAssessed ? number_format($res['performance_score'], 3, '.', '') : '<span class="text-muted font-italic small">-</span>' ?>
                                                </td>
                                                <td class="align-middle">
                                                    <?= $isAssessed ? number_format($res['outstanding_accomplishments_score'], 3, '.', '') : '<span class="text-muted font-italic small">-</span>' ?>
                                                </td>
                                                <td class="align-middle">
                                                    <?= $isAssessed ? number_format($res['application_of_education_score'], 3, '.', '') : '<span class="text-muted font-italic small">-</span>' ?>
                                                </td>
                                                <td class="align-middle">
                                                    <?= $isAssessed ? number_format($res['application_of_ld_score'], 3, '.', '') : '<span class="text-muted font-italic small">-</span>' ?>
                                                </td>
                                                <td class="align-middle">
                                                    <?= $isAssessed ? number_format($res['potential_final_score'], 3, '.', '') : '<span class="text-muted font-italic small">-</span>' ?>
                                                </td>
                                                <td class="align-middle font-weight-bold text-primary">
                                                    <?= $isAssessed ? number_format($res['total_accumulated_score'], 3, '.', '') : '<span class="text-muted font-italic small">Not Assessed</span>' ?>
                                                </td>
                                                <td class="align-middle text-capitalize small" style="white-space: normal;">
                                                    <?= !empty($res['hrmspb_remarks']) ? e($res['hrmspb_remarks']) : '<span class="text-muted font-italic">No Remarks</span>' ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-poll-h fa-3x text-gray-300 mb-3"></i>
                                <p class="text-gray-500 mb-0">No qualified applicants for this position.</p>
                                <a href="<?= customUri('hrmis', 'Call for Application Details', $publicationId) ?>"
                                    class="btn btn-primary btn-sm mt-3">
                                    <i class="fas fa-user-edit mr-1"></i>Applications for Initial Screening
                                </a>
                            </div>
                        <?php endif; ?>

                    </div>
                    <?php
                    $isFirst = false;
                endforeach;
                ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-folder-open fa-3x text-gray-300 mb-3"></i>
                <p class="text-gray-500 mb-0">No positions found for this publication.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof $.fn.DataTable !== 'undefined') {
            $('.position-results-table').DataTable({
                responsive: true,
                pagingType: "simple",
                lengthMenu: [
                    [10, 25, 50, 75, 100, -1],
                    [10, 25, 50, 75, 100, "All"]
                ],
                paging: true,
                order: [],
                autoWidth: false,
                info: true,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search applicants..."
                }
            });
        }

        // Change nav-link active typography class on tab toggle
        $('#positionTab a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            $('#positionTab a').removeClass('font-weight-bold text-primary').addClass('text-secondary');
            $(e.target).addClass('font-weight-bold text-primary').removeClass('text-secondary');

            // Adjust DataTable columns on tab switch to prevent visual misalignment
            if (typeof $.fn.DataTable !== 'undefined') {
                $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
            }
        });
    });
</script>