<?php
// bsa/bookings.php — FullCalendar JSON event feed
require_once(__DIR__ . '/../includes/function.php');
require_once(__DIR__ . '/includes/dtcsc-optional.php');
require_once(__DIR__ . '/includes/job-order-tables.php');

header('Content-Type: application/json; charset=utf-8');

if (empty($GLOBALS['userId'] ?? null)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'UNAUTHORIZED', 'message' => 'Please log in first.']);
    exit;
}

$start = trim((string) ($_GET['start'] ?? ''));
$end = trim((string) ($_GET['end'] ?? ''));

$sql = "SELECT b.`id`, b.`title`, b.`facility`, b.`start`, b.`end`, b.`all_day`, b.`event_type`
        FROM `bsa_bookings` b
        WHERE (? IS NULL OR b.`start` < ?)
          AND (? IS NULL OR b.`end` IS NULL OR b.`end` > ?)
        ORDER BY b.`start` ASC, b.`id` ASC";

try {
    $stmt = connection()->prepare($sql);
    $stmt->execute([$end, $end, $start, $start]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB_ERROR', 'message' => 'Failed to load bookings.']);
    exit;
}

$events = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $allDay = (bool) (int) $row['all_day'];
    $startDt = date('Y-m-d H:i:s', strtotime($row['start']));
    $endDt = $row['end'] !== null ? date('Y-m-d H:i:s', strtotime($row['end'])) : null;

    $event = [
        'id' => (int) $row['id'],
        'title' => $row['title'],
        'allDay' => $allDay,
    ];

    if ($allDay) {
        $event['start'] = date('Y-m-d', strtotime($startDt));
        $event['end'] = $endDt !== null ? date('Y-m-d', strtotime($endDt)) : null;
    } else {
        $event['start'] = str_replace(' ', 'T', $startDt);
        $event['end'] = $endDt !== null ? str_replace(' ', 'T', $endDt) : null;
    }

    if (!empty($row['facility'])) {
        $event['title'] .= ' (' . $row['facility'] . ')';
    }

    $typeColors = [
        'DTC Booking' => '#4e73df',
        'Vehicle Book' => '#1cc88a',
        'JOB Order Request' => '#f6c23e',
    ];
    if (isset($typeColors[$row['event_type']])) {
        $event['color'] = $typeColors[$row['event_type']];
    }

    // Detail rows shown in the calendar's click-through popup. The feed is the
    // only place the raw booking row is available, so the popup is built from
    // these props instead of re-querying on click.
    $event['extendedProps'] = [
        'kind'    => 'booking',
        'heading' => (string) (!empty($row['facility']) ? $row['facility'] : $row['title']),
        'status'  => '',
        'details' => [
            'Event type' => (string) ($row['event_type'] ?: 'N/A'),
            'Facility'   => (string) ($row['facility'] ?: 'N/A'),
            'Start'      => $startDt,
            'End'        => $endDt ?: 'N/A',
            'Duration'   => ($allDay ? 'All day' : 'Timed'),
        ],
    ];

    $events[] = $event;
}

// Approved facility-booking schedules share a navy -> maya blue range so the
// calendar reads as one family; pending bookings stay amber.
$approvedBlues = ['#000080', '#4169e1', '#1c39bb', '#73c2fb'];
$approvedBlueIdx = 0;

