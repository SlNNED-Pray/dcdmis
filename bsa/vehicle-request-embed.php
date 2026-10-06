<?php
/**
 * Vehicle Request Form Embed Page (form only, no site chrome)
 * Loaded inside an iframe popup from the DCDMS booking calendar.
 */

require_once(__DIR__ . '/../includes/function.php');

if (empty($GLOBALS['userId'] ?? null)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'UNAUTHORIZED', 'message' => 'Please log in to submit a vehicle request.']);
    exit;
}

$csrfToken = csrf_token();

$prefill = [
    'requesting_office'    => '',
    'date_request'         => date('Y-m-d'),
    'requesting_personnel' => '',
    'contact_number'       => '',
    'no_of_passengers'     => '',
    'purpose_of_travel'    => '',
    'destination'          => '',
    'depart_date'          => trim((string) ($_GET['start_date'] ?? '')),
    'depart_time'          => trim((string) ($_GET['start_time'] ?? '')),
    'return_date'          => trim((string) ($_GET['end_date'] ?? '')),
    'return_time'          => trim((string) ($_GET['end_time'] ?? '')),
    'special_request'      => '',
    'requested_by_name'    => '',
    'requested_by_position' => '',
    'concurred_by_name'    => '',
    'concurred_by_position' => '',
    'division_chief'       => '',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Request a Vehicle - Schools Division of Dipolog City">
    <title>Vehicle Request Form - Schools Division of Dipolog City</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Local icon font -->
    <link rel="stylesheet" href="fonts/icomoon/style.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- DTC-SC booking form styles (shared layout) -->
    <link rel="stylesheet" href="<?php echo uri(); ?>/dtcsc-booking/public/assets/css/style.css">
    <style>
        body { background: #f8f9fa; }
        .embed-wrap { max-width: 820px; margin: 0 auto; padding: 1.25rem; }
        .form-head { background: white; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.08); padding: 1.25rem 1.5rem; margin-bottom: 1.25rem; text-align: center; }
        .form-head h2 { font-weight: 700; color: #212529; font-size: 1.4rem; margin-bottom: 0.25rem; }
        .form-head p { color: #6c757d; font-size: 0.85rem; margin: 0; }
        .form-head .control-no { font-weight: 600; color: #198754; font-size: 0.95rem; }
        .vehicle-type-options .form-check { border: 1px solid #dee2e6; border-radius: 10px; padding: 0.75rem 1rem; margin-bottom: 0.5rem; }
        .vehicle-type-options .form-check-input:checked ~ .form-check-label { font-weight: 600; color: #198754; }
    </style>
</head>
<body>
    <div class="embed-wrap">
        <?php require_once __DIR__ . '/includes/vehicle-request-form.php'; ?>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
</body>
</html>