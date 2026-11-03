<?php
// bsa/includes/dtcsc-optional.php
// Load the DTC-SC booking config only when the sibling app is present, and
// provide safe fallbacks so BSA still works without it.

$dtcscSiblingAvailable = is_file(__DIR__ . '/../../dtcsc-booking/config/config.php');

if ($dtcscSiblingAvailable) {
    require_once __DIR__ . '/../../dtcsc-booking/config/config.php';
}

if (!function_exists('generateCsrfToken')) {
    function generateCsrfToken()
    {
        return csrf_token();
    }
}

if (!function_exists('validateCsrfToken')) {
    function validateCsrfToken($token)
    {
        return !empty($token) && hash_equals(csrf_token(), (string) $token);
    }
}

if (!function_exists('formatDate')) {
    function formatDate($date)
    {
        return !empty($date) ? date('M d, Y', strtotime($date)) : 'N/A';
    }
}

if (!function_exists('formatTime')) {
    function formatTime($time)
    {
        return !empty($time) ? date('h:i A', strtotime($time)) : '';
    }
}

if (!defined('DISPLAY_DATETIME_FORMAT')) {
    define('DISPLAY_DATETIME_FORMAT', 'M d, Y h:i A');
}