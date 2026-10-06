<?php
// modules/documents/restore-document-dialog.php
require_once('../../includes/function.php');
require_once(root() . '/includes/string.php');
require_once(root() . '/includes/database/database.php');
require_once(root() . '/includes/database/document.php');
require_once(root() . '/includes/layout/components.php');

$documentId = sanitize(decipher($_GET['id'] ?? null));
$document = document($documentId);
$description = $type = '';
$modalTitle = 'Document not found';
$hasDocument = false;

if ($document) {
    $documentId = $document['id'];
    $description = $document['description'];
    $type = $document['document_type_id'];
    $documentLogs = documentLogs($documentId)[0];
    $status = strtolower(documentTransactionStatus($documentLogs['status_id']));
    $hasDocument = str_contains($status, 'cancel') && !str_contains($status, 'complete') && $documentLogs['received_from'] === $station;
    $modalTitle = $hasDocument ? 'Restore Document' : $modalTitle;
}
?>

<div class="modal-dialog <?= !$hasDocument ? 'modal-sm' : '' ?>">
    <div class="modal-content">
        <?php modalHeader($modalTitle) ?>

        <form action="" method="POST">
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
                        <label for="remarks" class="mb-0">Reason <?php showAsterisk() ?></label>
                        <textarea id="remarks" name="remarks" class="form-control" rows="3" autofocus
                            placeholder="Type reason..." title="Type document reason..." required></textarea>
                    </div>

                    <?php requiredLegend(0) ?>
                <?php } else {
                    missingAlert($modalTitle);
                } ?>
            </div>

            <div class="modal-footer">
                <?php if ($hasDocument): ?>
                    <input type="hidden" name="verifier" value="<?= e($_GET['id']) ?>">
                    <button class="btn btn-danger" name="restore-document" type="submit">Continue</button>
                <?php endif ?>
                <?php cancelModalButton() ?>
            </div>
        </form>
    </div>
</div>