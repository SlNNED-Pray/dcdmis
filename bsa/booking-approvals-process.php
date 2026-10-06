<?php
/**
 * bsa/booking-approvals-process.php (POST handler, not a shell view)
 * Approve / Reject a DTC-SC facility booking. Runs as a standalone endpoint
 * (like bsa/bookings-save.php) so it can redirect before any shell output.
 */
require_once(__DIR__ . '/../includes/function.php');
require_once(__DIR__ . '/includes/dtcsc-optional.php');

$listUrl = uri() . '/bsa/?v=' . encode('Booking Approvals');

if (empty($GLOBALS['userId'] ?? null)) {
    header('Location: ' . uri() . '/login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $listUrl);
    exit;
}

if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
    $_SESSION['dtcsc_flash'] = ['type' => 'danger', 'message' => 'Invalid security token. Please try again.'];
    header('Location: ' . $listUrl);
    exit;
}

if (!$dtcscSiblingAvailable) {
    $_SESSION['dtcsc_flash'] = ['type' => 'warning', 'message' => 'The DTC-SC Booking system is not installed. Facility bookings are unavailable.'];
    header('Location: ' . $listUrl);
    exit;
}

$bookingId = (int)($_POST['booking_id'] ?? 0);
$action = $_POST['action'] ?? '';
$remarks = trim($_POST['remarks'] ?? '');
$remarks = $remarks === '' ? null : mb_substr($remarks, 0, 500);

if (!in_array($action, ['approve', 'reject'], true) || !$bookingId) {
    $_SESSION['dtcsc_flash'] = ['type' => 'danger', 'message' => 'Invalid request.'];
    header('Location: ' . $listUrl);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
} catch (Throwable $e) {
    $_SESSION['dtcsc_flash'] = ['type' => 'danger', 'message' => 'The DTC-SC Booking system is currently unavailable.'];
    header('Location: ' . $listUrl);
    exit;
}

$stmt = $db->prepare("
    SELECT booking_id, status, facility_id, start_date, end_date, start_time, end_time
    FROM bookings
    WHERE booking_id = ?
");
$stmt->execute([$bookingId]);
$booking = $stmt->fetch();

if (!$booking) {
    $_SESSION['dtcsc_flash'] = ['type' => 'danger', 'message' => 'Booking not found.'];
    header('Location: ' . $listUrl);
    exit;
}

$backUrl = $listUrl . '&id=' . (int)$booking['booking_id'];

if ($booking['status'] !== 'pending') {
    $_SESSION['dtcsc_flash'] = ['type' => 'warning', 'message' => 'This booking has already been ' . $booking['status'] . ' and cannot be re-decided.'];
    header('Location: ' . $backUrl);
    exit;
}

if ($action === 'approve') {
    $overlaps = findOverlappingBookings(
        (int)$booking['facility_id'],
        $booking['start_date'],
        $booking['end_date'],
        $booking['start_time'],
        $booking['end_time'],
        (int)$booking['booking_id']
    );

    if ($overlaps) {
        $refs = array_map(function ($r) {
            return htmlspecialchars($r['booking_reference']) . ' (' . ucfirst($r['status']) . ')';
        }, $overlaps);
        $_SESSION['dtcsc_flash'] = [
            'type' => 'danger',
            'message' => 'Cannot approve: the schedule overlaps with: ' . implode(', ', $refs) . '. Resolve the conflict first.'
        ];
        header('Location: ' . $backUrl);
        exit;
    }
}

$newStatus = $action === 'approve' ? 'approved' : 'rejected';
$adminName = 'DTC Admin';

$db->beginTransaction();
try {
    $stmt = $db->prepare("
        UPDATE bookings
        SET status = ?, remarks = ?, approved_by = ?, decided_at = NOW()
        WHERE booking_id = ?
    ");
    $stmt->execute([$newStatus, $remarks, $adminName, $bookingId]);

    $stmt = $db->prepare("UPDATE booking_concurrence SET signature_status = ? WHERE booking_id = ?");
    $stmt->execute([$newStatus, $bookingId]);

    $db->commit();

    $_SESSION['dtcsc_flash'] = [
        'type' => 'success',
        'message' => 'Booking ' . ucfirst($newStatus) . ' successfully.'
    ];
} catch (Exception $e) {
    $db->rollBack();
    $_SESSION['dtcsc_flash'] = ['type' => 'danger', 'message' => 'An error occurred while saving the decision.'];
}

header('Location: ' . $backUrl);
exit;