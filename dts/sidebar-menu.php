<?php
// dts/sidebar-menu.php
sidebarDivider();

$count = stationTransactionCounts($station);
$countIncoming = number_format($count['incoming']);
$countPending = number_format($count['pending']);
$countOutgoing = number_format($count['outgoing']);
$countOngoing = number_format($count['ongoing']);

sidebarModalItem(uri() . '/modules/documents/save-document-dialog.php', 'New Document', 'fa-plus');
sidebarMenuItem(customUri('dts', 'Incoming Documents'), 'Incoming', 'fa-file-download', isset($url) && str_contains($url, 'Incoming'), $countIncoming);
sidebarMenuItem(customUri('dts', 'Pending Documents'), 'Pending', 'fa-history', isset($url) && str_contains($url, 'Pending'), $countPending);
sidebarMenuItem(customUri('dts', 'Outgoing Documents'), 'Outgoing', 'fa-file-upload', isset($url) && str_contains($url, 'Outgoing'), $countOutgoing);

sidebarDivider();

sidebarMenuItem(customUri('dts', 'Ongoing Documents'), 'Ongoing', 'fa-tasks', isset($url) && str_contains($url, 'Ongoing'), $countOngoing);

sidebarDivider();

sidebarMenuItem(customUri('dts', 'Completed Documents'), 'Completed', 'fa-check-circle', isset($url) && str_contains($url, 'Completed'));

if (!$isSchoolPortal) {
	sidebarMenuItem(customUri('dts', 'Received Documents'), 'Received', 'fa-hand-holding-medical', isset($url) && str_contains($url, 'Received'));
}

sidebarMenuItem(customUri('dts', 'Canceled Documents'), 'Canceled', 'fa-trash-alt', isset($url) && str_contains($url, 'Canceled'));

sidebarDivider();

sidebarMenuItem(customUri('dts', 'Section Summary'), 'Summary', 'fa-chart-bar', isset($url) && str_contains($url, 'Summary'));

if ($isRecordsPortal || $isAdminPortal || $isICT) {
	$documentTypes = count(documentTypes());
	sidebarMenuItem(customUri('dts', 'Transactions'), 'Transactions', 'fa-exchange-alt', isset($url) && str_contains($url, 'Transactions'));

	sidebarDivider();

	sidebarMenuItem(customUri('dts', 'Document Types'), 'Document Types', 'fa-file', isset($url) && str_contains($url, 'Document Types'), $documentTypes);
}