// Local standalone facility bookings (bsa_facility_bookings), colored by status.
try {
    $localStmt = connection()->prepare("
        SELECT id, booking_reference, activity_title, status, venue_option,
               start_date, end_date, start_time, end_time
        FROM bsa_facility_bookings
        WHERE status IN ('pending', 'approved')
          AND (? IS NULL OR CONCAT(start_date, ' ', COALESCE(start_time, '00:00:00')) < ?)
          AND (? IS NULL OR CONCAT(COALESCE(end_date, start_date), ' ', COALESCE(end_time, start_time, '00:00:00')) > ?)
        ORDER BY start_date ASC, start_time ASC
    ");
    $localStmt->execute([$end, $end, $start, $start]);
    foreach ($localStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $startDt = date('Y-m-d H:i:s', strtotime($row['start_date'] . ' ' . ($row['start_time'] ?? '00:00:00')));
        $endDt = $row['end_date'] !== null
            ? date('Y-m-d H:i:s', strtotime($row['end_date'] . ' ' . ($row['end_time'] ?? '00:00:00')))
            : null;
        $title = trim($row['activity_title'] . ' (' . $row['booking_reference'] . ')');
        if (!empty($row['venue_option'])) {
            $title .= ' - ' . $row['venue_option'];
        }
        $events[] = [
            'id'     => 'bf' . (int) $row['id'],
            'title'  => $title,
            'start'  => str_replace(' ', 'T', $startDt),
            'end'    => $endDt !== null ? str_replace(' ', 'T', $endDt) : null,
            'color'  => $row['status'] === 'approved'
                ? $approvedBlues[$approvedBlueIdx++ % count($approvedBlues)]
                : '#ffc107',
            'allDay' => false,
            'extendedProps' => [
                'kind'    => 'facility',
                'heading' => (string) (!empty($row['venue_option']) ? $row['venue_option'] : $row['activity_title']),
                'status'  => (string) $row['status'],
                'details' => [
                    'Reference' => (string) ($row['booking_reference'] ?: 'N/A'),
                    'Venue'     => (string) ($row['venue_option'] ?: 'N/A'),
                    'Start'     => $startDt,
                    'End'       => $endDt ?: 'N/A',
                ],
            ],
        ];
    }
} catch (Throwable $e) {
    // Do not fail the whole feed if the local facility bookings table is not created yet.
}

// Approved DTC-SC facility bookings, merged onto their schedules with the approved color.
try {
    $dtcStmt = Database::getInstance()->getConnection()->query("
        SELECT b.booking_id, b.booking_reference, b.activity_title, b.status,
               b.start_date, b.end_date, b.start_time, b.end_time,
               f.facility_name, v.venue_name
        FROM bookings b
        JOIN facilities f ON b.facility_id = f.facility_id
        JOIN venues v ON f.venue_id = v.venue_id
        WHERE b.status = 'approved'
        ORDER BY b.start_date ASC, b.start_time ASC
    ");
    foreach ($dtcStmt->fetchAll() as $row) {
        $events[] = [
            'id'     => 'dtc' . (int) $row['booking_id'],
            'title'  => $row['activity_title'] . ' (' . $row['booking_reference'] . ')',
            'start'  => str_replace(' ', 'T', date('Y-m-d H:i:s', strtotime($row['start_date'] . ' ' . $row['start_time']))),
            'end'    => str_replace(' ', 'T', date('Y-m-d H:i:s', strtotime($row['end_date'] . ' ' . $row['end_time']))),
            'color'  => $approvedBlues[$approvedBlueIdx++ % count($approvedBlues)],
            'allDay' => false,
            'extendedProps' => [
                'kind'    => 'dtc',
                'heading' => (string) (!empty($row['venue_name']) ? $row['venue_name'] : (!empty($row['facility_name']) ? $row['facility_name'] : $row['activity_title'])),
                'status'  => (string) $row['status'],
                'details' => [
                    'Reference' => (string) ($row['booking_reference'] ?: 'N/A'),
                    'Facility'  => (string) ($row['facility_name'] ?: 'N/A'),
                    'Venue'     => (string) ($row['venue_name'] ?: 'N/A'),
                    'Start'     => date('Y-m-d H:i:s', strtotime($row['start_date'] . ' ' . $row['start_time'])),
                    'End'       => date('Y-m-d H:i:s', strtotime($row['end_date'] . ' ' . $row['end_time'])),
                ],
            ],
        ];
    }
} catch (Throwable $e) {
    // Do not fail the whole feed if the DTC-SC database is unavailable.
}

// Approved vehicle requests, colored orange on their departure/return schedule.
try {
    $vehStmt = connection()->prepare("
        SELECT id, control_no, purpose_of_travel, destination, depart_date, depart_time,
               return_date, return_time
        FROM vehicle_requests
        WHERE status = 'available'
          AND (? IS NULL OR CONCAT(depart_date, ' ', COALESCE(depart_time, '00:00:00')) < ?)
          AND (? IS NULL OR CONCAT(COALESCE(return_date, depart_date), ' ', COALESCE(return_time, depart_time, '00:00:00')) > ?)
        ORDER BY depart_date ASC, depart_time ASC
    ");
    $vehStmt->execute([$end, $end, $start, $start]);
    foreach ($vehStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $startDt = date('Y-m-d H:i:s', strtotime($row['depart_date'] . ' ' . ($row['depart_time'] ?? '00:00:00')));
        $endDt = $row['return_date'] !== null
            ? date('Y-m-d H:i:s', strtotime($row['return_date'] . ' ' . ($row['return_time'] ?? '00:00:00')))
            : null;
        $title = trim($row['purpose_of_travel'] . ' (' . $row['control_no'] . ')');
        if (!empty($row['destination'])) {
            $title .= ' - ' . $row['destination'];
        }
        $events[] = [
            'id'     => 'vrv' . (int) $row['id'],
            'title'  => $title,
            'start'  => str_replace(' ', 'T', $startDt),
            'end'    => $endDt !== null ? str_replace(' ', 'T', $endDt) : null,
            'color'  => '#fd7e14',
            'allDay' => false,
            'extendedProps' => [
                'kind'    => 'vehicle',
                'heading' => (string) $row['purpose_of_travel'],
                'status'  => 'available',
                'details' => [
                    'Control no.' => (string) ($row['control_no'] ?: 'N/A'),
                    'Destination' => (string) ($row['destination'] ?: 'N/A'),
                    'Departure'   => $startDt,
                    'Return'      => $endDt ?: 'N/A',
                ],
            ],
        ];
    }
} catch (Throwable $e) {
    // Do not fail the whole feed if the vehicle_requests query fails.
}

// Approved Job Order/Request forms, shown on their requested or tracked work dates.
try {
    $joStmt = connection()->prepare("
        SELECT id, order_no, requestor_name, description_of_work, other_scope,
               location_of_work, date_request, date_time_started, date_time_completed,
               tracking_remarks
        FROM bsa_job_order_requests
        WHERE status = 'approved'
          AND COALESCE(DATE(date_time_started), date_request) IS NOT NULL
          AND (? IS NULL OR COALESCE(DATE(date_time_started), date_request) < ?)
          AND (? IS NULL OR COALESCE(DATE(date_time_completed), date_request) >= ?)
        ORDER BY COALESCE(DATE(date_time_started), date_request) ASC, id ASC
    ");
    $joStmt->execute([$end, $end, $start, $start]);

    $joColors = [
        'completed'       => '#1cc88a',
        'work_in_progress'=> '#f6c23e',
        'on_hold'         => '#e74a3c',
        'backlog'         => '#6c757d',
    ];

    foreach ($joStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $startDate = !empty($row['date_time_started'])
            ? date('Y-m-d', strtotime((string) $row['date_time_started']))
            : date('Y-m-d', strtotime((string) $row['date_request']));
        $endDate = !empty($row['date_time_completed'])
            ? date('Y-m-d', strtotime((string) $row['date_time_completed']))
            : $startDate;

        $workTypes = array_values(array_filter(array_map('trim', explode(',', (string) $row['description_of_work']))));
        $workLabel = trim((string) $row['other_scope']);
        if ($workLabel === '') {
            $workLabel = $workTypes ? implode(', ', array_slice($workTypes, 0, 2)) : 'Job Order';
        }

        $tracking = (string) ($row['tracking_remarks'] ?: 'work_in_progress');
        if (!isset($joColors[$tracking])) {
            $tracking = 'work_in_progress';
        }

        $title = $row['order_no'] . ' - ' . $workLabel;
        if (!empty($row['location_of_work'])) {
            $title .= ' @ ' . $row['location_of_work'];
        }

        $tooltip = $title . PHP_EOL
            . 'Requestor: ' . ($row['requestor_name'] ?: 'N/A') . PHP_EOL
            . 'Tracking: ' . jobOrderTrackingRemarksLabel($tracking)
            . (!empty($row['date_time_completed'])
                ? PHP_EOL . 'Completed: ' . date(DISPLAY_DATETIME_FORMAT, strtotime((string) $row['date_time_completed']))
                : '');

        $event = [
            'id'        => 'jo' . (int) $row['id'],
            'title'     => preg_replace('/\s+/', ' ', $title),
            'start'     => $startDate,
            'allDay'    => true,
            'color'     => $joColors[$tracking],
            'url'       => uri() . '/bsa/?v=' . encode('Booking Approvals') . '&id=' . (int) $row['id'] . '&type=joborder',
            'tooltip'   => $tooltip,
            'extendedProps' => [
                'kind'     => 'joborder',
                'orderNo'  => $row['order_no'],
                'tracking' => $tracking,
                'trackingLabel' => jobOrderTrackingRemarksLabel($tracking),
                'status'   => 'approved',
                'heading'  => (string) $row['order_no'],
                'details'  => [
                    'Requestor'  => (string) ($row['requestor_name'] ?: 'N/A'),
                    'Work'       => $workLabel ?: 'N/A',
                    'Location'   => (string) ($row['location_of_work'] ?: 'N/A'),
                    'Tracking'   => jobOrderTrackingRemarksLabel($tracking),
                    'Started'    => !empty($row['date_time_started'])
                        ? date(DISPLAY_DATETIME_FORMAT, strtotime((string) $row['date_time_started']))
                        : 'Not started',
                    'Completed'  => !empty($row['date_time_completed'])
                        ? date(DISPLAY_DATETIME_FORMAT, strtotime((string) $row['date_time_completed']))
                        : 'N/A',
                ],
            ],
        ];

        // All-day end dates are exclusive in FullCalendar; only span days when work
        // actually ran past the start date.
        if ($endDate > $startDate) {
            $event['end'] = date('Y-m-d', strtotime($endDate . ' +1 day'));
        }

        $events[] = $event;
    }
} catch (Throwable $e) {
    // Do not fail the whole feed if the job order tables are unavailable.
}

echo json_encode($events);