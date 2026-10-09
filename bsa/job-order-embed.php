<?php
/**
 * Job Order/Request Form Embed Page (form only, no site chrome).
 */

require_once(__DIR__ . '/../includes/function.php');
require_once(__DIR__ . '/includes/job-order-tables.php');

if (empty($GLOBALS['userId'] ?? null)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'UNAUTHORIZED', 'message' => 'Please log in to submit a job order request.']);
    exit;
}

ensureJobOrderTables();
$csrfToken = csrf_token();
$prefill = [
    'requesting_office' => '',
    'date_request'      => trim((string) ($_GET['date_request'] ?? '')),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Job Order/Request Form - Schools Division of Dipolog City">
    <title>Job Order/Request Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="fonts/icomoon/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #f8f9fa; font-family: Poppins, sans-serif; }
        .embed-wrap { max-width: 860px; margin: 0 auto; padding: 1.25rem; }
        .form-head, .booking-form-container { background: #fff; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,.08); }
        .form-head { padding: 1.25rem 1.5rem; margin-bottom: 1.25rem; text-align: center; }
        .form-head h2 { font-weight: 700; color: #000000; font-size: 1.4rem; margin-bottom: .25rem; }
        .form-head p { color: #000000; font-size: .85rem; margin: 0; }
        .form-head .control-no { font-weight: 600; color: #f5cc6d; font-size: .95rem; }
        .booking-form-container { padding: 1.5rem; }
        legend { font-size: 1rem; font-weight: 600; }
    </style>    
</head>
<body>
    <div class="embed-wrap">
        <?php require_once __DIR__ . '/includes/job-order-form.php'; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
</body>
</html>
