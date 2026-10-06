<?php
require_once(__DIR__ . '/../includes/function.php');
require_once(root() . '/includes/string.php');
require_once(root() . '/includes/database/system-log.php');
require_once(root() . '/ims/helpers.php');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'INVALID_METHOD', 'message' => 'Invalid request method.']);
    exit;
}

if (empty($userId)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'UNAUTHORIZED', 'message' => 'Please log in to issue an IAR.']);
    exit;
}

requireImsStaff();

$iarNo = trim((string) ($_POST['iar_no'] ?? ''));
$entityName = trim((string) ($_POST['entity'] ?? ''));
$fundCluster = trim((string) ($_POST['fund'] ?? ''));
$supplier = trim((string) ($_POST['supplier'] ?? ''));
$poNo = trim((string) ($_POST['po_no'] ?? ''));
$poDate = trim((string) ($_POST['po_date'] ?? ''));
$iarDate = trim((string) ($_POST['iar_date'] ?? ''));
$officeDept = trim((string) ($_POST['office_dept'] ?? ''));
$invoiceNo = trim((string) ($_POST['invoice_no'] ?? ''));
$invoiceDate = trim((string) ($_POST['invoice_date'] ?? ''));
$rcc = trim((string) ($_POST['responsibility_center_code'] ?? ''));
$inspectorName = trim((string) ($_POST['inspector_name'] ?? ''));
$dateInspected = trim((string) ($_POST['date_inspected'] ?? ''));
$dateReceived = trim((string) ($_POST['date_received'] ?? ''));
$acceptanceStatus = trim((string) ($_POST['acceptance_status'] ?? 'Complete'));
$partialQty = trim((string) ($_POST['partial_qty'] ?? ''));
$remarks = trim((string) ($_POST['remarks'] ?? ''));
$poId = (int) (decode((string) ($_POST['po_id'] ?? '')) ?: 0);
$issuedId = (int) (decode((string) ($_POST['issued_id'] ?? '')) ?: 0);

if ($inspectorName === '') {
    echo json_encode(['success' => false, 'error' => 'VALIDATION', 'message' => 'Inspector name is required.']);
    exit;
}

$items = json_decode((string) ($_POST['items'] ?? '[]'), true);
if (!is_array($items)) {
    $items = [];
}
$items = array_values(array_filter($items, static function ($it): bool {
    return is_array($it) && (int) ($it['item_id'] ?? 0) > 0 && (int) ($it['qty'] ?? 0) > 0;
}));
if (empty($items)) {
    echo json_encode(['success' => false, 'error' => 'VALIDATION', 'message' => 'No items to report.']);
    exit;
}

if ($iarNo === '') {
    $iarNo = imsNextDocumentNumber('IAR', 'issued_iar', 'iar_no');
}

if (!in_array($acceptanceStatus, ['Complete', 'Partial'], true)) {
    $acceptanceStatus = 'Complete';
}

beginTransaction();
$existingIar = $issuedId > 0
    ? find('SELECT id, po_no FROM issued_iar WHERE id = ?', [$issuedId])
    : null;

$iarData = [
    'iar_no' => $iarNo,
    'entity_name' => $entityName,
    'fund_cluster' => $fundCluster,
    'supplier' => $supplier,
    'po_no' => $poNo,
    'po_date' => $poDate !== '' ? $poDate : null,
    'iar_date' => $iarDate !== '' ? $iarDate : null,
    'office_dept' => $officeDept,
    'invoice_no' => $invoiceNo,
    'invoice_date' => $invoiceDate !== '' ? $invoiceDate : null,
    'responsibility_center_code' => $rcc,
    'inspector_name' => $inspectorName,
    'date_inspected' => $dateInspected !== '' ? $dateInspected : null,
    'date_received' => $dateReceived !== '' ? $dateReceived : null,
    'acceptance_status' => $acceptanceStatus,
    'partial_qty' => $partialQty,
    'remarks' => $remarks !== '' ? $remarks : null,
    'created_by' => (string) $userId,
];

if ($existingIar) {
    if ($existingIar['po_no'] !== $poNo) {
        rollBack();
        echo json_encode(['success' => false, 'error' => 'INVALID_IAR', 'message' => 'The IAR being updated does not belong to this purchase order.']);
        exit;
    }
    if (update('issued_iar', $iarData, '`id` = ?', [$issuedId]) === false) {
        rollBack();
        echo json_encode(['success' => false, 'error' => 'SAVE_FAILED', 'message' => 'Failed to update the IAR. Please try again.']);
        exit;
    }
    if (delete('issued_iar_items', '`issued_iar_id` = ?', [$issuedId]) === false) {
        rollBack();
        echo json_encode(['success' => false, 'error' => 'SAVE_FAILED', 'message' => 'Failed to update the IAR items. Please try again.']);
        exit;
    }
    $iarId = (int) $issuedId;
} else {
    $iarId = insert('issued_iar', $iarData);
    if ($iarId === false) {
        rollBack();
        echo json_encode(['success' => false, 'error' => 'SAVE_FAILED', 'message' => 'Failed to save the IAR. Please try again.']);
        exit;
    }
}

$allOk = true;
foreach ($items as $it) {
    $result = insert('issued_iar_items', [
        'issued_iar_id' => (int) $iarId,
        'item_id' => (int) $it['item_id'],
        'stock_no' => trim((string) ($it['stock_no'] ?? '')),
        'description' => trim((string) ($it['description'] ?? '')),
        'unit' => trim((string) ($it['unit'] ?? '')),
        'qty' => (int) $it['qty'],
    ]);
    if ($result === false) {
        $allOk = false;
        break;
    }
}

if (!$allOk) {
    rollBack();
    echo json_encode(['success' => false, 'error' => 'SAVE_FAILED', 'message' => 'Failed to save the IAR items. Please try again.']);
    exit;
}

if ($allOk && $poId > 0) {
    $deliveryStatus = $acceptanceStatus === 'Partial' ? 'Partial' : 'Delivered';
    if (update('purchase_requests', ['delivery_status' => $deliveryStatus], '`id` = ?', [$poId]) === false) {
        rollBack();
        echo json_encode(['success' => false, 'error' => 'SAVE_FAILED', 'message' => 'Failed to update the purchase order delivery status.']);
        exit;
    }
}

commit();
createSystemLog($stationId ?? null, $userId, ($existingIar ? 'Updated IAR ' : 'Issued IAR ') . $iarNo, $iarId, clientIp());
echo json_encode(['success' => true, 'id' => (int) $iarId, 'iar_no' => $iarNo]);
