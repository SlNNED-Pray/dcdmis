<?php
require_once(__DIR__ . '/helpers.php');
sidebarDivider();
if (!imsCanAccessAll()) {
    sidebarMenuItem(imsUri('Purchase Requests'), 'Purchase Requests', 'fa-file-invoice-dollar', $url === 'Purchase Requests' || $url === 'Create Purchase Request');
}
