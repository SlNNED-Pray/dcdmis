<?php
// dipee/auth.php - session authentication for the payroll system.

function startPayrollSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.name', PAYROLL_SESSION);
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

function currentUser(): array|false
{
    if (empty($_SESSION['dipee_user_id'])) {
        return false;
    }
    return find('SELECT * FROM `users` WHERE `id` = ? LIMIT 1', [$_SESSION['dipee_user_id']]);
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['dipee_user_id']);
}

function attemptLogin(string $username, string $password): bool
{
    $user = find('SELECT * FROM `users` WHERE `username` = ? LIMIT 1', [$username]);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['dipee_user_id'] = (int) $user['id'];
    $_SESSION['dipee_user_name'] = $user['full_name'];
    return true;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
