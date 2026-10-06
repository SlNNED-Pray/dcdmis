<?php
// modules/documents/edit-document.php
if (!$isDts) {
    require_once(root() . '/modules/error/403.php');
    return;
}

$documentId = isset($_GET['id']) ? sanitize(decode($_GET['id'])) : null;

$document = documentLog($documentId);
$documentLogs = null;
$description = $destination = $type = $purpose = $details = $attribute = $filename = '';
$isDescriptionEditable = true;
$pageTitle = 'New Document';
$hasDocument = true;
$forRelease = false;
$notFound = true;

if ($document) {
    $documentId = $document['id'];
    $type = $document['document_type_id'];
    $description = $document['description'];
    $destination = $document['forwarded_to'];
    $purpose = $document['status_id'];
    $details = $document['details'];
    $documentLogs = documentLogs($documentId);
    $documentStatus = strtolower(documentTransactionStatus($documentLogs[0]['status_id']));
    $hasDocument = !str_contains($documentStatus, 'complete') && !str_contains($documentStatus, 'cancel') && $documentLogs[0]['received_from'] === $station;
    $pageTitle = $hasDocument ? 'Edit Document' : 'Document not found';

    if (count($documentLogs) === 1 && $station === $document['created_from']) {
        $attribute = '';
        $isDescriptionEditable = true;
    } else {
        $attribute = ' disabled';
        $isDescriptionEditable = false;
    }

    $forRelease = str_contains(strtolower($document['status_id']), 'release') && $documentLogs[0]['received_from'] === 'RECORD' && $isRecordsPortal;
    $notFound = false;
} else {
    require_once(root() . '/modules/error/404.php');
    return;
}

$documentTypeDisplay = 'Outgoing Documents';

messageAlert($showAlert, $message, $success);
?>

<div class="d-flex align-items-center justify-content-between flex-row mt-2 mb-3">
    <nav class="d-flex align-items-center flex-row m-0">
        <ol class="breadcrumb m-0 p-0 bg-transparent">
            <li class="breadcrumb-item"><a href="<?= uri() . '/' . $activeApp ?>">Dashboard</a></li>
            <li class="breadcrumb-item">
                <a href="<?= customUri('dts', $documentTypeDisplay) ?>"><?= trim($documentTypeDisplay, ' Documents') ?></a>
            </li>
            <li class="breadcrumb-item"><a href="<?= customUri('dts', 'Document Information', $documentId) ?>"><?= e($documentId) ?></a></li>
            <li class="breadcrumb-item active">Edit</li>
        </ol>
    </nav>
</div>

