<?php
require_once(__DIR__ . '/helpers.php');

function imsNav(string $active): void
{
    $links = [
        'inventory' => ['Stock and Inventory', 'fa-boxes'],
        'ris' => ['Requisition Slips', 'fa-file-invoice'],
        'ris-disapproved' => ['Disapproved RIS', 'fa-file-invoice'],
        'iar-report' => ['Inspection and Acceptance Report', 'fa-clipboard-check'],
        'pr' => ['Purchase Requests', 'fa-file-invoice-dollar'],
        'stock-card' => ['Stock Card', 'fa-clipboard-list'],
        'rsmi' => ['Report of Supplies and Materials Issued', 'fa-file-alt'],
        'ics' => ['Inventory Custodian Slip', 'fa-clipboard-list'],
        'ics-issued' => ['Issued ICS', 'fa-file-invoice'],
        'par' => ['Property Acknowledgement Receipt', 'fa-file-signature'],
        'par-issued' => ['Issued PAR', 'fa-file-invoice'],
        'physical-count' => ['Physical Count', 'fa-clipboard-check'],
        'price-history' => ['Price History', 'fa-tags'],
        'suppliers' => ['Supplier', 'fa-truck'],
    ];
    echo '<ul class="nav nav-tabs mb-4">';
    foreach ($links as $key => [$label, $icon]) {
        if (!imsIsStaff() && $key !== 'ris') {
            continue;
        }
        $class = $key === $active ? ' active' : '';
        echo '<li class="nav-item"><a class="nav-link' . $class . '" href="' . customUri('ims', $label) . '">';
        echo '<i class="fas ' . $icon . '"></i> ' . e($label) . '</a></li>';
    }
    echo '</ul>';
}
