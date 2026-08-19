<?php
// include/initialization.php
ini_set('session.name', 'DCDMIS_SESSION');
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');

if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' cdn.jsdelivr.net code.jquery.com cdn.datatables.net; style-src 'self' 'unsafe-inline' cdn.jsdelivr.net fonts.googleapis.com cdn.datatables.net; img-src 'self' data: blob:; font-src 'self' fonts.gstatic.com; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self';");
    header_remove('X-Powered-By');
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

date_default_timezone_set("Asia/Manila");

if (!defined('UPLOAD_MAX_FILESIZE')) {
    define('UPLOAD_MAX_FILESIZE', '20M');
}
ini_set('upload_max_filesize', UPLOAD_MAX_FILESIZE);
ini_set('post_max_size', '25M');
ini_set('memory_limit', '256M');
ini_set('max_input_time', 300);
ini_set('max_execution_time', 300);

$displayErrors = (defined('PRODUCTION_MODE') && PRODUCTION_MODE) ? 0 : 1;
ini_set('display_errors', $displayErrors);

if (defined('ERROR_LOG_FILE') && !empty(ERROR_LOG_FILE)) {
    ini_set('error_log', ERROR_LOG_FILE);
}

ini_set('log_errors', 1);

if (!defined('FILE_UPLOAD_SIZE_LIMIT')) {
    if (function_exists('uploadMaxBytes')) {
        define('FILE_UPLOAD_SIZE_LIMIT', uploadMaxBytes());
    } else {
        $iniUpload = ini_get('upload_max_filesize') ?: UPLOAD_MAX_FILESIZE;
        define('FILE_UPLOAD_SIZE_LIMIT', $iniUpload);
    }
}

const HOME = 'pis';
$prefix = alias() . '_';
$baseUri = uri();
$cookieDomain = parse_url($baseUri, PHP_URL_HOST) ?: ($_SERVER['HTTP_HOST'] ?? DOMAIN);
$enableScripts = false;
$showAlert = false;
$message = null;
$success = false;
$activeApp = $_SESSION["{$prefix}activeApp"] ?? HOME;
$activeTab = $_SESSION["{$prefix}activeTab"] ?? null;
$userId = $_SESSION["{$prefix}userId"] ?? null;
$stationId = $_SESSION["{$prefix}stationId"] ?? null;
$station = $_SESSION["{$prefix}station"] ?? null;
$code = $_SESSION["{$prefix}code"] ?? null;
$portal = $_SESSION["{$prefix}portal"] ?? null;

if (empty($userId) && isset($_COOKIE["{$prefix}remember_token"])) {
    require_once(root() . '/includes/database/database.php');
    $cookieParts = explode('|', $_COOKIE["{$prefix}remember_token"], 2);
    if (count($cookieParts) === 2) {
        $cookieEmployeeId = (int) $cookieParts[0];
        $cookieToken = $cookieParts[1];
        $tokenHash = hash('sha256', $cookieToken);

        $rememberRecord = find(
            "SELECT `employee_id`, `expires_at` FROM `remember_tokens` 
            WHERE `employee_id` = ? AND `token_hash` = ? LIMIT 1",
            [$cookieEmployeeId, $tokenHash]
        );

        if ($rememberRecord && strtotime($rememberRecord['expires_at']) > time()) {
            $rememberUser = find(
                "SELECT u.`employee_id`, u.`access`, s.`station_id`, u.`link` 
                FROM `user_permissions` u 
                INNER JOIN `station_assignments` s ON u.`employee_id` = s.`employee_id` 
                WHERE u.`employee_id` = ? AND u.link NOT IN ('') LIMIT 1",
                [$cookieEmployeeId]
            );

            $rememberAccount = find(
                "SELECT e.`id`, e.`email_address` FROM `employees` e 
                WHERE e.`id` = ? AND e.`status` = 'Active' LIMIT 1",
                [$cookieEmployeeId]
            );

            if ($rememberUser && $rememberAccount) {
                $portal = $rememberUser['link'];
                $access = $rememberUser['access'];
                $stationIdVal = $rememberUser['station_id'];

                $_SESSION["{$prefix}userId"] = $cookieEmployeeId;
                $_SESSION["{$prefix}stationId"] = $stationIdVal;
                $_SESSION["{$prefix}code"] = $access;
                $_SESSION["{$prefix}portal"] = $portal;
                $_SESSION["{$prefix}email"] = $rememberAccount['email_address'];

                if ($portal === 'sch_portal') {
                    $school = find("SELECT `alias` FROM `schools` WHERE id = ? LIMIT 1", [$access]);
                    $stationName = $school['alias'] ?? '';
                } else {
                    $stationName = $access ?? '';
                }
                $_SESSION["{$prefix}station"] = $stationName;

                $userId = $cookieEmployeeId;
                $stationId = $stationIdVal;
                $station = $stationName;
                $code = $access;
                session_regenerate_id(true);
                $newToken = bin2hex(random_bytes(32));
                $newTokenHash = hash('sha256', $newToken);
                $newExpiry = date('Y-m-d H:i:s', time() + getSeconds(120));

                update('remember_tokens', [
                    'token_hash' => $newTokenHash,
                    'expires_at' => $newExpiry
                ], '`employee_id` = ? AND `token_hash` = ?', [$cookieEmployeeId, $tokenHash]);

                setcookie("{$prefix}remember_token", "{$cookieEmployeeId}|{$newToken}", [
                    'expires' => time() + getSeconds(120),
                    'path' => '/',
                    'domain' => $cookieDomain,
                    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }
        } else {
            delete('remember_tokens', '`employee_id` = ?', [$cookieEmployeeId ?? 0]);
            setcookie("{$prefix}remember_token", '', [
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => $cookieDomain,
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
    }
}

$hasPortal = !empty($portal);
$isSchoolPortal = $portal === 'sch_portal';
$isRecordsPortal = $portal === 'rec_portal';
$isAdminPortal = $portal === 'adm_portal';
$isPersonnel = $code === 'PER';
$isICT = $code === 'ICT';
$allowedMonitoringPositions = ['SDS', 'ASDS', 'CES', 'ATY3', 'ITO1', 'A3', 'ADOF5', 'ADOF4', 'SEPS', 'EPS2', 'PSDS', 'EPS', 'PLO3'];

if (function_exists('verify_csrf_token')) {
    verify_csrf_token();
}

$modules = ['hrmis', 'dts', 'pis', 'race', 'hrtdms', 'dmis', 'dtr', 'ims', 'monitoring_tools', 'system_overview'];

foreach ($modules as $area) {
    if (!isset($_SESSION["{$prefix}data_privacy_agreed_{$area}"])) {
        $_SESSION["{$prefix}data_privacy_agreed_{$area}"] = false;
    }
}

if (isset($_POST['accept_data_privacy'])) {
    $area = $_POST['data_privacy_area'] ?? '';
    if (in_array($area, $modules)) {
        $_SESSION["{$prefix}data_privacy_agreed_{$area}"] = true;
    }
    redirect($_SERVER['REQUEST_URI']);
}

require_once(root() . '/includes/database/database.php');
require_once(root() . '/includes/database/backup.php');
checkAndRunDatabaseBackup();