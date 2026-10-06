<?php
// export/document-log.php
if (!isset($_GET['v']) || empty($_GET['v'])) {
    require_once('../includes/function.php');
    redirect(uri() . '/login');
}

require_once('../includes/database/database.php');
require_once('../includes/database/document.php');
require_once('../includes/string.php');
require_once('../includes/database/school.php');
require_once('../includes/database/section.php');
require_once('../includes/database/employee.php');
require_once('../includes/database/position.php');
require_once('../includes/database/utility.php');

$documentId = isset($_GET['id']) ? sanitize(decode($_GET['id'])) : null;
$document = document($documentId); ?>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <td colspan="5"><?= e($document['id']) ?></td>
        </tr>
        <tr>
            <th>Type</th>
            <td colspan="5"><?= documentType($document['document_type_id'])['name'] ?></td>
        </tr>
        <tr>
            <th>Description</th>
            <td colspan="5"><?= e($document['description']) ?></td>
        </tr>
        <tr>
            <th>Created On</th>
            <td colspan="5"><?= toDate($document['created_at'], 'F d, Y h:i:s A') ?></td>
        </tr>
        <tr>
            <th>From</th>
            <td colspan="5"><?= stationName($document['created_from']) ?></td>
        </tr>
        <tr>
            <th>Status</th>
            <td colspan="5"><?= documentTransactionStatus($document['status_id']) ?></td>
        </tr>
        <tr>
            <th>Datetime</th>
            <th>Station/School</th>
            <th>Processed By</th>
            <th>Position</th>
            <th>Status</th>
            <th>Remarks</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $logs = documentLogs($documentId);
        foreach ($logs as $log): ?>
            <tr>
                <td><?= date('M d, Y h:i:s A', strtotime($log['created_at'])) ?></td>
                <td><?= stationName($log['received_from']) ?></td>
                <td><?= userName($log['processor_id']) ?></td>
                <td><?= position($log['processor_id'])['official_title'] ?></td>
                <td><?= documentTransactionStatus($log['status_id']) ?></td>
                <td><?= e($log['details']) ?></td>
            </tr>
        <?php endforeach ?>
    </tbody>
</table>