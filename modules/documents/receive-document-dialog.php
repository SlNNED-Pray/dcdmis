<?php
// modules/documents/receive-document-dialog.php
require_once('../../includes/function.php');
require_once(root() . '/includes/string.php');
require_once(root() . '/includes/database/database.php');
require_once(root() . '/includes/database/document.php');
require_once(root() . '/includes/layout/components.php');

$documentId = sanitize(decipher($_GET['id'] ?? null));
$document = document($documentId);
$description = $type = $details = null;
$modalTitle = 'Document not found';
$hasDocument = false;

if ($document) {
    $documentId = $document['id'];
    $description = $document['description'];
    $type = $document['document_type_id'];
    $documentLogs = documentLogs($documentId)[0];
    $status = strtolower(documentTransactionStatus($documentLogs['status_id']));
    $details = $documentLogs['details'];
    $hasDocument = !str_contains($status, 'complete') && !str_contains($status, 'cancel') && $documentLogs['forwarded_to'] === $station;
    $modalTitle = $hasDocument ? 'Receive Document' : $modalTitle;
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

                    <div class="form-group mb-0">
                        <label for="description" class="mb-0">Description</label>
                        <textarea id="description" class="form-control text-uppercase" rows="3"
                            disabled><?= e($description) ?></textarea>
                    </div>

                    <?php if (!empty($details)): ?>
                        <div class="form-group mt-3 mb-0">
                            <label for="details" class="mb-0">Additional details</label>
                            <textarea id="details" class="form-control text-uppercase" rows="2"
                                disabled><?= e($details) ?></textarea>
                        </div>
                    <?php endif;

                    $documentLogAttachments = documentLogAttachments($documentLogs['id']);

                    if ($documentLogAttachments): ?>
                        <div class="form-group mt-3 mb-0">
                            <label class="mb-0">Attachments</label>
                            <div>
                                <?php foreach ($documentLogAttachments as $attachment) {
                                    $file = explode('_', $attachment['file_name'], 2);
                                    linkButtonSplit("$baseUri/" . $attachment['file_name'], $file[1], 'fa-paperclip', "View $file[1]", 'secondary', true);
                                } ?>
                            </div>
                        </div>
                    <?php endif ?>
                <?php } else {
                    missingAlert($modalTitle);
                } ?>
            </div>

            <div class="modal-footer">
                <?php if ($hasDocument): ?>
                    <input type="hidden" name="verifier" value="<?= e($_GET['id']) ?>">
                    <button class="btn btn-primary" type="submit" name="receive-document">Continue</button>
                <?php endif ?>
                <?php cancelModalButton() ?>
            </div>
        </form>
    </div>
</div>