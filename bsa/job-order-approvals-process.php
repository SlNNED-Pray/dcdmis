<?php
/**
 * bsa/job-order-approvals-process.php (POST handler, not a shell view)
 * Approve / Reject a Job Order/Request (bsa_job_order_requests).
 */
require_once(__DIR__ . '/../includes/function.php');
require_once(__DIR__ . '/../includes/database/database.php');
require_once(__DIR__ . '/../includes/database/account.php');
require_once(__DIR__ . '/../includes/database/system-log.php');
require_once(__DIR__ . '/includes/job-order-tables.php');

$listUrl = uri() . '/bsa/?v=' . encode('Booking Approvals');

$isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') || (($_POST['ajax'] ?? '') === '1');

$respond = function (array $payload, string $redirectUrl, int $status = 200) use ($isAjax) {
    if ($isAjax) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit;
    }
    if (!empty($payload['message'])) {
        $_SESSION['joborder_flash'] = [
            'type' => $payload['type'] ?? (($payload['success'] ?? false) ? 'success' : 'danger'),
            'message' => $payload['message'],
        ];
    }
    header('Location: ' . $redirectUrl);
    exit;
};

if (empty($GLOBALS['userId'] ?? null)) {
    $respond(['success' => false, 'message' => 'Please log in before deciding a job order request.'], uri() . '/login', $isAjax ? 401 : 302);
}

if (!userRole($GLOBALS['userId'], 'bsa')) {
    $respond(['success' => false, 'message' => 'You do not have permission to decide job order requests.'], uri() . '/bsa/');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $respond(['success' => false, 'message' => 'Invalid request method.'], $listUrl, $isAjax ? 405 : 302);
}

verify_csrf_token();

ensureJobOrderTables();

$jobOrderId = (int) ($_POST['job_order_id'] ?? 0);
$action = $_POST['action'] ?? '';
$remarks = trim($_POST['remarks'] ?? '');
$remarks = $remarks === '' ? null : mb_substr($remarks, 0, 500);

if (!in_array($action, ['approve', 'reject'], true) || !$jobOrderId) {
    $respond(['success' => false, 'message' => 'Invalid request.'], $listUrl);
}

$order = find("SELECT id, order_no, status FROM bsa_job_order_requests WHERE id = ?", [$jobOrderId]);

if (!$order) {
    $respond(['success' => false, 'message' => 'Job order request not found.'], $listUrl);
}

$backUrl = $listUrl . '&id=' . (int) $order['id'] . '&type=joborder';
$printUrl = $backUrl . '&print=1';
$trackingUrl = uri() . '/bsa/job-order-tracking.php?id=' . (int) $order['id'];

if ($order['status'] !== 'pending') {
    $respond([
        'success' => false,
        'type' => 'warning',
        'message' => 'This job order request has already been ' . $order['status'] . ' and cannot be re-decided.',
    ], $backUrl);
}

$newStatus = $action === 'approve' ? 'approved' : 'rejected';

beginTransaction();
try {
    if (update('bsa_job_order_requests', [
        'status' => $newStatus,
        'remarks' => $remarks,
        'decided_by' => (string) $GLOBALS['userId'],
        'decided_at' => date('Y-m-d H:i:s'),
    ] + ($action === 'approve' ? ['tracking_remarks' => 'work_in_progress'] : []), '`id` = ?', [$jobOrderId]) === false) {
        throw new Exception('Failed to update job order status.');
    }
    commit();
    try {
        createSystemLog($GLOBALS['stationId'] ?? null, $GLOBALS['userId'], 'Job Order ' . ucfirst($newStatus), $order['order_no'], clientIp());
    } catch (Throwable $logError) {
        error_log('Job order system log error: ' . $logError->getMessage());
    }
    $respond([
        'success' => true,
        'message' => 'Job order request ' . $order['order_no'] . ' ' . ucfirst($newStatus) . ' successfully.',
        'status' => $newStatus,
        'id' => (int) $order['id'],
        'order_no' => $order['order_no'],
        'track_url' => $action === 'approve' ? $trackingUrl : null,
        'request_print_url' => $printUrl,
    ], $backUrl);
} catch (Throwable $e) {
    rollBack();
    error_log('Job order decision error: ' . $e->getMessage());
    $respond(['success' => false, 'message' => 'An error occurred while saving the decision.'], $backUrl);
}
