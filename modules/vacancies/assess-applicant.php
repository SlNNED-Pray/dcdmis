<?php
// modules/vacancies/assess-applicant.php

if (!$isHrmis) {
    require_once root() . '/modules/error/403.php';
    return;
}

$applicationId = isset($_GET['id']) ? sanitize(decipher($_GET['id'])) : null;
$appRecord = null;
$score = null;
$applicantName = 'Unknown Applicant';
$positionTitle = 'Unknown Position';
$publication = null;
$publicationId = null;
$appCode = null;
$scoringWeights = null;

if ($applicationId) {
    $appRecord = applicationRecord($applicationId);
    if ($appRecord) {
        $score = getAssessmentScore($applicationId);
        $appCode = applicantCode($appRecord['application_code_id']);
        $applicantName = applicantName($appCode);

        $positionData = find("SELECT * FROM `positions` WHERE `id` = ?", [$appRecord['position_id']]);
        $positionTitle = $positionData ? $positionData['official_title'] : 'Unknown Position';
        $positionSG = $positionData ? (int) $positionData['salary_grade'] : 0;
        $positionCategory = $positionData ? $positionData['category'] : 'N/A';

        $isPrincipal = stripos($positionTitle, 'Principal') !== false;
        if ($isPrincipal && $positionSG >= 17 && $positionSG <= 22) {
            $sgLabel = 'SG 17-22 (School Administration Positions)';
        } elseif ($positionSG >= 1 && $positionSG <= 9) {
            $sgLabel = stripos($positionCategory, 'general service') !== false
                ? 'SG 1-9 (General Services)'
                : 'SG 1-9 (Non-General Services)';
        } elseif ($positionSG >= 10 && $positionSG <= 22) {
            $sgLabel = 'SG 10-22';
        } elseif ($positionSG == 24) {
            $sgLabel = 'SG 24 (Chief Positions)';
        } else {
            // Fallback: use SG 10-22 category for unspecified grades
            $sgLabel = 'SG 10-22';
        }

        $scoringWeights = find(
            "SELECT * FROM `scoring_criteria_weights` WHERE `scoring_category` = ? LIMIT 1",
            [$sgLabel]
        );

        $publicationId = $appRecord['publication_id'];
        $publication = publication($publicationId);
    }
}

// Default weights fallback (if table is empty or no match)
$weights = [
    'education' => $scoringWeights ? (float) $scoringWeights['education_max_points'] : 5,
    'training' => $scoringWeights ? (float) $scoringWeights['training_max_points'] : 10,
    'experience' => $scoringWeights ? (float) $scoringWeights['experience_max_points'] : 15,
    'performance' => $scoringWeights ? (float) $scoringWeights['performance_max_points'] : 20,
    'accomplishments' => $scoringWeights ? (float) $scoringWeights['accomplishments_max_points'] : 10,
    'application_edu' => $scoringWeights ? (float) $scoringWeights['application_education_max_points'] : 10,
    'application_ld' => $scoringWeights ? (float) $scoringWeights['application_ld_max_points'] : 10,
    'potential' => $scoringWeights ? (float) $scoringWeights['potential_max_points'] : 20,
    'total' => $scoringWeights ? (float) $scoringWeights['total_max_points'] : 100,
    'category_label' => $scoringWeights ? $scoringWeights['scoring_category'] : 'N/A',
];

if (!$appRecord || !$publication) {
    require_once(root() . '/modules/error/no-results-found.php');
    return;
}

messageAlert($showAlert, $message, $success);
?>

<div class="d-flex align-items-center justify-content-between flex-row mt-2 mb-3">
    <nav class="d-flex align-items-center flex-row m-0">
        <ol class="breadcrumb m-0 p-0 bg-transparent">
            <li class="breadcrumb-item"><a href="<?= "$baseUri/$activeApp" ?>">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= customUri('hrmis', 'Call for Applications') ?>">Call for
                    Applications</a></li>
            <li class="breadcrumb-item"><a
                    href="<?= customUri('hrmis', 'Call for Application Details', $publicationId) ?>">
                    <?= e($publication['code']) ?>
                </a></li>
            <li class="breadcrumb-item"><a
                    href="<?= customUri('hrmis', 'Qualified Applicants', $publicationId) ?>">Qualified Applicants</a>
            </li>
            <li class="breadcrumb-item active">Assess</li>
        </ol>
    </nav>
</div>

