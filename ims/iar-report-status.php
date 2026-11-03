<?php
require_once(__DIR__ . '/../includes/function.php');
require_once(root() . '/includes/string.php');
require_once(root() . '/includes/database/system-log.php');
require_once(root() . '/ims/helpers.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (empty($userId)) {
    redirect(uri() . '/login');
}

requireImsStaff();

$id = (int) ($_POST['id'] ?? 0);
$status = trim((string) ($_POST['status'] ?? ''));
$filter = trim((string) ($_POST['filter'] ?? ''));

$back = customUri('ims', 'Inspection and Acceptance Report');
if ($filter !== '') {
    $back .= (strpos($back, '?') === false ? '?' : '&') . 'filter=' . urlencode($filter);
}

if (!in_array($status, ['Delivered', 'Partial'], true)) {
    $_SESSION["{$prefix}stock_flash"] = ['success' => false, 'message' => 'Invalid delivery status.'];
    redirect($back);
}

$order = $id > 0 ? find('SELECT po_no, delivery_status FROM purchase_requests WHERE id = ?', [$id]) : false;
if (!$order) {
    $_SESSION["{$prefix}stock_flash"] = ['success' => false, 'message' => 'Purchase order not found.'];
    redirect($back);
}

if (($order['delivery_status'] ?? 'Pending') === 'Delivered') {
    $_SESSION["{$prefix}stock_flash"] = ['success' => false, 'message' => 'This purchase order is already fully delivered.'];
    redirect($back);
}

$result = update('purchase_requests', ['delivery_status' => $status], '`id` = ?', [$id]);
if ($result === false) {
    $_SESSION["{$prefix}stock_flash"] = ['success' => false, 'message' => 'Failed to update the delivery status. Please try again.'];
    redirect($back);
}

createSystemLog($stationId ?? null, $userId, 'Marked PO ' . $order['po_no'] . ' (ID ' . $id . ') as ' . $status, $id, clientIp());
$_SESSION["{$prefix}stock_flash"] = ['success' => true, 'message' => 'Purchase order ' . $order['po_no'] . ' marked as ' . $status . '.'];
redirect($back);