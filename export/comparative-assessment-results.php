<?php
// export/comparative-assessment-results.php
if (!isset($_GET['v']) || empty($_GET['v'])) {
    require_once('../includes/function.php');
    redirect("{$baseUri}/login");
}

require_once(root() . '/includes/database/employee.php');
require_once(root() . '/includes/database/vacancy.php');

$publicationId = isset($_GET['id']) ? sanitize(decode($_GET['id'])) : null;
$positionId = isset($_GET['pos']) ? sanitize(decode($_GET['pos'])) : null;

if (empty($publicationId)) {
    redirect(uri() . '/login');
}

$publication = publication($publicationId);

$pubItems = publicationItems($publicationId);
$positions = [];
foreach ($pubItems as $item) {
    if ($positionId !== null && $item['position_id'] != $positionId) {
        continue;
    }
    $posDetails = find("SELECT `category` FROM `positions` WHERE `id` = ?", [$item['position_id']]);
    $category = $posDetails ? $posDetails['category'] : 'N/A';

    $positions[$item['position_id']] = [
        'id' => $item['position_id'],
        'official_title' => $item['official_title'],
        'salary_grade' => $item['salary_grade'],
        'category' => $category
    ];
}

uasort($positions, function ($a, $b) {
    return strcmp($a['official_title'], $b['official_title']);
});

$results = qualifiedApplicantsAssessmentResults($publicationId);
$resultsByPosition = [];
if ($results) {
    foreach ($results as $res) {
        $resultsByPosition[$res['position_id']][] = $res;
    }
}

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
            'pbet_let_lept' => (float) ($w['pbet_let_lept_max_points'] ?? 0),
            'ppst_cot' => (float) ($w['ppst_cot_max_points'] ?? 0),
            'ppst_reflection' => (float) ($w['ppst_reflection_max_points'] ?? 0),
            'ppst' => (float) ($w['ppst_max_points'] ?? 0),
            'bei' => (float) ($w['bei_max_points'] ?? 0),
            'total' => (float) $w['total_max_points'],
        ];
    }
}
?>

<table>
    <tbody>
        <tr>
            <td colspan="14" style="font-weight: bold; font-size: 16px; text-align: center;">COMPARATIVE ASSESSMENT
                RESULTS</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Code:</td>
            <td colspan="13" style="mso-number-format:'\@';"><?= e($publication['code'] ?? 'N/A') ?></td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Title:</td>
            <td colspan="13"><?= e($publication['title'] ?? 'N/A') ?></td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Description:</td>
            <td colspan="13"><?= e($publication['description'] ?? 'N/A') ?>
            </td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Export Date:</td>
            <td colspan="13"><?= date('F d, Y g:i A') ?></td>
        </tr>
    </tbody>
</table>

