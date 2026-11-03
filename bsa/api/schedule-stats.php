<?php
/**
 * bsa/api/schedule-stats.php (GET)
 * Schedule counts for the dashboard bar graph, bucketed per period.
 *
 * Query: period = day | week | month | year  (default: month)
 *
 * Categories mirror the calendar feed (bsa/bookings.php): DTC Booking,
 * Vehicle Booking, Job Order Request. Each source only contributes the rows
 * the calendar would show (pending/approved facilities, approved DTC-SC and
 * job orders, available vehicle requests).
 */
require_once(__DIR__ . '/../../includes/function.php');

header('Content-Type: application/json; charset=utf-8');

if (empty($GLOBALS['userId'] ?? null)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'UNAUTHORIZED', 'message' => 'Please log in.']);
    exit;
}

$period = strtolower(trim((string) ($_GET['period'] ?? 'month')));
if (!in_array($period, ['day', 'week', 'month', 'year'], true)) {
    $period = 'month';
}

$today = new DateTimeImmutable('today');

// Centered window around today: past and upcoming schedules are both counted,
// so a booking made today for next week still shows on the graph.
switch ($period) {
    case 'day':
        $start = $today->modify('-14 days');
        $bucketCount = 30;
        $step = '+1 day';
        $labelFormat = 'M j';
        break;
    case 'week':
        // Buckets start on Monday.
        $start = $today->modify('-' . ((int) $today->format('N') - 1) . ' days')->modify('-42 days');
        $bucketCount = 12;
        $step = '+7 days';
        $labelFormat = 'M j';
        break;
    case 'month':
        $start = $today->modify('first day of this month')->modify('-6 months');
        $bucketCount = 12;
        $step = '+1 month';
        $labelFormat = 'M Y';
        break;
    default: // year
        $start = (new DateTimeImmutable($today->format('Y-01-01')))->modify('-2 years');
        $bucketCount = 5;
        $step = '+1 year';
        $labelFormat = 'Y';
        break;
}

$bucketKeys = [];
$cursor = $start;
for ($i = 0; $i < $bucketCount; $i++) {
    $bucketKeys[] = $cursor->format('Y-m-d');
    $cursor = $cursor->modify($step);
}
$windowStart = $bucketKeys[0];
$windowEnd = (new DateTimeImmutable($bucketKeys[$bucketCount - 1]))->modify($step . ' -1 day')->format('Y-m-d');

$categories = [
    'dtc'      => 'DTC Booking',
    'vehicle'  => 'Vehicle Booking',
    'joborder' => 'Job Order Request',
];

$counts = [];
foreach ($bucketKeys as $key) {
    $counts[$key] = ['dtc' => 0, 'vehicle' => 0, 'joborder' => 0];
}

// Maps a schedule date onto the bucket it belongs to (the exact day for
// "day", the Monday of its week, the 1st of its month, or Jan 1 of its year).
$bucketKeyFor = function ($dateValue) use ($period, $bucketKeys) {
    if ($dateValue === null || $dateValue === '') {
        return null;
    }
    $ts = strtotime((string) $dateValue);
    if ($ts === false) {
        return null;
    }
    $d = (new DateTimeImmutable())->setTimestamp($ts);
    switch ($period) {
        case 'day':
            $key = date('Y-m-d', $ts);
            break;
        case 'week':
            $key = $d->modify('-' . ((int) $d->format('N') - 1) . ' days')->format('Y-m-d');
            break;
        case 'month':
            $key = date('Y-m-01', $ts);
            break;
        default: // year
            $key = date('Y-01-01', $ts);
            break;
    }
    return in_array($key, $bucketKeys, true) ? $key : null;
};

$addRow = function (string $category, $dateValue) use (&$counts, $bucketKeyFor) {
    $key = $bucketKeyFor($dateValue);
    if ($key === null || !isset($counts[$key][$category])) {
        return;
    }
    $counts[$key][$category]++;
};

// 1) Standalone calendar rows (bsa_bookings), split by event type.
try {
    $stmt = connection()->prepare("
        SELECT event_type, DATE(start) AS d
        FROM bsa_bookings
        WHERE DATE(start) BETWEEN ? AND ?
    ");
    $stmt->execute([$windowStart, $windowEnd]);
    $typeMap = [
        'DTC Booking'       => 'dtc',
        'Vehicle Book'      => 'vehicle',
        'JOB Order Request' => 'joborder',
    ];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        // Legacy rows predate event_type; they carry a facility, so they are
        // facility (DTC) bookings on the calendar.
        $eventType = trim((string) $row['event_type']);
        $mapped = $eventType === '' ? 'dtc' : ($typeMap[$eventType] ?? null);
        if ($mapped !== null) {
            $addRow($mapped, $row['d']);
        }
    }
} catch (Throwable $e) {
    // Table unavailable; keep the other sources.
}

// 2) Local facility bookings submitted through the DTC Booking form.
try {
    $stmt = connection()->prepare("
        SELECT start_date AS d
        FROM bsa_facility_bookings
        WHERE status IN ('pending', 'approved')
          AND start_date BETWEEN ? AND ?
    ");
    $stmt->execute([$windowStart, $windowEnd]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $addRow('dtc', $row['d']);
    }
} catch (Throwable $e) {
    // Table not created yet.
}

// 3) Approved DTC-SC bookings.
try {
    $stmt = connection()->prepare("
        SELECT start_date AS d
        FROM bookings
        WHERE status = 'approved'
          AND start_date BETWEEN ? AND ?
    ");
    $stmt->execute([$windowStart, $windowEnd]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $addRow('dtc', $row['d']);
    }
} catch (Throwable $e) {
    // DTC-SC tables unavailable.
}

// 4) Approved/available vehicle requests, counted on their departure date.
try {
    $stmt = connection()->prepare("
        SELECT depart_date AS d
        FROM vehicle_requests
        WHERE status = 'available'
          AND depart_date BETWEEN ? AND ?
    ");
    $stmt->execute([$windowStart, $windowEnd]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $addRow('vehicle', $row['d']);
    }
} catch (Throwable $e) {
    // Table not created yet.
}

// 5) Approved job orders, counted on the day work starts (or was requested).
try {
    $stmt = connection()->prepare("
        SELECT COALESCE(DATE(date_time_started), date_request) AS d
        FROM bsa_job_order_requests
        WHERE status = 'approved'
          AND COALESCE(DATE(date_time_started), date_request) BETWEEN ? AND ?
    ");
    $stmt->execute([$windowStart, $windowEnd]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $addRow('joborder', $row['d']);
    }
} catch (Throwable $e) {
    // Job order table unavailable.
}

$buckets = [];
$totals = ['dtc' => 0, 'vehicle' => 0, 'joborder' => 0, 'total' => 0];
foreach ($bucketKeys as $key) {
    $row = $counts[$key];
    $rowTotal = $row['dtc'] + $row['vehicle'] + $row['joborder'];
    $buckets[] = [
        'key'   => $key,
        'label' => date($labelFormat, strtotime($key)),
        'dtc'      => $row['dtc'],
        'vehicle'  => $row['vehicle'],
        'joborder' => $row['joborder'],
        'total'    => $rowTotal,
    ];
    $totals['dtc'] += $row['dtc'];
    $totals['vehicle'] += $row['vehicle'];
    $totals['joborder'] += $row['joborder'];
    $totals['total'] += $rowTotal;
}

echo json_encode([
    'success'    => true,
    'period'     => $period,
    'window'     => ['start' => $windowStart, 'end' => $windowEnd],
    'categories' => $categories,
    'buckets'    => $buckets,
    'totals'     => $totals,
]);
