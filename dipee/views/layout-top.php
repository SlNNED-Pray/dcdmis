<?php
// dipee/views/layout-top.php
$flash = takeFlash();
$nav = [
    'dashboard' => 'Dashboard',
    'employees' => 'Employees',
    'deductions' => 'Deductions',
    'payroll' => 'Payroll',
    'settings' => 'Settings',
    'import' => 'Import legacy data',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(PAYROLL_TITLE) ?></title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<header class="topbar no-print">
    <div class="topbar-inner">
        <a class="brand" href="index.php">
            <span class="brand-mark">DP</span>
            <span>
                <strong><?= e(PAYROLL_TITLE) ?></strong>
                <small><?= e(getSetting('division', PAYROLL_SUBTITLE)) ?></small>
            </span>
        </a>
        <nav class="main-nav">
            <?php foreach ($nav as $key => $label): ?>
                <a href="index.php?page=<?= e($key) ?>" class="<?= $page === $key ? 'active' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="user-box">
            <span><?= e($user['full_name'] ?? '') ?></span>
            <a class="btn btn-ghost btn-sm" href="index.php?page=logout">Sign out</a>
        </div>
    </div>
</header>
<main class="container">
    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> no-print"><?= e($flash['message']) ?></div>
    <?php endif; ?>
