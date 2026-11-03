<?php
// modules/documents/document-type/save-document-type-station-step-dialog.php
require_once('../../../includes/function.php');
require_once(root() . '/includes/string.php');
require_once(root() . '/includes/database/database.php');
require_once(root() . '/includes/database/document.php');
require_once(root() . '/includes/database/section.php');
require_once(root() . '/includes/layout/components.php');

$documentTypeStepId = isset($_GET['id']) ? sanitize(decipher($_GET['id'])) : null;
$copiedId = isset($_GET['c']) ? sanitize(decipher($_GET['c'])) : null;
$documentTypeId = isset($_GET['t']) ? sanitize(decipher($_GET['t'])) : null;
$documentTypeStep = documentTypeStationStep($documentTypeStepId);
$documentType = documentType($documentTypeId)['name'];
$stepNumber = $destinationId = $description = $note = $idealProcessingTime = null;
$modalTitle = 'New Document Type Station Step';

if ($documentTypeStep) {
    $modalTitle = ($documentTypeStepId === $copiedId ? 'Copy' : 'Edit') . '  Document Type Station Step';
    $stepNumber = $documentTypeStep['step_number'];
    $destinationId = $documentTypeStep['destination_id'];
    $description = $documentTypeStep['step_description'];
    $note = $documentTypeStep['step_note'];
    $idealProcessingTime = $documentTypeStep['ideal_processing_time'];
} else {
    $stepNumber = count(documentTypeStationSteps($documentTypeId)) + 1;
}
?>

<div class="modal-dialog">
    <div class="modal-content">
        <?php modalHeader($modalTitle); ?>

        <form action="" method="POST">
            <?= csrf_field(); ?>
            <div class="modal-body">
                <div class="form-group">
                    <label for="document-type" class="mb-0">Document Type</label>
                    <input type="text" id="document-type" class="form-control" value="<?= e($documentType) ?>" readonly>
                </div>

                <div class="form-group">
                    <label for="step-number" class="mb-0">Step Number</label>
                    <input type="text" id="step-number" name="step_number" class="form-control"
                        value="<?= e($stepNumber) ?>" readonly>
                </div>

                <div class="form-group">
                    <label for="destination" class="mb-0">Destination <?php showAsterisk() ?></label>
                    <select name="destination" id="destination" class="form-control"
                        title="Select document type station step destination..." required>
                        <option value="">Select destination...</option>
                        <?php $divisions = functionalDivisions();
                        foreach ($divisions as $division): ?>
                            <optgroup label="<?= e($division['name']) ?>">
                                <?php $sections = sections($division['id']);
                                foreach ($sections as $section) { ?>
                                    <option value="<?= $section['id'] ?>" <?= setOptionSelected($section['id'], $destinationId) ?>>
                                        <?= $section['name'] ?>
                                    </option>
                                     <?php
                                }
                                ?>
                            </optgroup>
                        <?php endforeach ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description" class="mb-0">Description <?php showAsterisk() ?></label>
                    <textarea id="description" name="description" class="form-control" rows="3"
                        placeholder="Type description..." title="Type description..." required><?= e($description) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="note" class="mb-0">Note</label>
                    <input type="text" name="note" id="note" class="form-control" value="<?= e($note) ?>"
                        placeholder="Type note details...">
                </div>

                <div class="form-group">
                    <label for="ideal_processing_time" class="mb-0">Ideal Processing Time <?php showAsterisk() ?></label>
                    <input type="text" name="ideal_processing_time" id="ideal_processing_time" class="form-control"
                        value="<?= e($idealProcessingTime) ?>" placeholder="Type ideal processing time..." required>
                </div>

                <?php requiredLegend(0) ?>
            </div>

            <div class="modal-footer">
                <input type="hidden" name="verifier" value="<?= e($_GET['id'] ?? '') ?>">
                <input type="hidden" name="data-verifier" value="<?= e($_GET['t'] ?? '') ?>">
                <button class="btn btn-primary" name="save-document-type-station-step" type="submit">Continue</button>
                <?php cancelModalButton() ?>
            </div>
        </form>
    </div>
</div>