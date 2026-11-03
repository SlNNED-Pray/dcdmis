<?php
/**
 * bsa/api/dtc-booking-availability.php (GET)
 * Returns the venue options that already have a pending/approved booking
 * overlapping the proposed schedule, so the request form can hide them.
 *
 * Query: start_date, end_date, start_time, end_time, [exclude_id]
 */
require_once(__DIR__ . '/../../includes/function.php');
require_once(__DIR__ . '/../includes/dtc-booking-tables.php');

header('Content-Type: application/json; charset=utf-8');

if (empty($GLOBALS['userId'] ?? null)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'UNAUTHORIZED', 'message' => 'Please log in.']);
    exit;
}

$startDate = trim((string) ($_GET['start_date'] ?? ''));
$endDate   = trim((string) ($_GET['end_date'] ?? ''));
$startTime = trim((string) ($_GET['start_time'] ?? ''));
$endTime   = trim((string) ($_GET['end_time'] ?? ''));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{2}:\d{2}/', $startTime)) {
    echo json_encode(['success' => true, 'conflicts' => [], 'venues' => []]);
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $endDate = $startDate;
}
if (!preg_match('/^\d{2}:\d{2}/', $endTime)) {
    $endTime = '23:59';
}
$startDate = substr($startDate, 0, 10);
$endDate   = substr($endDate, 0, 10);
$startTime = substr($startTime, 0, 5);
$endTime   = substr($endTime, 0, 5);

try {
    ensureDtcBookingTables();
    $db = connection();

    $sql = "SELECT booking_reference, status, venue_option, activity_title,
                   start_date, end_date, start_time, end_time
            FROM bsa_facility_bookings
            WHERE status IN ('pending', 'approved')
              AND NULLIF(venue_option, '') IS NOT NULL
              AND CONCAT(start_date, ' ', start_time) < CONCAT(?, ' ', ?)
              AND CONCAT(COALESCE(NULLIF(end_date, ''), start_date), ' ', COALESCE(NULLIF(end_time, ''), '23:59')) > CONCAT(?, ' ', ?)";
    $params = [$endDate, $endTime, $startDate, $startTime];

    $excludeId = (int) ($_GET['exclude_id'] ?? 0);
    if ($excludeId > 0) {
        $sql .= " AND id <> ?";
        $params[] = $excludeId;
    }
    $sql .= " ORDER BY start_date ASC, start_time ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $conflicts = [];
    $venues = [];
    foreach ($rows as $row) {
        $bookedVenue = (string) $row['venue_option'];
        // Expand to the venue's conflict group (Main Hall <-> Inner Room block each other).
        foreach (dtcVenueGroupMembers($bookedVenue) as $blockedVenue) {
            if (!in_array($blockedVenue, $conflicts, true)) {
                $conflicts[] = $blockedVenue;
            }
        }
        $venues[] = [
            'venue_option'      => $bookedVenue,
            'booking_reference' => (string) $row['booking_reference'],
            'status'            => (string) $row['status'],
            'activity_title'    => (string) ($row['activity_title'] ?? ''),
            'start_date'        => (string) $row['start_date'],
            'end_date'          => (string) ($row['end_date'] ?? ''),
            'start_time'        => (string) $row['start_time'],
            'end_time'          => (string) ($row['end_time'] ?? ''),
        ];
    }

    echo json_encode(['success' => true, 'conflicts' => $conflicts, 'venues' => $venues]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'SERVER_ERROR', 'message' => 'Unable to check venue availability.']);
}
