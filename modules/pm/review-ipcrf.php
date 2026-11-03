<?php
// modules/pm/review-ipcrf.php - Validator/Rater review page
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

$isValidator = ($userId === (int) $ipcrf['validator_id']);

if (!$isValidator) {
    require_once(root() . '/modules/error/403.php');
    return;
}

$employee = employee($ipcrf['employee_id']);
$validator = employee($ipcrf['validator_id']);
$objectives = pmObjectives($ipcrfId);
$movList = pmMovByIpcrf($ipcrfId);
$adjustmentRequests = pmAdjustmentRequests($ipcrfId, 'Pending');

$canApprove = ($ipcrf['status'] === 'Submitted');
$canRate = ($ipcrf['status'] === 'Approved' || $ipcrf['status'] === 'Validated');

// Group objectives by KRA for display (each KRA title forms a distinct group)
$kraGroups = [];
foreach ($objectives as $obj) {
    $groupKey = $obj['kra_id'] . '|' . strtolower(trim($obj['kra_title']));
    if (!isset($kraGroups[$groupKey])) {
        $kraGroups[$groupKey] = [
            'title' => $obj['kra_title'],
            'weight' => $obj['kra_weight'],
            'objectives' => []
        ];
    }
    $kraGroups[$groupKey]['objectives'][] = $obj;
}

// Overall rating totals
$totalWeight = 0;
$totalScore = 0;
foreach ($objectives as $obj) {
    $totalWeight += (float) $obj['weight'];
    $totalScore += ($obj['score'] !== null)
        ? (float) $obj['score']
        : ((float) ($obj['average_rating'] ?? 0) * (float) $obj['weight'] / 100);
}
$adjectivalRating = pmAdjectivalRating($totalScore);

// Get position titles
$rateePosition = position($ipcrf['employee_id']);
$rateePositionTitle = $ipcrf['position_title'] ?: ($rateePosition['official_title'] ?? '');
$validatorPosition = position($validator['id']);
$validatorPositionTitle = $validatorPosition['official_title'] ?? '';

messageAlert($showAlert, $message, $success);
?>

<div class="d-flex align-items-center justify-content-between flex-row mt-2 mb-3">
    <nav class="d-flex align-items-center flex-row m-0">
        <ol class="breadcrumb m-0 p-0 bg-transparent">
            <li class="breadcrumb-item"><a href="<?= uri() . '/' . $activeApp ?>">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= customUri('pis', 'IPCRF', $userId) ?>">IPCRF</a></li>
            <li class="breadcrumb-item active">Review IPCRF</li>
        </ol>
    </nav>
    <div>
        <?= pmStatusBadge($ipcrf['status']) ?>
        <span class="badge badge-light px-2 py-1 ml-1">Phase <?= e($ipcrf['phase']) ?></span>
    </div>
</div>

<!-- Pending Adjustment Requests Alert -->
<?php if (!empty($adjustmentRequests)): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle mr-1"></i>
        <strong><?= count($adjustmentRequests) ?> pending adjustment request(s)</strong> from the ratee.
        <a href="#adjustment-requests" class="alert-link">View requests</a>
    </div>
<?php endif; ?>

<!-- IPCRF Header Card -->
<div class="card border-left-success shadow mb-4">
    <div class="card-header py-3 bg-success text-white">
        <h6 class="m-0 font-weight-bold text-center">
            INDIVIDUAL PERFORMANCE COMMITMENT AND REVIEW FORM <?= e($ipcrf['school_year']) ?>
        </h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="font-weight-bold" width="30%">Ratee Name:</td>
                        <td class="text-uppercase"><?= e(toName($employee['last_name'], $employee['first_name'], $employee['middle_name'], $employee['name_extension'])) ?></td>
                    </tr>
                    <tr>
                        <td class="font-weight-bold">Position:</td>
                        <td><?= e($rateePositionTitle) ?></td>
                    </tr>
                    <tr>
                        <td class="font-weight-bold">Review Period:</td>
                        <td><?= e($ipcrf['review_period'] ?? $ipcrf['school_year']) ?></td>
                    </tr>
                    <tr>
                        <td class="font-weight-bold">Division:</td>
                        <td>Dipolog City</td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="font-weight-bold" width="30%">Rater Name:</td>
                        <td class="text-uppercase"><?= e(toName($validator['last_name'], $validator['first_name'], $validator['middle_name'], $validator['name_extension'])) ?></td>
                    </tr>
                    <tr>
                        <td class="font-weight-bold">Position:</td>
                        <td><?= e($validatorPositionTitle) ?></td>
                    </tr>
                    <tr>
                        <td class="font-weight-bold">Submitted:</td>
                        <td><?= $ipcrf['submitted_at'] ? date('F j, Y g:i A', strtotime($ipcrf['submitted_at'])) : '-' ?></td>
                    </tr>
                    <tr>
                        <td class="font-weight-bold">Rating Period:</td>
                        <td><?= e($ipcrf['school_year']) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <?php if ($ipcrf['ratee_remarks']): ?>
            <div class="alert alert-info mt-3 mb-0">
                <strong>Ratee Remarks:</strong> <?= nl2br(e($ipcrf['ratee_remarks'])) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Rating Form -->
