<?php
/**
 * bsa/booking-approvals.php (dcdmis shell content view)
 * Facility Booking Approvals + Vehicle Book Approvals: list, detail and decision forms.
 * Rendered inside the bsa module shell (routes as ?v=Booking Approvals).
 * Facility decisions POST to booking-approvals-process.php; vehicle decisions POST to vehicle-approvals-process.php.
 */
require_once __DIR__ . '/includes/dtcsc-optional.php';
require_once __DIR__ . '/includes/job-order-tables.php';
require_once __DIR__ . '/../includes/database/database.php';
require_once __DIR__ . '/../includes/database/account.php';

if (empty($GLOBALS['userId'] ?? null) || !userRole($GLOBALS['userId'], 'bsa')) {
    echo '<div class="card shadow mb-4"><div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Booking Approvals</h6></div>'
        . '<div class="card-body"><div class="alert alert-danger mb-0"><i class="fa fa-ban mr-2"></i><strong>Access denied.</strong> '
        . 'You need the <em>Booking &amp; Schedule Approvals (BSA)</em> system role to view booking approvals. '
        . 'Contact your division DMIS administrator to be granted access.</div></div></div>';
    return;
}

$db = null;
if ($dtcscSiblingAvailable) {
    try {
        $db = Database::getInstance()->getConnection();
    } catch (Throwable $e) {
        $db = null;
    }
}
$vrDb = connection();

$viewId = (int)($_GET['id'] ?? 0);
$viewType = $_GET['type'] ?? 'facility';
$printMode = !empty($_GET['print']);
if (!in_array($viewType, ['facility', 'vehicle', 'local', 'joborder'], true)) {
    $viewType = 'facility';
}
$listUrl = uri() . '/bsa/?v=' . encode('Booking Approvals');

function badgeClass($status) {
    switch ($status) {
        case 'approved':   return 'badge-success';
        case 'rejected':   return 'badge-danger';
        case 'cancelled':  return 'badge-secondary';
        default:           return 'badge-warning text-dark';
    }
}

function vehicleStatusTab($status) {
    switch ($status) {
        case 'available':    return 'approved';
        case 'not_available': return 'rejected';
        default:             return $status; // pending, cancelled
    }
}

function vehicleBadgeClass($status) {
    switch ($status) {
        case 'available':     return 'badge-success';
        case 'not_available': return 'badge-danger';
        case 'cancelled':     return 'badge-secondary';
        default:              return 'badge-warning text-dark';
    }
}

function vehicleStatusLabel($status) {
    return ucfirst(str_replace('_', ' ', $status));
}

function concTypeLabel($type) {
    return $type === 'requesting' ? 'Requested By' : 'Concurred By';
}

function checkedByName(): string {
    $emp = find("SELECT first_name, middle_name, last_name FROM dcdmis.employees WHERE id = ?", [20260105113222]);
    if (!$emp) {
        return '';
    }
    $first = trim((string) ($emp['first_name'] ?? ''));
    $middle = trim((string) ($emp['middle_name'] ?? ''));
    $last = trim((string) ($emp['last_name'] ?? ''));
    return strtoupper(trim($first . ($middle !== '' ? ' ' . substr($middle, 0, 1) . '.' : '') . ' ' . $last));
}

function approvedByName(array $booking): string {
    $first = trim((string) ($booking['emp_first_name'] ?? ''));
    $middle = trim((string) ($booking['emp_middle_name'] ?? ''));
    $last = trim((string) ($booking['emp_last_name'] ?? ''));
    if ($last === '') {
        return strtoupper(trim((string) ($booking['decided_by'] ?? '')));
    }
    return strtoupper(trim($first . ($middle !== '' ? ' ' . substr($middle, 0, 1) . '.' : '') . ' ' . $last));
}

/**
 * data-* payload that fills the JS Track modal for a job order row.
 */
function jobOrderTrackData(array $row): string {
    $dt = function ($value): string {
        if (empty($value)) {
            return '';
        }
        $ts = strtotime((string) $value);
        return $ts ? date('Y-m-d\TH:i', $ts) : '';
    };

    $pairs = [
        'track-id'             => (int) $row['id'],
        'track-order-no'       => (string) $row['order_no'],
        'track-actions'        => (string) ($row['actions_taken'] ?? ''),
        'track-recommendation' => (string) ($row['recommendation'] ?? ''),
        'track-assigned'       => (string) ($row['assigned_personnel'] ?? ''),
        'track-started'        => $dt($row['date_time_started'] ?? null),
        'track-completed'      => $dt($row['date_time_completed'] ?? null),
        'track-remarks'        => (string) ($row['tracking_remarks'] ?: 'work_in_progress'),
        'track-proponent-name' => (string) ($row['job_proponent_name'] ?? ''),
        'track-proponent-sign' => (string) ($row['job_proponent_signature'] ?? ''),
    ];

    $out = '';
    foreach ($pairs as $key => $value) {
        $out .= ' data-' . $key . '="' . htmlspecialchars($value, ENT_QUOTES) . '"';
    }
    return $out;
}

$booking = null;
$localBooking = null;
$equipment = $caterings = $externalEquipmentRows = $concurrences = [];
$vehicle = null;
$jobOrder = null;

