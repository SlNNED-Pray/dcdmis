<?php
// modules/documents/document-type/save-document-type-station-step-requirement-dialog.php
require_once('../../../includes/function.php');
require_once(root() . '/includes/string.php');
require_once(root() . '/includes/database/database.php');
require_once(root() . '/includes/database/document.php');
require_once(root() . '/includes/database/section.php');
require_once(root() . '/includes/database/utility.php');
require_once(root() . '/includes/layout/components.php');

$documentTypeRequirementId = isset($_GET['id']) ? sanitize(decipher($_GET['id'])) : null;
$documentTypeStepId = isset($_GET['s']) ? sanitize(decipher($_GET['s'])) : null;
$copiedId = isset($_GET['c']) ? sanitize(decipher($_GET['c'])) : null;
$step = documentTypeStationStep($documentTypeStepId);
$stepNumber = $step['step_number'];
$destination = stationName($step['destination_id']);
$documentType = $step['document_type'];
$documentTypeStep = $step['step_description'];
$description = $note = $isMandatory = null;
$modalTitle = 'New Document Type Station Step Requirement';
$requirement = documentTypeStationStepRequirement($documentTypeRequirementId);

if ($requirement) {
    $modalTitle = ($documentTypeRequirementId === $copiedId ? 'Copy' : 'Edit') . '  Document Type Station Step Requirement';
    $description = $requirement['description'];
    $note = $requirement['note'];
    $isMandatory = $requirement['is_mandatory'];
}
?>

<div class="modal-dialog">
    <div class="modal-content">
        <?php modalHeader($modalTitle); ?>

        <form action="" method="POST">
            <?= csrf_field(); ?>
            <div class="modal-body">
                <div class="form-group mb-2">
                    <label for="note" class="mb-0">Document Type</label>
                    <input type="text" class="form-control" value="<?= e($documentType) ?>" readonly>
                </div>

                <div class="form-group mb-2">
                    <label for="note" class="mb-0">Step / Station</label>
                    <input type="text" class="form-control" value="<?= e("{$stepNumber} - {$destination}") ?>" readonly>
                </div>

                <div class="form-group mb-2">
                    <label for="note" class="mb-0">Step Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3"
                        placeholder="Type requirement description..." title="Type requirement description..."
                        readonly><?= e($documentTypeStep) ?></textarea>
                </div>

                <div class="form-group mb-2">
                    <label for="description" class="mb-0">Requirement Description <?php showAsterisk() ?></label>
                    <textarea id="description" name="description" class="form-control" rows="3"
                        placeholder="Type requirement description..." title="Type requirement description..."
                        required><?= e($description) ?></textarea>
                </div>

                <div class="form-group mb-2">
                    <label for="note" class="mb-0">Requirement Note</label>
                    <input type="text" name="note" id="note" class="form-control"
                        placeholder="Type requirement note details..." value="<?= e($note) ?>">
                </div>

                <div class="form-group mb-1">
                    <div class="form-check" title="Check if mandatory requirement">
                        <input class="form-check-input" id="is-mandatory" type="checkbox" name="is-mandatory" value="1"
                            <?= setItemChecked($isMandatory) ?>>
                        <label class="form-check-label" for="is-mandatory">Mandatory</label>
                    </div>
                </div>

                <?php requiredLegend(0) ?>
            </div>

            <div class="modal-footer">
                <input type="hidden" name="verifier" value="<?= $_GET['id'] ?? null ?>">
                <?php
                $verifier = $_GET['id'] ?? null;
                $verifier = $documentTypeStepId === $copiedId ? null : $verifier;
                ?>
                <input type="hidden" name="data-verifier" value="<?= $_GET['s'] ?? null ?>">
                <button type="submit" class="btn btn-primary"
                    name="save-document-type-station-step-requirement">Continue</button>
                <?php cancelModalButton() ?>
            </div>
        </form>
    </div>
</div>