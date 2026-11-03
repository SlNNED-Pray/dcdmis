<?php
/**
 * DTC Facility Booking Form Embed Page (form only, no site chrome)
 * Loaded inside an iframe popup from the DCDMS booking calendar.
 * Auto-populates the requesting employee's office, contact, and signatories from dcdmis.
 */

require_once(__DIR__ . '/../includes/function.php');
require_once(__DIR__ . '/../includes/database/database.php');
require_once(__DIR__ . '/../includes/database/employee.php');
require_once(__DIR__ . '/../includes/database/position.php');
require_once(__DIR__ . '/includes/dtc-booking-tables.php');

if (empty($GLOBALS['userId'] ?? null)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'UNAUTHORIZED', 'message' => 'Please log in to submit a facility booking.']);
    exit;
}

try {
    ensureDtcBookingTables();
} catch (Throwable $e) {
    // Tables already exist or will be created on POST.
}

$csrfToken = csrf_token();

function dtcBookingFullName($emp): string
{
    if (!$emp) {
        return '';
    }
    $name = trim((string) ($emp['first_name'] ?? ''));
    if (!empty($emp['middle_name'])) {
        $name .= ' ' . strtoupper(mb_substr((string) $emp['middle_name'], 0, 1)) . '.';
    }
    $name .= ' ' . strtoupper(trim((string) ($emp['last_name'] ?? '')));
    if (!empty($emp['name_extension'])) {
        $name .= ' ' . trim((string) $emp['name_extension']);
    }
    return trim($name);
}

$emp = employee($GLOBALS['userId']);
$pos = position($GLOBALS['userId']);

$auto = [
    'requesting_office'      => '',
    'contact_person'         => '',
    'email_address'          => '',
    'contact_number'         => '',
    'requested_by_name'      => '',
    'requested_by_position'  => '',
    'requested_by_signature' => '',
    'concurred_by_name'      => '',
    'concurred_by_position'  => '',
    'concurred_by_signature' => '',
];

if ($emp) {
    $empName = dtcBookingFullName($emp);
    $auto['contact_person']         = $empName;
    $auto['email_address']          = trim((string) ($emp['email_address'] ?? ''));
    $auto['contact_number']         = trim((string) ($emp['mobile_number'] ?? ''));
    $auto['requested_by_name']      = $empName;
    $auto['requested_by_signature'] = $empName;
}

if ($pos) {
    $auto['requesting_office']     = trim((string) ($pos['station'] ?? ''));
    $auto['requested_by_position'] = trim((string) ($pos['official_title'] ?? ''));

    $head = null;
    if (!empty($pos['station_id'])) {
        $head = find("SELECT `head_id` FROM `schools` WHERE `id` = ? LIMIT 1", [$pos['station_id']]);
    }
    if ($head && !empty($head['head_id'])) {
        $headEmp = employee($head['head_id']);
        $headPos = position($head['head_id']);
        if ($headEmp) {
            $headName = dtcBookingFullName($headEmp);
            $auto['concurred_by_name']      = $headName;
            $auto['concurred_by_signature'] = $headName;
        }
        if ($headPos) {
            $auto['concurred_by_position'] = trim((string) ($headPos['official_title'] ?? ''));
        }
    }
}

$prefill = [
    'requesting_office'      => $auto['requesting_office'],
    'date_request'           => date('Y-m-d'),
    'contact_person'         => $auto['contact_person'],
    'email_address'          => $auto['email_address'],
    'contact_number'         => $auto['contact_number'],
    'requested_by_name'      => $auto['requested_by_name'],
    'requested_by_position'  => $auto['requested_by_position'],
    'requested_by_signature' => $auto['requested_by_signature'],
    'concurred_by_name'      => $auto['concurred_by_name'],
    'concurred_by_position'  => $auto['concurred_by_position'],
    'concurred_by_signature' => $auto['concurred_by_signature'],
    'start_date'             => trim((string) ($_GET['start_date'] ?? '')),
    'end_date'               => trim((string) ($_GET['end_date'] ?? '')),
    'start_time'             => trim((string) ($_GET['start_time'] ?? '')),
    'end_time'               => trim((string) ($_GET['end_time'] ?? '')),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Book the Division Training Center - Schools Division of Dipolog City">
    <title>Division Training Center (DTC) Booking Form</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Local icon font -->
    <link rel="stylesheet" href="fonts/icomoon/style.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .embed-wrap { max-width: 860px; margin: 0 auto; padding: 1.25rem; }
        .form-head { background: white; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.08); padding: 1.25rem 1.5rem; margin-bottom: 1.25rem; text-align: center; }
        .form-head h2 { font-weight: 700; color: #212529; font-size: 1.4rem; margin-bottom: 0.25rem; }
        .form-head p { color: #6c757d; font-size: 0.85rem; margin: 0; }
        .form-head .control-no { font-weight: 600; color: #4e73df; font-size: 0.95rem; }
        .booking-form-container { background: white; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.08); padding: 1.5rem; }
        .booking-progress .progress-bar { background-color: #4e73df; transition: width 0.3s ease; }
        .step-indicators .step { color: #adb5bd; text-align: center; flex: 1; font-size: 0.75rem; font-weight: 600; }
        .step-indicators .step.active { color: #4e73df; }
        .step-indicators .step-number { background: #e9ecef; border-radius: 50%; display: inline-block; width: 24px; height: 24px; line-height: 24px; font-size: 0.75rem; margin-bottom: 2px; }
        .step-indicators .step.active .step-number { background: #4e73df; color: white; }
        .form-step { display: none; }
        .form-step.active { display: block; }
        .equipment-options .form-check { border: 1px solid #dee2e6; border-radius: 10px; padding: 0.6rem 1rem; margin-bottom: 0.5rem; }
        .booking-summary p { margin-bottom: 0.4rem; }
        .success-message { text-align: center; padding: 2.5rem 1rem; }
        .success-icon { font-size: 4rem; color: #28a745; }
        .auto-note { font-size: 0.75rem; color: #28a745; }
        .dtc-autocomplete { position: relative; }
        .dtc-autocomplete-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 1050;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
            max-height: 240px;
            overflow-y: auto;
            display: none;
        }
        .dtc-autocomplete-suggestions .item {
            padding: 0.55rem 0.85rem;
            cursor: pointer;
            border-bottom: 1px solid #f1f3f5;
        }
        .dtc-autocomplete-suggestions .item:last-child { border-bottom: 0; }
        .dtc-autocomplete-suggestions .item:hover,
        .dtc-autocomplete-suggestions .item.active { background: #eef2ff; }
        .dtc-autocomplete-suggestions .item .main { font-weight: 600; color: #212529; }
        .dtc-autocomplete-suggestions .item .sub { font-size: 0.75rem; color: #6c757d; }
    </style>
</head>
<body>
    <div class="embed-wrap">
        <?php require_once __DIR__ . '/includes/dtc-booking-form.php'; ?>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
</body>
</html>