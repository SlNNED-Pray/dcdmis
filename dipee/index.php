<?php
// dipee/index.php - single entry point and router.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

startPayrollSession();

if (!installed()) {
    redirect('install.php');
}

$page = $_GET['page'] ?? 'dashboard';

if ($page === 'logout') {
    logout();
    redirect('index.php');
}

$routes = [
    'dashboard' => 'dashboard.php',
    'employees' => 'employees.php',
    'employee-form' => 'employee-form.php',
    'deductions' => 'deductions.php',
    'payroll' => 'payroll.php',
    'register' => 'register.php',
    'payslip' => 'payslip.php',
    'settings' => 'settings.php',
    'import' => 'import.php',
];

if (!isLoggedIn()) {
    require __DIR__ . '/views/login.php';
    exit;
}

if (!isset($routes[$page])) {
    $page = 'dashboard';
}

$user = currentUser();

require __DIR__ . '/views/layout-top.php';
require __DIR__ . '/views/' . $routes[$page];
require __DIR__ . '/views/layout-bottom.php';
