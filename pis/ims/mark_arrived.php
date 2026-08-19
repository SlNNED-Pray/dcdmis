<?php
if (!$isPis) {
    require_once(root() . '/modules/error/403.php');
    return;
}

$id = (int)($_GET['id'] ?? 0);
$stmt = connection()->prepare('SELECT id, status FROM purchase_requests WHERE id = ?');
$stmt->execute([$id]);
$pr = $stmt->fetch();
if (!$pr) {
    messageAlert(true, 'Purchase request not found.', false);
    return;
}
if ($pr['status'] !== 'pending') {
    messageAlert(true, 'This purchase request is not pending.', false);
    return;
}

connection()->prepare("UPDATE purchase_requests SET status = 'arrived' WHERE id = ?")->execute([$id]);
$message = 'Purchase request marked as arrived. Proceed with inspection.';
$success = true;
messageAlert(true, $message, $success);
echo '<a href="' . e(customUri('pis', 'Purchase Requests')) . '">Back to Purchase Requests</a>';