<?php if ($canApprove || $canRate): ?>
<form action="" method="POST" id="rating-form">
    <?= csrf_field() ?>
    <input type="hidden" name="verifier" value="<?= cipher($ipcrfId) ?>">
<?php endif; ?>

<!-- Objectives Rating Table -->
<div class="card shadow mb-4">
    <div class="card-header py-2 bg-light">
        <h6 class="m-0 font-weight-bold text-dark">
            <i class="fas fa-clipboard-check mr-1"></i> Performance Objectives - Rating
        </h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-sm mb-0">
                <thead class="bg-light">
                    <tr class="text-center small">
                        <th width="12%">KRA</th>
                        <th width="18%">Objectives</th>
                        <th width="6%">Timeline</th>
                        <th width="4%">Wt%</th>
                        <th width="20%">Performance Indicator</th>
                        <th width="10%">Actual Results</th>
                        <th width="4%">Q</th>
                        <th width="4%">E</th>
                        <th width="4%">T</th>
                        <th width="4%">Ave</th>
                        <th width="6%">Score</th>
                        <th width="8%">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($kraGroups as $kraId => $kra): ?>
                        <?php $firstRow = true; $rowCount = count($kra['objectives']); ?>
                        <?php foreach ($kra['objectives'] as $oi => $obj): ?>
                            <tr class="small">
                                <?php if ($firstRow): ?>
                                    <td rowspan="<?= $rowCount ?>" class="align-middle font-weight-bold bg-light">
                                        <?= e($kra['title']) ?>
                                    </td>
                                    <?php $firstRow = false; ?>
                                <?php endif; ?>
                                <td class="align-middle"><?= e($obj['objective']) ?></td>
                                <td class="align-middle text-center"><?= e($obj['timeline'] ?? '-') ?></td>
                                <td class="align-middle text-center"><?= e($obj['weight']) ?>%</td>
                                <td class="align-middle small"><?= nl2br(e($obj['performance_indicator'] ?? '-')) ?></td>
                                <td class="align-middle"><?= e($obj['actual_result'] ?? '-') ?></td>
                                <?php if ($canRate): ?>
                                    <input type="hidden" name="obj_id[]" value="<?= cipher($obj['id']) ?>">
                                    <td class="align-middle p-1">
                                        <select name="rating_q[]" class="form-control form-control-sm rating-input" required>
                                            <option value="">-</option>
                                            <?php for ($r = 5; $r >= 1; $r--): ?>
                                                <option value="<?= $r ?>" <?= $obj['rating_q'] == $r ? 'selected' : '' ?>><?= $r ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </td>
                                    <td class="align-middle p-1">
                                        <select name="rating_e[]" class="form-control form-control-sm rating-input" required>
                                            <option value="">-</option>
                                            <?php for ($r = 5; $r >= 1; $r--): ?>
                                                <option value="<?= $r ?>" <?= $obj['rating_e'] == $r ? 'selected' : '' ?>><?= $r ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </td>
                                    <td class="align-middle p-1">
                                        <select name="rating_t[]" class="form-control form-control-sm rating-input" required>
                                            <option value="">-</option>
                                            <?php for ($r = 5; $r >= 1; $r--): ?>
                                                <option value="<?= $r ?>" <?= $obj['rating_t'] == $r ? 'selected' : '' ?>><?= $r ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </td>
                                <?php else: ?>
                                    <td class="align-middle text-center"><?= $obj['rating_q'] ?? '-' ?></td>
                                    <td class="align-middle text-center"><?= $obj['rating_e'] ?? '-' ?></td>
                                    <td class="align-middle text-center"><?= $obj['rating_t'] ?? '-' ?></td>
                                <?php endif; ?>
                                <td class="align-middle text-center font-weight-bold">
                                    <?= $obj['average_rating'] ? number_format($obj['average_rating'], 2) : '-' ?>
                                </td>
                                <td class="align-middle text-center font-weight-bold">
                                    <?= $obj['score'] !== null ? number_format($obj['score'], 2) : ($obj['average_rating'] !== null ? number_format($obj['average_rating'] * $obj['weight'] / 100, 2) : '-') ?>
                                </td>
                                <?php if ($canRate): ?>
                                    <td class="align-middle p-1">
                                        <input type="text" name="obj_remarks[]" class="form-control form-control-sm" 
                                            value="<?= e($obj['remarks'] ?? '') ?>" placeholder="Remarks">
                                    </td>
                                <?php else: ?>
                                    <td class="align-middle small"><?= e($obj['remarks'] ?? '-') ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                        <tr class="small bg-light font-weight-bold text-center">
                            <td colspan="3" class="align-middle text-left">OVERALL RATING FOR ACCOMPLISHMENTS</td>
                            <td class="align-middle"><?= number_format($totalWeight, 0) ?>%</td>
                            <td colspan="6" class="align-middle text-right">Total Score</td>
                            <td class="align-middle"><?= number_format($totalScore, 2) ?></td>
                            <td></td>
                        </tr>
                        <tr class="small bg-light font-weight-bold text-center">
                            <td colspan="12" class="align-middle text-right">
                                Adjectival Rating: <span class="badge badge-info"><?= e($adjectivalRating) ?></span>
                            </td>
                        </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Means of Verification -->
