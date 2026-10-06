<?php
require_once(__DIR__ . '/helpers.php');
sidebarDivider();
if (imsIsStaff()) {
sidebarMenuItem(customUri('ims', 'Stock and Inventory'), 'Stock and Inventory', 'fa-boxes', $url === 'Stock and Inventory' || $url === 'Create Stock Item');
sidebarMenuItem(customUri('ims', 'Requisition Slips'), 'Requisition Slips', 'fa-file-invoice', $url === 'Requisition Slips' || $url === 'Create RIS');
sidebarMenuItem(customUri('ims', 'Disapproved RIS'), 'Disapproved RIS', 'fa-file-invoice', $url === 'Disapproved RIS');
sidebarMenuItem(customUri('ims', 'Purchase Requests'), 'Purchase Requests', 'fa-file-invoice-dollar', $url === 'Purchase Requests' || $url === 'Create Purchase Request');
sidebarMenuItem(customUri('ims', 'Stock Card'), 'Stock Card', 'fa-clipboard-list', $url === 'Stock Card');
sidebarMenuItem(customUri('ims', 'Report of Supplies and Materials Issued'), 'Report of Supplies and Materials Issued', 'fa-file-alt', $url === 'Report of Supplies and Materials Issued');
sidebarMenuItem(customUri('ims', 'Inventory Custodian Slip'), 'Inventory Custodian Slip', 'fa-clipboard-list', $url === 'Inventory Custodian Slip');
sidebarMenuItem(customUri('ims', 'Issued ICS'), 'Issued ICS', 'fa-file-invoice', $url === 'Issued ICS');
sidebarMenuItem(customUri('ims', 'Property Acknowledgement Receipt'), 'Property Acknowledgement Receipt', 'fa-file-signature', $url === 'Property Acknowledgement Receipt');
sidebarMenuItem(customUri('ims', 'Issued PAR'), 'Issued PAR', 'fa-file-invoice', $url === 'Issued PAR');
sidebarMenuItem(customUri('ims', 'Inspection and Acceptance Report'), 'Inspection and Acceptance Report', 'fa-clipboard-check', $url === 'Inspection and Acceptance Report');
sidebarMenuItem(customUri('ims', 'Physical Count'), 'Physical Count', 'fa-clipboard-check', $url === 'Physical Count');
sidebarMenuItem(customUri('ims', 'Price History'), 'Price History', 'fa-tags', $url === 'Price History');
sidebarMenuItem(customUri('ims', 'Supplier'), 'Supplier', 'fa-truck', $url === 'Supplier');
} else {
sidebarMenuItem(customUri('ims', 'Requisition Slips'), 'Requisition Slips', 'fa-file-invoice', $url === 'Requisition Slips' || $url === 'Create RIS');
}