<?php
// modules/pm/print-ipcrf-nt.php - Official non-teaching IPCRF print template
if (!$isPis) {
    require_once(root() . '/modules/error/403.php');
    return;
}

$ipcrfId = (int) sanitize(decode($_GET['id'] ?? null));
$ipcrf = pmIpcrf($ipcrfId);

if (!$ipcrf) {
    require_once(root() . '/modules/error/no-results-found.php');
    return;
}

$isOwner = ($userId === (int) $ipcrf['employee_id']);
$isValidator = ($userId === (int) $ipcrf['validator_id']);

if (!$isOwner && !$isValidator) {
    require_once(root() . '/modules/error/403.php');
    return;
}

$employee = employee($ipcrf['employee_id']);
$validator = $ipcrf['validator_id'] ? employee($ipcrf['validator_id']) : null;
$approvingOfficer = !empty($ipcrf['approving_officer_id']) ? employee($ipcrf['approving_officer_id']) : null;
$positionData = position($ipcrf['employee_id']);
$positionTitle = $ipcrf['position_title'] ?: ($positionData['official_title'] ?? '');
$objectives = pmObjectives($ipcrfId);
$developmentPlans = pmDevelopmentPlans($ipcrfId);
$competencyRatings = pmCompetencyRatings($ipcrfId);

$competencyIndex = [];
foreach ($competencyRatings as $cr) {
    $competencyIndex[$cr['category']][$cr['competency_number']] = $cr['rating'];
}

$rateePositionTitle = $ipcrf['position_title'] ?: ($positionData['official_title'] ?? '');
$isHead = stripos($rateePositionTitle, 'head') !== false ||
    stripos($rateePositionTitle, 'principal') !== false ||
    stripos($rateePositionTitle, 'supervisor') !== false ||
    stripos($rateePositionTitle, 'chief') !== false;

$coreBehavioralCompetencies = [
    'self_management' => [
        'title' => 'Self-Management',
        'items' => [
            1 => 'Sets personal goals and direction, needs and development.',
            2 => 'Undertakes personal actions and behaviors that are clear and purposive and takes into account personal goals and values congruent to that of the organization.',
            3 => 'Display emotional maturity and enthusiasm for and is challenged by higher goals.',
            4 => 'Prioritize work task and schedules (through Gantt charts, checklist, etc.) to achieve goals.',
            5 => 'Sets high quality, challenging, realistic goals for self and others.'
        ]
    ],
    'professionalism_ethics' => [
        'title' => 'Professionalism and Ethics',
        'items' => [
            1 => 'Demonstrate the values and behavior enshrined in the Norms or Conduct and Ethical Standards for public officials and employee (RA 6713).',
            2 => 'Practices ethical and professional behavior and conduct taking into account the impact of his/her actions and decisions.',
            3 => 'Maintains professional image: being trustworthy, regularity of attendance and punctuality, good grooming and communication.',
            4 => 'Makes personal sacrifices to meet the organization\'s needs.',
            5 => 'Acts with a sense of urgency and responsibility to meet the organization\'s needs, improves systems and help others improve their effectiveness.'
        ]
    ],
    'result_focus' => [
        'title' => 'Result Focus',
        'items' => [
            1 => 'Achieves results with optimal use of time and resources most of the time.',
            2 => 'Avoids rework, mistakes and wastage through effective work methods by placing organizational needs before personal needs.',
            3 => 'Delivers error-free outputs most of the time by conforming to standard operating procedures correctly and consistently. Able to produce very satisfactory quality of work in terms of usefulness/acceptability and completeness with no supervision required.',
            4 => 'Expresses a desire to do better and may express frustration at waste of inefficiency. May focus on new or more precise ways of meeting goals set.',
            5 => 'Make specific changes in the system or in own work methods to improve performance. Examples may include doing something better, faster, at lower cost, more efficiently; or improving quality, customer satisfaction, morale, without setting any specific goals.'
        ]
    ],
    'teamwork' => [
        'title' => 'Teamwork',
        'items' => [
            1 => 'Willingly does his/her share of responsibility.',
            2 => 'Promotes collaboration and removes barriers to teamwork and goal accomplishment across the organization.',
            3 => 'Applies negotiation principles in arriving at win-win agreements.',
            4 => 'Drives consensus and team ownership of decisions.',
            5 => 'Works constructively and collaboratively with others and across organizations to accomplish organizational goals and objectives.'
        ]
    ],
    'service_orientation' => [
        'title' => 'Service Orientation',
        'items' => [
            1 => 'Can explain and articulate organizational directions, issues and problems.',
            2 => 'Take personal responsibility for dealing with and/or correcting customer service issues and concern.',
            3 => 'Initiates activities that promotes advocacy for men and women empowerment.',
            4 => 'Participates in updating of office vision, mission, mandates and strategies based on DepEd strategies and directions.',
            5 => 'Develops and adopts service improvement programs through simplified procedures that will further enhance service delivery.'
        ]
    ],
    'innovation' => [
        'title' => 'Innovation',
        'items' => [
            1 => 'Examines the root cause of problems and suggests effective solution. Fosters new ideas, processes, and suggest better ways to do things (cost and/or operational efficiency).',
            2 => 'Demonstrates an ability to think "beyond the box". Continuously focused on improving personal productivity to create higher values and results.',
            3 => 'Promotes a creative climate and inspire co-workers to develop original ideas or solutions.',
            4 => 'Translates creative thinking into tangible changes and solutions that improve the work unit and organization.',
            5 => 'Uses ingenious methods to accomplish responsibilities. Demonstrate resourcefulness and the ability to succeed with minimal resources.'
        ]
    ]
];