<?php if (!empty($movList)): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-2">
            <h6 class="m-0 font-weight-bold text-dark">
                <i class="fas fa-file-upload mr-1"></i> Means of Verification (MOV)
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="bg-light">
                        <tr class="small">
                            <th>KRA</th>
                            <th>Objective</th>
                            <th>File</th>
                            <th>Description</th>
                            <th>Uploaded</th>
                            <th width="10%">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movList as $mov): ?>
                            <tr class="small">
                                <td><?= e($mov['kra_title']) ?></td>
                                <td><?= e(substr($mov['objective'], 0, 50)) ?>...</td>
                                <td>
                                    <i class="fas fa-file mr-1"></i>
                                    <?= e($mov['original_name']) ?>
                                </td>
                                <td><?= e($mov['description'] ?? '-') ?></td>
                                <td><?= date('M d, Y', strtotime($mov['created_at'])) ?></td>
                                <td>
                                    <a href="<?= uri() ?>/uploads/mov/<?= e($mov['file_name']) ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                        <i class="fas fa-eye fa-sm"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Validator Remarks -->
<?php if ($canApprove || $canRate): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-2">
            <h6 class="m-0 font-weight-bold text-dark">Validator Remarks</h6>
        </div>
        <div class="card-body">
            <textarea name="validator_remarks" class="form-control" rows="3" 
                placeholder="Enter your remarks for the ratee..."><?= e($ipcrf['validator_remarks'] ?? '') ?></textarea>
        </div>
    </div>
<?php endif; ?>

<!-- Action Buttons -->
<div class="card shadow mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <a href="<?= customUri('pis', 'IPCRF', $userId) ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left mr-1"></i> Back
                </a>
                <?php if ((int) $ipcrf['phase'] >= 2 && (int) $ipcrf['phase'] < 4): ?>
                    <a href="<?= customUri('pis', 'Coaching Form', $ipcrfId) ?>" class="btn btn-info">
                        <i class="fas fa-clipboard-list mr-1"></i> Coaching Form
                    </a>
                    <a href="<?= customUri('pis', 'Recalibration Form', $ipcrfId) ?>" class="btn btn-info">
                        <i class="fas fa-balance-scale mr-1"></i> Recalibration Form
                    </a>
                <?php endif; ?>
                <?php if ((int) $ipcrf['phase'] >= 4): ?>
                    <a href="<?= customUri('pis', 'Phase 4', $ipcrfId) ?>" class="btn btn-success">
                        <i class="fas fa-award mr-1"></i> Development Planning
                    </a>
                <?php endif; ?>
                <a href="<?= customUri('pis', 'Print IPCRF', $ipcrfId) ?>" target="_blank" class="btn btn-dark">
                    <i class="fas fa-print mr-1"></i> Print IPCRF
                </a>
            </div>
            
            <?php if ($canApprove || $canRate): ?>
                <div>
                    <?php if ($canApprove): ?>
                        <button type="submit" name="approve-ipcrf" class="btn btn-success"
                            formnovalidate="formnovalidate"
                            onclick="return confirm('<?= ((int) $ipcrf['phase'] === 3) ? 'Approve this IPCRF rating and proceed to Phase 4?' : 'Approve this IPCRF commitment and proceed to Phase 2?' ?>')">
                            <i class="fas fa-check-circle mr-1"></i> <?= ((int) $ipcrf['phase'] === 3) ? 'Approve Rating' : 'Approve Commitment' ?>
                        </button>
                    <?php endif; ?>
                    <?php if ($canRate): ?>
                        <button type="submit" name="validate-ipcrf" class="btn btn-success"
                            onclick="return confirm('Validate this IPCRF? This will compute the final rating.')">
                            <i class="fas fa-check-circle mr-1"></i> Validate IPCRF
                        </button>
                    <?php endif; ?>
                    <button type="submit" name="return-ipcrf" class="btn btn-warning" 
                        formnovalidate="formnovalidate"
                        onclick="return confirm('Return this IPCRF to the ratee for revision?')">
                        <i class="fas fa-undo mr-1"></i> Return to Ratee
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($canApprove || $canRate): ?>
</form>
<?php endif; ?>