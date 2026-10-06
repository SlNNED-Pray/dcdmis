<?php
// bsa/api/employees-suggest.php — DCDMIS employee autocomplete for the DTC booking form (GET)
require_once(__DIR__ . '/../../includes/function.php');
require_once(__DIR__ . '/../../includes/database/database.php');
require_once(__DIR__ . '/../../includes/database/account.php');
require_once(__DIR__ . '/../../includes/database/employee.php');
require_once(__DIR__ . '/../../includes/database/position.php');

header('Content-Type: application/json; charset=utf-8');

if (empty($GLOBALS['userId'] ?? null)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'UNAUTHORIZED', 'message' => 'Please log in first.']);
    exit;
}

if (!userRole($GLOBALS['userId'], 'bsa')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'FORBIDDEN', 'message' => 'You do not have permission to search employee records.']);
    exit;
}

$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

function dtcSuggestFullName($emp): string
{
    $name = trim((string) ($emp['first_name'] ?? ''));
    if (!empty($emp['middle_name'])) {
        $name .= ' ' . strtoupper(mb_substr((string) $emp['middle_name'], 0, 1)) . '.';
    }
    $name .= ' ' . strtoupper(trim((string) ($emp['last_name'] ?? '')));
    if (!empty($emp['name_extension'])) {
        $name .= ' ' . trim((string) $emp['name_extension']);
    }
    return trim($name);
}

function dtcSuggestOfficeHead($stationId)
{
    if (empty($stationId)) {
        return null;
    }
    $head = find("SELECT `head_id` FROM `schools` WHERE `id` = ? LIMIT 1", [$stationId]);
    if (!$head || empty($head['head_id'])) {
        return null;
    }
    $headEmp = employee($head['head_id']);
    if (!$headEmp) {
        return null;
    }
    $headPos = position($head['head_id']);
    return [
        'name'     => dtcSuggestFullName($headEmp),
        'position' => trim((string) ($headPos['official_title'] ?? '')),
    ];
}

$db = connection();

$like = '%' . $q . '%';
$sql = "SELECT p.`id`, p.`first_name`, p.`middle_name`, p.`last_name`, p.`name_extension`,
               p.`email_address`, p.`mobile_number`, p.`status`,
               sa.`station_id`, pso.`official_title`, sc.`name` AS `office_name`
        FROM `employees` p
        INNER JOIN (
            SELECT `employee_id`, MAX(`assignment_date`) AS `latest_date`
            FROM `station_assignments`
            GROUP BY `employee_id`
        ) `latest` ON latest.`employee_id` = p.`id`
        INNER JOIN `station_assignments` sa ON sa.`employee_id` = p.`id` AND sa.`assignment_date` = latest.`latest_date`
        INNER JOIN `positions` pso ON pso.`id` = sa.`position_id`
        INNER JOIN `schools` sc ON sc.`id` = sa.`station_id`
        WHERE p.`status` <> 'Duplicate'
          AND (p.`first_name` LIKE ? OR p.`middle_name` LIKE ? OR p.`last_name` LIKE ? OR p.`name_extension` LIKE ? OR p.`email_address` LIKE ?)
        ORDER BY p.`last_name` ASC, p.`first_name` ASC
        LIMIT 15";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute([$like, $like, $like, $like, $like]);
    $results = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $results[] = [
            'id'            => (int) $row['id'],
            'name'          => dtcSuggestFullName($row),
            'email_address' => trim((string) ($row['email_address'] ?? '')),
            'mobile_number' => trim((string) ($row['mobile_number'] ?? '')),
            'position'      => trim((string) ($row['official_title'] ?? '')),
            'office'        => trim((string) ($row['office_name'] ?? '')),
            'head'          => dtcSuggestOfficeHead($row['station_id']),
        ];
    }
    echo json_encode($results);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB_ERROR', 'message' => 'Failed to load employee suggestions.']);
}