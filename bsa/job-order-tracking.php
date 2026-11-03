<?php
/**
 * bsa/job-order-tracking.php
 * Job Order/Request tracking form (fillable + printable).
 *
 * Pops up when a job order request is approved. Records the work performed, the
 * personnel assigned, the start/completion timestamps and the sign-off. The job
 * stays "Work in progress" until Comments/Remarks is explicitly set to Completed.
 */
require_once(__DIR__ . '/../includes/function.php');
require_once(__DIR__ . '/../includes/database/database.php');
require_once(__DIR__ . '/includes/job-order-tables.php');

if (empty($GLOBALS['userId'] ?? null)) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

ensureJobOrderTables();

$jobOrderId = (int) ($_GET['id'] ?? $_POST['job_order_id'] ?? 0);
$order = $jobOrderId ? find(
    "SELECT jo.*, e.first_name AS emp_first_name, e.middle_name AS emp_middle_name, e.last_name AS emp_last_name
     FROM bsa_job_order_requests jo
     LEFT JOIN dcdmis.employees e ON CAST(jo.decided_by AS UNSIGNED) = e.id
     WHERE jo.id = ?",
    [$jobOrderId]
) : false;

if (!$order) {
    http_response_code(404);
    echo 'Job order request not found.';
    exit;
}

$csrfToken = csrf_token();
$printMode = !empty($_GET['print']);

$workTypes = array_values(array_filter(array_map('trim', explode(',', (string) $order['description_of_work']))));
$remarks = (string) ($order['tracking_remarks'] ?: 'work_in_progress');
if (!in_array($remarks, jobOrderTrackingRemarks(), true)) {
    $remarks = 'work_in_progress';
}
$isCompleted = $remarks === 'completed';
$gssFocal = bsaGssFocal();

$dtValue = function ($value): string {
    if (empty($value)) {
        return '';
    }
    $ts = strtotime((string) $value);
    return $ts ? date('Y-m-d\TH:i', $ts) : '';
};

$dateTimeStarted = $dtValue($order['date_time_started'] ?? null);
$dateTimeCompleted = $dtValue($order['date_time_completed'] ?? null);
$preparedBy = $gssFocal['name'] !== '' ? $gssFocal['name'] : (string) ($order['prepared_by'] ?? '');

