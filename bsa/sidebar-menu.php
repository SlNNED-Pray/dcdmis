<?php
// bsa/sidebar-menu.php
if (!function_exists('uri')) {
    http_response_code(403);
    exit('Forbidden');
}

require_once(__DIR__ . '/includes/bsa-access.php');

sidebarDivider();
sidebarMenuItem(uri() . '/bsa/?v=' . encode('Booking & Schedule'), 'Booking & Schedule', 'fa-calendar-check', ($url ?? null) === 'Booking & Schedule');
if (canAccessBookingApprovals($GLOBALS['userId'] ?? null)) {
    sidebarMenuItem(uri() . '/bsa/?v=' . encode('Booking Approvals'), 'Booking Approvals', 'fa-clipboard-check', ($url ?? null) === 'Booking Approvals');
}