<form action="" method="POST" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="card border-left-primary shadow mb-4">
        <div class="card-header py-3">
            <?php contentTitle("Edit Document: " . $document['id']) ?>
        </div>

        <div class="card-body">
            <table cellspacing="0" width="100%">
                <tr>
                    <th class="align-top pr-3 pt-2" width="20%" scope="row">Code:</th>
                    <td class="pb-2" width="80%">
                        <input id="code" type="text" value="<?= e($documentId) ?>" class="form-control text-uppercase" disabled>
                    </td>
                </tr>
                <tr>
                    <th class="align-top pr-3 pt-2" width="20%" scope="row">
                        Document Type <?php showAsterisk($isDescriptionEditable) ?>
                    </th>
                    <td class="pb-2" width="80%">
                        <?php if ($isDescriptionEditable): ?>
                            <select name="document-type" id="document-type" class="form-control" title="Select document type..." required>
                                <option value="">Select type...</option>
                                <?php
                                $documentTypes = $isSchoolPortal ? documentTypes(true) : documentTypes(false);
                                foreach ($documentTypes as $documentType): ?>
                                    <option value="<?= $documentType['id'] ?>" <?= setOptionSelected($documentType['id'], $type) ?>>
                                        <?= $documentType['name'] ?>
                                    </option>
                                <?php endforeach ?>
                                <option value="1" <?= setOptionSelected('1', $type) ?>>Others</option>
                            </select>
                        <?php else: ?>
                            <input id="document-type-name" type="text" class="form-control" value="<?= documentType($type)['name'] ?>" disabled>
                            <input type="hidden" name="document-type" value="<?= $type ?>">
                        <?php endif ?>
                    </td>
                </tr>
                <tr>
                    <th class="align-top pr-3 pt-2" width="20%" scope="row">
                        Description <?php showAsterisk($isDescriptionEditable) ?>
                    </th>
                    <td class="pb-2" width="80%">
                        <textarea id="description" name="description" class="form-control" rows="3" placeholder="Type description..." title="Type document description..." <?= $attribute ?> required><?= e($description) ?></textarea>
                    </td>
                </tr>
                <?php if (!$isSchoolPortal): ?>
                <tr>
                    <th class="align-top pr-3 pt-2" scope="row">
                        Destination <?php showAsterisk() ?>
                    </th>
                    <td class="pb-2">
                        <?php if (!$forRelease) { ?>
                            <select name="destination" id="destination" class="form-control" title="Select document destination..." required>
                                <option value="">Select destination...</option>
                                <?php
                                $divisions = functionalDivisions();
                                foreach ($divisions as $division): ?>
                                    <optgroup label="<?= $division['name'] ?>">
                                        <?php
                                        $sections = sections($division['id']);
                                        foreach ($sections as $section) {
                                            if ($section['id'] !== $station) { ?>
                                                <option value="<?= $section['id'] ?>" <?= setOptionSelected($section['id'], $destination) ?>>
                                                    <?= $section['name'] ?>
                                                </option>
                                                <?php
                                            }
                                        } ?>
                                    </optgroup>
                                <?php endforeach ?>
                            </select>
                        <?php } else {
                            $school = schoolByAlias($destination);
                            if ($school) {
                                $school = $school['name'];
                            }
                            ?>
                            <input id="destination" class="form-control" type="text" value="<?= e($school) ?>" disabled>
                            <input name="destination" class="form-control" type="hidden" value="<?= e($destination) ?>" required>
                        <?php } ?>
                    </td>
                </tr>
                <?php endif ?>
                <tr>
                    <th class="align-top pr-3 pt-2" scope="row">
                        Purpose <?php showAsterisk() ?>
                    </th>
                    <td class="pb-2">
                        <?php if (!$forRelease): ?>
                            <select name="purpose" id="purpose" class="form-control" title="Select document purpose..." required>
                                <option value="">Select purpose...</option>
                                <?php
                                $documentPurposes = documentTransactionStatus();
                                foreach ($documentPurposes as $documentPurpose): ?>
                                    <option value="<?= $documentPurpose['id'] ?>" <?= setOptionSelected($documentPurpose['id'], $purpose) ?>>
                                        <?= $documentPurpose['name'] ?>
                                    </option>
                                <?php endforeach ?>
                            </select>
                        <?php else: ?>
                            <input id="purpose" name="purpose" class="form-control" type="text" value="<?= e($purpose) ?>" required readonly>
                        <?php endif ?>
                    </td>
                </tr>
                <tr>
                    <th class="align-top pr-3 pt-2" scope="row">Additional details:</th>
                    <td class="pb-2">
                        <textarea id="details" name="details" class="form-control" rows="2" placeholder="Type additional details..." title="Type additional details..."><?= e($details) ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th class="align-top pr-3 pt-2" scope="row">Attachment:</th>
                    <td class="pb-2">
                        <?php
                        if ($documentLogs) {
                            $logId = $documentLogs[0]['id'];
                            $documentLogAttachments = documentLogAttachments($logId);

                            if ($documentLogAttachments) { ?>
                                <div class="small text-muted">Current attachments will be preserved. Uploading files will attach new ones.</div>
                                <div class="my-3">
                                    <div class="list-group">
                                        <?php foreach ($documentLogAttachments as $attachment) {
                                            $file = explode('_', $attachment['file_name'], 2);
                                            ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center px-2 py-1">
                                                <div class="d-flex align-items-center">
                                                    <?php linkButtonSplit("$baseUri/" . $attachment['file_name'], $file[1], 'fa-paperclip', "View $file[1]", 'secondary', true); ?>
                                                </div>
                                                <div class="ml-2">
                                                    <?= modalButtonSplit(uri() . '/modules/documents/delete-attachment-dialog.php?id=' . cipher($attachment['id']), '', 'fa-trash-alt', 'Delete Attachment', 'danger') ?>
                                                </div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            <?php }
                        } ?>

                        <input id="file-upload" name="file-upload[]" type="file" multiple class="w-100" accept="application/msword, application/vnd.openxmlformats-officedocument.wordprocessingml.document, application/pdf, image/*, application/vnd.ms-powerpoint, application/vnd.openxmlformats-officedocument.presentationml.presentation, application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
                        <div class="small mt-1 text-muted">Allowed: .doc/docx, .xls/xlsx, .ppt/pptx, .jpg/jpeg, .png, .gif, .pdf (Max file upload size: <?= UPLOAD_MAX_FILESIZE ?>B)</div>
                        
                    </td>
                </tr>
            </table>

            <?php requiredLegend(0); ?>
            <div class="d-flex align-items-center flex-row-reverse pt-3">
                <input type="hidden" name="verifier" value="<?= isset($_GET['id']) ? e($_GET['id']) : null ?>">
                    <input type="hidden" name="file-verifier" value="<?= cipher($filename ?? '') ?>">
                    <?php if ($documentLogs[0]['received_from'] == $station && $documentLogs[0]['forwarded_to'] !== null) { ?>
                        <button class="btn btn-primary ml-2" name="edit-document" type="submit">Save Changes</button>
                    <?php } ?>
                    <a href="<?= customUri('dts', 'Document Information', $documentId) ?>" class="btn btn-secondary">Cancel</a>
                </div>
        </div>
    </div>
</form>