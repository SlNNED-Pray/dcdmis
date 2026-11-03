<?php
require_once(__DIR__ . '/helpers.php');
echo '<li class="nav-item"><a class="nav-link ' . ($url === 'Inventory Management System' ? 'active' : '') . '" href="' . uri() . '/ims"><i class="fas fa-tachometer-alt"></i> <span>INVENTORY MANAGEMENT SYSTEM</span></a></li>';