// "Noted by" is the Administrative Officer V who decided the request; resolve from the database.
$notedFirst = trim((string) ($order['emp_first_name'] ?? ''));
$notedMiddle = trim((string) ($order['emp_middle_name'] ?? ''));
$notedLast = trim((string) ($order['emp_last_name'] ?? ''));
$notedBy = $notedFirst !== '' || $notedLast !== ''
    ? strtoupper(trim($notedFirst . ($notedMiddle !== '' ? ' ' . substr($notedMiddle, 0, 1) . '.' : '') . ' ' . $notedLast))
    : trim((string) ($order['noted_by'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Order/Request Form — <?php echo htmlspecialchars($order['order_no']); ?></title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f6f8; color: #111; font-family: "Times New Roman", serif; font-size: 13px; }
        .sheet { max-width: 95.25mm; margin: 16px auto; background: #fff; padding: 0; box-shadow: 0 2px 12px rgba(0,0,0,.12); }
        .header { font-family: "Old English Text MT", Arial, sans-serif; text-align: center; font-size: 16px; line-height: 1.25; }
        .header img { height: 2cm; width: auto; }
        .header2 { font-family: "Trajan Pro", Arial, sans-serif; text-align: center; font-size: 13px; border-bottom: 3px solid #111; padding-bottom: 6px; }
        h1 { font-size: 20px; text-align: center; margin: 16px 0 10px; }
        .control-no { text-align: right; margin-bottom: 10px; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border: 1px solid #111; padding: 6px 8px; vertical-align: top; }
        th { width: 18%; background: #e8e8e8; font-family: "Bookman Old Style", serif; font-size: 10pt; font-weight: 400; text-align: left; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        td.value { width: 32%; }
        .form-control, select, textarea, input[type="text"], input[type="datetime-local"] { width: 100%; border: 1px solid #999; padding: 5px 6px; font-family: "Times New Roman", serif; font-size: 12px; background: #fff; }
        textarea { min-height: 62px; resize: vertical; }
        .actions th { background: #e8e8e8; text-align: center; }
        .actions td { height: 58px; }
        .signatories th, .signatories td { height: 74px; }
        .ack { font-style: italic; margin: 0 0 10px; }
        .toolbar { max-width: 95.25mm; margin: 0 auto 12px; padding: 0; display: flex; gap: 8px; justify-content: flex-end; }
        .btn { font-family: Arial, sans-serif; font-size: 13px; padding: 7px 14px; border: 1px solid #666; background: #fff; cursor: pointer; }
        .btn-primary { background: #007bff; border-color: #007bff; color: #fff; }
        .btn-primary:disabled { opacity: .6; cursor: default; }
        #saveMessage { display: none; margin: 0 0 12px; padding: 9px 12px; font-family: Arial, sans-serif; font-size: 13px; }
        #saveMessage.alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        #saveMessage.alert-danger { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .hint { font-family: Arial, sans-serif; font-size: 11px; color: #555; }
        @page { size: A4 portrait; margin: 10mm 12.7mm 16mm; }
        @media print {
            body { background: #fff; }
            .toolbar, #saveMessage, .hint { display: none !important; }
            .sheet, .jo-print-scope .jo-sheet { max-width: none !important; margin: 0 !important; padding: 0 !important; box-shadow: none !important; }
            /* The print sheet carries its own A4 geometry (196mm x 279mm); restore it
               after the generic reset above so it fills exactly one page. */
            .jo-print-scope .jo-sheet { width: 95.25mm !important; max-width: 95.25mm !important; margin: 0 auto !important; padding: 0 !important; min-height: 317.5mm !important; }
            input, textarea, select { border: 0 !important; padding: 0 !important; background: transparent !important; }
        }
    </style>
</head>
<body>

<?php if (!$printMode): ?>
<div class="toolbar">
    <button type="button" class="btn" onclick="window.print();"><i class="fa">&#128424;</i> Print</button>
    <button type="button" class="btn btn-primary" id="saveTrackingBtn">Save Progress</button>
</div>
<?php endif; ?>

<?php if ($printMode): ?>
    <?php
    // Print renders the same read-only DepEd Annex 8 sheet as the approvals print view,
    // so both entry points produce an identical document.
    $printSheetPrintButton = false;
    $jobOrder = $order;
    require __DIR__ . '/includes/job-order-print-sheet.php';
    ?>
<?php else: ?>
<div class="sheet">
    <div class="header">
        <img src="image/logo.png" alt=""><br>
        Republic of the Philippines<br>
        Department of Education
    </div>
    <div class="header2"> Region IX &ndash; Zamboanga Peninsula <br>
        SCHOOLS DIVISION OF DIPOLOG CITY
    </div>

    <h1>Job Order/Request Form</h1>
    <div class="control-no">Order No. <strong><?php echo htmlspecialchars($order['order_no']); ?></strong></div>

    <div id="saveMessage" class="alert"></div>

    <form id="trackingForm" method="POST" action="<?php echo uri(); ?>/bsa/api/job-order-tracking-save.php">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <input type="hidden" name="job_order_id" value="<?php echo (int)$order['id']; ?>">

        <table>
            <tr>
                <th>Requesting Personnel</th>
                <td class="value"><?php echo htmlspecialchars($order['requesting_personnel']); ?></td>
                <th>Date</th>
                <td class="value"><?php echo date('M j, Y', strtotime((string) $order['date_request'])); ?></td>
            </tr>
            <tr>
                <th>Location of Work</th>
                <td class="value"><?php echo htmlspecialchars($order['location_of_work']); ?></td>
                <th>Job Proponent</th>
                <td class="value"><?php echo htmlspecialchars($order['requestor_name']); ?></td>
            </tr>
            <tr>
                <th>Description of Work</th>
                <td colspan="3">
                    <?php foreach (['Electrical', 'Carpentry', 'Airconditioning', 'Janitorial', 'Plumbing', 'ICT-related', 'Others'] as $workType): ?>
                        [<?php echo in_array($workType, $workTypes, true) ? 'X' : ' '; ?>] <?php echo htmlspecialchars($workType); ?>&nbsp;&nbsp;
                    <?php endforeach; ?>
                    <?php if (!empty($order['other_scope'])): ?>
                        <br><strong>Other scope of work:</strong> <?php echo nl2br(htmlspecialchars($order['other_scope'])); ?>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <table>
            <tr>
                <th>Actions Taken</th>
                <td colspan="3">
                    <textarea name="actions_taken" class="form-control" placeholder="Describe the work performed so far..."><?php echo htmlspecialchars((string) ($order['actions_taken'] ?? '')); ?></textarea>
                </td>
            </tr>
            <tr>
                <th>Recommendation</th>
                <td colspan="3">
                    <textarea name="recommendation" class="form-control" placeholder="Recommendation for the next step..."><?php echo htmlspecialchars((string) ($order['recommendation'] ?? '')); ?></textarea>
                </td>
            </tr>
            <tr>
                <th>Assigned Personnel</th>
                <td colspan="3">
                    <input type="text" name="assigned_personnel" value="<?php echo htmlspecialchars((string) ($order['assigned_personnel'] ?? '')); ?>" placeholder="Names of personnel assigned">
                </td>
            </tr>
            <tr>
                <th>Date &amp; time Started</th>
                <td class="value">
                    <input type="datetime-local" name="date_time_started" value="<?php echo htmlspecialchars($dateTimeStarted); ?>">
                </td>
                <th>Date &amp; Time Completed</th>
                <td class="value">
                    <input type="datetime-local" name="date_time_completed" id="dateTimeCompleted" value="<?php echo htmlspecialchars($dateTimeCompleted); ?>" <?php echo $isCompleted ? '' : 'disabled'; ?>>
                    <div class="hint" id="completedHint"><?php echo $isCompleted ? '' : 'Enabled when Comments/Remarks is set to Completed.'; ?></div>
                </td>
            </tr>
            <tr>
                <th>Comments/ Remarks</th>
                <td colspan="3">
                    <select name="tracking_remarks" id="trackingRemarks">
                        <?php foreach (jobOrderTrackingRemarks() as $option): ?>
                            <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $option === $remarks ? 'selected' : ''; ?>><?php echo htmlspecialchars(jobOrderTrackingRemarksLabel($option)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>

        <p class="ack">I hereby acknowledge that the work above has been satisfactorily completed.</p>

        <table class="actions">
            <tr><th colspan="2">Actions Taken</th></tr>
            <tr>
                <td>
                    Status:
                    [<?php echo $isCompleted ? 'X' : ' '; ?>] Completed<br>
                    [<?php echo !$isCompleted ? 'X' : ' '; ?>] Work in progress
                </td>
                <td>
                    <strong>Last updated:</strong>
                    <?php echo !empty($order['tracking_updated_at']) ? date('M j, Y g:i A', strtotime((string) $order['tracking_updated_at'])) : 'Not yet saved'; ?>
                </td>
            </tr>
            <tr class="signatories">
                <th>Prepared by:<br><small>(GSS Focal)</small></th>
                <td><?php echo htmlspecialchars($preparedBy !== '' ? $preparedBy : 'GSS Focal'); ?></td>
                <th>Noted by:</th>
                <td>
                    <em>Administrative Officer V (Admin)</em><br>
                    <?php echo htmlspecialchars($notedBy !== '' ? $notedBy : '____________________'); ?>
                </td>
            </tr>
            <tr class="signatories">
                <th>Job proponent:<br><small>(Name and signature)</small></th>
                <td colspan="3">
                    <input type="text" name="job_proponent_name" value="<?php echo htmlspecialchars((string) ($order['job_proponent_name'] ?? '')); ?>" placeholder="Name">
                    <input type="text" name="job_proponent_signature" value="<?php echo htmlspecialchars((string) ($order['job_proponent_signature'] ?? '')); ?>" placeholder="Signature">
                </td>
            </tr>
        </table>
    </form>
</div>
<?php endif; ?>

<?php if (!$printMode): ?>
<script>
(function () {
    var remarks = document.getElementById('trackingRemarks');
    var completed = document.getElementById('dateTimeCompleted');
    var completedHint = document.getElementById('completedHint');

    function syncCompleted() {
        var isCompleted = remarks.value === 'completed';
        completed.disabled = !isCompleted;
        if (!isCompleted) {
            completed.value = '';
        }
        if (completedHint) {
            completedHint.textContent = isCompleted ? '' : 'Enabled when Comments/Remarks is set to Completed.';
        }
    }

    if (remarks) {
        remarks.addEventListener('change', syncCompleted);
        syncCompleted();
    }

    var form = document.getElementById('trackingForm');
    var button = document.getElementById('saveTrackingBtn');
    var message = document.getElementById('saveMessage');

    function showMessage(type, text) {
        if (!message) {
            return;
        }
        message.className = 'alert alert-' + type;
        message.textContent = text;
        message.style.display = 'block';
    }

    if (button && form) {
        button.addEventListener('click', function () {
            var data = new FormData(form);
            if (completed && completed.disabled) {
                data.delete('date_time_completed');
            }
            button.disabled = true;
            fetch(form.getAttribute('action'), {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (response) { return response.json(); })
                .then(function (result) {
                    button.disabled = false;
                    if (!result || !result.success) {
                        showMessage('danger', (result && result.message) || 'Unable to save the progress.');
                        return;
                    }
                    showMessage('success', result.message + ' (' + result.tracking_remarks_label + ')');
                })
                .catch(function () {
                    button.disabled = false;
                    showMessage('danger', 'An error occurred while saving the progress.');
                });
        });
    }
}());
</script>
<?php endif; ?>
</body>
</html>
