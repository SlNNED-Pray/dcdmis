<?php
require_once(__DIR__ . '/helpers.php');

// Shared sticky-header treatment for IMS list tables. Print endpoints do not
// load this file, so their paper layouts are unaffected.
?>
<style>
#content-wrapper { overflow-x: visible !important; }
.card-body > .table-responsive { overflow: visible !important; }
.card-body table.table > thead {
    position: sticky;
    top: 0;
    z-index: 10;
}
.card-body table.table > thead > tr > th {
    background: #f8f9fc;
    border-bottom: 2px solid #4e73df;
    white-space: nowrap;
    vertical-align: middle;
    text-align: center;
    text-transform: uppercase;
    font-size: .8rem;
    letter-spacing: .03em;
}
.card-body table.table > thead > tr > th.text-right { text-align: right; }
</style>
<?php

function imsNav(string $active): void
{
    ?>
    <script>
    (function () {
        document.addEventListener('click', function (e) {
            var trigger = e.target && e.target.closest ? e.target.closest('[data-target="#risModal"]') : null;
            if (!trigger) return;
            // Bootstrap is not loaded yet (for example while a large page is still
            // parsing). Remember the click and open the modal once it is ready.
            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) return;
            window.__imsRisPending = true;
            if (!window.__imsRisLoadBound) {
                window.__imsRisLoadBound = true;
                window.addEventListener('load', function () {
                    if (window.__imsRisPending && window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
                        window.__imsRisPending = false;
                        window.jQuery('#risModal').modal('show');
                    }
                });
            }
        }, true);
    })();
    </script>
    <?php
    $links = [
        'inventory' => ['Stock and Inventory', 'fa-boxes'],
        'ris' => ['Requisition Slips', 'fa-file-invoice'],
        'ris-disapproved' => ['Disapproved RIS', 'fa-file-invoice'],
        'iar-report' => ['Inspection and Acceptance Report', 'fa-clipboard-check'],
        'pr' => ['Purchase Requests', 'fa-file-invoice-dollar'],
        'rcppe' => ['RCPPE', 'fa-building'],
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
    if (!imsCanAccessAll()) {
        $class = $active === 'pr' ? ' active' : '';
        echo '<li class="nav-item"><a class="nav-link' . $class . '" href="' . imsUri('Purchase Requests') . '"><i class="fas fa-file-invoice-dollar"></i> Purchase Requests</a></li>';
        echo '</ul>';
        return;
    }
    foreach ($links as $key => [$label, $icon]) {
        $class = $key === $active ? ' active' : '';
        echo '<li class="nav-item"><a class="nav-link' . $class . '" href="' . imsUri($label) . '">';
        echo '<i class="fas ' . $icon . '"></i> ' . e($label) . '</a></li>';
    }
    echo '</ul>';
}
