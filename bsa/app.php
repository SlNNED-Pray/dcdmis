<?php
// bsa/app.php
$activeApp = $_SESSION["{$prefix}activeApp"] = 'bsa';
$page = $appTitle = 'Booking & Schedule';
$showAlert = false;
$message = '';
$success = false;

if (empty($userId)) {
    redirect("{$baseUri}/login");
}

if (isset($_SESSION["{$prefix}change_password"])) {
    redirect("{$baseUri}/login/change");
}