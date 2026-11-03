<?php
/**
 * bsa/api/vehicle-availability.php (GET)
 * Returns the Vehicle Types that are already booked (pending/available)
 * for a schedule overlapping the proposed trip, so the form can disable them.
 *
 * Query: depart_date, depart_time, return_date, return_time, [exclude_id]
 */
require_once(__DIR__ . '/../../includes/function.php');

header('Content-Type: application/json; charset=utf-8');

if (empty($GLOBALS['userId'] ?? null)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'UNAUTHORIZED', 'message' => 'Please log in.']);
    exit;
}

$departDate = trim((string) ($_GET['depart_date'] ?? ''));
$departTime = trim((string) ($_GET['depart_time'] ?? ''));
$returnDate = trim((string) ($_GET['return_date'] ?? ''));
$returnTime = trim((string) ($_GET['return_time'] ?? ''));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $departDate) || !preg_match('/^\d{2}:\d{2}/', $departTime)) {
    echo json_encode(['success' => true, 'conflicts' => [], 'vehicles' => []]);
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $returnDate)) {
    $returnDate = $departDate;
}
if (!preg_match('/^\d{2}:\d{2}/', $returnTime)) {
    $returnTime = '23:59';
}
$departDate = substr($departDate, 0, 10);
$returnDate = substr($returnDate, 0, 10);
$departTime = substr($departTime, 0, 5);
$returnTime = substr($returnTime, 0, 5);

try {
    $db = connection();

    $sql = "SELECT control_no, status, vehicle_type, depart_date, depart_time, return_date, return_time, destination
            FROM vehicle_requests
            WHERE status IN ('pending', 'available')
              AND CONCAT(depart_date, ' ', depart_time) < CONCAT(?, ' ', ?)
              AND CONCAT(COALESCE(return_date, depart_date), ' ', COALESCE(return_time, '23:59')) > CONCAT(?, ' ', ?)";
    $params = [$returnDate, $returnTime, $departDate, $departTime];

    $excludeId = (int) ($_GET['exclude_id'] ?? 0);
    if ($excludeId > 0) {
        $sql .= " AND id <> ?";
        $params[] = $excludeId;
    }
    $sql .= " ORDER BY depart_date ASC, depart_time ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $conflicts = [];
    $vehicles = [];
    foreach ($rows as $row) {
        $type = (string) $row['vehicle_type'];
        if ($type !== '' && !in_array($type, $conflicts, true)) {
            $conflicts[] = $type;
        }
        $vehicles[] = [
            'vehicle_type' => $type,
            'control_no'   => (string) $row['control_no'],
            'status'       => (string) $row['status'],
            'destination'  => (string) ($row['destination'] ?? ''),
            'depart_date'  => (string) $row['depart_date'],
            'depart_time'  => (string) $row['depart_time'],
            'return_date'  => (string) ($row['return_date'] ?? ''),
            'return_time'  => (string) ($row['return_time'] ?? ''),
        ];
    }

    echo json_encode(['success' => true, 'conflicts' => $conflicts, 'vehicles' => $vehicles]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'SERVER_ERROR', 'message' => 'Unable to check vehicle availability.']);
}
