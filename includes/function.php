<?php
// include/function.php
require_once('config.php');

function dd($value)
{
    echo '<pre>';
    ob_start();
    var_dump($value);
    $output = ob_get_clean();
    echo htmlspecialchars($output);
    echo '</pre>';
    die();
}

function hashPassword(string $password): string
{
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function cipher($string)
{
    return base64_encode($string);
}

function decipher($string)
{
    if ($string === null) {
        return '';
    }
    return base64_decode((string) $string);
}

function encode($string)
{
    return urlencode(cipher($string));
}

function decode($string)
{
    return urldecode(decipher($string));
}

function root()
{
    static $root = null;
    if ($root === null) {
        $root = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
    }
    return $root;
}

function uri($domain = null)
{
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $root = $domain !== null ? $domain : $_SERVER['HTTP_HOST'];
    if ($domain !== null) {
        return "{$protocol}{$root}";
    }

    return "{$protocol}{$root}" . basePath();
}

/**
 * Path of the application relative to the document root.
 *
 * Empty when the app is served from its own virtual host (production and the
 * usual http://depeddipolog.test setup), and "/depeddipolog.test" when the
 * app is reached through the htdocs folder (for example
 * http://localhost/depeddipolog.test/).
 */
function basePath()
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $base = '';
    $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $applicationRoot = realpath(dirname(__DIR__));
    if ($documentRoot !== false && $applicationRoot !== false) {
        $documentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');
        $applicationRoot = str_replace('\\', '/', $applicationRoot);
        if ($documentRoot !== '' && $applicationRoot !== $documentRoot
            && strpos($applicationRoot . '/', $documentRoot . '/') === 0) {
            $base = substr($applicationRoot, strlen($documentRoot));
        }
    }

    return $base;
}

function customUri($page, $view, $id = null, $domain = null)
{
    $value = ($id !== null) ? '&id=' . encode($id) : '';

    return uri($domain) . "/{$page}?v=" . encode($view) . $value;
}

function title($page = null)
{
    return $page === null ? SITE_TITLE : "{$page} | " . SITE_TITLE;
}

function alias()
{
    return SITE_ALIAS;
}

function description()
{
    return SITE_DESCRIPTION;
}

function author()
{
    return SITE_AUTHOR;
}

function division()
{
    return DIVISION;
}

function divisionId()
{
    return DIVISION_ID;
}

function region()
{
    return REGION;
}

function clientIp()
{
    return $_SERVER['REMOTE_ADDR'];
}

function redirect($url = null)
{
    if ($url !== null) {
        header("Location: $url");
        exit;
    }
}

function generateID()
{
    $timestamp = date('YmdHis');
    $random = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
    return "{$timestamp}{$random}";
}

function getSeconds($hours = 1)
{
    return $hours * 60 * 60;
}

function setActiveItem($reference, $value, $class = 'active')
{
    return strtolower($reference) === strtolower($value) ? " {$class}" : '';
}

function setActiveNavigation($condition, $class = 'active')
{
    return $condition ? " {$class}" : '';
}

function isValidEmail($email, $domain = null)
{
    if ($domain === null) {
        return preg_match("/^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/", $email);
    }

    return preg_match("/^[a-zA-Z0-9_.-]+@" . preg_quote($domain, '/') . "$/", $email);
}

function setOptionSelected($reference, $value)
{
    return strtolower($reference) === strtolower($value) ? ' selected' : '';
}

function setItemChecked($condition)
{
    return $condition ? ' checked' : '';
}

function getDateDifference($date)
{
    $now = new DateTime();
    $bdate = new DateTime($date);
    return $now->diff($bdate)->y;
}

function e($string)
{
    $string = (string) $string;
    return str_replace(['<', '>'], ['&lt;', '&gt;'], $string);
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
        }
    }
    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf_token()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $submittedToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        if (empty($submittedToken) || !hash_equals($sessionToken, $submittedToken)) {
            header('HTTP/1.1 403 Forbidden');

            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'CSRF_EXPIRED',
                    'message' => 'CSRF token validation failed or session expired. Please try again.',
                    'csrf_token' => csrf_token()
                ]);
                exit();
            } else {
                header('Location: ' . uri() . '/oops?e=403&reason=csrf');
                exit();
            }
        }
    }
}

function uploadMaxBytes()
{
    $val = defined('UPLOAD_MAX_FILESIZE') ? UPLOAD_MAX_FILESIZE : ini_get('upload_max_filesize');
    return parseSizeToBytes($val);
}

function parseSizeToBytes($size_string)
{
    $size_string = trim($size_string);
    $last = strtolower($size_string[strlen($size_string) - 1]);
    $val = (int) $size_string;
    switch ($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}

function stageUploadedFile(array $file_data, array $allowed_MIME_map, string $target_folder, string $prefix = 'FILE'): array
{
    if ($file_data['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("File upload failed with error system code: " . $file_data['error']);
    }

    if ($file_data['size'] > FILE_UPLOAD_SIZE_LIMIT) {
        $limitStr = defined('UPLOAD_MAX_FILESIZE') ? UPLOAD_MAX_FILESIZE . 'B' : 'the system limit';
        throw new Exception("The chosen file size exceeds the maximum allowed upload limit of {$limitStr}.");
    }

    try {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $real_MIME_type = $finfo->file($file_data['tmp_name']);
    } catch (Exception $e) {
        throw new Exception("Failed to analyze the file properties securely.");
    }

    if (!$real_MIME_type || !array_key_exists($real_MIME_type, $allowed_MIME_map)) {
        throw new Exception("Invalid file content structure detected. Format transformation rejected.");
    }

    $ext = $allowed_MIME_map[$real_MIME_type];
    $timestamp = date('YmdHis');
    $secure_name = strtoupper("{$prefix}_{$timestamp}_" . bin2hex(random_bytes(4))) . ".{$ext}";
    $sanitized_target_folder = rtrim($target_folder, '/\\');

    return [
        'tmp_name' => $file_data['tmp_name'],
        'secure_name' => $secure_name,
        'full_path' => "{$sanitized_target_folder}/{$secure_name}",
        'extension' => $ext
    ];
}

function commitStagedFile(array $staged_file): void
{
    $directory = dirname($staged_file['full_path']);
    if (!is_dir($directory)) {
        if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new Exception("Failed to establish isolated system directory tracks.");
        }
    }

    if (!move_uploaded_file($staged_file['tmp_name'], $staged_file['full_path'])) {
        throw new Exception("Failed to execute storage target disk migrations safely.");
    }
}

require_once('initialization.php');