$leadershipCompetencies = [
    'leading_people' => [
        'title' => 'Leading People',
        'items' => [
            1 => 'Uses basic persuasion techniques in a discussion or presentation e.g., staff mobilization, appeals to reason and/or emotions, uses data and examples, visual aids.',
            2 => 'Persuades, convinces or influences others, in order to have a specific impact or effect.',
            3 => '"Sets a good example", is a credible and respected leader; and demonstrates desired behavior.',
            4 => 'Forwards personal, professionalism and work unit needs and interest in an issue.',
            5 => 'Relevant vision for the organization and influences other to share ownership of DepEd goals, in order to create an effective work environment.'
        ]
    ],
    'people_performance' => [
        'title' => 'People Performance Management',
        'items' => [
            1 => 'Makes specific changes in the performance (e.g., does something better, faster, at lower cost, more efficiently; improves quality, customer satisfaction, morale, revenues).',
            2 => 'Set performance standards and measures progress of employees based on office and department targets.',
            3 => 'Provides feedback and technical assistance such as coaching for performance improvement and action planning.',
            4 => 'States performance expectations clearly and checks understanding and commitment.',
            5 => 'Performs all the stages of Result-Based Performance Management System supported by evidence and required documents/forms.'
        ]
    ],
    'people_development' => [
        'title' => 'People Development',
        'items' => [
            1 => 'Improves the skills and effectiveness of individuals through employing a range of development strategies.',
            2 => 'Facilitates workplace effectiveness through coaching and motivating/developing people a work environment that promotes mutual trust and respect.',
            3 => 'Conceptualizes and implements learning interventions to meet identified training needs.',
            4 => 'Does long term coaching or training by arranging appropriate and helpful assignments, formal training, or other experiences for the purpose of supporting a person\'s learning and development.',
            5 => 'Cultivates a learning environment by structuring interactive experiences such as looking for the future opportunities that are in support of achieving individual career goals.'
        ]
    ]
];

$coreSkills = [
    'oral_communication' => [
        'title' => 'Oral Communication',
        'items' => [
            1 => 'Follows instructions accurately.',
            2 => 'Expresses self clearly, fluently and articulately.',
            3 => 'Uses appropriate medium for the message.',
            4 => 'Adjust communication style to others.',
            5 => 'Guides discussions between and among peers to meet an objective.'
        ]
    ],
    'written_communication' => [
        'title' => 'Written Communication',
        'items' => [
            1 => 'Knows the different written business communication formats used in the DepEd.',
            2 => 'Write routine correspondence/communications, narrative and descriptive report based on ready available information data with minimal spelling or grammatical errors (e.g. Memos, minutes, etc.)',
            3 => 'Secures information from required references (i.e., Directories, schedules, notices, instruction) for specific purposes.',
            4 => 'Self-edits words, numbers, phonetic notation and content, if necessary.',
            5 => 'Demonstrates clarity, fluency, impact, conciseness, and effectiveness in his/her written communications.'
        ]
    ],
    'computer_ict' => [
        'title' => 'Computer / ICT Skills',
        'items' => [
            1 => 'Prepares basic composition (e.g., letters, reports, spreadsheets and graphic presentation using word processing and Excel.',
            2 => 'Identifies different computer parts, turns the computer on/off, and work on a given task with acceptable speed and accuracy and connects computer peripherals (e.g., printers, modems, multi-media projectors, etc.)',
            3 => 'Prepares simple presentations using Microsoft PowerPoint.',
            4 => 'Utilizes technologies to: access information to enhance professional productivity, assists in conducting research and communication through local and global professional networks.',
            5 => 'Recommends appropriate and updated technology to enhance productivity and professional practice.'
        ]
    ]
];

