<?php
// modules/documents/document-type/save-document-type-dialog.php
require_once('../../../includes/function.php');
require_once(root() . '/includes/string.php');
require_once(root() . '/includes/database/database.php');
require_once(root() . '/includes/database/document.php');
require_once(root() . '/includes/layout/components.php');

$documentTypeId = isset($_GET['id']) ? sanitize(decipher($_GET['id'])) : null;
$copiedId = isset($_GET['c']) ? sanitize(decipher($_GET['c'])) : null;
$documentType = documentType($documentTypeId);
$name = $description = $for_school = null;
$modalTitle = 'New Document Type';

if ($documentType) {
    $modalTitle = $documentTypeId === $copiedId ? 'Copy Document Type' : 'Edit Document Type';
    $name = $documentType['name'];
    $description = $documentType['description'];
    $for_school = (bool) $documentType['for_school'];
}
?>

<div class="modal-dialog">
    <div class="modal-content">
        <?php modalHeader($modalTitle); ?>

        <form action="" method="POST">
            <?= csrf_field(); ?>
            <div class="modal-body">
                <div class="form-group">
                    <label for="type" class="mb-0">Name <?php showAsterisk() ?></label>
                    <input type="text" id="type" name="type" class="form-control" value="<?= e($name) ?>">
                </div>

                <div class="form-group">
                    <label for="description" class="mb-0">Description</label>
                    <textarea id="description" name="description" class="form-control"
                        rows="3"><?= e($description) ?></textarea>
                </div>

                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="for_school" name="for_school"
                            <?= setItemChecked($for_school) ?> value="1">
                        <label class="pt-1 custom-control-label form-text text-muted small" for="for_school">Check if
                            document type is also applicable for
                            school transactions.</label>
                    </div>
                </div>

                <?php requiredLegend(0) ?>
            </div>

            <div class="modal-footer">
                <input type="hidden" name="verifier" value="<?= e($_GET['id'] ?? '') ?>">
                <button class="btn btn-primary" name="save-document-type" type="submit">Continue</button>
                <?php cancelModalButton() ?>
            </div>
        </form>
    </div>
</div>