<?php foreach ($positions as $posId => $pos):
    $posResults = $resultsByPosition[$posId] ?? [];
    $sgLabel = getScoringCategoryLabel($pos['salary_grade'], $pos['category'], $pos['official_title']);
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
    $isTeacher2to7orMT = ($sgLabel === 'Teacher II to Teacher III and Master Teacher I to Master Teacher III' || $sgLabel === 'Teacher II to Teacher III' || $sgLabel === 'Teacher II to Teacher VII' || $sgLabel === 'Teacher III to Teacher VII' || $sgLabel === 'Master Teacher I to Master Teacher III');
    $isTeacher1 = ($sgLabel === 'Teacher I (DO 007 s. 2023)');
    $colspan = ($isTeacher2to7orMT || $isTeacher1) ? 14 : 16;
    ?>
    <table>
        <thead>
            <tr>
                <th colspan="<?= $colspan ?>" style="font-weight: bold; background-color: #d0d0d0; font-size: 14px; text-align: left;">
                    POSITION: <?= strtoupper(e($pos['official_title'])) ?> (SG <?= e($pos['salary_grade']) ?>)
                </th>
            </tr>
            <tr>
                <th style="font-weight: bold; background-color: #f2f2f2; border: 1px solid #000000; text-align: center;">
                    Rank</th>
                <th style="font-weight: bold; background-color: #f2f2f2; border: 1px solid #000000; text-align: center;">
                    Applicant Code</th>
                <th style="font-weight: bold; background-color: #f2f2f2; border: 1px solid #000000; text-align: center;">
                    Last Name</th>
                <th style="font-weight: bold; background-color: #f2f2f2; border: 1px solid #000000; text-align: center;">
                    First Name</th>
                <th style="font-weight: bold; background-color: #f2f2f2; border: 1px solid #000000; text-align: center;">
                    Name Extension</th>
                <th style="font-weight: bold; background-color: #f2f2f2; border: 1px solid #000000; text-align: center;">
                    Middle Name</th>
                <th style="font-weight: bold; background-color: #f2f2f2; border: 1px solid #000000; text-align: center;">
                    Education</th>
                <th style="font-weight: bold; background-color: #f2f2f2; border: 1px solid #000000; text-align: center;">
                    Training</th>
                <th style="font-weight: bold; background-color: #f2f2f2; border: 1px solid #000000; text-align: center;">
                    Experience</th>
                <?php if ($isTeacher2to7orMT): ?>
                    <th style="font-weight: bold; background-color: #f2f2f2; border: 1px solid #000000; text-align: center;">
                        Performance</th>
                    <th style="font-weight: bold; background-color: #f2f2f2; border: 1px solid #000000; text-align: center;">
                        PPST</th>
                    <th style="font-weight: bold; background-color: #f2f2f2; border: 1px solid #000000; text-align: center;">
                        BEI</th>
                <?php elseif ($isTeacher1): ?>
                    <th style="font-weight: bold; background-color: #f2f2f2; border: 1px solid #000000; text-align: center;">
                        PBET/LET/LEPT</th>
                    <th style="font-weight: bold; background-color: #f2f2f2; border: 1px solid #000000; text-align: center;">
                        PPST COI</th>
                    <th style="font-weight: bold; background-color: #f2f2f2; border: 1px solid #000000; text-align: center;">
                        PPST Reflection</th>
                <?php else: ?>
                    <th style="font-weight: bold; background-color: #f2f2f2; border: 1px solid #000000; text-align: center;">
                        Performance</th>
                    <th style="font-weight: bold; background-color: #f2f2f2; border: 1px solid #000000; text-align: center;">
                        Outstanding Accomplishments</th>
                    <th style="font-weight: bold; background-color: #f2f2f2; border: 1px solid #000000; text-align: center;">
                        Application of Education</th>
                    <th style="font-weight: bold; background-color: #f2f2f2; border: 1px solid #000000; text-align: center;">
                        Application of L&D</th>
                    <th style="font-weight: bold; background-color: #f2f2f2; border: 1px solid #000000; text-align: center;">
                        Potential</th>
                <?php endif; ?>
                <th style="font-weight: bold; background-color: #f2f2f2; border: 1px solid #000000; text-align: center;">
                    Total Score</th>
                <th style="font-weight: bold; background-color: #f2f2f2; border: 1px solid #000000; text-align: center;">
                    Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($posResults) > 0):
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
                foreach ($posResults as $index => $res):
                    $applicantId = $res['application_code_id'] ?? applicantId($res['application_code']);
                    $person = $applicantId ? (employee($applicantId) ?: applicant($applicantId)) : null;
                    $lastName = $person['last_name'] ?? '';
                    $firstName = $person['first_name'] ?? '';
                    $nameExtension = $person['name_extension'] ?? '';
                    $middleName = $person['middle_name'] ?? '';
                    $isAssessed = ($res['total_accumulated_score'] !== null);
                    ?>
                    <tr style="text-transform: uppercase;">
                        <td style="text-align: center; border: 1px solid #000000;"><?= $ranks[$index] ?></td>
                        <td style="text-align: center; border: 1px solid #000000; mso-number-format:'\@';">
                            <?= e($res['application_code'] ?? '') ?></td>
                        <td style="border: 1px solid #000000;"><?= strtoupper(e($lastName)) ?></td>
                        <td style="border: 1px solid #000000;"><?= strtoupper(e($firstName)) ?></td>
                        <td style="border: 1px solid #000000;"><?= strtoupper(e($nameExtension)) ?></td>
                        <td style="border: 1px solid #000000;"><?= strtoupper(e($middleName)) ?></td>
                        <td style="text-align: center; border: 1px solid #000000;">
                            <?= $isAssessed ? number_format($res['education_score'], 3, '.', '') : '0.000' ?>
                        </td>
                        <td style="text-align: center; border: 1px solid #000000;">
                            <?= $isAssessed ? number_format($res['training_score'], 3, '.', '') : '0.000' ?>
                        </td>
                        <td style="text-align: center; border: 1px solid #000000;">
                            <?= $isAssessed ? number_format($res['experience_score'], 3, '.', '') : '0.000' ?>
                        </td>
                        <?php if ($isTeacher2to7orMT): ?>
                            <td style="text-align: center; border: 1px solid #000000;">
                                <?= $isAssessed ? number_format($res['performance_score'], 3, '.', '') : '0.000' ?>
                            </td>
                            <td style="text-align: center; border: 1px solid #000000;">
                                <?= $isAssessed ? number_format($res['ppst_score'] ?? $res['ppst_cot_score'] ?? 0, 3, '.', '') : '0.000' ?>
                            </td>
                            <td style="text-align: center; border: 1px solid #000000;">
                                <?= $isAssessed ? number_format($res['bei_score'] ?? $res['potential_bei_raw'] ?? $res['potential_final_score'] ?? 0, 3, '.', '') : '0.000' ?>
                            </td>
                        <?php elseif ($isTeacher1): ?>
                            <td style="text-align: center; border: 1px solid #000000;">
                                <?= $isAssessed ? number_format($res['pbet_let_lept_score'] ?? 0, 3, '.', '') : '0.000' ?>
                            </td>
                            <td style="text-align: center; border: 1px solid #000000;">
                                <?= $isAssessed ? number_format($res['ppst_cot_score'] ?? 0, 3, '.', '') : '0.000' ?>
                            </td>
                            <td style="text-align: center; border: 1px solid #000000;">
                                <?= $isAssessed ? number_format($res['ppst_reflection_score'] ?? 0, 3, '.', '') : '0.000' ?>
                            </td>
                        <?php else: ?>
                            <td style="text-align: center; border: 1px solid #000000;">
                                <?= $isAssessed ? number_format($res['performance_score'], 3, '.', '') : '0.000' ?>
                            </td>
                            <td style="text-align: center; border: 1px solid #000000;">
                                <?= $isAssessed ? number_format($res['outstanding_accomplishments_score'], 3, '.', '') : '0.000' ?>
                            </td>
                            <td style="text-align: center; border: 1px solid #000000;">
                                <?= $isAssessed ? number_format($res['application_of_education_score'], 3, '.', '') : '0.000' ?>
                            </td>
                            <td style="text-align: center; border: 1px solid #000000;">
                                <?= $isAssessed ? number_format($res['application_of_ld_score'], 3, '.', '') : '0.000' ?>
                            </td>
                            <td style="text-align: center; border: 1px solid #000000;">
                                <?= $isAssessed ? number_format($res['potential_final_score'], 3, '.', '') : '0.000' ?>
                            </td>
                        <?php endif; ?>
                        <td style="text-align: center; font-weight: bold; border: 1px solid #000000;">
                            <?= $isAssessed ? number_format($res['total_accumulated_score'], 3, '.', '') : 'Not Assessed' ?>
                        </td>
                        <td style="border: 1px solid #000000;"><?= e($res['hrmspb_remarks'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="<?= $colspan ?>" style="text-align: center; font-style: italic; color: #777; border: 1px solid #000000;">No
                        qualified applicants have been assessed for this position yet.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
<?php endforeach; ?>