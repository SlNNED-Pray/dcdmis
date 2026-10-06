<?php
/**
 * bsa/dtc-booking-approvals-process.php (POST handler, not a shell view)
 * Approve / Reject a local DTC facility booking (bsa_facility_bookings).
 */
require_once(__DIR__ . '/../includes/function.php');
require_once(__DIR__ . '/../includes/database/database.php');
require_once(__DIR__ . '/../includes/database/account.php');
require_once(__DIR__ . '/../includes/database/system-log.php');

$listUrl = uri() . '/bsa/?v=' . encode('Booking Approvals');

if (empty($GLOBALS['userId'] ?? null)) {
    header('Location: ' . uri() . '/login');
    exit;
}

if (!userRole($GLOBALS['userId'], 'bsa')) {
    $_SESSION['dtcsc_flash'] = ['type' => 'danger', 'message' => 'You do not have permission to approve bookings.'];
    header('Location: ' . uri() . '/bsa/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $listUrl);
    exit;
}

verify_csrf_token();

$bookingId = (int) ($_POST['booking_id'] ?? 0);
$action = $_POST['action'] ?? '';
$remarks = trim($_POST['remarks'] ?? '');
$remarks = $remarks === '' ? null : mb_substr($remarks, 0, 500);

if (!in_array($action, ['approve', 'reject'], true) || !$bookingId) {
    $_SESSION['dtcsc_flash'] = ['type' => 'danger', 'message' => 'Invalid request.'];
    header('Location: ' . $listUrl);
    exit;
}

$db = connection();

$stmt = $db->prepare("
    SELECT id, booking_reference, status, venue_option, start_date, end_date, start_time, end_time
    FROM bsa_facility_bookings
    WHERE id = ?
");
$stmt->execute([$bookingId]);
$booking = $stmt->fetch();

if (!$booking) {
    $_SESSION['dtcsc_flash'] = ['type' => 'danger', 'message' => 'Booking not found.'];
    header('Location: ' . $listUrl);
    exit;
}

$backUrl = $listUrl . '&id=' . (int) $booking['id'] . '&type=local';

if ($booking['status'] !== 'pending') {
    $_SESSION['dtcsc_flash'] = ['type' => 'warning', 'message' => 'This booking has already been ' . $booking['status'] . ' and cannot be re-decided.'];
    header('Location: ' . $backUrl);
    exit;
}

if ($action === 'approve') {
    $endDate = $booking['end_date'] ?: $booking['start_date'];
    $endTime = $booking['end_time'] ?: '23:59';
    $stmt = $db->prepare("
        SELECT booking_reference, status
        FROM bsa_facility_bookings
        WHERE status IN ('pending', 'approved')
          AND id <> ?
          AND NULLIF(venue_option, '') IS NOT NULL
          AND venue_option = ?
          AND CONCAT(start_date, ' ', start_time) < CONCAT(?, ' ', ?)
          AND CONCAT(COALESCE(NULLIF(end_date, ''), start_date), ' ', COALESCE(NULLIF(end_time, ''), '23:59')) > CONCAT(?, ' ', ?)
        ORDER BY start_date ASC, start_time ASC
    ");
    $stmt->execute([
        (int) $bookingId,
        $booking['venue_option'],
        $endDate, $endTime,
        $booking['start_date'], $booking['start_time']
    ]);
    $overlaps = $stmt->fetchAll();

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

beginTransaction();
try {
    if (update('bsa_facility_bookings', [
        'status' => $newStatus,
        'remarks' => $remarks,
        'decided_by' => (string) $GLOBALS['userId'],
        'decided_at' => date('Y-m-d H:i:s'),
    ], '`id` = ?', [$bookingId]) === false) {
        throw new Exception('Failed to update booking status.');
    }
    commit();
    createSystemLog($GLOBALS['stationId'] ?? null, $GLOBALS['userId'], 'Booking ' . ucfirst($newStatus), $booking['booking_reference'], clientIp());
    $_SESSION['dtcsc_flash'] = [
        'type' => 'success',
        'message' => 'Booking ' . $booking['booking_reference'] . ' ' . ucfirst($newStatus) . ' successfully.'
    ];
} catch (Throwable $e) {
    rollBack();
    $_SESSION['dtcsc_flash'] = ['type' => 'danger', 'message' => 'An error occurred while saving the decision.'];
}

header('Location: ' . $backUrl);
exit;