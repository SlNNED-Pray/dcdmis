<?php
// dipee/views/login.php
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sign-in'])) {
    verifyCsrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
        $error = 'Enter your username and password.';
    } elseif (!attemptLogin($username, $password)) {
        $error = 'Invalid username or password.';
    } else {
        redirect('index.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · <?= e(PAYROLL_TITLE) ?></title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body class="login-page">
<main class="login-card">
    <div class="login-brand">
        <span class="brand-mark">DP</span>
        <div>
            <h1><?= e(PAYROLL_TITLE) ?></h1>
            <p class="muted"><?= e(PAYROLL_SUBTITLE) ?></p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="stack">
        <?= csrfField() ?>
        <label>
            Username
            <input type="text" name="username" autocomplete="username" autofocus required>
        </label>
        <label>
            Password
            <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button class="btn btn-primary btn-block" type="submit" name="sign-in" value="1">Sign in</button>
    </form>
</main>
</body>
</html>