if ($viewId && $viewType === 'joborder') {
    ensureJobOrderTables();
    $jobStmt = $vrDb->prepare("SELECT jo.*, e.first_name AS emp_first_name, e.last_name AS emp_last_name, e.middle_name AS emp_middle_name FROM bsa_job_order_requests jo LEFT JOIN dcdmis.employees e ON CAST(jo.decided_by AS UNSIGNED) = e.id WHERE jo.id = ?");
    $jobStmt->execute([$viewId]);
    $jobOrder = $jobStmt->fetch();

    if (!$jobOrder) {
        $_SESSION['joborder_flash'] = ['type' => 'danger', 'message' => 'Job order request not found.'];
        header('Location: ' . $listUrl);
        exit;
    }
} elseif ($viewId && $viewType === 'vehicle') {
    $stmt = $vrDb->prepare("SELECT v.*, e.first_name AS emp_first_name, e.last_name AS emp_last_name FROM vehicle_requests v LEFT JOIN dcdmis.employees e ON v.created_by = e.id WHERE v.id = ?");
    $stmt->execute([$viewId]);
    $vehicle = $stmt->fetch();

    if (!$vehicle) {
        $_SESSION['vehicle_flash'] = ['type' => 'danger', 'message' => 'Vehicle request not found.'];
        header('Location: ' . $listUrl);
        exit;
    }
} elseif ($viewId && $viewType === 'local') {
    $localStmt = $vrDb->prepare("SELECT bf.*, e.first_name AS emp_first_name, e.last_name AS emp_last_name, e.middle_name AS emp_middle_name, e.name_extension AS emp_name_extension FROM bsa_facility_bookings bf LEFT JOIN dcdmis.employees e ON CAST(bf.decided_by AS UNSIGNED) = e.id WHERE bf.id = ?");
    $localStmt->execute([$viewId]);
    $localBooking = $localStmt->fetch();

    if (!$localBooking) {
        $_SESSION['dtcsc_flash'] = ['type' => 'danger', 'message' => 'Booking not found.'];
        header('Location: ' . $listUrl);
        exit;
    }
} elseif ($viewId) {
    if ($db === null) {
        $_SESSION['dtcsc_flash'] = ['type' => 'warning', 'message' => 'The DTC-SC Booking system is not installed. Facility bookings are unavailable.'];
        header('Location: ' . $listUrl);
        exit;
    }
    $stmt = $db->prepare("
        SELECT b.*, f.facility_name, v.venue_name, v.venue_code
        FROM bookings b
        JOIN facilities f ON b.facility_id = f.facility_id
        JOIN venues v ON f.venue_id = v.venue_id
        WHERE b.booking_id = ?
    ");
    $stmt->execute([$viewId]);
    $booking = $stmt->fetch();

    if (!$booking) {
        $_SESSION['dtcsc_flash'] = ['type' => 'danger', 'message' => 'Booking not found.'];
        header('Location: ' . $listUrl);
        exit;
    }

    $stmt = $db->prepare("
        SELECT e.equipment_name, be.quantity
        FROM booking_equipment be
        JOIN equipment e ON be.equipment_id = e.equipment_id
        WHERE be.booking_id = ?
    ");
    $stmt->execute([$viewId]);
    $equipment = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT * FROM booking_catering WHERE booking_id = ?");
    $stmt->execute([$viewId]);
    $caterings = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT * FROM booking_external_equipment WHERE booking_id = ?");
    $stmt->execute([$viewId]);
    $externalEquipmentRows = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT * FROM booking_concurrence WHERE booking_id = ? ORDER BY concurrence_type");
    $stmt->execute([$viewId]);
    $concurrences = $stmt->fetchAll();
} else {
    $allowedStatuses = ['pending', 'approved', 'rejected', 'cancelled', 'all'];
    $status = $_GET['status'] ?? 'pending';
    if (!in_array($status, $allowedStatuses, true)) {
        $status = 'pending';
    }

    $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'cancelled' => 0, 'all' => 0];
    if ($db !== null) {
        $countStmt = $db->query("SELECT status, COUNT(*) AS c FROM bookings GROUP BY status");
        foreach ($countStmt->fetchAll() as $row) {
            $counts[$row['status']] = (int)$row['c'];
            $counts['all'] += (int)$row['c'];
        }
    }

    try {
        $localCountStmt = $vrDb->query("SELECT status, COUNT(*) AS c FROM bsa_facility_bookings GROUP BY status");
        foreach ($localCountStmt->fetchAll() as $row) {
            $counts[$row['status']] = (int)$row['c'];
            $counts['all'] += (int)$row['c'];
        }
    } catch (Throwable $e) {
        // Local facility bookings table may not be created yet.
    }

    $vrCountStmt = $vrDb->query("SELECT status, COUNT(*) AS c FROM vehicle_requests GROUP BY status");
    foreach ($vrCountStmt->fetchAll() as $row) {
        $tab = vehicleStatusTab($row['status']);
        $counts[$tab] += (int)$row['c'];
        $counts['all'] += (int)$row['c'];
    }

    try {
        ensureJobOrderTables();
        $joCountStmt = $vrDb->query("SELECT status, COUNT(*) AS c FROM bsa_job_order_requests GROUP BY status");
        foreach ($joCountStmt->fetchAll() as $row) {
            if (isset($counts[$row['status']])) {
                $counts[$row['status']] += (int)$row['c'];
            }
            $counts['all'] += (int)$row['c'];
        }
    } catch (Throwable $e) {
        // Job order request table may not be created yet.
    }

    $search = trim($_GET['q'] ?? '');
    $searchLike = '%' . $search . '%';

    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 25;
    $offset = ($page - 1) * $perPage;

    $where = [];
    $params = [];

    if ($status !== 'all') {
        $where[] = 'b.status = ?';
        $params[] = $status;
    }
    if ($search !== '') {
        $where[] = '(b.booking_reference LIKE ? OR b.activity_title LIKE ? OR b.requesting_office LIKE ? OR b.contact_person LIKE ?)';
        array_push($params, $searchLike, $searchLike, $searchLike, $searchLike);
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $facilityTotal = 0;
    if ($db !== null) {
        $totalStmt = $db->prepare("SELECT COUNT(*) AS total FROM bookings b $whereSql");
        $totalStmt->execute($params);
        $facilityTotal = (int)$totalStmt->fetchColumn();
    }

    $vrWhere = [];
    $vrParams = [];
    if ($status !== 'all') {
        switch ($status) {
            case 'approved':
                $vrWhere[] = "v.status = 'available'";
                break;
            case 'rejected':
                $vrWhere[] = "v.status = 'not_available'";
                break;
            default:
                $vrWhere[] = 'v.status = ?';
                $vrParams[] = $status;
        }
    }
    if ($search !== '') {
        $vrWhere[] = '(v.control_no LIKE ? OR v.requesting_office LIKE ? OR v.requesting_personnel LIKE ? OR v.purpose_of_travel LIKE ? OR v.destination LIKE ?)';
        array_push($vrParams, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike);
    }
    $vrWhereSql = $vrWhere ? ('WHERE ' . implode(' AND ', $vrWhere)) : '';

    $vrStmt = $vrDb->prepare("SELECT * FROM vehicle_requests v $vrWhereSql ORDER BY CASE WHEN v.status = 'pending' THEN 0 ELSE 1 END, v.depart_date ASC, v.depart_time ASC");
    $vrStmt->execute($vrParams);
    $vehicleRequests = $vrStmt->fetchAll();

    $localWhere = [];
    $localParams = [];
    if ($status !== 'all') {
        $localWhere[] = 'bf.status = ?';
        $localParams[] = $status;
    }
    if ($search !== '') {
        $localWhere[] = '(bf.booking_reference LIKE ? OR bf.activity_title LIKE ? OR bf.requesting_office LIKE ? OR bf.contact_person LIKE ?)';
        array_push($localParams, $searchLike, $searchLike, $searchLike, $searchLike);
    }
    $localWhereSql = $localWhere ? ('WHERE ' . implode(' AND ', $localWhere)) : '';

    $localRows = [];
    try {
        $localListStmt = $vrDb->prepare("SELECT bf.* FROM bsa_facility_bookings bf $localWhereSql ORDER BY CASE WHEN bf.status = 'pending' THEN 0 ELSE 1 END, bf.start_date ASC, bf.start_time ASC");
        $localListStmt->execute($localParams);
        $localRows = array_map(function ($row) {
            return $row + ['row_type' => 'local'];
        }, $localListStmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $e) {
        // Local facility bookings table may not be created yet.
    }

    $joWhere = [];
    $joParams = [];
    if ($status !== 'all') {
        $joWhere[] = 'jo.status = ?';
        $joParams[] = $status;
    }
    if ($search !== '') {
        $joWhere[] = '(jo.order_no LIKE ? OR jo.requesting_personnel LIKE ? OR jo.requestor_name LIKE ? OR jo.location_of_work LIKE ? OR jo.description_of_work LIKE ? OR jo.other_scope LIKE ?)';
        array_push($joParams, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike);
    }
    $joWhereSql = $joWhere ? ('WHERE ' . implode(' AND ', $joWhere)) : '';

    $jobOrderRows = [];
    try {
        ensureJobOrderTables();
        $joListStmt = $vrDb->prepare("SELECT jo.* FROM bsa_job_order_requests jo $joWhereSql ORDER BY CASE WHEN jo.status = 'pending' THEN 0 ELSE 1 END, jo.date_request DESC, jo.id DESC");
        $joListStmt->execute($joParams);
        $jobOrderRows = array_map(function ($row) {
            return $row + ['row_type' => 'joborder'];
        }, $joListStmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $e) {
        // Job order request table may not be created yet.
    }

    $total = $facilityTotal + count($localRows) + count($vehicleRequests) + count($jobOrderRows);
    $totalPages = max(1, (int)ceil($total / $perPage));

    $bookings = [];
    if ($db !== null) {
        $stmt = $db->prepare("
            SELECT b.*, f.facility_name, v.venue_name, v.venue_code
            FROM bookings b
            JOIN facilities f ON b.facility_id = f.facility_id
            JOIN venues v ON f.venue_id = v.venue_id
            $whereSql
            ORDER BY CASE WHEN b.status = 'pending' THEN 0 ELSE 1 END, b.start_datetime ASC
            LIMIT $perPage OFFSET $offset
        ");
        $stmt->execute($params);
        $bookings = $stmt->fetchAll();
    }

    $rows = [];
    foreach ($bookings as $row) {
        $rows[] = $row + ['row_type' => 'facility'];
    }
    if ($page === 1) {
        $vehicleRows = [];
        foreach ($vehicleRequests as $row) {
            $vehicleRows[] = $row + ['row_type' => 'vehicle'];
        }
        $rows = array_merge($localRows, $rows, $vehicleRows, $jobOrderRows);
    }
}

contentTitle('Booking Approvals');
?>

<style>
    .dtc-print-sheet {
        max-width: 980px;
        margin: 2inch auto;
        padding: 24px;
        color: #111;
        background: #fff;
        font-family: "Times New Roman", serif;
        font-size: 13px;
    }
    .header { font-family: "Old English Text MT", Arial, sans-serif; text-align: center; font-size: 16px; line-height: 1.25; margin-bottom: 1px; }
    .header img { height: 2cm; width: auto; }
    .header2 { font-family: "Trajan Pro", Arial, sans-serif; text-align: center; font-size: 13px; margin-bottom: 1px; border-bottom: 3px solid #111; padding-bottom: 6px; }
    .dtc-print-sheet h1 { font-size: 22px; margin: 0 0 18px; font-weight: 700; }
    .dtc-print-control { text-align: right; margin-bottom: 12px; font-size: 15px; }
    .dtc-print-table, .dtc-print-actions { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    .dtc-print-table th, .dtc-print-table td, .dtc-print-actions th, .dtc-print-actions td { border: 1px solid #111; padding: 7px 9px; vertical-align: top; min-height: 25px; }
    .dtc-print-table th, .dtc-print-actions th { font-family: "Bookman Old Style", serif; font-size: 10pt; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .dtc-print-table th { width: 18%; background: #e8e8e8; font-weight: 400; text-align: left; }
    .dtc-print-table td { width: 32%; }
    .dtc-print-table .dtc-print-signatories th, .dtc-print-table .dtc-print-signatories td { height: 80px; }
    .dtc-print-table .dtc-print-long td { min-height: 64px; }
    .dtc-print-actions th { background: #e8e8e8; text-align: center; font-size: 10pt; }
    .dtc-print-actions td { height: 62px; }
    .dtc-print-actions .dtc-print-signatories td { height: 80px; }
    .dtc-print-ack { font-style: italic; margin: 0 0 10px; }
    .footer-img { text-align: center; margin-top: 14px; }
    .footer-img img { width: 100%; max-width: 210mm; height: auto; display: block; margin: 0 auto; }
    @page { size: A4 portrait; margin: 10mm 12.7mm 16mm; }
    @media print {
        body * { visibility: hidden; }
        .dtc-print-sheet, .dtc-print-sheet *, .jo-sheet, .jo-sheet * { visibility: visible; }
        .dtc-print-sheet, .jo-sheet { position: absolute; left: 0; top: 0; width: 100%; max-width: none; margin: 0; padding: 8mm; box-sizing: border-box; display: block; }
        .dtc-print-sheet { display: flex; flex-direction: column; min-height: 100vh; }
        .dtc-print-sheet .footer-img { margin-top: auto; margin-bottom: 0; }
        .dtc-print-table th, .dtc-print-actions th { font-family: "Bookman Old Style", serif !important; font-size: 10pt !important; background-color: #e8e8e8 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .sidebar, .topbar, .navbar, .no-print, form, .btn, .pagination, .alert-dismissible .close { display: none !important; }
        .card { box-shadow: none !important; border: 1px solid #dee2e6 !important; }
        .container-fluid { width: 100% !important; max-width: none !important; }
    }
</style>

<?php
$flashSession = null;
foreach (['dtcsc_flash', 'vehicle_flash', 'joborder_flash'] as $flashKey) {
    if (!empty($_SESSION[$flashKey])) {
        $flashSession = ['type' => $_SESSION[$flashKey]['type'], 'message' => $_SESSION[$flashKey]['message']];
        unset($_SESSION[$flashKey]);
        break;
    }
}
?>
<?php if ($flashSession): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flashSession['type']); ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($flashSession['message']); ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
<?php endif; ?>

<?php if ($printMode && $viewId && ($booking || $localBooking)): ?>
    <?php
    $printBooking = $booking ?: $localBooking;
    $printIsLocal = $localBooking !== null;
    $printRequestedBy = ['name' => '', 'position' => ''];
    $printConcurredBy = ['name' => '', 'position' => ''];
    if ($printIsLocal) {
        $printRequestedBy = ['name' => $printBooking['requested_by_name'] ?? '', 'position' => $printBooking['requested_by_position'] ?? ''];
        $printConcurredBy = ['name' => $printBooking['concurred_by_name'] ?? '', 'position' => $printBooking['concurred_by_position'] ?? ''];
    } else {
        foreach ($concurrences as $concurrence) {
            if ($concurrence['concurrence_type'] === 'requesting') {
                $printRequestedBy = ['name' => $concurrence['person_name'], 'position' => $concurrence['position']];
            } elseif ($concurrence['concurrence_type'] === 'concurred') {
                $printConcurredBy = ['name' => $concurrence['person_name'], 'position' => $concurrence['position']];
            }
        }
    }
    $printEquipment = $printIsLocal ? ($printBooking['equipment_needed'] ?: 'None') : ($equipment ? implode(', ', array_map(function ($item) {
        return $item['equipment_name'] . ($item['quantity'] > 1 ? ' (x' . (int) $item['quantity'] . ')' : '');
    }, $equipment)) : 'None');
    $printCatering = $printIsLocal ? ($printBooking['external_catering'] === 'Yes' ? 'Yes' : 'No') : ($caterings ? 'Yes' : 'No');
    $printExternalEquipment = $printIsLocal ? ($printBooking['external_equipment'] === 'Yes' ? 'Yes' : 'No') : ($externalEquipmentRows ? 'Yes' : 'No');
    $printReference = $printBooking['booking_reference'];
    $printDate = $printIsLocal ? $printBooking['date_of_request'] : ($printBooking['created_at'] ?? '');
    $printActivityType = strtolower((string) ($printBooking['activity_type'] ?? ''));
    $printGovernance = strtolower((string) ($printIsLocal ? ($printBooking['activity_level'] ?? '') : ($printBooking['governance_level'] ?? '')));
    $printVenue = $printIsLocal ? ($printBooking['venue_option'] ?: 'N/A') : ($printBooking['venue_name'] . ' - ' . $printBooking['facility_name']);
    $printEndDate = $printBooking['end_date'] ?: $printBooking['start_date'];
    $printEndTime = $printBooking['end_time'] ?: $printBooking['start_time'];
    $printExternalDetails = $printBooking['external_equipment_details'] ?? '';
    $printSpecialRequests = $printBooking['special_requests'] ?? '';
    $printStatus = $printBooking['status'];
    $printRemarks = $printBooking['remarks'] ?? '';
    $printApprovedBy = $printIsLocal ? approvedByName($printBooking) : ($printBooking['approved_by'] ?? '');
    $printCheckedByName = checkedByName();
    ?>
    <div class="dtc-print-sheet">
        <div class="page-header">
            <div class="header">
                <img src="image/logo.png"><br>
                Republic of the Philippines<br>
                Department of Education
            </div>
            <div class="header2"> Region IX – Zamboanga Peninsula <br>
                SCHOOLS DIVISION OF DIPOLOG CITY
            </div>
        </div>
        <h1 style = text-align:center; >Division Training Center (DTC) Booking Form</h1>
        <div class="dtc-print-control">Control No. <strong><?php echo htmlspecialchars($printReference); ?></strong></div>

        <table class="dtc-print-table">
            <tr>
                <th>Requesting Office</th><td><?php echo htmlspecialchars($printBooking['requesting_office']); ?></td>
                <th>Date</th><td><?php echo formatDate($printDate); ?></td>
            </tr>
            <tr>
                <th>Contact Person</th><td><?php echo htmlspecialchars($printBooking['contact_person']); ?></td>
                <th>Contact Number</th><td><?php echo htmlspecialchars($printBooking['contact_phone'] ?: 'N/A'); ?></td>
            </tr>
            <tr>
                <th>Name of Activity</th><td><?php echo htmlspecialchars($printBooking['activity_title']); ?></td>
                <th>Type of Activity</th><td>[<?php echo $printActivityType === 'official' ? 'X' : ' '; ?>] Official<br>[<?php echo $printActivityType === 'external' ? 'X' : ' '; ?>] External</td>
            </tr>
            <tr>
                <th>If official activity, select level of governance for the organizer</th>
                <td colspan="3">[<?php echo $printGovernance === 'school' ? 'X' : ' '; ?>] School &nbsp; [<?php echo $printGovernance === 'division' ? 'X' : ' '; ?>] Division &nbsp; [<?php echo $printGovernance === 'region' ? 'X' : ' '; ?>] Region &nbsp; [<?php echo $printGovernance === 'central_office' ? 'X' : ' '; ?>] Central Office</td>
            </tr>
            <tr>
                <th>Inclusive Dates of Use</th><td><?php echo formatDate($printBooking['start_date']) . ' to ' . formatDate($printEndDate); ?></td>
                <th>Inclusive Time of Use</th><td><?php echo formatTime($printBooking['start_time']) . ' to ' . formatTime($printEndTime); ?></td>
            </tr>
            <tr>
                <th>Venue</th><td><?php echo htmlspecialchars($printVenue); ?></td>
                <th>No. of Participants</th><td><?php echo (int) $printBooking['participant_count']; ?></td>
            </tr>
            <tr>
                <th>Equipment Needed</th><td><?php echo htmlspecialchars($printEquipment); ?><br>No. of Microphone: __________</td>
                <th>With external catering services?</th><td>[<?php echo $printCatering === 'Yes' ? 'X' : ' '; ?>] Yes &nbsp; [<?php echo $printCatering === 'No' ? 'X' : ' '; ?>] No</td>
            </tr>
            <tr>
                <th>With external equipment? If yes, please specify.</th><td><?php echo $printExternalEquipment; ?><?php echo $printExternalDetails !== '' ? ': ' . htmlspecialchars($printExternalDetails) : ''; ?></td>
                <th>Special Requests</th><td><?php echo nl2br(htmlspecialchars($printSpecialRequests)); ?></td>
            </tr>
            <tr class="dtc-print-signatories">
                <th>Requested by:<br><small>(Name, position and signature)</small></th>
                <td><?php echo htmlspecialchars($printRequestedBy['name']); ?><br><?php echo htmlspecialchars($printRequestedBy['position']); ?></td>
                <th>Concurred by:</th>
                <td><?php echo htmlspecialchars($printConcurredBy['name']); ?><br><?php echo htmlspecialchars($printConcurredBy['position']); ?><br>Functional Division Chief</td>
            </tr>
        </table>

        <table class="dtc-print-actions">
            <tr><th colspan="2">Actions Taken</th></tr>
            <tr>
                <td>[<?php echo $printStatus === 'approved' ? 'X' : ' '; ?>] Available<br>[<?php echo $printStatus === 'rejected' ? 'X' : ' '; ?>] Not Available</td>
                <td><strong>Remarks:</strong><br><?php echo nl2br(htmlspecialchars($printRemarks)); ?></td>
            </tr>
            <tr>
                <td>Checked as to availability by:<br><br><?php echo htmlspecialchars($printCheckedByName ?: 'Facility Coordinator / Admin Aide VI'); ?><br>Facility Coordinator / Admin Aide VI</td>
                <td>Approved by:<br><br><?php echo htmlspecialchars($printApprovedBy ?: 'DTC Manager / Admin Officer V (Admin)'); ?> <br>DTC Manager/Admin Officer V (Admin)</td>
            </tr>
        </table>

        <div class="footer-img">
            <img src="image/footer.png" alt="Footer">
        </div>
    </div>

<?php elseif ($printMode && $viewId && $viewType === 'vehicle' && !empty($vehicle)): ?>
    <?php
    $printVehicleReturn = $vehicle['return_date']
        ? formatDate($vehicle['return_date']) . ($vehicle['return_time'] ? ' at ' . formatTime($vehicle['return_time']) : '')
        : 'N/A';
    $printVehicleDecision = trim((string) (($vehicle['emp_first_name'] ?? '') . ' ' . ($vehicle['emp_last_name'] ?? '')));
    if ($printVehicleDecision === '') {
        $printVehicleDecision = (string) ($vehicle['decided_by'] ?? '');
    }
    $printVehicleDecision = strtoupper($printVehicleDecision);
    ?>
    <div class="dtc-print-sheet">
        <div class="page-header">
            <div class="header">
                <img src="image/logo.png"><br>
                Republic of the Philippines<br>
                Department of Education
            </div>
            <div class="header2"> Region IX – Zamboanga Peninsula <br>
                SCHOOLS DIVISION OF DIPOLOG CITY
            </div>
             
        </div>
        <h1 style = "text-align:center;">Vehicle Request - Booking Approval</h1>
        <div class="dtc-print-control">Control No. <strong><?php echo htmlspecialchars($vehicle['control_no'] ?? ''); ?></strong></div>

        <table class="dtc-print-table">
            <tr>
                <th>Requesting Office</th><td><?php echo htmlspecialchars($vehicle['requesting_office']); ?></td>
                <th>Date</th><td><?php echo formatDate($vehicle['date_request']); ?></td>
            </tr>
            <tr>
                <th>Requesting Personnel</th><td><?php echo htmlspecialchars($vehicle['requesting_personnel']); ?></td>
                <th>Contact Number</th><td><?php echo htmlspecialchars($vehicle['contact_number']); ?></td>
            </tr>
            <tr>
                <th>Purpose of Travel</th><td colspan="3"><?php echo nl2br(htmlspecialchars($vehicle['purpose_of_travel'])); ?></td>
            </tr>
            <tr>
                <th>Destination</th><td><?php echo htmlspecialchars($vehicle['destination']); ?></td>
                <th>Vehicle Type</th><td><?php echo htmlspecialchars($vehicle['vehicle_type']); ?></td>
            </tr>
            <tr>
                <th>Departure</th><td><?php echo formatDate($vehicle['depart_date']) . ' at ' . formatTime($vehicle['depart_time']); ?></td>
                <th>Estimated Return</th><td><?php echo nl2br(htmlspecialchars($printVehicleReturn)); ?></td>
            </tr>
            <tr>
                <th>No. of Passengers</th><td><?php echo (int) $vehicle['no_of_passengers']; ?></td>
                <th>Special Request</th><td><?php echo nl2br(htmlspecialchars($vehicle['special_request'] ?: 'None')); ?></td>
            </tr>
            <tr>
                <th>Functional Division Chief</th><td colspan="3"><?php echo htmlspecialchars($vehicle['division_chief'] ?: 'N/A'); ?></td>
            </tr>
            <tr class="dtc-print-signatories">
                <th>Requested by:<br><small>(Name, position and signature)</small></th>
                <td><?php echo htmlspecialchars($vehicle['requested_by_name'] ?: 'N/A'); ?><br><?php echo htmlspecialchars($vehicle['requested_by_position'] ?: ''); ?></td>
                <th>Concurred by:</th>
                <td><?php echo htmlspecialchars($vehicle['concurred_by_name'] ?: 'N/A'); ?><br><?php echo htmlspecialchars($vehicle['concurred_by_position'] ?: ''); ?><br>Functional Division Chief</td>
            </tr>
        </table>

        <table class="dtc-print-actions">
            <tr><th colspan="2">Actions Taken</th></tr>
            <tr>
                <td>[<?php echo $vehicle['status'] === 'available' ? 'X' : ' '; ?>] Available<br>[<?php echo $vehicle['status'] === 'not_available' ? 'X' : ' '; ?>] Not Available</td>
                <td><strong>Remarks:</strong><br><?php echo nl2br(htmlspecialchars($vehicle['remarks'] ?: '')); ?></td>
            </tr>
            <tr>
                <td>Checked as to availability by:<br><br><?php echo htmlspecialchars(checkedByName() ?: 'Vehicle Coordinator / Admin Staff'); ?><br>Vehicle Coordinator / Admin Staff</td>
                <td>Approved by:<br><br><?php echo htmlspecialchars($printVehicleDecision ?: 'DTC Manager / Admin Officer V (Admin)'); ?><br>DTC Manager/Admin Officer V (Admin)</td>

            </tr>
        </table>

        <div class="footer-img">
            <img src="image/footer.png" alt="Footer">
        </div>
    </div>

<?php elseif ($printMode && $viewId && $viewType === 'joborder' && !empty($jobOrder)): ?>
    <?php require __DIR__ . '/includes/job-order-print-sheet.php'; ?>

<?php elseif ($viewId && $viewType === 'vehicle'): ?>

    <div class="d-flex align-items-center justify-content-between mb-3">
        <a href="<?php echo $listUrl; ?>&status=pending" class="btn btn-sm btn-outline-secondary">&larr; Back to Approvals</a>
        <div>
            <span class="badge <?php echo vehicleBadgeClass($vehicle['status']); ?>" style="font-size:1rem;"><?php echo vehicleStatusLabel($vehicle['status']); ?></span>
            <span class="font-weight-bold h5 ml-2"><?php echo htmlspecialchars($vehicle['control_no']); ?></span>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fa fa-info-circle mr-1"></i>Request Details</h6></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3"><strong>Requesting Office:</strong><br><?php echo htmlspecialchars($vehicle['requesting_office']); ?></div>
                        <div class="col-md-6 mb-3"><strong>Date:</strong><br><?php echo formatDate($vehicle['date_request']); ?></div>
                        <div class="col-md-6 mb-3"><strong>Requesting Personnel:</strong><br><?php echo htmlspecialchars($vehicle['requesting_personnel']); ?></div>
                        <div class="col-md-6 mb-3"><strong>Contact Number:</strong><br><?php echo htmlspecialchars($vehicle['contact_number']); ?></div>
                        <div class="col-md-6 mb-3"><strong>No. of Passengers:</strong><br><?php echo (int)$vehicle['no_of_passengers']; ?></div>
                        <div class="col-md-6 mb-3"><strong>Requested By:</strong><br><?php echo htmlspecialchars($vehicle['requested_by_name']); ?> - <?php echo htmlspecialchars($vehicle['requested_by_position']); ?></div>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fa fa-road mr-1"></i>Travel Details</h6></div>
                <div class="card-body">
                    <p class="mb-2"><strong>Purpose of Travel:</strong> <?php echo nl2br(htmlspecialchars($vehicle['purpose_of_travel'])); ?></p>
                    <p class="mb-2"><strong>Destination:</strong> <?php echo htmlspecialchars($vehicle['destination']); ?></p>
                    <div class="row mt-2">
                        <div class="col-md-6 mb-3"><strong>Departure:</strong><br><?php echo formatDate($vehicle['depart_date']) . ' at ' . formatTime($vehicle['depart_time']); ?></div>
                        <div class="col-md-6 mb-3"><strong>Estimated Return:</strong><br><?php echo $vehicle['return_date'] ? formatDate($vehicle['return_date']) . ($vehicle['return_time'] ? ' at ' . formatTime($vehicle['return_time']) : '') : 'N/A'; ?></div>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fa fa-car mr-1"></i>Vehicle</h6></div>
                <div class="card-body">
                    <p class="mb-2"><strong>Vehicle Type:</strong>
                        <span class="badge <?php echo vehicleBadgeClass($vehicle['status']); ?>"><?php echo htmlspecialchars($vehicle['vehicle_type']); ?></span>
                    </p>
                    <p class="mb-0"><strong>Special Request:</strong> <?php echo nl2br(htmlspecialchars($vehicle['special_request'] ?: 'None')); ?></p>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fa fa-pen mr-1"></i>Signatories</h6></div>
                <div class="card-body">
                    <div class="mb-2">
                        <span class="text-muted small">Requested By</span>
                        <div><strong><?php echo htmlspecialchars($vehicle['requested_by_name']); ?></strong> - <?php echo htmlspecialchars($vehicle['requested_by_position'] ?: 'N/A'); ?></div>
                    </div>
                    <div class="mb-2">
                        <span class="text-muted small">Concurred By</span>
                        <div><strong><?php echo htmlspecialchars($vehicle['concurred_by_name'] ?: 'N/A'); ?></strong> - <?php echo htmlspecialchars($vehicle['concurred_by_position'] ?: 'N/A'); ?></div>
                    </div>
                    <div class="mb-0">
                        <span class="text-muted small">Functional Division Chief</span>
                        <div><?php echo htmlspecialchars($vehicle['division_chief'] ?: 'N/A'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fa fa-clipboard-check mr-1"></i>Actions Taken</h6></div>
                <div class="card-body">
                    <?php if ($vehicle['status'] === 'pending'): ?>
                        <p class="text-muted small">Check the vehicle as <strong>Available</strong> to approve the request, or mark it <strong>Not Available</strong>. A remark may be added.</p>
                        <form method="POST" action="<?php echo uri(); ?>/bsa/vehicle-approvals-process.php">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <input type="hidden" name="vehicle_id" value="<?php echo (int)$vehicle['id']; ?>">
                            <div class="form-group">
                                <label for="remarks">Remarks (optional)</label>
                                <textarea class="form-control" id="remarks" name="remarks" rows="3" placeholder="e.g. Vehicle available. / No vehicle available on this date."></textarea>
                            </div>
                            <button type="submit" name="action" value="available" class="btn btn-success btn-block mb-2">
                                <i class="fa fa-check-circle mr-1"></i>Available
                            </button>
                            <button type="submit" name="action" value="not_available" class="btn btn-danger btn-block"
                                    onclick="return confirm('Mark this vehicle request as Not Available?')">
                                <i class="fa fa-times-circle mr-1"></i>Not Available
                            </button>
                        </form>
                    <?php elseif (!empty($vehicle['decided_at'])): ?>
                        <p class="mb-2"><strong>Checked by:</strong> <?php echo htmlspecialchars(trim(($vehicle['emp_first_name'] ?? '') . ' ' . ($vehicle['emp_last_name'] ?? '')) ?: ($vehicle['decided_by'] ?: 'N/A')); ?></p>
                        <p class="mb-2"><strong>Checked on:</strong> <?php echo date(DISPLAY_DATETIME_FORMAT, strtotime($vehicle['decided_at'])); ?></p>
                        <p class="mb-0"><strong>Remarks:</strong> <?php echo nl2br(htmlspecialchars($vehicle['remarks'] ?: 'None')); ?></p>
                    <?php else: ?>
                        <p class="text-muted mb-0">This vehicle request is not awaiting a decision.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fa fa-history mr-1"></i>Audit</h6></div>
                <div class="card-body">
                    <p class="mb-1 text-muted small"><strong>Created:</strong> <?php echo date(DISPLAY_DATETIME_FORMAT, strtotime($vehicle['created_at'])); ?></p>
                    <p class="mb-0 text-muted small"><strong>Last updated:</strong> <?php echo date(DISPLAY_DATETIME_FORMAT, strtotime($vehicle['updated_at'])); ?></p>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($viewId && $viewType === 'local'): ?>

    <div class="d-flex align-items-center justify-content-between mb-3">
        <a href="<?php echo $listUrl; ?>&status=pending" class="btn btn-sm btn-outline-secondary">&larr; Back to Bookings</a>
        <div>
            <span class="badge <?php echo badgeClass($localBooking['status']); ?>" style="font-size:1rem;"><?php echo ucfirst($localBooking['status']); ?></span>
            <span class="font-weight-bold h5 ml-2"><?php echo htmlspecialchars($localBooking['booking_reference']); ?></span>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fa fa-info-circle mr-1"></i>Activity Details</h6></div>
                <div class="card-body">
                    <p class="mb-2"><strong>Activity:</strong> <?php echo htmlspecialchars($localBooking['activity_title']); ?></p>
                    <?php if (!empty($localBooking['activity_type'])): ?>
                        <p class="mb-0">
                            <span class="badge badge-light mr-2"><?php echo htmlspecialchars(ucfirst($localBooking['activity_type'])); ?></span>
                            <?php if (!empty($localBooking['activity_level'])): ?>
                                <span class="badge badge-light"><?php echo htmlspecialchars(ucfirst($localBooking['activity_level'])); ?></span>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fa fa-calendar mr-1"></i>Facility &amp; Schedule</h6></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3"><strong>Venue:</strong><br><?php echo htmlspecialchars($localBooking['venue_option'] ?: 'N/A'); ?></div>
                        <div class="col-md-6 mb-3"><strong>Participants:</strong><br><?php echo (int)$localBooking['participant_count']; ?></div>
                        <div class="col-md-6 mb-3"><strong>Start:</strong><br><?php echo formatDate($localBooking['start_date']) . ' at ' . formatTime($localBooking['start_time']); ?></div>
                        <div class="col-md-6 mb-3"><strong>End:</strong><br><?php echo formatDate($localBooking['end_date'] ?: $localBooking['start_date']) . ($localBooking['end_time'] ? ' at ' . formatTime($localBooking['end_time']) : ' (open end)'); ?></div>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fa fa-user mr-1"></i>Requestor &amp; Contact</h6></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3"><strong>Requesting Office:</strong><br><?php echo htmlspecialchars($localBooking['requesting_office']); ?></div>
                        <div class="col-md-6 mb-3"><strong>Date of Request:</strong><br><?php echo !empty($localBooking['date_of_request']) ? formatDate($localBooking['date_of_request']) : 'N/A'; ?></div>
                        <div class="col-md-6 mb-3"><strong>Contact Person:</strong><br><?php echo htmlspecialchars($localBooking['contact_person']); ?></div>
                        <div class="col-md-6 mb-3"><strong>Email:</strong><br><?php echo htmlspecialchars($localBooking['contact_email'] ?: 'N/A'); ?></div>
                        <div class="col-md-6 mb-3"><strong>Contact Number:</strong><br><?php echo htmlspecialchars($localBooking['contact_phone'] ?: 'N/A'); ?></div>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fa fa-cube mr-1"></i>Requirements</h6></div>
                <div class="card-body">
                    <p class="mb-2"><strong>Equipment Needed:</strong> <?php echo htmlspecialchars($localBooking['equipment_needed'] ?: 'None'); ?></p>
                    <p class="mb-2"><strong>No. of Microphones:</strong> <?php echo (int)$localBooking['no_of_microphones']; ?></p>
                    <p class="mb-2"><strong>External Catering Services:</strong> <?php echo htmlspecialchars($localBooking['external_catering'] ?? 'N/A'); ?></p>
                    <p class="mb-2"><strong>External Equipment:</strong> <?php echo htmlspecialchars($localBooking['external_equipment'] ?? 'No'); ?>
                        <?php if (!empty($localBooking['external_equipment_details'])): ?>
                            - <?php echo nl2br(htmlspecialchars($localBooking['external_equipment_details'])); ?>
                        <?php endif; ?>
                    </p>
                    <p class="mb-0"><strong>Special Requests:</strong> <?php echo nl2br(htmlspecialchars($localBooking['special_requests'] ?: 'None')); ?></p>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fa fa-pen mr-1"></i>Signatories</h6></div>
                <div class="card-body">
                    <div class="mb-2">
                        <span class="text-muted small">Requested by</span>
                        <div><strong><?php echo htmlspecialchars($localBooking['requested_by_name'] ?: 'N/A'); ?></strong> - <?php echo htmlspecialchars($localBooking['requested_by_position'] ?: 'N/A'); ?></div>
                        <div class="text-muted small">Signature: <?php echo htmlspecialchars($localBooking['requested_by_signature'] ?: 'N/A'); ?></div>
                    </div>
                    <div class="mb-0">
                        <span class="text-muted small">Concurred by (Functional Division Chief)</span>
                        <div><strong><?php echo htmlspecialchars($localBooking['concurred_by_name'] ?: 'N/A'); ?></strong> - <?php echo htmlspecialchars($localBooking['concurred_by_position'] ?: 'N/A'); ?></div>
                        <div class="text-muted small">Signature: <?php echo htmlspecialchars($localBooking['concurred_by_signature'] ?: 'N/A'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fa fa-clipboard-check mr-1"></i>Decision</h6></div>
                <div class="card-body">
                    <?php if ($localBooking['status'] === 'pending'): ?>
                        <p class="text-muted small">Approve to confirm the schedule, or reject this request. A rejection reason will be saved with the booking.</p>
                        <form method="POST" action="<?php echo uri(); ?>/bsa/dtc-booking-approvals-process.php">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <input type="hidden" name="booking_id" value="<?php echo (int)$localBooking['id']; ?>">
                            <div class="form-group">
                                <label for="remarks">Remarks (optional)</label>
                                <textarea class="form-control" id="remarks" name="remarks" rows="3" placeholder="e.g. Approved. / Schedule conflict with another activity."></textarea>
                            </div>
                            <button type="submit" name="action" value="approve" class="btn btn-success btn-block mb-2">
                                <i class="fa fa-check-circle mr-1"></i>Approve
                            </button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger btn-block"
                                    onclick="return confirm('Reject this booking request? A rejection reason may be given in Remarks.')">
                                <i class="fa fa-times-circle mr-1"></i>Reject
                            </button>
                        </form>
                    <?php elseif (!empty($localBooking['decided_at'])): ?>
                        <p class="mb-2"><strong>Decided by:</strong> <?php echo htmlspecialchars($localBooking['decided_by'] ?: 'N/A'); ?></p>
                        <p class="mb-2"><strong>Decided on:</strong> <?php echo date(DISPLAY_DATETIME_FORMAT, strtotime($localBooking['decided_at'])); ?></p>
                        <p class="mb-0"><strong>Remarks:</strong> <?php echo nl2br(htmlspecialchars($localBooking['remarks'] ?: 'None')); ?></p>
                    <?php else: ?>
                        <p class="text-muted mb-0">This booking is not awaiting a decision.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fa fa-history mr-1"></i>Audit</h6></div>
                <div class="card-body">
                    <p class="mb-1 text-muted small"><strong>Created:</strong> <?php echo date(DISPLAY_DATETIME_FORMAT, strtotime($localBooking['created_at'])); ?></p>
                    <p class="mb-0 text-muted small"><strong>Last updated:</strong> <?php echo date(DISPLAY_DATETIME_FORMAT, strtotime($localBooking['updated_at'])); ?></p>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($viewId && $viewType === 'joborder'): ?>

    <?php
    $viewTracking = (string) ($jobOrder['tracking_remarks'] ?: 'work_in_progress');
    if (!in_array($viewTracking, jobOrderTrackingRemarks(), true)) {
        $viewTracking = 'work_in_progress';
    }
    $viewHasTrack = !empty($jobOrder['actions_taken']) || !empty($jobOrder['recommendation'])
        || !empty($jobOrder['assigned_personnel']) || !empty($jobOrder['date_time_started'])
        || !empty($jobOrder['date_time_completed']) || !empty($jobOrder['prepared_by']);
    $viewStamp = function ($value): string {
        if (empty($value)) {
            return '';
        }
        $ts = strtotime((string) $value);
        return $ts ? date(DISPLAY_DATETIME_FORMAT, $ts) : '';
    };
    ?>

    <div class="d-flex align-items-center justify-content-between mb-3">
        <a href="<?php echo $listUrl; ?>&status=pending" class="btn btn-sm btn-outline-secondary">&larr; Back to Approvals</a>
        <div>
            <span class="badge <?php echo badgeClass($jobOrder['status']); ?>" style="font-size:1rem;"><?php echo ucfirst($jobOrder['status']); ?></span>
            <span class="font-weight-bold h5 ml-2"><?php echo htmlspecialchars($jobOrder['order_no']); ?></span>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fa fa-info-circle mr-1"></i>Request Details</h6></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3"><strong>Date:</strong><br><?php echo formatDate($jobOrder['date_request']); ?></div>
                        <div class="col-md-6 mb-3"><strong>Requesting Personnel:</strong><br><?php echo htmlspecialchars($jobOrder['requesting_personnel']); ?></div>
                        <div class="col-md-6 mb-3"><strong>Job Proponent:</strong><br><?php echo htmlspecialchars($jobOrder['requestor_name']); ?></div>
                        <div class="col-md-6 mb-3"><strong>Location of Work:</strong><br><?php echo htmlspecialchars($jobOrder['location_of_work']); ?></div>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fa fa-tools mr-1"></i>Scope of Work</h6></div>
                <div class="card-body">
                    <p class="mb-2">
                        <?php
                        $jobWorkTypes = array_values(array_filter(array_map('trim', explode(',', (string) $jobOrder['description_of_work']))));
                        foreach (['Electrical', 'Carpentry', 'Airconditioning', 'Janitorial', 'Plumbing', 'ICT-related', 'Others'] as $workType):
                            ?>
                            <span class="badge badge-<?php echo in_array($workType, $jobWorkTypes, true) ? 'primary' : 'light'; ?> mr-1 mb-1"><?php echo htmlspecialchars($workType); ?></span>
                        <?php endforeach; ?>
                    </p>
                    <p class="mb-0"><strong>Other scope of work:</strong> <?php echo nl2br(htmlspecialchars($jobOrder['other_scope'] ?: 'None')); ?></p>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fa fa-clipboard-check mr-1"></i>Tracking Details</h6>
                    <span class="badge badge-<?php echo $viewTracking === 'completed' ? 'success' : ($viewTracking === 'on_hold' ? 'warning' : ($viewTracking === 'backlog' ? 'secondary' : 'info')); ?>"><?php echo htmlspecialchars(jobOrderTrackingRemarksLabel($viewTracking)); ?></span>
                </div>
                <div class="card-body">
                    <?php if (!$viewHasTrack): ?>
                        <p class="text-muted mb-0">
                            Not yet tracked. Once this job order is approved, use
                            <strong>Track Job Order</strong> to fill up the actions taken,
                            recommendation, assigned personnel and the completion details.
                        </p>
                    <?php else: ?>
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                <tr>
                                    <th class="text-muted small font-weight-normal pl-0" style="width:38%;vertical-align:top;">Actions Taken</th>
                                    <td class="small"><?php echo nl2br(htmlspecialchars((string) ($jobOrder['actions_taken'] ?? ''))); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted small font-weight-normal pl-0" style="vertical-align:top;">Recommendation</th>
                                    <td class="small"><?php echo nl2br(htmlspecialchars((string) ($jobOrder['recommendation'] ?? ''))); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted small font-weight-normal pl-0" style="vertical-align:top;">Assigned Personnel</th>
                                    <td class="small"><?php echo htmlspecialchars((string) ($jobOrder['assigned_personnel'] ?: '—')); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted small font-weight-normal pl-0" style="vertical-align:top;">Prepared by</th>
                                    <td class="small"><?php echo htmlspecialchars((string) ($jobOrder['prepared_by'] ?: bsaGssFocal()['name'] ?: '—')); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted small font-weight-normal pl-0" style="vertical-align:top;">Date &amp; time Started</th>
                                    <td class="small"><?php echo htmlspecialchars($viewStamp($jobOrder['date_time_started'] ?? null) ?: '—'); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted small font-weight-normal pl-0" style="vertical-align:top;">Date &amp; Time Completed</th>
                                    <td class="small"><?php echo htmlspecialchars($viewStamp($jobOrder['date_time_completed'] ?? null) ?: '—'); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted small font-weight-normal pl-0" style="vertical-align:top;">Comments/Remarks</th>
                                    <td class="small"><?php echo htmlspecialchars(jobOrderTrackingRemarksLabel($viewTracking)); ?></td>
                                </tr>
                            </tbody>
                        </table>

                        <p class="small fst-italic text-muted mt-3 mb-3">
                            I hereby acknowledge that the work above has been satisfactorily completed.
                        </p>

                        <div class="row border-top pt-3">
                            <div class="col-md-6 mb-2">
                                <strong class="small">Job proponent:</strong><br>
                                <span class="small"><?php echo htmlspecialchars((string) ($jobOrder['job_proponent_name'] ?: '—')); ?></span><br>
                                <span class="text-muted small">Signature: <?php echo htmlspecialchars((string) ($jobOrder['job_proponent_signature'] ?: '—')); ?></span>
                            </div>
                            <div class="col-md-6 mb-2">
                                <strong class="small">Noted by:</strong> Administrative Officer V (Admin)<br>
                                <span class="small"><?php echo htmlspecialchars((string) ($jobOrder['noted_by'] ?: '—')); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fa fa-pen mr-1"></i>Requestor</h6></div>
                <div class="card-body">
                    <div><strong><?php echo htmlspecialchars($jobOrder['requestor_name']); ?></strong></div>
                    <div class="text-muted small">Signature: <?php echo htmlspecialchars($jobOrder['requestor_signature'] ?: 'N/A'); ?></div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fa fa-clipboard-check mr-1"></i>Decision</h6></div>
                <div class="card-body">
                    <?php if ($jobOrder['status'] === 'pending'): ?>
                        <p class="text-muted small">Approve to release this job order request, or reject it. A remark may be saved with the decision.</p>
                        <div id="jobOrderDecisionMessage" class="alert" style="display:none;"></div>
                        <form id="jobOrderDecisionForm" method="POST" action="<?php echo uri(); ?>/bsa/job-order-approvals-process.php">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <input type="hidden" name="job_order_id" value="<?php echo (int)$jobOrder['id']; ?>">
                            <div class="form-group">
                                <label for="remarks">Remarks (optional)</label>
                                <textarea class="form-control" id="remarks" name="remarks" rows="3" placeholder="e.g. Approved. / Materials not available."></textarea>
                            </div>
                            <button type="submit" name="action" value="approve" class="btn btn-success btn-block mb-2">
                                <i class="fa fa-check-circle mr-1"></i>Approve &amp; Track
                            </button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger btn-block"
                                    onclick="return confirm('Reject this job order request?')">
                                <i class="fa fa-times-circle mr-1"></i>Reject
                            </button>
                        </form>
                    <?php elseif (!empty($jobOrder['decided_at'])): ?>
                        <p class="mb-2"><strong>Decided by:</strong> <?php echo htmlspecialchars(approvedByName($jobOrder) ?: 'N/A'); ?></p>
                        <p class="mb-2"><strong>Decided on:</strong> <?php echo date(DISPLAY_DATETIME_FORMAT, strtotime($jobOrder['decided_at'])); ?></p>
                        <p class="mb-0"><strong>Remarks:</strong> <?php echo nl2br(htmlspecialchars($jobOrder['remarks'] ?: 'None')); ?></p>
                    <?php else: ?>
                        <p class="text-muted mb-0">This job order request is not awaiting a decision.</p>
                    <?php endif; ?>
                    <?php if ($jobOrder['status'] === 'approved'): ?>
                        <a href="<?php echo uri(); ?>/bsa/job-order-tracking.php?id=<?php echo (int)$jobOrder['id']; ?>" target="_blank" class="btn btn-outline-primary btn-block mt-3" title="Printable Track form">
                            <i class="fa fa-print mr-1"></i>Print Track Form
                        </a>
                        <button type="button" class="btn btn-primary btn-block mt-2 js-track-open"<?php echo jobOrderTrackData($jobOrder); ?>>
                            <i class="fa fa-clipboard-check mr-1"></i>Track Job Order
                        </button>
                        <p class="mb-0 mt-2 small text-muted">
                            <strong>Current status:</strong>
                            <?php echo htmlspecialchars(jobOrderTrackingRemarksLabel($jobOrder['tracking_remarks'] ?: 'work_in_progress')); ?>
                            <?php if (!empty($jobOrder['date_time_completed'])): ?>
                                &middot; <strong>Completed:</strong> <?php echo date(DISPLAY_DATETIME_FORMAT, strtotime((string) $jobOrder['date_time_completed'])); ?>
                            <?php endif; ?>
                        </p>
                        <a href="<?php echo $listUrl; ?>&id=<?php echo (int)$jobOrder['id']; ?>&type=joborder&print=1" target="_blank" class="btn btn-outline-secondary btn-block mt-2">
                            <i class="fa fa-print mr-1"></i>Print Job Order Form
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fa fa-history mr-1"></i>Audit</h6></div>
                <div class="card-body">
                    <p class="mb-1 text-muted small"><strong>Created:</strong> <?php echo date(DISPLAY_DATETIME_FORMAT, strtotime($jobOrder['created_at'])); ?></p>
                    <p class="mb-0 text-muted small"><strong>Last updated:</strong> <?php echo date(DISPLAY_DATETIME_FORMAT, strtotime($jobOrder['updated_at'])); ?></p>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var form = document.getElementById('jobOrderDecisionForm');
        if (!form) {
            return;
        }
        var message = document.getElementById('jobOrderDecisionMessage');

        function showMessage(type, text) {
            if (!message) {
                return;
            }
            message.className = 'alert alert-' + type;
            message.textContent = text;
            message.style.display = 'block';
        }

        form.addEventListener('submit', function (event) {
            var submitter = event.submitter;
            var action = submitter ? submitter.value : 'approve';
            event.preventDefault();

            var buttons = form.querySelectorAll('button[type="submit"]');
            for (var i = 0; i < buttons.length; i++) {
                buttons[i].disabled = true;
            }

            var data = new FormData(form);
            data.append('ajax', '1');

            fetch(form.getAttribute('action'), {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (response) { return response.json(); })
                .then(function (result) {
                    if (!result || !result.success) {
                        showMessage('danger', (result && result.message) || 'Unable to save the decision.');
                        for (var j = 0; j < buttons.length; j++) { buttons[j].disabled = false; }
                        return;
                    }
                    showMessage('success', result.message);
                    if (action === 'approve' && typeof window.openJobOrderTrackModal === 'function') {
                        // The job stays on Work in progress until it is explicitly marked Completed.
                        window.openJobOrderTrackModal({
                            id: result.id,
                            orderNo: result.order_no,
                            remarks: 'work_in_progress'
                        });
                        return;
                    }
                    window.setTimeout(function () { window.location.reload(); }, 1200);
                })
                .catch(function () {
                    showMessage('danger', 'An error occurred while saving the decision.');
                    for (var k = 0; k < buttons.length; k++) { buttons[k].disabled = false; }
                });
        });
    }());
    </script>

<?php elseif ($viewId): ?>

    <div class="d-flex align-items-center justify-content-between mb-3">
        <a href="<?php echo $listUrl; ?>&status=pending" class="btn btn-sm btn-outline-secondary">&larr; Back to Bookings</a>
        <div>
            <a href="<?php echo $listUrl; ?>&id=<?php echo (int)$booking['booking_id']; ?>"
               class="badge <?php echo badgeClass($booking['status']); ?>" style="font-size:1rem;"><?php echo ucfirst($booking['status']); ?></a>
            <span class="font-weight-bold h5 ml-2"><?php echo htmlspecialchars($booking['booking_reference']); ?></span>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fa fa-info-circle mr-1"></i>Activity Details</h6></div>
                <div class="card-body">
                    <p class="mb-2"><strong>Activity:</strong> <?php echo htmlspecialchars($booking['activity_title']); ?></p>
                    <?php if (!empty($booking['activity_description'])): ?>
                        <p class="mb-2"><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($booking['activity_description'])); ?></p>
                    <?php endif; ?>
                    <p class="mb-0">
                        <span class="badge badge-light mr-2"><?php echo ucfirst($booking['activity_type']); ?></span>
                        <?php if ($booking['governance_level']): ?>
                            <span class="badge badge-light"><?php echo ucfirst(str_replace('_', ' ', $booking['governance_level'])); ?></span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fa fa-calendar mr-1"></i>Facility &amp; Schedule</h6></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3"><strong>Facility:</strong><br><?php echo htmlspecialchars($booking['venue_name'] . ' - ' . $booking['facility_name']); ?></div>
                        <div class="col-md-6 mb-3"><strong>Participants:</strong><br><?php echo (int)$booking['participant_count']; ?></div>
                        <div class="col-md-6 mb-3"><strong>Start:</strong><br><?php echo formatDate($booking['start_date']) . ' at ' . formatTime($booking['start_time']); ?></div>
                        <div class="col-md-6 mb-3"><strong>End:</strong><br><?php echo formatDate($booking['end_date']) . ' at ' . formatTime($booking['end_time']); ?></div>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fa fa-user mr-1"></i>Requestor &amp; Contact</h6></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3"><strong>Requesting Office:</strong><br><?php echo htmlspecialchars($booking['requesting_office']); ?></div>
                        <div class="col-md-6 mb-3"><strong>Contact Person:</strong><br><?php echo htmlspecialchars($booking['contact_person']); ?></div>
                        <div class="col-md-6 mb-3"><strong>Contact Number:</strong><br><?php echo htmlspecialchars($booking['contact_phone'] ?: 'N/A'); ?></div>
                        <div class="col-md-6 mb-3"><strong>Email:</strong><br><?php echo htmlspecialchars($booking['contact_email']); ?></div>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fa fa-cube mr-1"></i>Equipment &amp; Requirements</h6></div>
                <div class="card-body">
                    <p class="mb-2"><strong>Equipment:</strong>
                        <?php if ($equipment): ?>
                            <?php echo implode(', ', array_map(function ($e) {
                                return htmlspecialchars($e['equipment_name']) . ($e['quantity'] > 1 ? ' (x' . (int)$e['quantity'] . ')' : '');
                            }, $equipment)); ?>
                        <?php else: ?>
                            None
                        <?php endif; ?>
                    </p>
                    <?php if ($caterings): ?>
                        <p class="mb-2"><strong>External Catering:</strong>
                            <?php echo implode('; ', array_map(function ($c) {
                                return htmlspecialchars($c['catering_type']) . ($c['meal_count'] ? ' - ' . (int)$c['meal_count'] . ' meals' : '') . ($c['budget'] ? ' - P' . number_format((float)$c['budget'], 2) : '');
                            }, $caterings)); ?>
                        </p>
                    <?php endif; ?>
                    <p class="mb-2"><strong>External Equipment:</strong>
                        <?php if ($externalEquipmentRows): ?>
                            <?php echo implode(', ', array_map(function ($e) {
                                return htmlspecialchars($e['equipment_name']) . ($e['quantity'] > 1 ? ' (x' . (int)$e['quantity'] . ')' : '');
                            }, $externalEquipmentRows)); ?>
                        <?php else: ?>
                            No
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($booking['external_equipment_details'])): ?>
                        <p class="mb-2"><strong>External Equipment Details:</strong> <?php echo nl2br(htmlspecialchars($booking['external_equipment_details'])); ?></p>
                    <?php endif; ?>
                    <p class="mb-0"><strong>Special Requests:</strong> <?php echo nl2br(htmlspecialchars($booking['special_requests'] ?: 'None')); ?></p>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fa fa-pen mr-1"></i>Signatories</h6></div>
                <div class="card-body">
                    <?php if ($concurrences): ?>
                        <?php foreach ($concurrences as $conc): ?>
                            <div class="d-flex justify-content-between align-items-start border-bottom pb-2 mb-2">
                                <div>
                                    <span class="text-muted small"><?php echo concTypeLabel($conc['concurrence_type']); ?></span>
                                    <div><strong><?php echo htmlspecialchars($conc['person_name']); ?></strong> - <?php echo htmlspecialchars($conc['position'] ?: 'N/A'); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($conc['office_name']); ?></div>
                                </div>
                                <span class="badge <?php echo $conc['signature_status'] === 'approved' ? 'badge-success' : ($conc['signature_status'] === 'rejected' ? 'badge-danger' : 'badge-secondary'); ?>">
                                    <?php echo ucfirst($conc['signature_status']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted mb-0">No signatory information recorded.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fa fa-clipboard-check mr-1"></i>Decision</h6></div>
                <div class="card-body">
                    <?php if ($booking['status'] === 'pending'): ?>
                        <p class="text-muted small">Approve to confirm the schedule, or reject this request. A rejection reason will be saved with the booking.</p>
                        <form method="POST" action="<?php echo uri(); ?>/bsa/booking-approvals-process.php">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <input type="hidden" name="booking_id" value="<?php echo (int)$booking['booking_id']; ?>">
                            <div class="form-group">
                                <label for="remarks">Remarks (optional)</label>
                                <textarea class="form-control" id="remarks" name="remarks" rows="3" placeholder="e.g. Approved. / Schedule conflict with another activity."></textarea>
                            </div>
                            <button type="submit" name="action" value="approve" class="btn btn-success btn-block mb-2">
                                <i class="fa fa-check-circle mr-1"></i>Approve
                            </button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger btn-block"
                                    onclick="return confirm('Reject this booking request? A rejection reason may be given in Remarks.')">
                                <i class="fa fa-times-circle mr-1"></i>Reject
                            </button>
                        </form>
                    <?php elseif (!empty($booking['decided_at'])): ?>
                        <p class="mb-2"><strong>Decided by:</strong> <?php echo htmlspecialchars($booking['approved_by'] ?: 'N/A'); ?></p>
                        <p class="mb-2"><strong>Decided on:</strong> <?php echo date(DISPLAY_DATETIME_FORMAT, strtotime($booking['decided_at'])); ?></p>
                        <p class="mb-0"><strong>Remarks:</strong> <?php echo nl2br(htmlspecialchars($booking['remarks'] ?: 'None')); ?></p>
                    <?php else: ?>
                        <p class="text-muted mb-0">This booking is not awaiting a decision.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fa fa-history mr-1"></i>Audit</h6></div>
                <div class="card-body">
                    <p class="mb-1 text-muted small"><strong>Created:</strong> <?php echo date(DISPLAY_DATETIME_FORMAT, strtotime($booking['created_at'])); ?></p>
                    <p class="mb-0 text-muted small"><strong>Last updated:</strong> <?php echo date(DISPLAY_DATETIME_FORMAT, strtotime($booking['updated_at'])); ?></p>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                <h5 class="m-0 font-weight-bold text-gray-800">Booking Schedules</h5>
                <form class="form-inline" method="get" action="<?php echo uri(); ?>/bsa/">
                    <input type="hidden" name="v" value="<?php echo encode('Booking Approvals'); ?>">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
                    <input type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" class="form-control mr-2" placeholder="Search reference, activity, office...">
                    <button class="btn btn-outline-primary" type="submit"><i class="fa fa-search"></i></button>
                </form>
            </div>

          

            <ul class="nav nav-pills mb-3">
                <?php
                $tabs = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'all' => 'All'];
                foreach ($tabs as $key => $label): ?>
                    <li class="nav-item mr-2">
                        <a class="nav-link <?php echo $status === $key ? 'active' : ''; ?>"
                           href="<?php echo $listUrl; ?>&status=<?php echo $key; ?>&q=<?php echo urlencode($search); ?>">
                            <?php echo $label; ?>
                            <span class="badge <?php echo $status === $key ? 'badge-light' : 'badge-secondary'; ?>"><?php echo $counts[$key]; ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if (isset($rows) && !$rows): ?>
                <p class="text-center text-muted my-4 mb-0">No bookings found.</p>
            <?php elseif (isset($rows)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Type</th>
                                <th>Reference</th>
                                <th>Activity / Purpose</th>
                                <th>Requesting Office</th>
                                <th>Facility / Vehicle</th>
                                <th>Schedule</th>
                                <th>Status</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <?php if ($row['row_type'] === 'vehicle'): ?>
                                    <tr>
                                        <td><span class="badge badge-info">Vehicle</span></td>
                                        <td class="font-weight-bold"><?php echo htmlspecialchars($row['control_no']); ?></td>
                                        <td><?php echo htmlspecialchars($row['purpose_of_travel']); ?></td>
                                        <td><?php echo htmlspecialchars($row['requesting_office']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($row['vehicle_type']); ?><br>
                                            <span class="text-muted small"><i class="fa fa-arrow-right"></i> <?php echo htmlspecialchars($row['destination']); ?></span>
                                        </td>
                                        <td class="small">
                                            Departure: <?php echo formatDate($row['depart_date']); ?><br>
                                            <span class="text-muted"><?php echo formatTime($row['depart_time']); ?><?php echo $row['return_date'] ? ' &middot; Return: ' . formatDate($row['return_date']) . ' ' . ($row['return_time'] ? formatTime($row['return_time']) : '') : ''; ?></span>
                                        </td>
                                        <td><span class="badge <?php echo vehicleBadgeClass($row['status']); ?>"><?php echo vehicleStatusLabel($row['status']); ?></span></td>
                                        <td class="text-right">
                                            <a href="<?php echo $listUrl; ?>&id=<?php echo (int)$row['id']; ?>&type=vehicle" class="btn btn-sm btn-outline-primary">View</a>
                                            <?php if ($row['status'] === 'available'): ?>
                                                <a href="<?php echo $listUrl; ?>&id=<?php echo (int)$row['id']; ?>&type=vehicle&print=1" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fa fa-print mr-1"></i>Print</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php elseif ($row['row_type'] === 'local'): ?>
                                    <tr>
                                        <td><span class="badge badge-success">Local</span></td>
                                        <td class="font-weight-bold"><?php echo htmlspecialchars($row['booking_reference']); ?></td>
                                        <td><?php echo htmlspecialchars($row['activity_title']); ?></td>
                                        <td><?php echo htmlspecialchars($row['requesting_office']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($row['venue_option'] ?: ($row['facility_name'] ?? '')); ?><br>
                                            <span class="text-muted small"><?php echo (int)$row['participant_count'] . ' participants'; ?></span>
                                        </td>
                                        <td class="small">
                                            <?php echo formatDate($row['start_date']); ?> - <?php echo formatDate($row['end_date'] ?: $row['start_date']); ?><br>
                                            <span class="text-muted"><?php echo formatTime($row['start_time']); ?> - <?php echo $row['end_time'] ? formatTime($row['end_time']) : 'open end'; ?></span>
                                        </td>
                                        <td><span class="badge <?php echo badgeClass($row['status']); ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                        <td class="text-right">
                                            <a href="<?php echo $listUrl; ?>&id=<?php echo (int)$row['id']; ?>&type=local" class="btn btn-sm btn-outline-primary">View</a>
                                            <?php if ($row['status'] === 'approved'): ?>
                                                <a href="<?php echo $listUrl; ?>&id=<?php echo (int)$row['id']; ?>&type=local&print=1" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fa fa-print mr-1"></i>Print</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php elseif ($row['row_type'] === 'joborder'): ?>
                                    <tr>
                                        <td><span class="badge badge-warning text-dark">Job Order</span></td>
                                        <td class="font-weight-bold"><?php echo htmlspecialchars($row['order_no']); ?></td>
                                        <td><?php echo htmlspecialchars($row['description_of_work']); ?><?php echo !empty($row['other_scope']) ? ' - ' . htmlspecialchars($row['other_scope']) : ''; ?></td>
                                        <td><?php echo htmlspecialchars($row['requestor_name']); ?><br><span class="text-muted small"><?php echo htmlspecialchars($row['requesting_personnel']); ?></span></td>
                                        <td>
                                            <?php echo htmlspecialchars($row['location_of_work']); ?><br>
                                            <span class="text-muted small">Date: <?php echo formatDate($row['date_request']); ?></span>
                                        </td>
                                        <td class="small"><?php echo formatDate($row['date_request']); ?></td>
                                        <td><span class="badge <?php echo badgeClass($row['status']); ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                        <td class="text-right">
                                            <a href="<?php echo $listUrl; ?>&id=<?php echo (int)$row['id']; ?>&type=joborder" class="btn btn-sm btn-outline-primary">View</a>
                                            <?php if ($row['status'] === 'approved'): ?>
                                                <button type="button" class="btn btn-sm btn-primary js-track-open"<?php echo jobOrderTrackData($row); ?> title="<?php echo htmlspecialchars(jobOrderTrackingRemarksLabel($row['tracking_remarks'] ?: 'work_in_progress')); ?>"><i class="fa fa-clipboard-check mr-1"></i>Track</button>
                                                <a href="<?php echo $listUrl; ?>&id=<?php echo (int)$row['id']; ?>&type=joborder&print=1" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fa fa-print mr-1"></i>Print</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                        <td class="font-weight-bold"><?php echo htmlspecialchars($row['booking_reference']); ?></td>
                                        <td><?php echo htmlspecialchars($row['activity_title']); ?></td>
                                        <td><?php echo htmlspecialchars($row['requesting_office']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($row['venue_name']); ?><br>
                                            <span class="text-muted small"><?php echo htmlspecialchars($row['facility_name']); ?></span>
                                        </td>
                                        <td class="small">
                                            <?php echo formatDate($row['start_date']); ?> - <?php echo formatDate($row['end_date']); ?><br>
                                            <span class="text-muted"><?php echo formatTime($row['start_time']); ?> - <?php echo formatTime($row['end_time']); ?></span>
                                        </td>
                                        <td><span class="badge <?php echo badgeClass($row['status']); ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                        <td class="text-right">
                                            <a href="<?php echo $listUrl; ?>&id=<?php echo (int)$row['booking_id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                            <?php if ($row['status'] === 'approved'): ?>
                                                <a href="<?php echo $listUrl; ?>&id=<?php echo (int)$row['booking_id']; ?>&print=1" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fa fa-print mr-1"></i>Print</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav class="mt-3">
                        <ul class="pagination pagination-sm justify-content-center mb-0">
                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo $listUrl; ?>&status=<?php echo htmlspecialchars($status); ?>&q=<?php echo urlencode($search); ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

<?php if (!$printMode): ?>
<!-- Track (JS modal) form -->
<div class="modal fade" id="jobOrderTrackModal" tabindex="-1" role="dialog" aria-labelledby="jobOrderTrackModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title font-weight-bold text-primary mb-0" id="jobOrderTrackModalLabel">
                    <i class="fa fa-clipboard-check mr-1"></i>Track &mdash; <span id="trackModalOrderNo"></span>
                </h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="jobOrderTrackForm" novalidate>
                <div class="modal-body py-3">
                    <div id="trackModalAlert" class="alert d-none" role="alert"></div>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <input type="hidden" name="job_order_id" id="trackModalId" value="">

                    <div class="form-group mb-2">
                        <label class="mb-1"><strong>Actions Taken</strong></label>
                        <textarea class="form-control form-control-sm" name="actions_taken" rows="2" placeholder="Work performed so far..."></textarea>
                    </div>

                    <div class="form-group mb-2">
                        <label class="mb-1"><strong>Recommendation</strong></label>
                        <textarea class="form-control form-control-sm" name="recommendation" rows="2" placeholder="Recommendation for the next step..."></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6 mb-2">
                            <label class="mb-1"><strong>Assigned Personnel</strong></label>
                            <input type="text" class="form-control form-control-sm" name="assigned_personnel" placeholder="Names of personnel assigned">
                        </div>
                        <div class="form-group col-md-6 mb-2">
                            <label class="mb-1"><strong>Prepared by:</strong> <small class="text-muted">(auto from GSS Focal)</small></label>
                            <input type="text" class="form-control form-control-sm" id="trackPreparedBy" value="<?php echo htmlspecialchars(bsaGssFocal()['name']); ?>" readonly>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6 mb-2">
                            <label class="mb-1"><strong>Date &amp; time Started</strong></label>
                            <input type="datetime-local" class="form-control form-control-sm" name="date_time_started" id="trackStarted">
                        </div>
                        <div class="form-group col-md-6 mb-2">
                            <label class="mb-1"><strong>Date &amp; Time Completed</strong></label>
                            <input type="datetime-local" class="form-control form-control-sm" name="date_time_completed" id="trackCompleted" disabled>
                            <small class="form-text text-muted" id="trackCompletedHint">Fill this in only when the project is finished.</small>
                        </div>
                    </div>

                    <div class="form-group mb-2">
                        <label class="mb-1"><strong>Comments/ Remarks</strong></label>
                        <select class="form-control form-control-sm" name="tracking_remarks" id="trackRemarks">
                            <?php foreach (jobOrderTrackingRemarks() as $option): ?>
                                <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $option === 'work_in_progress' ? 'selected' : ''; ?>><?php echo htmlspecialchars(jobOrderTrackingRemarksLabel($option)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" class="custom-control-input" id="trackAcknowledge" required>
                        <label class="custom-control-label" for="trackAcknowledge">
                            I hereby acknowledge that the work above has been satisfactorily completed.
                        </label>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6 mb-2">
                            <label class="mb-1"><strong>Job proponent (name and signature)</strong></label>
                            <input type="text" class="form-control form-control-sm mb-1" name="job_proponent_name" placeholder="Name">
                            <input type="text" class="form-control form-control-sm" name="job_proponent_signature" placeholder="Signature">
                        </div>
                        <div class="form-group col-md-6 mb-2">
                            <label class="mb-1"><strong>Noted by:</strong> <small class="text-muted">Admin officer V (Admin)</small></label>
                            <input type="text" class="form-control form-control-sm" id="trackNotedBy" value="Administrative Officer V (Admin)" readonly>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <small class="text-muted mr-auto align-self-center" id="trackStatusHint"></small>
                    <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="trackSaveBtn">
                        <i class="fa fa-save mr-1"></i>Save Track
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
/**
 * Track modal: fill-up form for the job order tracking inputs.
 * Opened by any .js-track-open control, or automatically right after an approval.
 *
 * This block is emitted inside the page content, which the dashboard shell renders
 * BEFORE the shared jQuery/Bootstrap bundles at the end of the layout. Binding here
 * directly would throw "jQuery is not defined" and the Track buttons would do nothing,
 * so the work is deferred until the document is ready and those scripts have run.
 */
(function () {
    function initTrackModal() {
        var $modal = $('#jobOrderTrackModal');
        if (!$modal.length) {
            return;
        }
    var $form = $('#jobOrderTrackForm');
    var $alert = $('#trackModalAlert');
    var $saveBtn = $('#trackSaveBtn');
    var $completed = $('#trackCompleted');
    var $remarks = $('#trackRemarks');
    var $hint = $('#trackStatusHint');
    var saveUrl = <?php echo json_encode(uri() . '/bsa/api/job-order-tracking-save.php'); ?>;

    var remarkLabels = <?php echo json_encode(array_combine(
        jobOrderTrackingRemarks(),
        array_map('jobOrderTrackingRemarksLabel', jobOrderTrackingRemarks())
    )); ?>;

    function syncCompleted() {
        var isCompleted = $remarks.val() === 'completed';
        $completed.prop('disabled', !isCompleted);
        if (!isCompleted) {
            $completed.val('');
        }
        $('#trackCompletedHint')
            .text(isCompleted ? 'Project marked as finished.' : 'Fill this in only when the project is finished.');
        $hint.text('Current status: ' + (remarkLabels[$remarks.val()] || $remarks.val()));
    }

    // Populate and show the modal.
    window.openJobOrderTrackModal = function (data) {
        data = data || {};
        $('#trackModalId').val(data.id || '');
        $('#trackModalOrderNo').text(data.orderNo || '');
        $form.find('[name="actions_taken"]').val(data.actions || '');
        $form.find('[name="recommendation"]').val(data.recommendation || '');
        $form.find('[name="assigned_personnel"]').val(data.assigned || '');
        $form.find('[name="job_proponent_name"]').val(data.proponentName || '');
        $form.find('[name="job_proponent_signature"]').val(data.proponentSign || '');
        $('#trackStarted').val(data.started || '');
        $('#trackCompleted').val(data.completed || '');
        $remarks.val(data.remarks || 'work_in_progress');
        $('#trackAcknowledge').prop('checked', false);
        $alert.addClass('d-none').removeClass('alert-success alert-danger');
        syncCompleted();
        $modal.modal('show');
    };

    $remarks.on('change', syncCompleted);

    // Any control carrying the track data-* payload opens the modal.
    $(document).on('click', '.js-track-open', function () {
        var $btn = $(this);
        window.openJobOrderTrackModal({
            id: $btn.attr('data-track-id'),
            orderNo: $btn.attr('data-track-order-no'),
            actions: $btn.attr('data-track-actions'),
            recommendation: $btn.attr('data-track-recommendation'),
            assigned: $btn.attr('data-track-assigned'),
            started: $btn.attr('data-track-started'),
            completed: $btn.attr('data-track-completed'),
            remarks: $btn.attr('data-track-remarks'),
            proponentName: $btn.attr('data-track-proponent-name'),
            proponentSign: $btn.attr('data-track-proponent-sign')
        });
    });

    $form.on('submit', function (event) {
        event.preventDefault();

        if (!$('#trackAcknowledge').is(':checked')) {
            $alert.removeClass('d-none alert-success').addClass('alert-danger')
                .text('Please acknowledge that the work above has been satisfactorily completed.');
            return;
        }

        if ($remarks.val() === 'completed' && !$('#trackCompleted').val()) {
            $alert.removeClass('d-none alert-success').addClass('alert-danger')
                .text('Date & Time Completed is required when the project is marked as Completed.');
            return;
        }

        var data = new FormData(this);
        if ($completed.prop('disabled')) {
            data.delete('date_time_completed');
        }

        $saveBtn.prop('disabled', true);
        $alert.removeClass('d-none alert-success alert-danger').addClass('alert-info').text('Saving...');

        fetch(saveUrl, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) { return response.json(); })
            .then(function (result) {
                $saveBtn.prop('disabled', false);
                if (!result || !result.success) {
                    $alert.removeClass('alert-info').addClass('alert-danger')
                        .text((result && result.message) || 'Unable to save the tracking details.');
                    return;
                }
                $alert.removeClass('alert-info').addClass('alert-success')
                    .text(result.message + ' Status: ' + result.tracking_remarks_label + '. Refreshing...');
                $hint.text('Saved as: ' + result.tracking_remarks_label);
                window.setTimeout(function () { window.location.reload(); }, 1200);
            })
            .catch(function () {
                $saveBtn.prop('disabled', false);
                $alert.removeClass('alert-info').addClass('alert-danger')
                    .text('An error occurred while saving the tracking details.');
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTrackModal);
    } else {
        initTrackModal();
    }
}());
</script>
<?php endif; ?>

<?php if ($printMode && $viewId): ?>
<script>
window.addEventListener('load', function () {
    window.print();
});
</script>
<?php endif; ?>