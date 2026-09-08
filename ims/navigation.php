<?php
function imsNav(string $active): void
{
    $links = [
        'inventory' => ['Stock and Inventory', 'fa-boxes'],
        'ris' => ['Requisition Slips', 'fa-file-invoice'],
        'ris-disapproved' => ['Disapproved RIS', 'fa-file-invoice'],
        'iar' => ['IAR', 'fa-clipboard-check'],
        'pr' => ['Purchase Requests', 'fa-file-invoice-dollar'],
        'stock-card' => ['Stock Card', 'fa-clipboard-list'],
        'ics' => ['Inventory Custodian Slip', 'fa-clipboard-list'],
        'ics-issued' => ['Issued ICS', 'fa-file-invoice'],
        'physical-count' => ['Physical Count', 'fa-clipboard-check'],
        'price-history' => ['Price History', 'fa-tags'],
    ];
    echo '<ul class="nav nav-tabs mb-4">';
    foreach ($links as $key => [$label, $icon]) {
        $class = $key === $active ? ' active' : '';
        echo '<li class="nav-item"><a class="nav-link' . $class . '" href="' . customUri('ims', $label) . '">';
        echo '<i class="fas ' . $icon . '"></i> ' . e($label) . '</a></li>';
    }
    echo '</ul>';
}
