<?php
// bsa/api/job-order-tracking-save.php — save Job Order/Request tracking progress (POST)
require_once(__DIR__ . '/../../includes/function.php');
require_once(__DIR__ . '/../includes/job-order-tables.php');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'INVALID_METHOD', 'message' => 'Invalid request method.']);
    exit;
}

if (empty($GLOBALS['userId'] ?? null)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'UNAUTHORIZED', 'message' => 'Please log in to update the job order progress.']);
    exit;
}

verify_csrf_token();
ensureJobOrderTables();

if (!function_exists('trackingInput')) {
    function trackingInput(string $key, int $maxLen = 65535): string
    {
        return mb_substr(trim((string) ($_POST[$key] ?? '')), 0, $maxLen);
    }
}

$jobOrderId = (int) ($_POST['job_order_id'] ?? 0);
$actionsTaken = trackingInput('actions_taken');
$recommendation = trackingInput('recommendation');
$assignedPersonnel = trackingInput('assigned_personnel', 255);
$dateTimeStarted = trackingInput('date_time_started', 16);
$dateTimeCompleted = trackingInput('date_time_completed', 16);
$trackingRemarks = trackingInput('tracking_remarks', 20);
$jobProponentName = trackingInput('job_proponent_name', 255);
$jobProponentSignature = trackingInput('job_proponent_signature', 255);

$errors = [];
if (!$jobOrderId) {
    $errors[] = 'A job order request is required.';
}
if (!in_array($trackingRemarks, jobOrderTrackingRemarks(), true)) {
    $errors[] = 'Please choose a valid Comments/Remarks value.';
}

// Date/time inputs arrive as datetime-local values (Y-m-d\TH:i); normalise to SQL datetime.
$normalise = function (string $value): string {
    if ($value === '') {
        return '';
    }
    $ts = strtotime(str_replace('T', ' ', $value));
    return $ts ? date('Y-m-d H:i:s', $ts) : '';
};

$dateTimeStartedSql = $normalise($dateTimeStarted);
$dateTimeCompletedSql = $normalise($dateTimeCompleted);

if ($dateTimeStarted !== '' && $dateTimeStartedSql === '') {
    $errors[] = 'Date & time Started is not a valid date and time.';
}

// The project stays in progress until it is explicitly marked Completed; only then is
// Date & Time Completed required (and it is cleared while the job is not completed).
if ($trackingRemarks === 'completed') {
    if ($dateTimeCompletedSql === '') {
        $errors[] = 'Date & Time Completed is required when Comments/Remarks is set to Completed.';
    }
} else {
    $dateTimeCompletedSql = null;
}

if ($errors) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'VALIDATION', 'message' => implode(' ', $errors)]);
    exit;
}

$order = find(
    "SELECT jo.id, jo.order_no, jo.status, jo.prepared_by, jo.noted_by,
            e.first_name AS emp_first_name, e.middle_name AS emp_middle_name, e.last_name AS emp_last_name
     FROM bsa_job_order_requests jo
     LEFT JOIN dcdmis.employees e ON CAST(jo.decided_by AS UNSIGNED) = e.id
     WHERE jo.id = ?",
    [$jobOrderId]
);
if (!$order) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'NOT_FOUND', 'message' => 'Job order request not found.']);
    exit;
}

if ($order['status'] !== 'approved') {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'NOT_APPROVED', 'message' => 'Only approved job order requests can be tracked.']);
    exit;
}

$gssFocal = bsaGssFocal();

// "Noted by" is the Administrative Officer V who decided the request; keep it in sync.
$notedFirst = trim((string) ($order['emp_first_name'] ?? ''));
$notedMiddle = trim((string) ($order['emp_middle_name'] ?? ''));
$notedLast = trim((string) ($order['emp_last_name'] ?? ''));
$notedByName = ($notedFirst !== '' || $notedLast !== '')
    ? strtoupper(trim($notedFirst . ($notedMiddle !== '' ? ' ' . substr($notedMiddle, 0, 1) . '.' : '') . ' ' . $notedLast))
    : trim((string) ($order['noted_by'] ?? ''));

$data = [
    'actions_taken'          => $actionsTaken !== '' ? $actionsTaken : null,
    'recommendation'         => $recommendation !== '' ? $recommendation : null,
    'assigned_personnel'     => $assignedPersonnel !== '' ? $assignedPersonnel : null,
    'prepared_by'            => $gssFocal['name'] !== '' ? $gssFocal['name'] : ($order['prepared_by'] ?? null),
    'date_time_started'      => $dateTimeStartedSql !== '' ? $dateTimeStartedSql : null,
    'date_time_completed'    => $dateTimeCompletedSql !== '' ? $dateTimeCompletedSql : null,
    'tracking_remarks'       => $trackingRemarks,
    'job_proponent_name'     => $jobProponentName !== '' ? $jobProponentName : null,
    'job_proponent_signature' => $jobProponentSignature !== '' ? $jobProponentSignature : null,
    'noted_by'               => $notedByName !== '' ? $notedByName : null,
    'noted_at'               => date('Y-m-d H:i:s'),
    'tracking_updated_by'    => (string) $GLOBALS['userId'],
    'tracking_updated_at'    => date('Y-m-d H:i:s'),
];

if (query("UPDATE bsa_job_order_requests SET " . implode(', ', array_map(function ($k) {
    return "`$k` = ?";
}, array_keys($data))) . " WHERE id = ?", [...array_values($data), $jobOrderId]) === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'SAVE_FAILED', 'message' => 'An error occurred while saving the job order progress.']);
    exit;
}

echo json_encode([
    'success' => true,
    'id' => $jobOrderId,
    'order_no' => $order['order_no'],
    'tracking_remarks' => $trackingRemarks,
    'tracking_remarks_label' => jobOrderTrackingRemarksLabel($trackingRemarks),
    'message' => 'Job order progress saved.',
]);