$coreBehavioralAvg = pmCompetencyCategoryAverage($ipcrfId, 'core_behavioral');
$leadershipAvg = pmCompetencyCategoryAverage($ipcrfId, 'leadership');
?>


<style>
    @page {
        size: landscape;
        margin: 10mm;
    }

    @media print {
        body * {
            visibility: hidden;
        }

        #print-area,
        #print-area * {
            visibility: visible;
        }

        #print-area {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            margin: 0;
            padding: 0;
        }

        .no-print {
            display: none !important;
        }

        .btn,
        .navbar,
        #accordionSidebar,
        .topbar {
            display: none !important;
        }
    }

    .ipcrf-table,
    .ipcrf-table th,
    .ipcrf-table td {
        border: 1px solid #000;
        border-collapse: collapse;
    }

    .ipcrf-table th,
    .ipcrf-table td {
        padding: 3px 5px;
        font-size: 10px;
        vertical-align: top;
    }

    .ipcrf-table th {
        text-align: center;
        font-weight: bold;
    }

    .section-title {
        text-align: center;
        font-weight: bold;
        border: 1px solid #000;
        padding: 4px;
        margin: 8px 0 0;
        font-size: 11px;
    }

    .print-header td {
        border: none;
        padding: 2px 4px;
        font-size: 11px;
    }

    .text-xs {
        font-size: 9px;
    }

    .text-center {
        text-align: center;
    }

    .text-right {
        text-align: right;
    }

    .signature-line {
        border-top: 1px solid #000;
        margin-top: 25px;
        padding-top: 2px;
        font-size: 10px;
    }

    .competency-col {
        width: 50%;
        vertical-align: top;
        padding: 3px;
    }

    .competency-sub {
        font-weight: bold;
        margin-bottom: 2px;
    }

    .competency-desc {
        margin-bottom: 2px;
        padding-left: 12px;
    }

    .competency-desc::before {
        content: attr(data-num);
        position: absolute;
        left: 0;
    }

    .competency-row {
        display: table;
        width: 100%;
    }

    .competency-text {
        display: table-cell;
        width: 92%;
        padding-right: 4px;
    }

    .competency-rating {
        display: table-cell;
        width: 8%;
        text-align: center;
        font-weight: bold;
    }

    .page-break {
        page-break-before: always;
    }

    .competency-table {
        width: 100%;
        border-collapse: collapse;
    }

    .competency-table td {
        border: 1px solid #000;
        vertical-align: top;
        padding: 3px;
    }
</style>

