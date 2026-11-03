<?php
// modules/documents/document-type/document-type-detail.php
if (!$isDts) {
    require_once(root() . '/modules/error/403.php');
    return;
}

$isRecordsAdminICT = $isICT || $isRecordsPortal || $isAdminPortal;
$documentTypeId = isset($_GET['id']) ? sanitize(decode($_GET['id'])) : null;
$documentType = documentType($documentTypeId);
$name = $description = $for_school = null;

messageAlert($showAlert, $message, $success);

if ($documentType) {
    $name = $documentType['name'];
    $description = $documentType['description'];
    $for_school = $documentType['for_school'];
} else {
    require_once(root() . '/modules/error/no-results-found.php');
    return;
}

$steps = documentTypeStationSteps($documentTypeId);
$requirements = documentTypeStationStepRequirements($documentTypeId);
$requirementCount = count($requirements);
$stepCount = count($steps);
$hasSteps = !empty($steps);
?>

<div class="d-flex align-items-center justify-content-between flex-row mt-2 mb-3">
    <nav class="d-flex align-items-center flex-row m-0">
        <ol class="breadcrumb m-0 p-0 bg-transparent">
            <li class="breadcrumb-item"><a href="<?= "{$baseUri}/{$activeApp}" ?>">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= customUri('dts', 'Document Types') ?>">Document Types</a></li>
            <li class="breadcrumb-item active"><?= $name ?></li>
        </ol>
    </nav>
</div>