<div class="row">
    <div class="col-12">
        <div class="card shadow mb-4 border-left-primary">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Call for Application Details
                </h6>
            </div>
            <div class="card-body">
                <h5 class="font-weight-bold text-gray-800 mb-1">
                    <?= e($publication['title']) ?>
                </h5>
                <p class="text-muted small mb-2">
                    <?= e($publication['code']) ?>
                </p>
                <?php if (!empty($publication['description'])): ?>
                    <p class="small text-gray-700 mb-2">
                        <?= e($publication['description']) ?>
                    </p>
                <?php endif ?>

                <div class="text-gray-800 m-0">
                    <span class="badge badge-primary text-capitalize">
                        <?= e($publication['status']) ?>
                    </span>
                </div>
                <div class="small text-gray-800">
                    <?= toLongDate($publication['open_date']) ?> to
                    <?= toLongDate($publication['close_date']) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-4 col-lg-5 col-md-12">
        <div class="row">
            <div class="col-12">
                <div class="card shadow mb-4 border-left-info">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-info">Applicant Info</h6>
                    </div>
                    <div class="card-body">
                        <h5 class="text-uppercase font-weight-bold text-gray-800 mb-1">
                            <?php if (!empty($appRecord['application_code_id'])): ?>
                                <a href="<?= e(customUri('hrmis', 'Applicant Information', $appRecord['application_code_id'])) ?>" target="_blank" title="View Applicant Information"><?= e($applicantName) ?></a>
                            <?php else: ?>
                                <?= e($applicantName) ?>
                            <?php endif; ?>
                        </h5>
                        <p class="text-muted mb-0 small">
                            <?= e($appCode) ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card shadow mb-4 border-left-success">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-success">Position Applied
                        </h6>
                    </div>
                    <div class="card-body">
                        <h5 class="text-uppercase font-weight-bold text-gray-800 mb-1">
                            <?= e($positionTitle) ?>
                        </h5>
                        <div class="small text-muted mb-2">Salary Grade:
                            <?= e($positionSG) ?> | Category:
                            <?= e($positionCategory) ?>
                        </div>

                        <?php
                        $vacanciesData = query("SELECT vpi.vacancy_id, pi.item_number, pi.station_id 
                                     FROM vacancy_publication_items AS vpi
                                     INNER JOIN vacancies AS v ON vpi.vacancy_id = v.id
                                     INNER JOIN plantilla_items AS pi ON v.plantilla_item_id = pi.id
                                     WHERE vpi.publication_id = ? AND pi.position_id = ?",
                            [$publicationId, $appRecord['position_id']]
                        );

                        $vacanciesCount = is_array($vacanciesData) ? count($vacanciesData) : 0;
                        ?>

                        <?php if ($vacanciesCount > 1): ?>
                            <p class="mb-0 text-gray-800"><strong>Available Items:</strong>
                                <?= $vacanciesCount ?> Item<?= $vacanciesCount > 1 ? 's' : '' ?>
                            </p>
                        <?php else:
                            $vacancyData = ($vacanciesCount === 1) ? $vacanciesData[0] : null;
                            $itemNumber = $vacancyData ? $vacancyData['item_number'] : 'N/A';
                            $stationName = 'N/A';
                            if ($vacancyData && !empty($vacancyData['station_id'])) {
                                $school = schoolById($vacancyData['station_id']);
                                $stationName = $school ? $school['name'] : 'Unknown';
                            }
                            ?>
                            <p class="mb-1 text-gray-800"><strong>Item Number:</strong>
                                <?= e($itemNumber) ?>
                            </p>
                            <p class="mb-0 text-gray-800"><strong>Station:</strong>
                                <?= e($stationName) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card shadow mb-4 border-left-warning">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-warning">Criteria and Point System</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="px-3 pt-2 pb-1">
                            <p class="small text-muted mb-2">Category:
                                <strong>
                                    <?= e($weights['category_label']) ?>
                                </strong>
                            </p>
                        </div>
                        <table class="table table-sm table-bordered mb-0" style="font-size:0.78rem;">
                            <thead class="thead-light">
                                <tr>
                                    <th class="px-2">Criterion</th>
                                    <th class="text-center px-2">Max</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="px-2">Education</t d>
                                    <td class="text-center px-2">
                                        <?= $weights['education'] ?>
                                    </td>
                                </tr>
                                <tr>

                                    <td class="px-2">Training</td>
                                    <td class="text-center px-2">
                                        <?= $weights['training'] ?>
                                    </td>
                                </tr>

                                <tr>
                                    <td class="px-2">Experience</td>
                                    <td class="text-center px-2">
                                        <?= $weights['experience'] ?>
                                    </td>

                                </tr>
                                <tr>
                                    <td class="px-2">Performance</td>
                                    <td class="text-center px-2">
                                        <?= $weights['performance'] ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-2">Accomplishments</td>
                                    <td class="text-center px-2">

                                        <?= $weights['accomplishments'] ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-2">Application
                                        of Education</td>
                                    <td class="text-center px-2">
                                        <?= $weights['application_edu'] ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-2">Application of L&D</td>
                                    <td class="text-center px-2">
                                        <?= $weights['application_ld'] ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-2">Potential</td>
                                    <td class="text-center px-2">
                                        <?= $weights['potential'] ?>
                                    </td>
                                </tr>
                                <tr class="font-weight-bold table-warning">
                                    <td class="px-2">Total</td>
                                    <td class="text-center px-2">
                                        <?= $weights['total'] ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <a href="<?= customUri('hrmis', 'Qualified Applicants', $publicationId) ?>"
            class="btn btn-secondary btn-block shadow mb-4">
            <i class="fas fa-arrow-circle-left mr-1"></i> Back to Qualified Applicants
        </a>
    </div>

    <div class="col-xl-8 col-lg-7 col-md-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Applicant Assessment</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field(); ?>

                    <div class="row">
                        <div class="col-xl-6 col-md-12 col-sm-12">
                            <h6 class="font-weight-bold text-gray-800 mb-3">Core Assessment Criteria</h6>
                            <div class="form-group">
                                <label for="education_score"
                                    class="font-weight-bold text-dark mb-0 d-flex justify-content-between">
                                    <span>Education</span>
                                    <span class="text-muted font-weight-normal small">Max:
                                        <strong><?= $weights['education'] ?></strong></span>
                                </label>
                                <input type="number" step="0.001" min="0" max="<?= $weights['education'] ?>"
                                    data-max="<?= $weights['education'] ?>" class="form-control score-input"
                                    id="education_score" name="education_score"
                                    value="<?= e(number_format($score['education_score'] ?? 0, 3, '.', '')) ?>"
                                    required>
                            </div>
                            <div class="form-group">
                                <label for="training_score"
                                    class="font-weight-bold text-dark mb-0 d-flex justify-content-between">
                                    <span>Training</span>
                                    <span class="text-muted font-weight-normal small">Max:
                                        <strong><?= $weights['training'] ?></strong></span>
                                </label>
                                <input type="number" step="0.001" min="0" max="<?= $weights['training'] ?>"
                                    data-max="<?= $weights['training'] ?>" class="form-control score-input"
                                    id="training_score" name="training_score"
                                    value="<?= e(number_format($score['training_score'] ?? 0, 3, '.', '')) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="experience_score"
                                    class="font-weight-bold text-dark mb-0 d-flex justify-content-between">
                                    <span>Experience</span>
                                    <span class="text-muted font-weight-normal small">Max:
                                        <strong><?= $weights['experience'] ?></strong></span>
                                </label>
                                <input type="number" step="0.001" min="0" max="<?= $weights['experience'] ?>"
                                    data-max="<?= $weights['experience'] ?>" class="form-control score-input"
                                    id="experience_score" name="experience_score"
                                    value="<?= e(number_format($score['experience_score'] ?? 0, 3, '.', '')) ?>"
                                    required>
                            </div>
                            <div class="form-group">
                                <label for="performance_score"
                                    class="font-weight-bold text-dark mb-0 d-flex justify-content-between">
                                    <span>Performance</span>
                                    <span class="text-muted font-weight-normal small">Max:
                                        <strong><?= $weights['performance'] ?></strong></span>
                                </label>
                                <input type="number" step="0.001" min="0" max="<?= $weights['performance'] ?>"
                                    data-max="<?= $weights['performance'] ?>" class="form-control score-input"
                                    id="performance_score" name="performance_score"
                                    value="<?= e(number_format($score['performance_score'] ?? 0, 3, '.', '')) ?>"
                                    required>
                            </div>
                            <div class="form-group">
                                <label for="outstanding_accomplishments_score"
                                    class="font-weight-bold text-dark mb-0 d-flex justify-content-between">
                                    <span>Outstanding Accomplishments</span>
                                    <span class="text-muted font-weight-normal small">Max:
                                        <strong><?= $weights['accomplishments'] ?></strong></span>
                                </label>
                                <input type="number" step="0.001" min="0" max="<?= $weights['accomplishments'] ?>"
                                    data-max="<?= $weights['accomplishments'] ?>" class="form-control score-input"
                                    id="outstanding_accomplishments_score" name="outstanding_accomplishments_score"
                                    value="<?= e(number_format($score['outstanding_accomplishments_score'] ?? 0, 3, '.', '')) ?>"
                                    required>
                            </div>
                        </div>

                        <div class="col-xl-6 col-md-12 col-sm-12">
                            <h6 class="font-weight-bold text-gray-800 mb-3">Application of L and D</h6>
                            <div class="form-group">
                                <label for="application_of_education_score"
                                    class="font-weight-bold text-dark mb-0 d-flex justify-content-between">
                                    <span>Application of Education</span>
                                    <span class="text-muted font-weight-normal small">Max:
                                        <strong><?= $weights['application_edu'] ?></strong></span>
                                </label>
                                <input type="number" step="0.001" min="0" max="<?= $weights['application_edu'] ?>"
                                    data-max="<?= $weights['application_edu'] ?>" class="form-control score-input"
                                    id="application_of_education_score" name="application_of_education_score"
                                    value="<?= e(number_format($score['application_of_education_score'] ?? 0, 3, '.', '')) ?>"
                                    required>
                            </div>
                            <div class="form-group">
                                <label for="application_of_ld_score"
                                    class="font-weight-bold text-dark mb-0 d-flex justify-content-between">
                                    <span>Application of L and D</span>
                                    <span class="text-muted font-weight-normal small">Max:
                                        <strong><?= $weights['application_ld'] ?></strong></span>
                                </label>
                                <input type="number" step="0.001" min="0" max="<?= $weights['application_ld'] ?>"
                                    data-max="<?= $weights['application_ld'] ?>" class="form-control score-input"
                                    id="application_of_ld_score" name="application_of_ld_score"
                                    value="<?= e(number_format($score['application_of_ld_score'] ?? 0, 3, '.', '')) ?>"
                                    required>
                            </div>

                            <h6 class="font-weight-bold text-gray-800 mt-4 mb-2">Potential Raw Points
                                <span class="text-muted font-weight-normal small ml-1">(Max Combined:
                                    <strong><?= $weights['potential'] ?></strong>)</span>
                            </h6>
                            <small class="text-muted d-block mb-2">Individual raw points — their sum is capped at
                                <strong><?= $weights['potential'] ?></strong>.</small>
                            <div class="row">
                                <div class="col-md-4 form-group">
                                    <label for="potential_written_exam_raw" class="mb-0 small">Written Test</label>
                                    <input type="number" step="0.001" min="0" max="<?= $weights['potential'] ?>"
                                        data-max="<?= $weights['potential'] ?>"
                                        class="form-control score-input potential-raw" id="potential_written_exam_raw"
                                        name="potential_written_exam_raw"
                                        value="<?= e(number_format($score['potential_written_exam_raw'] ?? 0, 3, '.', '')) ?>"
                                        required>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="potential_bei_raw" class="mb-0 small">BEI</label>
                                    <input type="number" step="0.001" min="0" max="<?= $weights['potential'] ?>"
                                        data-max="<?= $weights['potential'] ?>"
                                        class="form-control score-input potential-raw" id="potential_bei_raw"
                                        name="potential_bei_raw"
                                        value="<?= e(number_format($score['potential_bei_raw'] ?? 0, 3, '.', '')) ?>"
                                        required>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="potential_wst_raw" class="mb-0 small">WST</label>
                                    <input type="number" step="0.001" min="0" max="<?= $weights['potential'] ?>"
                                        data-max="<?= $weights['potential'] ?>"
                                        class="form-control score-input potential-raw" id="potential_wst_raw"
                                        name="potential_wst_raw"
                                        value="<?= e(number_format($score['potential_wst_raw'] ?? 0, 3, '.', '')) ?>"
                                        required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="potential_final_score"
                                class="font-weight-bold text-dark mb-0 d-flex justify-content-between">
                                <span>Potential Final Points</span>
                                <span class="text-muted font-weight-normal small">Max:
                                    <strong><?= $weights['potential'] ?></strong></span>
                            </label>
                            <input type="number" step="0.001" min="0" max="<?= $weights['potential'] ?>"
                                data-max="<?= $weights['potential'] ?>" class="form-control bg-light font-weight-bold"
                                id="potential_final_score" name="potential_final_score"
                                value="<?= e(number_format($score['potential_final_score'] ?? 0, 3, '.', '')) ?>"
                                required readonly>
                            <small class="form-text text-muted font-italic">Auto calculated (Written + BEI + WST),
                                capped at <?= $weights['potential'] ?></small>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="total_accumulated_score"
                                class="font-weight-bold text-gray-800 mb-0 d-flex justify-content-between">
                                <span>Total Accumulated Points</span>
                                <span class="text-muted font-weight-normal small">Max:
                                    <strong><?= $weights['total'] ?></strong></span>
                            </label>
                            <input type="number" step="0.001" min="0" max="<?= $weights['total'] ?>"
                                class="form-control bg-light text-gray-800 font-weight-bold"
                                id="total_accumulated_score" name="total_accumulated_score"
                                value="<?= e(number_format($score['total_accumulated_score'] ?? 0, 3, '.', '')) ?>"
                                required readonly>
                            <small class="form-text text-muted font-italic">Auto calculated total criteria sum</small>
                        </div>
                        <div class="col-md-12 form-group">
                            <label for="hrmspb_remarks" class="font-weight-bold text-dark mb-0">Remarks</label>
                            <textarea class="form-control" id="hrmspb_remarks" name="hrmspb_remarks" rows="3"
                                placeholder="Enter remarks..."><?= e($score['hrmspb_remarks'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <?php
                    // Fetch other applications of the applicant in the current publication
                    $otherApplications = query(
                        "SELECT va.id, va.position_id, p.official_title, p.salary_grade, p.category 
                         FROM vacancy_applications AS va
                         INNER JOIN positions AS p ON va.position_id = p.id
                         WHERE va.publication_id = ? AND va.application_code_id = ? AND va.id != ?",
                        [$publicationId, $appRecord['application_code_id'], $applicationId]
                    );

                    $applicableOtherApps = [];
                    foreach ($otherApplications as $otherApp) {
                        $otherSG = (int) $otherApp['salary_grade'];
                        $otherCategory = $otherApp['category'];

                        if ($otherSG >= 1 && $otherSG <= 9) {
                            $otherSgLabel = stripos($otherCategory, 'general service') !== false
                                ? 'SG 1-9 (General Services)'
                                : 'SG 1-9 (Non-General Services)';
                        } elseif ($otherSG >= 10 && $otherSG <= 22) {
                            $otherSgLabel = 'SG 10-22';
                        } elseif ($otherSG == 24) {
                            $otherSgLabel = 'SG 24 (Chief Positions)';
                        } else {
                            $otherSgLabel = 'SG 10-22';
                        }

                        if ($otherSgLabel === $sgLabel) {
                            // Check for existing score
                            $existingScore = find("SELECT `total_accumulated_score` FROM `assessment_scores` WHERE `application_id` = ?", [$otherApp['id']]);
                            $otherApp['current_score'] = $existingScore ? $existingScore['total_accumulated_score'] : null;
                            $applicableOtherApps[] = $otherApp;
                        }
                    }
                    ?>

                    <?php if (!empty($applicableOtherApps)): ?>
                        <div class="card bg-light border-left-info mb-4 text-left">
                            <div class="card-body py-3">
                                <h6 class="font-weight-bold text-info mb-2">
                                    <i class="fas fa-copy mr-1"></i> Apply Points to Other Applications
                                </h6>
                                <p class="small text-muted mb-3">
                                    This applicant has also applied for other positions with the same scoring criteria
                                    weights.
                                    Select the positions below to also save the same points for them:
                                </p>
                                <?php foreach ($applicableOtherApps as $otherApp): ?>
                                    <div class="custom-control custom-checkbox mb-2">
                                        <input type="checkbox" class="custom-control-input" id="apply_to_<?= $otherApp['id'] ?>"
                                            name="apply_to_other_apps[]" value="<?= cipher($otherApp['id']) ?>">
                                        <label class="custom-control-label font-weight-bold text-gray-800"
                                            for="apply_to_<?= $otherApp['id'] ?>">
                                            <?= e($otherApp['official_title']) ?>
                                            <span class="font-weight-normal text-muted small">(SG
                                                <?= e($otherApp['salary_grade']) ?>)</span>
                                            <?php if ($otherApp['current_score'] !== null): ?>
                                                <span class="badge badge-secondary ml-1">Current Points:
                                                    <?= number_format($otherApp['current_score'], 3, '.', '') ?></span>
                                            <?php else: ?>
                                                <span class="badge badge-light ml-1 text-secondary">No Points</span>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="text-right">
                        <input type="hidden" name="verifier" value="<?= cipher($applicationId) ?>">
                        <button type="submit" name="save-assessment-score" class="btn btn-primary px-5 shadow">
                            <i class="fas fa-save mr-1"></i> Save Assessment Points
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const scoreInputs = document.querySelectorAll('.score-input');
        const potentialFinalInput = document.getElementById('potential_final_score');
        const totalAccumulatedInput = document.getElementById('total_accumulated_score');

        const MAX_POTENTIAL = <?= $weights['potential'] ?>;
        const MAX_TOTAL = <?= $weights['total'] ?>;

        // Clamp a value to [0, max]
        function clamp(val, max) {
            return Math.min(Math.max(val, 0), max);
        }

        // Enforce per-input max on blur/change (highlight if over max)
        function enforceMax(input) {
            const max = parseFloat(input.dataset.max);
            if (isNaN(max)) return;
            const val = parseFloat(input.value) || 0;
            if (val > max) {
                input.classList.add('is-invalid');
            } else {
                input.classList.remove('is-invalid');
            }
        }

        function getVal(id) {
            return parseFloat(document.getElementById(id).value) || 0;
        }

        const examInput = document.getElementById('potential_written_exam_raw');
        const beiInput = document.getElementById('potential_bei_raw');
        const wstInput = document.getElementById('potential_wst_raw');

        // Dynamically update each raw field's max to MAX_POTENTIAL minus the other two
        function updateRawMaxValues() {
            const exam = getVal('potential_written_exam_raw');
            const bei = getVal('potential_bei_raw');
            const wst = getVal('potential_wst_raw');

            const examMax = Math.max(0, MAX_POTENTIAL - bei - wst);
            const beiMax = Math.max(0, MAX_POTENTIAL - exam - wst);
            const wstMax = Math.max(0, MAX_POTENTIAL - exam - bei);

            examInput.max = examMax.toFixed(3);
            examInput.dataset.max = examMax.toFixed(3);
            beiInput.max = beiMax.toFixed(3);
            beiInput.dataset.max = beiMax.toFixed(3);
            wstInput.max = wstMax.toFixed(3);
            wstInput.dataset.max = wstMax.toFixed(3);

            enforceMax(examInput);
            enforceMax(beiInput);
            enforceMax(wstInput);
        }

        function calculateScores() {
            const edu = clamp(getVal('education_score'), <?= $weights['education'] ?>);
            const tra = clamp(getVal('training_score'), <?= $weights['training'] ?>);
            const exp = clamp(getVal('experience_score'), <?= $weights['experience'] ?>);
            const perf = clamp(getVal('performance_score'), <?= $weights['performance'] ?>);
            const acc = clamp(getVal('outstanding_accomplishments_score'), <?= $weights['accomplishments'] ?>);
            const appEdu = clamp(getVal('application_of_education_score'), <?= $weights['application_edu'] ?>);
            const appLd = clamp(getVal('application_of_ld_score'), <?= $weights['application_ld'] ?>);

            const exam = getVal('potential_written_exam_raw');
            const bei = getVal('potential_bei_raw');
            const wst = getVal('potential_wst_raw');

            // Potential final is sum of raw scores, capped at the potential max
            const potFinal = clamp(exam + bei + wst, MAX_POTENTIAL);
            potentialFinalInput.value = potFinal.toFixed(3);

            const total = clamp(edu + tra + exp + perf + acc + appEdu + appLd + potFinal, MAX_TOTAL);
            totalAccumulatedInput.value = total.toFixed(3);
        }

        scoreInputs.forEach(input => {
            input.addEventListener('input', function () {
                if (this.classList.contains('potential-raw')) {
                    updateRawMaxValues();
                }
                enforceMax(this);
                calculateScores();
            });
            input.addEventListener('change', function () {
                if (this.classList.contains('potential-raw')) {
                    updateRawMaxValues();
                }
                enforceMax(this);
                calculateScores();
            });
        });

        // Initial run
        updateRawMaxValues();
        calculateScores();
        scoreInputs.forEach(enforceMax);
    });
</script>