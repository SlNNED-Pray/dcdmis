<?php
// bsa/vehicle-approvals-process.php — decide a vehicle request (POST)
require_once(__DIR__ . '/../includes/function.php');
require_once(__DIR__ . '/../includes/database/database.php');
require_once(__DIR__ . '/../includes/database/account.php');

if (empty($GLOBALS['userId'] ?? null)) {
    redirect(uri() . '/login');
}

if (!userRole($GLOBALS['userId'], 'bsa')) {
    $_SESSION['vehicle_flash'] = ['type' => 'danger', 'message' => 'You do not have permission to decide vehicle requests.'];
    header('Location: ' . uri() . '/bsa/');
    exit;
}

verify_csrf_token();

$listUrl = uri() . '/bsa/?v=' . encode('Booking Approvals');

$id = (int) ($_POST['vehicle_id'] ?? 0);
$action = trim((string) ($_POST['action'] ?? ''));
$remarks = trim((string) ($_POST['remarks'] ?? ''));

$validActions = ['available', 'not_available'];
if ($id <= 0 || !in_array($action, $validActions, true)) {
    $_SESSION['vehicle_flash'] = ['type' => 'danger', 'message' => 'Invalid vehicle request or action.'];
    header('Location: ' . $listUrl);
    exit;
}

$db = connection();

$stmt = $db->prepare("SELECT id, status FROM vehicle_requests WHERE id = ?");
$stmt->execute([$id]);
$vehicle = $stmt->fetch();

if (!$vehicle) {
    $_SESSION['vehicle_flash'] = ['type' => 'danger', 'message' => 'Vehicle request not found.'];
    header('Location: ' . $listUrl);
    exit;
}

if ($vehicle['status'] !== 'pending') {
    $_SESSION['vehicle_flash'] = ['type' => 'danger', 'message' => 'This vehicle request is not awaiting a decision.'];
    header('Location: ' . $listUrl . '&status=pending');
    exit;
}

try {
    $stmt = $db->prepare("
        UPDATE vehicle_requests
        SET status = ?, remarks = ?, decided_by = ?, decided_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$action, $remarks, (string) $GLOBALS['userId'], $id]);

    $_SESSION['vehicle_flash'] = [
        'type' => 'success',
        'message' => 'Vehicle request ' . $vehicle['id'] . ' marked as ' . str_replace('_', ' ', $action) . '.',
    ];
    header('Location: ' . $listUrl . '&status=' . ($action === 'available' ? 'approved' : 'rejected'));
} catch (Throwable $e) {
    $_SESSION['vehicle_flash'] = ['type' => 'danger', 'message' => 'Failed to update the vehicle request.'];
    header('Location: ' . $listUrl);
}