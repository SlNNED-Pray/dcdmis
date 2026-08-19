<?php
// pis/app.php
$activeApp = $_SESSION["{$prefix}activeApp"] = HOME;
$page = $appTitle = 'Inventory Management System';

if (!isset($userId)) {
    redirect("{$baseUri}/login");
}

if (isset($_SESSION["{$prefix}change_password"])) {
    redirect("{$baseUri}/login/change");
}

if (isset($_POST['primary-search-button'])) {
    redirect(customUri('pis', 'Search', sanitize($_POST['primary-search-text'])));
}