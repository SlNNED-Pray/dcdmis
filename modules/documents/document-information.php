<?php
// modules/documents/document-information.php
if (!$isDts) {
    require_once(root() . '/modules/error/403.php');
    return;
}

$documentId = sanitize(decode($_GET['id'] ?? null));
$document = document($documentId);

if ($document === false) {
    require_once(root() . '/modules/error/no-results-found.php');
    return;
}

messageAlert($showAlert, $message, $success);

$documentId = $document['id'];
$documentType = 'Ongoing Documents';

if (isCompletedDocument($documentId, $station)) {
    $documentType = 'Completed Documents';
} elseif (isCanceledDocument($documentId, $station)) {
    $documentType = 'Canceled Documents';
} elseif (isIncomingDocument($documentId, $station)) {
    $documentType = 'Incoming Documents';
} elseif (isPendingDocument($documentId, $station)) {
    $documentType = 'Pending Documents';
} elseif (isOutgoingDocument($documentId, $station)) {
    $documentType = 'Outgoing Documents';
} elseif (isReceivedDocument($documentId, $station)) {
    $documentType = 'Received Documents';
}

$logs = documentLogs($documentId);
?>

<div class="d-flex align-items-center justify-content-between flex-row mt-2 mb-3">
    <nav class="d-flex align-items-center flex-row m-0">
        <ol class="breadcrumb m-0 p-0 bg-transparent">
            <li class="breadcrumb-item"><a href="<?= uri() . '/' . $activeApp ?>">Dashboard</a></li>
            <li class="breadcrumb-item">
                <a href="<?= customUri('dts', $documentType) ?>"><?= trim($documentType, ' Documents') ?></a>
            </li>
            <li class="breadcrumb-item active"><?= e($documentId) ?></li>
        </ol>
    </nav>
</div>

