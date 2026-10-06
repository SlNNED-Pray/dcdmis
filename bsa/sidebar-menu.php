<?php
// bsa/sidebar-menu.php
sidebarDivider();
sidebarMenuItem(uri() . '/bsa/', 'Booking & Schedule', 'fa-calendar-check', true);
sidebarMenuItem(uri() . '/bsa/?v=' . encode('Booking Approvals'), 'Booking Approvals', 'fa-clipboard-check', ($url ?? null) === 'Booking Approvals');