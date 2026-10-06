<?php
// bsa/api/job-order-save.php — create a Job Order/Request (POST)
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
    echo json_encode(['success' => false, 'error' => 'UNAUTHORIZED', 'message' => 'Please log in to submit a job order request.']);
    exit;
}

verify_csrf_token();
ensureJobOrderTables();

function jobOrderInput(string $key, int $maxLen = 65535): string
{
    return mb_substr(trim((string) ($_POST[$key] ?? '')), 0, $maxLen);
}

$dateRequest = jobOrderInput('date_request', 10);
$requestingOffice = jobOrderInput('requesting_office', 255);
$requestingPersonnel = jobOrderInput('requesting_personnel', 255);
$locationOfWork = jobOrderInput('location_of_work', 255);
$otherScope = jobOrderInput('other_scope');
$requestorName = jobOrderInput('requestor_name', 255);
$workTypes = is_array($_POST['work_types'] ?? null) ? $_POST['work_types'] : [];
$allowedWorkTypes = ['Electrical', 'Carpentry', 'Airconditioning', 'Janitorial', 'Plumbing', 'ICT-related', 'Others'];
$workTypes = array_values(array_intersect($allowedWorkTypes, array_map('strval', $workTypes)));

$errors = [];
if ($dateRequest === '' || !strtotime($dateRequest)) {
    $errors[] = 'A valid Date is required.';
}
if ($requestingOffice === '') {
    $errors[] = 'Requesting Office is required.';
}
if ($requestingPersonnel === '') {
    $errors[] = 'Requesting Personnel is required.';
}
if ($locationOfWork === '') {
    $errors[] = 'Location of Work is required.';
}
if (!$workTypes) {
    $errors[] = 'Select at least one type of work.';
}
if (in_array('Others', $workTypes, true) && $otherScope === '') {
    $errors[] = 'Please describe the other scope of work.';
}
if ($requestorName === '') {
    $errors[] = "Requestor's Name is required.";
}

if ($errors) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'VALIDATION', 'message' => implode(' ', $errors)]);
    exit;
}

try {
    $db = connection();
    $year = date('Y');
    beginTransaction();
    query("SELECT GET_LOCK(?, 10)", ['bsa_job_ref_seq']);
    $rows = query("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(order_no, '-', -1) AS UNSIGNED)), 0) + 1 AS n FROM bsa_job_order_requests WHERE order_no LIKE ?", ["JO-{$year}-%"]);
    $orderNo = sprintf('JO-%s-%05d', $year, (int) ($rows[0]['n'] ?? 1));

    try {
        $id = insert('bsa_job_order_requests', [
            'order_no' => $orderNo,
            'date_request' => $dateRequest,
            'requesting_office' => $requestingOffice,
            'requesting_personnel' => $requestingPersonnel,
            'location_of_work' => $locationOfWork,
            'description_of_work' => implode(', ', $workTypes),
            'other_scope' => $otherScope !== '' ? $otherScope : null,
            'requestor_name' => $requestorName,
            'status' => 'pending',
            'created_by' => (string) $GLOBALS['userId'],
        ]);

    if ($id === false) {
        throw new Exception('SAVE_FAILED');
    }

        query("SELECT RELEASE_LOCK(?)", ['bsa_job_ref_seq']);
        commit();
        echo json_encode(['success' => true, 'id' => (int) $id, 'order_no' => $orderNo, 'message' => 'Job order request submitted successfully.']);
    } catch (Throwable $e) {
        query("SELECT RELEASE_LOCK(?)", ['bsa_job_ref_seq']);
        throw $e;
    }
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'SAVE_FAILED', 'message' => 'An error occurred while saving the job order request.']);
}