<div id="print-area" class="container-fluid mt-3">
    <div class="text-center no-print mb-3">
        <button onclick="window.print()" class="btn btn-primary btn-lg">
            <i class="fas fa-print mr-1"></i> Print IPCRF
        </button>
        <a href="<?= customUri('pis', 'IPCRF Details', $ipcrfId) ?>" class="btn btn-secondary btn-lg">
            <i class="fas fa-arrow-left mr-1"></i> Back
        </a>
    </div>

    <h5 class="text-center font-weight-bold text-uppercase mb-3" style="font-size: 13px;">
        Individual Performance Commitment and Review Form <?= date('Y') ?><br>
        <span class="text-muted" style="font-size: 11px;">DepEd Schools Division of Dipolog City</span>
    </h5>

    <table class="print-header" width="100%">
        <tr>
            <td width="70%"><strong>Name:</strong>
                <?= strtoupper(e(toName($employee['last_name'], $employee['first_name'], $employee['middle_name'], $employee['name_extension'], true))) ?>
            </td>
            <td width="30%"><strong>Name of Rater:</strong>
                <?= $validator ? strtoupper(e(toName($validator['last_name'], $validator['first_name'], $validator['middle_name'], $validator['name_extension'], true))) : '________________' ?>
            </td>
        </tr>
        <tr>
            <td><strong>Position:</strong> <?= e($positionTitle) ?></td>
            <td><strong>Position:</strong>
                <?= $validator ? e(position($validator['id'])['official_title'] ?? '') : '________________' ?>
            </td>
        </tr>
        <tr>
            <td><strong>Review Period:</strong> <?= e($ipcrf['review_period'] ?? $ipcrf['school_year']) ?></td>
            <td><strong>Review Date:</strong>
                <?= $ipcrf['validated_at'] ? date('F j, Y', strtotime($ipcrf['validated_at'])) : '________________' ?>
            </td>
        </tr>
        <tr>
            <td><strong>Division:</strong> Dipolog City</td>
            <td><strong>Rating Period:</strong> <?= e($ipcrf['school_year']) ?></td>
        </tr>
    </table>

    <h6 class="section-title">Performance Commitment and Review</h6>

    <table class="ipcrf-table" width="100%">
        <thead>
            <tr>
                <th rowspan="2" width="12%">KRA</th>
                <th rowspan="2" width="15%">Objectives</th>
                <th rowspan="2" width="10%">Timeline</th>
                <th rowspan="2" width="6%">Weight per KRA</th>
                <th colspan="5" width="32%">Performance Indicator<br><span class="text-xs">(5-Outstanding, 4-Very
                        Satisfactory, 3-Satisfactory, 2-Unsatisfactory, 1-Poor)</span></th>
                <th rowspan="2" width="6%">Actual Results</th>
                <th rowspan="2" width="3%">Q</th>
                <th rowspan="2" width="3%">E</th>
                <th rowspan="2" width="3%">T</th>
                <th rowspan="2" width="4%">Ave</th>
                <th rowspan="2" width="6%">Score</th>
            </tr>
            <tr>
                <th class="text-xs">5</th>
                <th class="text-xs">4</th>
                <th class="text-xs">3</th>
                <th class="text-xs">2</th>
                <th class="text-xs">1</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($objectives)):
                $i = 1;
                $totalScore = 0;
                // Pre-calculate rowspans for KRA grouping
                $kraRowspans = [];
                foreach ($objectives as $obj) {
                    $key = strtolower(trim($obj['kra_title']));
                    if (!isset($kraRowspans[$key])) {
                        $kraRowspans[$key] = 0;
                    }
                    $kraRowspans[$key]++;
                }
                $kraRendered = []; ?>
                <?php foreach ($objectives as $obj):
                    $q = $obj['rating_q'] ?? null;
                    $e = $obj['rating_e'] ?? null;
                    $t = $obj['rating_t'] ?? null;
                    $ave = $obj['average'] ?? ($q !== null && $e !== null && $t !== null ? round(($q + $e + $t) / 3, 2) : null);
                    $score = $obj['score'] ?? ($ave !== null && !empty($obj['weight']) ? round($ave * ($obj['weight'] / 100), 4) : null);
                    if ($score !== null)
                        $totalScore += $score;
                    $perfInd = !empty($obj['performance_indicators']) ? $obj['performance_indicators'] : $obj['performance_indicator'];
                    $kraKey = strtolower(trim($obj['kra_title']));
                    $isFirstInKra = !isset($kraRendered[$kraKey]);
                    if ($isFirstInKra) $kraRendered[$kraKey] = true;
                    ?>
                    <tr>
                        <?php if ($isFirstInKra): ?>
                            <td rowspan="<?= $kraRowspans[$kraKey] ?>" style="vertical-align: middle;"><?= e($obj['kra_title']) ?></td>
                        <?php endif; ?>
                        <td><?= e($obj['objective']) ?></td>
                        <td class="text-center"><?= e($obj['timeline'] ?? '-') ?></td>
                        <td class="text-center"><?= e($obj['weight'] ?? '0') ?>%</td>
                        <td colspan="5" class="text-xs"><?= e($perfInd) ?></td>
                        <td class="text-center"><?= e($obj['actual_results'] ?? $obj['actual_result'] ?? '-') ?></td>
                        <td class="text-center"><?= $q !== null ? e($q) : '-' ?></td>
                        <td class="text-center"><?= $e !== null ? e($e) : '-' ?></td>
                        <td class="text-center"><?= $t !== null ? e($t) : '-' ?></td>
                        <td class="text-center"><?= $ave !== null ? e(number_format($ave, 2)) : '-' ?></td>
                        <td class="text-center"><?= $score !== null ? e(number_format($score, 4)) : '-' ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="14" class="text-right font-weight-bold">Total</td>
                    <td class="text-center font-weight-bold">
                        <?= e($ipcrf['adjectival_rating'] ?? 'N/A') ?>
                        <?= number_format($ipcrf['final_rating'] ?? $totalScore, 2) ?>
                    </td>
                </tr>
            <?php else: ?>
                <tr>
                    <td colspan="15" class="text-center">No objectives found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <table width="100%" style="margin-top: 15px;">
        <tr>
            <td width="33%" class="text-center">
                <div class="signature-line">Ratee</div>
                <small><?= strtoupper(e(toName($employee['last_name'], $employee['first_name'], $employee['middle_name'], $employee['name_extension'], true))) ?><br><?= e($positionTitle) ?></small>
            </td>
            <td width="33%" class="text-center">
                <div class="signature-line">Rater</div>
                <small><?= $validator ? strtoupper(e(toName($validator['last_name'], $validator['first_name'], $validator['middle_name'], $validator['name_extension'], true))) : '' ?><br><?= $validator ? e(position($validator['id'])['official_title'] ?? '') : '' ?></small>
            </td>
            <td width="33%" class="text-center">
                <div class="signature-line">Approved by:</div>
                <small><?= $approvingOfficer ? strtoupper(e(toName($approvingOfficer['last_name'], $approvingOfficer['first_name'], $approvingOfficer['middle_name'], $approvingOfficer['name_extension'], true))) : '' ?><br><?= $approvingOfficer ? e(position($approvingOfficer['id'])['official_title'] ?? '') : '' ?></small>
            </td>
        </tr>
    </table>

    <h6 class="section-title page-break" style="margin-top: 15px;">Summary of Ratings for Discussion</h6>
    <?php
    $finalRateForPrint = $ipcrf['final_rating'] ?? ($totalScore ?? 0);
    ?>
    <table class="ipcrf-table" width="100%">
        <tr>
            <th width="50%">Finals Performance Results</th>
            <th width="50%">Accomplishments of KRAs and Objectives</th>
        </tr>
        <tr>
            <td class="text-center font-weight-bold"><?= number_format($finalRateForPrint, 3) ?></td>
            <td class="text-center font-weight-bold"><?= number_format($finalRateForPrint, 3) ?></td>
        </tr>
    </table>

    <p class="text-xs mt-2 mb-1"><strong>Employee-Superior Agreement</strong></p>
    <p class="text-xs mb-1">This signature below confirms that the employee and his/her superior have agreed to the
        contents of the performance as captured in this form.</p>
    <table class="ipcrf-table" width="100%">
        <tr>
            <th width="50%">Name of Employee:
                <?= strtoupper(e(toName($employee['last_name'], $employee['first_name'], $employee['middle_name'], $employee['name_extension'], true))) ?>
            </th>
            <th width="50%">Name of Superior:
                <?= $validator ? strtoupper(e(toName($validator['last_name'], $validator['first_name'], $validator['middle_name'], $validator['name_extension'], true))) : '' ?>
            </th>
        </tr>
        <tr>
            <td>Signature: ________________________________</td>
            <td>Signature: ________________________________</td>
        </tr>
        <tr>
            <td>Date: ________________________________</td>
            <td>Date: ________________________________</td>
        </tr>
    </table>

    <h6 class="section-title">Development Plans</h6>
    <table class="ipcrf-table" width="100%">
        <thead>
            <tr>
                <th width="18%">Strengths</th>
                <th width="18%">Development Needs</th>
                <th width="26%">Action Plan (Recommended Development Intervention)</th>
                <th width="18%">Timeline</th>
                <th width="20%">Resources Needed</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($developmentPlans)): ?>
                <?php foreach ($developmentPlans as $dp): ?>
                    <tr>
                        <td><?= e($dp['strengths']) ?></td>
                        <td><?= e($dp['development_needs']) ?></td>
                        <td><?= e($dp['action_plan']) ?></td>
                        <td class="text-center"><?= e($dp['timeline']) ?></td>
                        <td><?= e($dp['resources']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="text-center">No development plan recorded.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <h6 class="section-title">Core Competencies</h6>

    <table class="competency-table" style="border: 1px solid #000; margin-top: 1px;">
        <tr>
            <td class="competency-col" style="border: 1px solid #000; vertical-align: top; padding: 3px;">
                <?php foreach (['self_management', 'professionalism_ethics', 'result_focus'] as $key): ?>
                    <?php $competency = $coreBehavioralCompetencies[$key];
                    $category = 'core_behavioral'; ?>
                    <table class="ipcrf-table" width="100%" style="margin-bottom: 4px;">
                        <thead>
                            <tr>
                                <th class="text-left" colspan="3"><?= e($competency['title']) ?></th>
                            </tr>
                            <tr>
                                <th width="6%">#</th>
                                <th>Indicator</th>
                                <th width="10%">Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($competency['items'] as $num => $desc):
                                $rating = $competencyIndex[$category][$key . '_' . $num] ?? '-'; ?>
                                <tr>
                                    <td class="text-center"><?= $num ?></td>
                                    <td class="text-xs"><?= e($desc) ?></td>
                                    <td class="text-center font-weight-bold"><?= e($rating) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>
            </td>
            <td class="competency-col" style="border: 1px solid #000; vertical-align: top; padding: 3px;">
                <?php foreach (['teamwork', 'service_orientation', 'innovation'] as $key): ?>
                    <?php $competency = $coreBehavioralCompetencies[$key];
                    $category = 'core_behavioral'; ?>
                    <table class="ipcrf-table" width="100%" style="margin-bottom: 4px;">
                        <thead>
                            <tr>
                                <th class="text-left" colspan="3"><?= e($competency['title']) ?></th>
                            </tr>
                            <tr>
                                <th width="6%">#</th>
                                <th>Indicator</th>
                                <th width="10%">Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($competency['items'] as $num => $desc):
                                $rating = $competencyIndex[$category][$key . '_' . $num] ?? '-'; ?>
                                <tr>
                                    <td class="text-center"><?= $num ?></td>
                                    <td class="text-xs"><?= e($desc) ?></td>
                                    <td class="text-center font-weight-bold"><?= e($rating) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>
            </td>
        </tr>
    </table>

    <?php $coreSkillsAvg = pmCompetencyCategoryAverage($ipcrfId, 'core_skills'); ?>
    <h6 class="section-title page-break" style="margin-top: 15px;">Core Skills</h6>
    <table class="competency-table" style="border: 1px solid #000; margin-top: 1px;">
        <tr>
            <td class="competency-col" style="border: 1px solid #000; vertical-align: top; padding: 3px;">
                <?php foreach (['oral_communication'] as $key): ?>
                    <?php $competency = $coreSkills[$key];
                    $category = 'core_skills'; ?>
                    <table class="ipcrf-table" width="100%" style="margin-bottom: 4px;">
                        <thead>
                            <tr>
                                <th class="text-left" colspan="3"><?= e($competency['title']) ?></th>
                            </tr>
                            <tr>
                                <th width="6%">#</th>
                                <th>Indicator</th>
                                <th width="10%">Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($competency['items'] as $num => $desc):
                                $rating = $competencyIndex[$category][$key . '_' . $num] ?? '-'; ?>
                                <tr>
                                    <td class="text-center"><?= $num ?></td>
                                    <td class="text-xs"><?= e($desc) ?></td>
                                    <td class="text-center font-weight-bold"><?= e($rating) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>
            </td>
            <td class="competency-col" style="border: 1px solid #000; vertical-align: top; padding: 3px;">
                <?php foreach (['written_communication', 'computer_ict'] as $key): ?>
                    <?php $competency = $coreSkills[$key];
                    $category = 'core_skills'; ?>
                    <table class="ipcrf-table" width="100%" style="margin-bottom: 4px;">
                        <thead>
                            <tr>
                                <th class="text-left" colspan="3"><?= e($competency['title']) ?></th>
                            </tr>
                            <tr>
                                <th width="6%">#</th>
                                <th>Indicator</th>
                                <th width="10%">Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($competency['items'] as $num => $desc):
                                $rating = $competencyIndex[$category][$key . '_' . $num] ?? '-'; ?>
                                <tr>
                                    <td class="text-center"><?= $num ?></td>
                                    <td class="text-xs"><?= e($desc) ?></td>
                                    <td class="text-center font-weight-bold"><?= e($rating) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>
            </td>
        </tr>
    </table>

    <?php if ($isHead): ?>
        <h6 class="section-title" style="margin-top: 15px;">Leadership Competencies</h6>
        <table class="competency-table" style="border: 1px solid #000; margin-top: 1px;">
            <tr>
                <td class="competency-col" style="border: 1px solid #000; vertical-align: top; padding: 3px;">
                    <?php foreach (['leading_people', 'people_performance'] as $key): ?>
                        <?php $competency = $leadershipCompetencies[$key];
                        $category = 'leadership'; ?>
                        <table class="ipcrf-table" width="100%" style="margin-bottom: 4px;">
                            <thead>
                                <tr>
                                    <th class="text-left" colspan="3"><?= e($competency['title']) ?></th>
                                </tr>
                                <tr>
                                    <th width="6%">#</th>
                                    <th>Indicator</th>
                                    <th width="10%">Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($competency['items'] as $num => $desc):
                                    $rating = $competencyIndex[$category][$key . '_' . $num] ?? '-'; ?>
                                    <tr>
                                        <td class="text-center"><?= $num ?></td>
                                        <td class="text-xs"><?= e($desc) ?></td>
                                        <td class="text-center font-weight-bold"><?= e($rating) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endforeach; ?>
                </td>
                <td class="competency-col" style="border: 1px solid #000; vertical-align: top; padding: 3px;">
                    <?php foreach (['people_development'] as $key): ?>
                        <?php $competency = $leadershipCompetencies[$key];
                        $category = 'leadership'; ?>
                        <table class="ipcrf-table" width="100%" style="margin-bottom: 4px;">
                            <thead>
                                <tr>
                                    <th class="text-left" colspan="3"><?= e($competency['title']) ?></th>
                                </tr>
                                <tr>
                                    <th width="6%">#</th>
                                    <th>Indicator</th>
                                    <th width="10%">Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($competency['items'] as $num => $desc):
                                    $rating = $competencyIndex[$category][$key . '_' . $num] ?? '-'; ?>
                                    <tr>
                                        <td class="text-center"><?= $num ?></td>
                                        <td class="text-xs"><?= e($desc) ?></td>
                                        <td class="text-center font-weight-bold"><?= e($rating) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endforeach; ?>
                </td>
            </tr>
        </table>
    <?php endif; ?>

    <?php
    $overallCompetency = 0;
    if (count($competencyRatings)) {
        $overallCompetency = array_sum(array_column($competencyRatings, 'rating')) / count($competencyRatings);
    }
    ?>
    <?php
    $secondLabel = $isHead ? 'Leadership Competencies' : 'Core Skills';
    $secondValue = $isHead ? $leadershipAvg : $coreSkillsAvg;
    ?>
    <table class="ipcrf-table" width="100%" style="margin-top: 10px;">
        <tr>
            <th class="text-right" width="20%">Core Behavioral Competencies</th>
            <td class="text-center font-weight-bold" width="12%">
                <?= $coreBehavioralAvg ? number_format($coreBehavioralAvg, 2) : '-' ?>
            </td>
            <th class="text-right" width="20%"><?= e($secondLabel) ?></th>
            <td class="text-center font-weight-bold" width="12%">
                <?= $secondValue ? number_format($secondValue, 2) : '-' ?>
            </td>
        </tr>
        <tr>
            <th class="text-right" colspan="2">Overall Rating</th>
            <td class="text-center font-weight-bold" colspan="2"><?= number_format($overallCompetency, 3) ?></td>
        </tr>
    </table>

    <p class="text-xs mt-1">5 - Role Model; 4 - Consistently Demonstrates; 3 - Most of the Time Demonstrates; 2 -
        Sometimes Demonstrates; 1 - Rarely Demonstrates</p>

    <table width="100%" style="margin-top: 20px;">
        <tr>
            <td width="33%" class="text-center">
                <div class="signature-line">Rater</div>
                <small><?= $validator ? strtoupper(e(toName($validator['last_name'], $validator['first_name'], $validator['middle_name'], $validator['name_extension'], true))) : '' ?></small>
            </td>
            <td width="33%" class="text-center">
                <div class="signature-line">Ratee</div>
                <small><?= strtoupper(e(toName($employee['last_name'], $employee['first_name'], $employee['middle_name'], $employee['name_extension'], true))) ?></small>
            </td>
            <td width="33%" class="text-center">
                <div class="signature-line">Approving Officer</div>
                <small><?= $approvingOfficer ? strtoupper(e(toName($approvingOfficer['last_name'], $approvingOfficer['first_name'], $approvingOfficer['middle_name'], $approvingOfficer['name_extension'], true))) : '' ?></small>
            </td>
        </tr>
    </table>
</div>