<div class="card border-left-primary shadow mb-4">
    <div class="card-header py-3">
        <?php
        $title = e($name) . ' Steps';
        if (!$hasSteps) {
            contentTitle($title);
        } else {
            contentTitleWithModal($title, "{$baseUri}/modules/documents/document-type/save-document-type-station-step-dialog.php?t=" . $_GET['id'], 'Add', 'fa-plus');
        } ?>
    </div>

    <div class="card-body">
        <?php if (!$hasSteps): ?>
            <div class="text-center pt-5 pb-4 text-muted">
                <i class="fas fa-clipboard-list fa-5x mb-3 text-gray-300"></i>
                <p>No configured steps yet for <strong><?= e($name) ?></strong>.</p>
                <?php modalButtonSplit("{$baseUri}/modules/documents/document-type/save-document-type-station-step-dialog.php?t=" . $_GET['id'], 'Add Step', 'fa-plus', 'Add first step') ?>
            </div>
        <?php else: ?>
            <div class="m-0 p-2 rounded alert-info text-left d-flex">
                <span class="d-inline-block">
                    <i class="fas fa-info-circle mr-1"></i>
                </span>
                <span class="ml-2 d-inline-block d-flex align-items-center small">
                    <div>Configure station-by-station documentary checklist requirements for document type
                        <strong><?= e($name) ?></strong>. <strong><?= count($requirements) ?>
                        </strong> requirement item<?= $requirementCount > 1 ? 's' : '' ?> configured
                        across <strong><?= $stepCount ?></strong> station
                        step<?= $stepCount > 1 ? 's' : '' ?>.
                    </div>
                </span>
            </div>

            <div>
                <?php
                $index = 0;
                foreach ($steps as $step):
                    $req = documentTypeStationStepRequirements($documentTypeId, $step['id']);
                    $index++; ?>
                    <div class="card mb-0 mt-3 border">
                        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="font-weight-bold text-dark uppercase">
                                    <?= stationName($step['destination_id']) ?>
                                </div>
                                <div class="text-left d-flex">
                                    <div>
                                        <div class="badge badge-primary px-1 mt-1 mr-2">
                                            <span class="uppercase">Step</span>
                                            <div class="font-weight-bold h6 mt-1 mb-0">
                                                <?= $step['step_number'] ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-muted">
                                        <div>
                                            <?= $step['description'] ?>
                                        </div>
                                        <?php if (!empty($step['note'])): ?>
                                            <div class="text-danger small">
                                                Note: <?= $step['note'] ?>
                                            </div>
                                        <?php endif;
                                        $step_items = count($req);
                                        ?>
                                        <div>
                                            <?php if (!empty($step['ideal_processing_time'])): ?>
                                                <span class="badge badge-secondary mr-1" title="Ideal Processing Time">
                                                    <i class="far fa-clock mr-1"></i>
                                                    <?= e($step['ideal_processing_time']) ?>
                                                </span>
                                            <?php endif; ?>
                                            <span class="badge badge-info">
                                                <i class="fas fa-file-alt mr-1"></i>
                                                <?= "{$step_items} item" . ($step_items > 1 ? 's' : '') ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="dropdown no-arrow">
                                <?php dropdownEllipsis() ?>
                                <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in">
                                    <?php modalDropdownItem(uri() . '/modules/documents/document-type/save-document-type-station-step-requirement-dialog.php?id=' . cipher($step['id']) . '&t=' . $_GET['id'], 'Add', 'fa-plus', 'Add Requirement'); ?>
                                    <div class="dropdown-divider"></div>
                                    <?php modalDropdownItem(uri() . '/modules/documents/document-type/save-document-type-station-step-dialog.php?id=' . cipher($step['id']) . '&t=' . $_GET['id'], 'Edit', 'fa-edit', 'Edit Step');
                                    modalDropdownItem(uri() . '/modules/documents/document-type/delete-document-type-station-step-dialog.php?id=' . cipher($step['id']), 'Delete', 'fa-trash', 'Delete Step'); ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php
                            $requirements = documentTypeStationStepRequirements($documentTypeId, $step['id']);
                            if (empty($requirements)): ?>
                                <div class="text-center pt-4 text-muted">
                                    <i class="fas fa-plus fa-2x mb-1 text-gray-300"></i>
                                    <p class="mb-1">No step requirement set.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover border-bottom table-striped mb-0">
                                        <thead class="bg-gray-100 text-uppercase">
                                            <tr class="small">
                                                <th class="align-middle text-center" width="5%">#</th>
                                                <th class="align-middle" width="80%">Checklist Requirement Item</th>
                                                <th class="align-middle text-center" width="5%">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $count = 1;
                                            foreach ($requirements as $req): ?>
                                                <tr>
                                                    <td class="align-middle text-center text-muted"><?= $count++ ?></td>
                                                    <td class="align-middle text-dark">
                                                        <div>
                                                            <?= e($req['requirement_description']) ?>
                                                            <?php if (!empty($req['is_mandatory'])): ?>
                                                                <span class="badge badge-danger text-uppercase text-xs ml-2">
                                                                    Required
                                                                </span>
                                                            <?php endif ?>
                                                        </div>
                                                        <?php if (!empty($req['note'])): ?>
                                                            <div class="text-danger small">
                                                                <?= e($req['note']) ?>
                                                            </div>
                                                        <?php endif ?>
                                                    </td>
                                                    <td class="align-middle text-center">
                                                        <div class="dropdown no-arrow">
                                                            <?php dropdownEllipsis() ?>
                                                            <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in">
                                                                <?php modalDropdownItem(uri() . '/modules/documents/document-type/save-document-type-station-step-requirement-dialog.php?id=' . cipher($req['id']) . '&s=' . cipher($step['id']), 'Edit', 'fa-edit', 'Edit Requirement');
                                                                modalDropdownItem(uri() . '/modules/documents/document-type/delete-document-type-station-step-requirement-dialog.php?id=' . cipher($req['id']), 'Delete', 'fa-trash', 'Delete Requirement'); ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                            <div class="text-center py-2">
                                <?php modalButtonSplit("{$baseUri}/modules/documents/document-type/save-document-type-station-step-requirement-dialog.php?s=" . cipher($step['id']), 'Add Requirement', 'fa-plus', 'Add requirement') ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach ?>

                <div class="text-center mt-3">
                    <?php modalButtonSplit("{$baseUri}/modules/documents/document-type/save-document-type-station-step-dialog.php?t=" . $_GET['id'], 'Add another step', 'fa-plus', 'Add another step') ?>
                </div>
            </div>
        <?php endif ?>
    </div>
</div>