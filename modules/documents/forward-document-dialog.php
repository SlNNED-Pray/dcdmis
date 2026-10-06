<?php
// modules/documents/forward-document-dialog.php
require_once('../../includes/function.php');
require_once(root() . '/includes/string.php');
require_once(root() . '/includes/database/database.php');
require_once(root() . '/includes/database/document.php');
require_once(root() . '/includes/database/section.php');
require_once(root() . '/includes/database/school.php');
require_once(root() . '/includes/layout/components.php');

$documentId = sanitize(decipher($_GET['id'] ?? null));
$document = document($documentId);
$description = $destination = $type = $purpose = $details = null;
$modalTitle = 'Document not found';
$hasDocument = $forRelease = false;

if ($document) {
    $documentId = $document['id'];
    $description = $document['description'];
    $type = $document['document_type_id'];
    $purpose = $document['status_id'];
    $destination = $document['created_from'];
    $documentLogs = documentLogs($documentId)[0];
    $documentStatus = strtolower(documentTransactionStatus($documentLogs['status_id']));
    $hasDocument = !str_contains($documentStatus, 'complete') && !str_contains($documentStatus, 'cancel') && $documentLogs['received_from'] === $station && $documentLogs['forwarded_to'] === null;
    $modalTitle = $hasDocument ? 'Forward Document' : $modalTitle;
    $forRelease = strtolower(documentTransactionStatus($document['status_id'])) === 'for release' && $portal === 'rec_portal';
}
?>

<div class="modal-dialog <?= !$hasDocument ? 'modal-sm' : '' ?>">
    <div class="modal-content">
        <?php modalHeader($modalTitle) ?>

        <form action="" method="POST" enctype="multipart/form-data">
            <?= csrf_field(); ?>
            <div class="modal-body">
                <?php if ($hasDocument) { ?>
                    <div class="form-group">
                        <label for="code" class="mb-0">Code</label>
                        <input id="code" type="text" value="<?= e($documentId) ?>" class="form-control text-uppercase"
                            disabled>
                    </div>

                    <div class="form-group">
                        <label for="type" class="mb-0">Type</label>
                        <input id="type" class="form-control text-uppercase" value="<?= documentType($type)['name'] ?>"
                            disabled>
                    </div>

                    <div class="form-group">
                        <label for="description" class="mb-0">Description</label>
                        <textarea id="description" class="form-control text-uppercase" rows="3"
                            disabled><?= e($description) ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="destination" class="mb-0">Destination <?php showAsterisk() ?></label>
                        <?php if (!$forRelease) { ?>
                            <select id="destination" name="destination" class="form-control"
                                title="Select document destination..." required>
                                <option value="">Select destination...</option>
                                <?php
                                $divisions = functionalDivisions();
                                foreach ($divisions as $division): ?>
                                    <optgroup label="<?= e($division['name']) ?>">
                                        <?php
                                        $sections = sections($division['id']);
                                        foreach ($sections as $section) {
                                            if ($section['id'] !== $station) { ?>
                                                <option value="<?= $section['id'] ?>"><?= $section['name'] ?></option>
                                                <?php
                                            }
                                        } ?>
                                    </optgroup>
                                <?php endforeach ?>
                            </select>
                        <?php } else {
                            $stationName = section($destination)['name'] ?? null;

                            if ($stationName === null) {
                                $stationName = schoolByAlias($destination)['name'] ?? null;
                            }
                            ?>
                            <input id="destination" class="form-control text-uppercase" type="text"
                                value="<?= e($stationName) ?>" disabled>
                            <input name="destination" type="hidden" value="<?= e($destination) ?>">
                        <?php } ?>
                    </div>

                    <div class="form-group">
                        <label for="purpose" class="mb-0">Purpose <?php showAsterisk() ?></label>
                        <?php if (!$forRelease): ?>
                            <select id="purpose" name="purpose" class="form-control" title="Select document purpose..."
                                required>
                                <option value="">Select purpose...</option>
                                <?php
                                $documentPurpose = documentTransactionStatus();
                                foreach ($documentPurpose as $purpose): ?>
                                    <option value="<?= e($purpose['id']) ?>"><?= e($purpose['name']) ?></option>
                                <?php endforeach ?>
                            </select>
                        <?php else: ?>
                            <input id="purpose" class="form-control text-uppercase" type="text"
                                value="<?= e(documentTransactionStatus($purpose)) ?>" required readonly>
                            <input name="purpose" type="hidden" value="<?= e($purpose) ?>">
                        <?php endif ?>
                    </div>

                    <div class="form-group">
                        <label for="details" class="mb-0">Additional details</label>
                        <textarea id="details" name="details" class="form-control" rows="2"
                            placeholder="Type additional details..." title="Type document additional details..."></textarea>
                    </div>

                    <div class="form-group">
                        <label class="mb-0" for="file-upload">Attachment</label>
                        <input id="file-upload" name="file-upload[]" type="file" multiple class="w-100"
                            accept="application/msword, application/vnd.openxmlformats-officedocument.wordprocessingml.document, application/pdf, image/*, application/vnd.ms-powerpoint, application/vnd.openxmlformats-officedocument.presentationml.presentation, application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
                        <div class="small mt-1 text-muted">Allowed: .doc/docx, .xls/xlsx, .ppt/pptx, .jpg/jpeg, .png, .gif,
                            .pdf (Max file upload size: <?= UPLOAD_MAX_FILESIZE ?>B)</div>
                    </div>

                    <?php requiredLegend(0) ?>
                <?php } else {
                    missingAlert($modalTitle);
                } ?>
            </div>

            <div class="modal-footer">
                <?php if ($hasDocument): ?>
                    <input type="hidden" name="verifier" value="<?= e($_GET['id']) ?>">
                    <button class="btn btn-primary" name="forward-document" type="submit">Continue</button>
                <?php endif ?>
                <?php cancelModalButton() ?>
            </div>
        </form>
    </div>
</div>