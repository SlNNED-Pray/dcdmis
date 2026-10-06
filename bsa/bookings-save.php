<?php
// bsa/bookings-save.php — create a booking (POST)
require_once(__DIR__ . '/../includes/function.php');
require_once(root() . '/includes/string.php');
require_once(root() . '/includes/database/database.php');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'INVALID_METHOD', 'message' => 'Invalid request method.']);
    exit;
}

if (empty($userId)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'UNAUTHORIZED', 'message' => 'Please log in to save a booking.']);
    exit;
}

$eventOptions = ['DTC Booking', 'Vehicle Book', 'JOB Order Request'];

$type = trim((string) ($_POST['type'] ?? ''));
$startRaw = trim((string) ($_POST['start'] ?? ''));
$endRaw = trim((string) ($_POST['end'] ?? ''));
$allDay = (int) ($_POST['all_day'] ?? 0) === 1;

if ($type === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'VALIDATION', 'message' => 'Please select an event option.']);
    exit;
}

if (!in_array($type, $eventOptions, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'VALIDATION', 'message' => 'Invalid event option.']);
    exit;
}

if ($allDay) {
    $startRaw = (strlen($startRaw) === 10) ? $startRaw : substr($startRaw, 0, 10);
    $endRaw = (strlen($endRaw) === 10) ? $endRaw : substr($endRaw, 0, 10);
}

$start = date('Y-m-d H:i:s', strtotime($startRaw));
$end = $endRaw !== '' ? date('Y-m-d H:i:s', strtotime($endRaw)) : null;

if ($start === false || ($endRaw !== '' && $end === false)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'VALIDATION', 'message' => 'Invalid date/time values.']);
    exit;
}

beginTransaction();
$bookingId = insert('bsa_bookings', [
    'title' => $type,
    'event_type' => $type,
    'start' => $start,
    'end' => $end,
    'all_day' => $allDay ? 1 : 0,
    'station_id' => !empty($stationId) ? (string) $stationId : null,
    'created_by' => (string) $userId,
]);

if ($bookingId === false) {
    rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'SAVE_FAILED', 'message' => 'Failed to save the booking. Please try again.']);
    exit;
}

commit();
echo json_encode(['success' => true, 'id' => (int) $bookingId, 'message' => 'Booking saved.']);