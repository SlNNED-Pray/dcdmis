<?php
// modules/documents/save-document-dialog.php
require_once('../../includes/function.php');
require_once(root() . '/includes/string.php');
require_once(root() . '/includes/database/database.php');
require_once(root() . '/includes/database/document.php');
require_once(root() . '/includes/database/section.php');
require_once(root() . '/includes/database/school.php');
require_once(root() . '/includes/layout/components.php');
?>

<div class="modal-dialog">
    <div class="modal-content">
        <?php modalHeader('New Document') ?>

        <form action="" method="POST" enctype="multipart/form-data">
            <?= csrf_field(); ?>

            <div class="modal-body">
                <div class="form-group">
                    <label for="document-type" class="mb-0">Document Type <?php showAsterisk() ?></label>
                    <select name="document-type" id="document-type" class="form-control" title="Select document type..."
                        required>
                        <option value="">Select type...</option>
                        <?php
                        $documentTypes = documentTypes($isSchoolPortal);
                        foreach ($documentTypes as $documentType): ?>
                            <option value="<?= e($documentType['id']) ?>">
                                <?= e($documentType['name']) ?>
                            </option>
                        <?php endforeach ?>
                        <option value="1">Others</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description" class="mb-0">Description <?php showAsterisk() ?></label>
                    <textarea id="description" name="description" class="form-control" rows="3"
                        placeholder="Type description..." title="Type document description..." required></textarea>
                </div>

                <?php if (!$isSchoolPortal): ?>
                    <div class="form-group">
                        <label class="mb-0" for="destination">Destination <?php showAsterisk() ?></label>
                        <select name="destination" id="destination" class="form-control"
                            title="Select document destination..." required>
                            <option value="">Select destination...</option>
                            <?php if ($isRecordsPortal) {
                                $allSchools = schools(true);
                                if (!empty($allSchools)) { ?>
                                    <optgroup label="Schools">
                                        <?php foreach ($allSchools as $sch) {
                                            $schAlias = $sch['alias'] ?: $sch['id'];
                                            if ($schAlias !== $station && $sch['id'] !== $station) { ?>
                                                <option value="<?= e($schAlias) ?>"><?= e($sch['name']) ?></option>
                                            <?php }
                                        } ?>
                                    </optgroup>
                                <?php }
                            } ?>
                            <?php $divisions = functionalDivisions();
                            foreach ($divisions as $division): ?>
                                <optgroup label="<?= e($division['name']) ?>">
                                    <?php $sections = sections($division['id']);
                                    foreach ($sections as $section) {
                                        if ($section['id'] !== $station) { ?>
                                            <option value="<?= $section['id'] ?>">
                                                <?= $section['name'] ?>
                                            </option>
                                            <?php
                                        }
                                    } ?>
                                </optgroup>
                            <?php endforeach ?>
                        </select>
                    </div>
                <?php endif ?>

                <div class="form-group">
                    <label class="mb-0" for="purpose">Purpose <?php showAsterisk() ?></label>
                    <select name="purpose" id="purpose" class="form-control" title="Select document purpose..."
                        required>
                        <option value="">Select purpose...</option>
                        <?php $documentPurposes = documentTransactionStatus();
                        foreach ($documentPurposes as $documentPurpose): ?>
                            <option value="<?= e($documentPurpose['id']) ?>">
                                <?= e($documentPurpose['name']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="mb-0" for="details">Additional details</label>
                    <textarea id="details" name="details" class="form-control" rows="2"
                        placeholder="Type additional details..." title="Type additional details..."></textarea>
                </div>

                <div class="form-group">
                    <label class="mb-0" for="file-upload">Attachment</label>
                    <input id="file-upload" name="file-upload[]" type="file" multiple class="w-100"
                        accept="application/msword, application/vnd.openxmlformats-officedocument.wordprocessingml.document, application/pdf, image/*, application/vnd.ms-powerpoint, application/vnd.openxmlformats-officedocument.presentationml.presentation, application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
                    <div class="small mt-1 text-muted">Allowed: .doc/docx, .xls/xlsx, .ppt/pptx, .jpg/jpeg, .png, .gif, .pdf (Max file upload size: <?= UPLOAD_MAX_FILESIZE ?>B)</div>
                </div>

                <?php requiredLegend(0) ?>
            </div>

            <div class="modal-footer">
                <button class="btn btn-primary" name="save-document" type="submit">Continue</button>
                <?php cancelModalButton() ?>
            </div>
        </form>
    </div>
</div>