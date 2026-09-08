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
    echo json_encode(['success' => false, 'error' => 'UNAUTHORIZED', 'message' => 'Please log in to issue an ICS.']);
    exit;
}

$icsNo = trim((string) ($_POST['ics_no'] ?? ''));
$receivedFrom = trim((string) ($_POST['received_from'] ?? ''));
$receivedFromSignature = trim((string) ($_POST['received_from_signature'] ?? ''));
$receivedFromPosition = trim((string) ($_POST['received_from_position'] ?? ''));
$receivedBy = trim((string) ($_POST['received_by'] ?? ''));
$receivedBySignature = trim((string) ($_POST['received_by_signature'] ?? ''));
$receivedByPosition = trim((string) ($_POST['received_by_position'] ?? ''));
$icsDate = trim((string) ($_POST['ics_date'] ?? ''));
$remarks = trim((string) ($_POST['remarks'] ?? ''));

$items = json_decode((string) ($_POST['items'] ?? '[]'), true);
if (!is_array($items)) {
    $items = [];
}
$items = array_values(array_filter($items, static function ($it): bool {
    return is_array($it) && (int) ($it['item_id'] ?? 0) > 0;
}));

if (empty($items)) {
    echo json_encode(['success' => false, 'error' => 'NO_ITEMS', 'message' => 'Please include at least one item.']);
    exit;
}

if ($icsNo === '') {
    $icsNo = imsNextDocumentNumber('ICS', 'issued_ics', 'ics_no');
}

$totalCost = 0.0;
$lines = [];
foreach ($items as $it) {
    $qty = max((int) ($it['qty'] ?? 0), 0);
    $amount = round((float) ($it['amount'] ?? 0), 2);
    $unitCost = $qty > 0 ? round($amount / $qty, 2) : 0.0;
    $totalCost += $amount;
    $lines[] = [
        'item_id' => (int) $it['item_id'],
        'stock_no' => trim((string) ($it['stock_no'] ?? '')),
        'description' => trim((string) ($it['description'] ?? '')),
        'unit' => trim((string) ($it['unit'] ?? '')),
        'qty' => $qty,
        'unit_cost' => $unitCost,
        'amount' => $amount,
        'status' => trim((string) ($it['status'] ?? '')),
    ];
}

beginTransaction();
$icsId = insert('issued_ics', [
    'ics_no' => $icsNo,
    'received_from' => $receivedFrom,
    'received_from_signature' => $receivedFromSignature,
    'received_from_position' => $receivedFromPosition,
    'received_by' => $receivedBy,
    'received_by_signature' => $receivedBySignature,
    'received_by_position' => $receivedByPosition,
    'ics_date' => $icsDate !== '' ? $icsDate : null,
    'remarks' => $remarks !== '' ? $remarks : null,
    'total_cost' => round($totalCost, 2),
    'created_by' => (string) $userId,
]);

if ($icsId === false) {
    rollBack();
    echo json_encode(['success' => false, 'error' => 'SAVE_FAILED', 'message' => 'Failed to save the ICS. Please try again.']);
    exit;
}

$allOk = true;
foreach ($lines as $line) {
    $result = insert('issued_ics_items', [
        'issued_ics_id' => (int) $icsId,
        'item_id' => $line['item_id'],
        'stock_no' => $line['stock_no'],
        'description' => $line['description'],
        'unit' => $line['unit'],
        'qty' => $line['qty'],
        'unit_cost' => $line['unit_cost'],
        'amount' => $line['amount'],
        'status' => $line['status'],
    ]);
    if ($result === false) {
        $allOk = false;
        break;
    }
}

if (!$allOk) {
    rollBack();
    echo json_encode(['success' => false, 'error' => 'SAVE_FAILED', 'message' => 'Failed to save the ICS items. Please try again.']);
    exit;
}

commit();
createSystemLog($stationId ?? null, $userId, 'Issued ICS ' . $icsNo, $icsId, clientIp());
echo json_encode(['success' => true, 'id' => (int) $icsId, 'ics_no' => $icsNo]);