<div class="card border-left-primary shadow mb-4">
    <div class="card-header py-3">
        <?php contentTitle("Document Information: " . strtoupper($documentId)) ?>
    </div>

    <div class="card-body">
        <table cellspacing="0">
            <tr>
                <th class="align-top pr-3" scope="row">Type:</th>
                <td class="text-uppercase">
                    <?= documentType($document['document_type_id'])['name'] ?>
                </td>
            </tr>
            <tr>
                <th class="align-top pr-3" scope="row">Description:</th>
                <td class="text-uppercase"><?= e($document['description']) ?></td>
            </tr>
            <tr>
                <th class="align-top pr-3" scope="row">Created on:</th>
                <td class="text-uppercase"><?= toDate($document['created_at'], 'F d, Y h:i:s A') ?></td>
            </tr>
            <tr>
                <th class="align-top pr-3" scope="row">From:</th>
                <td class="text-uppercase"><?= stationName($document['created_from']) ?></td>
            </tr>
            <?php if ($document['status_id'] !== null): ?>
                <tr>
                    <th class="align-top pr-3" scope="row">Status:</th>
                    <td class="text-uppercase">
                        <?= documentTransactionStatus($document['status_id']) ?>
                    </td>
                </tr>
            <?php endif ?>
        </table>

        <div class="d-flex align-items-center flex-row-reverse mt-2 mb-3">
            <div class="d-inline-block">
                <?php
                $hasSuccess = false;

                linkButtonSplit(customUri('print', 'Document Tracking Slip', $documentId), 'Print', 'fa-print', 'Print Document Tracking Slip', 'primary', true);

                switch ($documentType) {
                    case 'Incoming Documents':
                        if (!isDocument($documentId, 'Cancel')) {
                            modalButtonSplit(uri() . '/modules/documents/receive-document-dialog.php?id=' . cipher($documentId), 'Receive', 'fa-hand-holding-medical', 'Receive Document', 'success');
                            $hasSuccess = true;
                        }
                        break;
                    case 'Pending Documents':
                        if (!$isSchoolPortal) {
                            modalButtonSplit(uri() . '/modules/documents/forward-document-dialog.php?id=' . cipher($documentId), 'Forward', 'fa-share', 'Forward Document', 'info');
                        }
                        modalButtonSplit(uri() . '/modules/documents/complete-document-dialog.php?id=' . cipher($documentId), 'Mark Complete', 'fa-check-circle', 'Mark Complete Document', 'success');
                        break;
                    case 'Outgoing Documents':
                        if (!isDocument($documentId, 'Complete') && !isDocument($documentId, 'cancel')) {
                            linkButtonSplit(customUri('dts', 'Edit Document', $documentId), 'Edit', 'fa-edit', 'Edit Document', 'info');
                        }
                        break;
                    case 'Completed Documents':
                    case 'Received Documents':
                        if (isDocument($documentId, 'complete') && $logs[0]['received_from'] === $station) {
                            modalButtonSplit(uri() . '/modules/documents/reopen-document-dialog.php?id=' . cipher($documentId), 'Reopen', 'fa-envelope-open', 'Reopen Document', 'danger');
                        }
                        break;
                    case 'Canceled Documents':
                        if (isDocumentFrom($documentId, $station) && $document['created_from'] === $station && isDocument($documentId, 'cancel')) {
                            modalButtonSplit(uri() . '/modules/documents/restore-document-dialog.php?id=' . cipher($documentId), 'Restore', 'fa-undo', 'Restore Document', 'success');
                            $hasSuccess = true;
                        }
                        break;
                    default:
                        break;
                }

                switch ($documentType) {
                    case 'Pending Documents':
                        if ($isSchoolPortal) {
                            break;
                        }

                        $hasSuccess = true;

                        if (isDocumentFrom($documentId, $station) && $document['created_from'] === $station && !isDocument($documentId, 'Cancel')) {
                            modalButtonSplit(uri() . '/modules/documents/cancel-document-dialog.php?id=' . cipher($documentId), 'Cancel', 'fa-trash-alt', 'Cancel Document', 'danger');
                        }
                        break;
                    case 'Outgoing Documents':
                        if (isDocumentFrom($documentId, $station) && $document['created_from'] === $station && !isDocument($documentId, 'Cancel')) {
                            modalButtonSplit(uri() . '/modules/documents/cancel-document-dialog.php?id=' . cipher($documentId), 'Cancel', 'fa-trash-alt', 'Cancel Document', 'danger');
                        }
                        break;
                    default:
                        break;
                }

                linkButtonSplit(customUri('export', 'document-log', $documentId), 'Export', 'fa-file-excel', 'Export as Excel file', 'warning');
                ?>
            </div>
        </div>

        <div class="timeline">
            <?php
            $logCount = 0;

            foreach ($logs as $log) {
                $logCount++;
                $logId = $log['id'];
                $from = stationName($log['received_from']);
                $to = stationName($log['forwarded_to']);
                $displayName = userName($log['processor_id'], true, true);
                $user = employee($log['processor_id']);
                $displayPhoto = file_exists(root() . '/' . $user['profile_picture']) ? uri() . '/' . $user['profile_picture'] : uri() . '/assets/img/user.png';
                $icon = 'flag';
                $hasDestination = !empty($to) && $to !== null;
                $status = documentTransactionStatus($log['status_id']);
                $details = $log['details'];
                $isCompleted = str_contains(strtolower($status), 'complete');
                $isCanceled = str_contains(strtolower($status), 'cancel');
                $bgColor = '';

                if ($logCount === 1) {
                    $bgColor = 'bg-success';
                }

                if ($logCount === 1 && $hasDestination) {
                    $icon = 'truck';
                }

                if ($logCount > 1 && $hasDestination) {
                    $icon = 'flag';
                }

                if ($logCount >= 1 && !$hasDestination) {
                    $icon = 'check';
                }

                if ($logCount === 1 && $isCompleted) {
                    $icon = 'trophy';
                }

                if ($logCount === 1 && $isCanceled) {
                    $icon = 'times';
                    $bgColor = 'bg-danger';
                }
                ?>
                <div class="timeline-item">
                    <div class="timeline-item-marker">
                        <div class="timeline-item-marker-text text-uppercase">
                            <?= date('M d, Y', strtotime($log['created_at'])) . '<br>' . date('h:i:s A', strtotime($log['created_at'])) ?>
                        </div>
                        <div class="timeline-item-marker-indicator <?= e($bgColor) ?>">
                            <i class="fas fa-<?= e($icon) ?>"></i>
                        </div>
                    </div>
                    <div class="timeline-item-content pt-0">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="timeline-item-content-header-text font-weight-bold text-uppercase mb-0">
                                    <?= e($from) ?>
                                </h5>
                            </div>

                            <div class="card-body">
                                <div>
                                    <span
                                        class="d-inline-block img-profile rounded-circle justify-content-center align-middle overflow-hidden">
                                        <img src="<?= e($displayPhoto) ?>" alt="<?= e($displayName) ?>" height="40px"
                                            width="40px">
                                    </span>

                                    <div class="ml-2 d-inline-block align-middle">
                                        <div class="text-uppercase">
                                            <?php modalItem(uri() . '/modules/users/user-info-dialog.php?id=' . cipher($log['processor_id']), $displayName) ?>
                                        </div>

                                        <div class="text-uppercase text-xs">
                                            <?= position($log['processor_id'])['official_title'] ?>
                                        </div>
                                    </div>
                                </div>

                                <?= $hasDestination ? '<div class="mb-3">Forwarded to ' . strtoupper($to) . '</div>' : '' ?>

                                <div class="font-weight-bold text-lg"><?= e($status) ?></div>

                                <?php if (!empty($details)): ?>
                                    <div class="alert alert-warning d-inline-block px-2 py-1 mt-3 mb-0"><?= e($details) ?></div>
                                <?php endif ?>
                            </div>

                            <?php $documentLogAttachments = documentLogAttachments($logId);

                            if ($documentLogAttachments): ?>
                                <div class="card-footer">
                                    <?php foreach ($documentLogAttachments as $attachment) {
                                        $file = explode('_', $attachment['file_name'], 2);
                                        linkButtonSplit("$baseUri/" . $attachment['file_name'], $file[1], 'fa-paperclip', "View $file[1]", 'secondary', true);
                                    } ?>
                                </div>
                            <?php endif ?>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>