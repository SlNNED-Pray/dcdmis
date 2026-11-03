<?php
// bsa/vehicle-approvals-process.php — decide a vehicle request (POST)
require_once(__DIR__ . '/../includes/function.php');
require_once(__DIR__ . '/../includes/database/database.php');
require_once(__DIR__ . '/../includes/database/account.php');
require_once(__DIR__ . '/includes/bsa-access.php');

if (empty($GLOBALS['userId'] ?? null)) {
    redirect(uri() . '/login');
}

if (!canAccessBookingApprovals($GLOBALS['userId'])) {
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

$stmt = $db->prepare("SELECT id, status, vehicle_type, depart_date, depart_time, return_date, return_time FROM vehicle_requests WHERE id = ?");
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

if ($action === 'available') {
    $endDate = $vehicle['return_date'] ?: $vehicle['depart_date'];
    $endTime = $vehicle['return_time'] ?: '23:59';
    $conflictStmt = $db->prepare("
        SELECT control_no, status
        FROM vehicle_requests
        WHERE status IN ('pending', 'available')
          AND id <> ?
          AND vehicle_type = ?
          AND CONCAT(depart_date, ' ', depart_time) < CONCAT(?, ' ', ?)
          AND CONCAT(COALESCE(return_date, depart_date), ' ', COALESCE(return_time, '23:59')) > CONCAT(?, ' ', ?)
        ORDER BY depart_date ASC, depart_time ASC
    ");
    $conflictStmt->execute([
        $id,
        $vehicle['vehicle_type'],
        $endDate, $endTime,
        $vehicle['depart_date'], $vehicle['depart_time'],
    ]);
    $overlaps = $conflictStmt->fetchAll();

    if ($overlaps) {
        $refs = array_map(function ($r) {
            return htmlspecialchars($r['control_no']) . ' (' . ucfirst($r['status']) . ')';
        }, $overlaps);
        $_SESSION['vehicle_flash'] = [
            'type' => 'danger',
            'message' => 'Vehicle is not available. Select another Vehicle Type (conflicts with: ' . implode(', ', $refs) . ').'
        ];
        header('Location: ' . $listUrl . '&status=pending');
        exit;
    }
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