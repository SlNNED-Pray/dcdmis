<?php
require_once(__DIR__ . '/helpers.php');
sidebarDivider();
sidebarMenuItem(customUri('ims', 'Purchase Requests'), 'Purchase Requests', 'fa-file-invoice-dollar', $url === 'Purchase Requests' || $url === 'Create Purchase Request');
