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
    echo json_encode(['success' => false, 'error' => 'UNAUTHORIZED', 'message' => 'Please log in to issue a PAR.']);
    exit;
}

$parNo = trim((string) ($_POST['par_no'] ?? ''));
$entityName = trim((string) ($_POST['entity'] ?? ''));
$fundCluster = trim((string) ($_POST['fund'] ?? ''));
$endUser = trim((string) ($_POST['end_user'] ?? ''));
$endUserPosition = trim((string) ($_POST['end_user_pos'] ?? ''));
$endUserDate = trim((string) ($_POST['end_user_date'] ?? ''));
$remarks = trim((string) ($_POST['remarks'] ?? ''));

$items = json_decode((string) ($_POST['items'] ?? '[]'), true);
if (!is_array($items)) {
    $items = [];
}
$items = array_values(array_filter($items, static function ($it): bool {
    return is_array($it) && (int) ($it['item_id'] ?? 0) > 0;
}));

if ($parNo === '') {
    $parNo = imsNextDocumentNumber('PAR', 'issued_par', 'par_no');
}

$totalCost = 0.0;
$lines = [];
foreach ($items as $it) {
    $qty = max((int) ($it['qty'] ?? 0), 0);
    $amount = round((float) ($it['amount'] ?? 0), 2);
    $totalCost += $amount;
    $lines[] = [
        'item_id' => (int) $it['item_id'],
        'stock_no' => trim((string) ($it['stock_no'] ?? '')),
        'description' => trim((string) ($it['description'] ?? '')),
        'unit' => trim((string) ($it['unit'] ?? '')),
        'qty' => $qty,
        'date_acquired' => trim((string) ($it['date_acquired'] ?? '')),
        'amount' => $amount,
        'remarks' => trim((string) ($it['remarks'] ?? '')),
    ];
}

beginTransaction();
$parId = insert('issued_par', [
    'par_no' => $parNo,
    'entity_name' => $entityName,
    'fund_cluster' => $fundCluster,
    'end_user' => $endUser,
    'end_user_position' => $endUserPosition,
    'end_user_date' => $endUserDate !== '' ? $endUserDate : null,
    'remarks' => $remarks !== '' ? $remarks : null,
    'total_cost' => round($totalCost, 2),
    'created_by' => (string) $userId,
]);

if ($parId === false) {
    rollBack();
    echo json_encode(['success' => false, 'error' => 'SAVE_FAILED', 'message' => 'Failed to save the PAR. Please try again.']);
    exit;
}

$allOk = true;
foreach ($lines as $line) {
    $result = insert('issued_par_items', [
        'issued_par_id' => (int) $parId,
        'item_id' => $line['item_id'],
        'stock_no' => $line['stock_no'],
        'description' => $line['description'],
        'unit' => $line['unit'],
        'qty' => $line['qty'],
        'date_acquired' => $line['date_acquired'],
        'amount' => $line['amount'],
        'remarks' => $line['remarks'],
    ]);
    if ($result === false) {
        $allOk = false;
        break;
    }
}

if (!$allOk) {
    rollBack();
    echo json_encode(['success' => false, 'error' => 'SAVE_FAILED', 'message' => 'Failed to save the PAR items. Please try again.']);
    exit;
}

commit();
createSystemLog($stationId ?? null, $userId, 'Issued PAR ' . $parNo, $parId, clientIp());
echo json_encode(['success' => true, 'id' => (int) $parId, 'par_no' => $parNo]);