<?php
// includes/database/document.php
function documentTransactionStatus(?int $status_id = null): string|array
{
    $sql = "SELECT `id`, `name` FROM `document_transaction_statuses`";
    if ($status_id) {
        $sql .= " WHERE `id` = ? LIMIT 1";
        $row = find($sql, [$status_id]);
        return $row['name'] ?? '';
    }
    $sql .= " WHERE `for_processing` = 1 ORDER BY `name` ASC";
    $result = query($sql);
    return is_array($result) ? $result : [];
}

function documentStatusId(string $status): int|null
{
    $sql = "SELECT `id` FROM `document_transaction_statuses` WHERE `name` = ? LIMIT 1";
    $result = find($sql, [$status]);
    return $result ? (int) $result['id'] : null;
}

function documentTypes(bool $for_school = false): array
{
    $where = ["`id` <> 1"];
    $params = [];
    if ($for_school) {
        $where[] = "`for_school` = ?";
        $params[] = 1;
    }
    $whereClause = implode(" AND ", $where);
    $sql = "SELECT `id`, `name`, `description`, `for_school` FROM `document_types` WHERE $whereClause ORDER BY `name` ASC";
    $result = query($sql, $params);
    return is_array($result) ? $result : [];
}

function documentType(?int $document_type_id): array
{
    $sql = "SELECT `name`, `description`, `for_school` FROM `document_types` WHERE `id` = ? LIMIT 1";
    $result = find($sql, [$document_type_id]);
    return is_array($result) ? $result : [];
}

function createDocumentType($name, $description, $for_school)
{
    $data = [
        'name' => $name,
        'description' => $description,
        'for_school' => $for_school,
    ];
    return insert('document_types', $data);
}

function updateDocumentType($name, $description, $for_school, $id)
{
    $data = [
        'name' => $name,
        'description' => $description,
        'for_school' => $for_school,
    ];
    return update('document_types', $data, '`id` = ?', [$id]);
}

function deleteDocumentType($id)
{
    return delete('document_types', '`id` = ?', [$id]);
}

function documentTypeStationSteps($document_type_id)
{
    $sql = "SELECT * FROM `document_type_station_steps` WHERE `document_type_id` = ?";
    $result = query($sql, [$document_type_id]);
    return is_array($result) ? $result : [];
}

function documentTypeStationStep($document_type_station_step_id)
{
    $sql = "SELECT * FROM `document_type_station_steps` WHERE `id` = ? LIMIT 1";
    $result = find($sql, [$document_type_station_step_id]);
    return is_array($result) ? $result : [];
}

function createDocumentTypeStationStep()
{

}

function updateDocumentTypeStationStep()
{

}

function deleteDocumentTypeStationStep($document_type_station_step_id)
{
    return delete('document_type_station_steps', '`id` = >', [$document_type_station_step_id]);
}

function documentTypeStationStepRequirements($document_type_id, $document_type_station_step_id = null)
{
    $sql = "SELECT 
                r.id,
                t.id AS `type_id`,
                t.name AS `document_type`,
                s.id AS `step_id`,
                s.step_number,
                s.destination_id,
                s.description AS `step_description`,
                s.note AS `step_note`,
                s.ideal_processing_time AS `ideal_time`,
                r.description AS `requirement_name`,
                r.is_mandatory 
            FROM `document_type_station_step_requirements` AS r 
            INNER JOIN `document_type_station_steps` AS s 
                ON r.document_type_station_step_id = s.id 
            INNER JOIN `document_types` AS t 
                ON s.document_type_id = t.id
            WHERE t.id = ?";

    $params = [$document_type_id];

    if (!empty($document_type_station_step_id)) {
        $sql .= " AND s.id = ?";
        $params[] = $document_type_station_step_id;
    }

    $sql .= " ORDER BY s.step_number ASC, r.id ASC";
    $result = query($sql, $params);

    return is_array($result) ? $result : [];
}

function document($document_transaction_id)
{
    $sql = "SELECT t.id, t.document_type_id, t.description, t.created_from, t.head_id, t.created_at, l.status_id FROM `document_transactions` AS t 
            LEFT JOIN `document_transaction_logs` AS l ON l.document_transaction_id = t.id AND l.is_new = 1 
            WHERE t.id = ? LIMIT 1";
    return find($sql, [$document_transaction_id]);
}

function isDocument($document_transaction_id, $status): bool
{
    $sql = "SELECT 1 
            FROM `document_transaction_logs` AS l 
            INNER JOIN `document_transaction_statuses` AS s ON l.status_id = s.id 
            WHERE l.document_transaction_id = ? 
              AND s.name LIKE ? AND l.is_new=1
            LIMIT 1";
    $statusPattern = "%{$status}%";
    $result = find($sql, [$document_transaction_id, $statusPattern]);
    return !empty($result);
}

function countDocumentsFrom($station_id, $year, $code)
{
    $sql = "SELECT COUNT(`id`) AS `total` 
            FROM `document_transactions` 
            WHERE `created_from` = ? 
              AND `id` LIKE ?";
    $pattern = "{$code}-{$year}-%";
    $result = find($sql, [$station_id, $pattern]);
    return (int) ($result['total'] ?? 0);
}

function documentOrigin($document_transaction_id)
{
    $sql = "SELECT 
                t.id, 
                t.description, 
                t.document_type_id, 
                l.status_id, 
                l.details, 
                t.head_id, 
                t.created_from, 
                t.created_at,
                l.created_at AS processed_at, 
                l.processor_id, 
                l.received_from 
            FROM `document_transactions` AS t 
            INNER JOIN `document_transaction_logs` AS l ON t.id = l.document_transaction_id 
            WHERE t.id = ? 
            ORDER BY l.created_at ASC 
            LIMIT 1";
    return find($sql, [$document_transaction_id]);
}

function isDocumentFrom($document_transaction_id, $received_from): bool
{
    $sql = "SELECT 1 FROM `document_transaction_logs` 
            WHERE `document_transaction_id` = ? 
              AND `received_from` = ? 
              AND `is_new` = 1 
            LIMIT 1";
    $results = query($sql, [$document_transaction_id, $received_from]);
    return !empty($results);
}

// document_transactions
function createDocument($document_transaction_id, $description, $document_type_id, $created_from, $head_id)
{
    $data = [
        'id' => $document_transaction_id,
        'document_type_id' => $document_type_id,
        'description' => $description,
        'created_from' => $created_from,
        'head_id' => $head_id,
    ];
    return insert('document_transactions', $data);
}

function updateDocument($document_transaction_id, $description, $document_type_id, $update_description = true)
{
    $data = [];
    if ($update_description) {
        $data['description'] = $description;
        $data['document_type_id'] = $document_type_id;
    }
    if (empty($data)) {
        return false;
    }
    return update('document_transactions', $data, "`id` = ?", [$document_transaction_id]);
}

function incomingDocuments($station_id, $from_date, $to_date, $limit = 1000)
{
    $sql = "SELECT 
                t.id, 
                t.description, 
                l.status_id, 
                t.created_from,
                l.received_from, 
                l.created_at
            FROM `document_transaction_logs` AS l 
            INNER JOIN `document_transactions` AS t ON l.document_transaction_id = t.id 
            WHERE l.received_from IS NOT NULL
                AND l.forwarded_to = ?
                AND l.status_id NOT IN (10, 11)
                AND l.is_new = 1 
                AND l.`created_at` >= ? 
                AND l.`created_at` < DATE_ADD(?, INTERVAL 1 DAY) 
            ORDER BY l.created_at DESC LIMIT ?";
    $results = query($sql, [$station_id, $from_date, $to_date, $limit]);
    return is_array($results) ? $results : [];
}

function isIncomingDocument($document_transaction_id, $station_id): bool
{
    $sql = "SELECT 1 FROM `document_transaction_logs` 
            WHERE `document_transaction_id` = ?
                AND received_from IS NOT NULL 
                AND forwarded_to = ? 
                AND status_id NOT IN (10, 11)
                AND is_new = 1
            LIMIT 1";
    $results = find($sql, [$document_transaction_id, $station_id]);

    return (bool) $results;
}

function pendingDocuments($station_id, $from_date, $to_date, $limit = 1000)
{
    $sql = "SELECT 
                t.id, 
                t.description, 
                l.processor_id, 
                t.created_from, 
                l.created_at
            FROM `document_transaction_logs` AS l 
            INNER JOIN `document_transactions` AS t ON l.document_transaction_id = t.id 
            WHERE l.received_from = ?
                AND l.forwarded_to IS NULL
                AND l.status_id NOT IN (10, 11)
                AND l.is_new = 1
                AND l.`created_at` >= ? 
                AND l.`created_at` < DATE_ADD(?, INTERVAL 1 DAY) 
            ORDER BY l.created_at DESC LIMIT ?";
    $results = query($sql, [$station_id, $from_date, $to_date, $limit]);
    return is_array($results) ? $results : [];
}

function isPendingDocument($document_transaction_id, $station_id): bool
{
    $sql = "SELECT 1 FROM `document_transaction_logs`
            WHERE document_transaction_id = ? 
              AND received_from = ? 
              AND forwarded_to IS NULL
              AND status_id NOT IN (10, 11)
              AND is_new = 1";
    $results = find($sql, [$document_transaction_id, $station_id]);
    return (bool) $results;
}

function outgoingDocuments($station_id, $from_date, $to_date, $limit = 1000)
{
    $sql = "SELECT 
                t.id, 
                t.description, 
                l.forwarded_to, 
                l.processor_id, 
                t.created_from, 
                l.created_at 
            FROM `document_transaction_logs` AS l 
            INNER JOIN `document_transactions` AS t ON l.document_transaction_id = t.id 
            WHERE l.received_from = ?
                AND l.forwarded_to IS NOT NULL
                AND l.status_id NOT IN (10, 11)
                AND l.is_new = 1
                AND l.`created_at` >= ? 
                AND l.`created_at` < DATE_ADD(?, INTERVAL 1 DAY) 
            ORDER BY l.created_at DESC LIMIT ?";
    $results = query($sql, [$station_id, $from_date, $to_date, $limit]);
    return is_array($results) ? $results : [];
}

function isOutgoingDocument($document_transaction_id, $station_id): bool
{
    $sql = "SELECT 1 FROM `document_transaction_logs` 
            WHERE document_transaction_id = ? 
                AND received_from = ?
                AND forwarded_to IS NOT NULL
                AND status_id NOT IN (10, 11)
                AND is_new = 1 
            LIMIT 1";
    $results = find($sql, [$document_transaction_id, $station_id]);
    return (bool) $results;
}

function ongoingDocuments($station_id, $from_date, $to_date, $limit = 1000)
{
    $sql = "SELECT 
                t.id, 
                t.description, 
                l.forwarded_to, 
                t.created_from,
                l.status_id, 
                t.created_at
            FROM `document_transactions` AS t
            INNER JOIN `document_transaction_logs` AS l ON t.id = l.document_transaction_id 
            WHERE t.created_from = ?
                AND l.forwarded_to IS NOT NULL
                AND l.status_id NOT IN (10, 11)
                AND l.is_new = 1
                AND l.`created_at` >= ? 
                AND l.`created_at` < DATE_ADD(?, INTERVAL 1 DAY) 
            GROUP BY t.id 
            ORDER BY l.created_at DESC LIMIT ?";
    $results = query($sql, [$station_id, $from_date, $to_date, $limit]);
    return is_array($results) ? $results : [];
}

function completedDocuments($station_id, $from_date, $to_date, $limit = 1000)
{
    $sql = "SELECT 
                t.`id`, 
                t.`description`, 
                t.`document_type_id`, 
                t.`created_at` AS `posted_on`, 
                l.`created_at` AS `completed_on`, 
                t.`created_from` 
            FROM `document_transactions` AS `t` 
            INNER JOIN `document_transaction_logs` AS `l` ON t.`id` = l.`document_transaction_id` 
            WHERE t.`created_from` = ?
                AND l.`forwarded_to` IS NULL
                AND l.`status_id` = 10
                AND l.`is_new` = 1
                AND l.`created_at` >= ? 
                AND l.`created_at` < DATE_ADD(?, INTERVAL 1 DAY) 
            ORDER BY l.`created_at` DESC LIMIT ?";
    return query($sql, [$station_id, $from_date, $to_date, $limit]);
}

function isCompletedDocument($document_transaction_id, $station_id): bool
{
    $sql = "SELECT 1 FROM `document_transactions` AS t 
            INNER JOIN `document_transaction_logs` AS l ON t.id = l.document_transaction_id 
            WHERE t.id = ? 
              AND t.created_from = ? 
              AND l.forwarded_to IS NULL
              AND l.status_id = 10
              AND l.is_new = 1 
            LIMIT 1";
    $results = find($sql, [$document_transaction_id, $station_id]);
    return (bool) $results;
}

function receivedDocuments($station_id, $from_date, $to_date)
{
    $sql = "SELECT 
                t.`id`, 
                t.`description`, 
                t.`document_type_id`, 
                t.`created_from`,
                l.`created_at`, 
                l.`processor_id`
            FROM `document_transaction_logs` AS `l` 
            INNER JOIN `document_transactions` AS `t` ON l.`document_transaction_id` = t.`id` 
            WHERE t.`created_from` != ?
                AND l.`received_from` = ?
                AND l.`forwarded_to` IS NULL
                AND l.`status_id` = 9
                AND l.`created_at` >= ? 
                AND l.`created_at` < DATE_ADD(?, INTERVAL 1 DAY) 
            ORDER BY l.`created_at` DESC 
            LIMIT 1000";
    return query($sql, [$station_id, $station_id, $from_date, $to_date]);
}

function isReceivedDocument($document_transaction_id, $station_id): bool
{
    $sql = "SELECT 1 FROM `document_transactions` AS t 
            INNER JOIN `document_transaction_logs` AS l ON t.`id` = l.`document_transaction_id` 
            WHERE t.`id` = ? 
                AND t.`created_from` != ?
                AND l.`received_from` = ?
                AND l.`forwarded_to` IS NULL
                AND l.`status_id` = 9
            LIMIT 1";
    $results = find($sql, [$document_transaction_id, $station_id, $station_id]);
    return (bool) $results;
}

function canceledDocuments($station_id, $from_date, $to_date, $limit = 1000)
{
    $sql = "SELECT 
                t.id, 
                t.description, 
                t.document_type_id, 
                t.created_at AS posted_on, 
                l.created_at AS canceled_on, 
                t.created_from 
            FROM `document_transactions` AS t 
            INNER JOIN `document_transaction_logs` AS l ON t.id = l.document_transaction_id 
            WHERE t.created_from = ?
                AND l.forwarded_to IS NULL
                AND l.status_id = 11
                AND l.is_new = 1 
                AND l.created_at >= ? 
                AND l.created_at < DATE_ADD(?, INTERVAL 1 DAY) 
            ORDER BY l.created_at DESC LIMIT ?";
    return query($sql, [$station_id, $from_date, $to_date, $limit]);
}

function isCanceledDocument($document_transaction_id, $station_id): bool
{
    $sql = "SELECT 1 FROM `document_transactions` AS t
            INNER JOIN `document_transaction_logs` AS l ON t.`id` = l.`document_transaction_id`
            WHERE t.`id` = ?
                AND t.created_from = ?
                AND l.forwarded_to IS NULL
                AND l.status_id = 11
                AND l.is_new = 1
            LIMIT 1";
    $results = find($sql, [$document_transaction_id, $station_id]);
    return (bool) $results;
}

function documentLog($document_transaction_id)
{
    $sql = "SELECT 
                t.id, 
                t.description, 
                t.document_type_id, 
                t.created_from, 
                l.created_at, 
                l.forwarded_to, 
                l.status_id, 
                l.details 
            FROM `document_transactions` AS t 
            INNER JOIN `document_transaction_logs` AS l ON t.id = l.document_transaction_id 
            WHERE t.id = ? 
            ORDER BY l.created_at DESC, l.id DESC 
            LIMIT 1";
    return find($sql, [$document_transaction_id]);
}

function documentLogs($document_transaction_id)
{
    $sql = "SELECT 
                id, 
                processor_id, 
                received_from, 
                forwarded_to, 
                status_id, 
                details, 
                created_at
            FROM `document_transaction_logs` 
            WHERE `document_transaction_id` = ? 
            ORDER BY `created_at` DESC, `id` DESC";
    $results = query($sql, [$document_transaction_id]);
    return is_array($results) ? $results : [];
}

function createDocumentLog($document_transaction_id, $processor_id, $received_from, $forwarded_to, $status_id, $is_new = 1, $details = '')
{
    $data = [
        'processor_id' => $processor_id,
        'received_from' => $received_from,
        'forwarded_to' => $forwarded_to,
        'status_id' => $status_id,
        'document_transaction_id' => $document_transaction_id,
        'is_new' => $is_new,
        'details' => $details
    ];
    return insert('document_transaction_logs', $data);
}

function updateDocumentLog($document_transaction_id, $processor_id, $received_from, $forwarded_to, $status_id, $is_new = 1, $details = '')
{
    $latest = find(
        "SELECT `id` FROM `document_transaction_logs` WHERE `document_transaction_id` = ? 
        ORDER BY `created_at` DESC, `id` DESC LIMIT 1",
        [$document_transaction_id]
    );
    if (!$latest) {
        return 0;
    }
    $log_id = $latest['id'];
    $data = [
        'processor_id' => $processor_id,
        'received_from' => $received_from,
        'forwarded_to' => $forwarded_to,
        'status_id' => $status_id,
        'is_new' => $is_new,
        'details' => $details
    ];
    return update('document_transaction_logs', $data, '`id` = ?', [$log_id]);
}

function updateDocumentLogsDone($document_transaction_id)
{
    $data = ['is_new' => 0];
    return update('document_transaction_logs', $data, "`document_transaction_id` = ? AND `is_new` = 1", [$document_transaction_id]);
}

function documentLogAttachments($transaction_log_id)
{
    return query(
        "SELECT `id`, `file_name`, `file_extension` FROM `document_transaction_log_attachments` WHERE `transaction_log_id` = ?",
        [$transaction_log_id]
    );
}

function createDocumentLogAttachment($transaction_log_id, $file_name, $file_extension)
{
    $data = [
        'transaction_log_id' => $transaction_log_id,
        'file_name' => $file_name,
        'file_extension' => $file_extension
    ];
    return insert('document_transaction_log_attachments', $data);
}

function deleteDocumentLogAttachment($attachment_id)
{
    $sql = "SELECT `file_name` FROM `document_transaction_log_attachments` WHERE `id` = ? LIMIT 1";
    $attachment = find($sql, [$attachment_id]);
    if ($attachment && file_exists(root() . '/' . $attachment['file_name'])) {
        unlink(root() . '/' . $attachment['file_name']);
    }
    return delete('document_transaction_log_attachments', '`id` = ?', [$attachment_id]);
}

function updateTransactionLogFrom($new_alias, $old_alias)
{
    $data = ['received_from' => $new_alias];
    if ($new_alias === $old_alias) {
        return 0;
    }
    return update(
        'document_transaction_logs',
        $data,
        '`received_from` = ?',
        [$old_alias]
    );
}

function updateTransactionLogTo($new_alias, $old_alias)
{
    $data = ['forwarded_to' => $new_alias];
    if ($new_alias === $old_alias) {
        return 0;
    }
    return update(
        'document_transaction_logs',
        $data,
        '`forwarded_to` = ?',
        [$old_alias]
    );
}

function updateTransactionFrom($new_alias, $old_alias)
{
    $data = ['created_from' => $new_alias];
    if ($new_alias === $old_alias) {
        return 0;
    }
    return update(
        'document_transactions',
        $data,
        '`created_from` = ?',
        [$old_alias]
    );
}

function documentByStatus($status, $employee_id, $station_id, $from_date = '', $to_date = '')
{
    $station_filter = "$station_id%";
    if (empty($from_date)) {
        $from_date = date('Y-m-d');
    }
    if (empty($to_date)) {
        $to_date = $from_date;
    }
    $sql = "SELECT COUNT(*) AS `count` FROM `system_logs` 
            WHERE `action` = ? AND `target_id` 
            LIKE ? AND `employee_id` = ? AND `created_at` >= ? AND `created_at` < DATE_ADD(?, INTERVAL 1 DAY)";
    $row = find($sql, [$status, $station_filter, $employee_id, $from_date, $to_date]);
    return $row ? (int) $row['count'] : 0;
}

function documentSearch($string)
{
    $likeTerm = "%{$string}%";
    $booleanSearchTerm = preg_replace('/-{2,}/', ' ', $string);
    $sql = "SELECT 
                t.`id`, 
                t.`description`, 
                t.`created_from`, 
                l.`status_id`, 
                t.`created_at`  
            FROM `document_transactions` AS t
            INNER JOIN `document_transaction_logs` AS l ON l.`id` = (
                SELECT MAX(`id`) 
                FROM `document_transaction_logs` 
                WHERE `document_transaction_id` = t.`id`
            )
            WHERE t.`id` LIKE ? OR MATCH(t.`description`) AGAINST (? IN BOOLEAN MODE)
            ORDER BY t.`created_at` DESC 
            LIMIT 1000";
    return query($sql, [$likeTerm, $booleanSearchTerm]);
}

function stationTransactionCounts($station_id)
{
    $sql = "SELECT 
        COUNT(DISTINCT CASE WHEN l.received_from IS NOT NULL AND l.forwarded_to = ? AND l.is_new = 1 THEN l.document_transaction_id END) AS incoming,
        COUNT(DISTINCT CASE WHEN l.received_from = ? AND l.forwarded_to IS NULL AND l.is_new = 1 THEN l.document_transaction_id END) AS pending,
        COUNT(DISTINCT CASE WHEN l.received_from = ? AND l.forwarded_to IS NOT NULL AND l.is_new = 1 THEN l.document_transaction_id END) AS outgoing,
        COUNT(DISTINCT CASE WHEN t.created_from = ? AND l.forwarded_to IS NOT NULL AND l.is_new = 1 THEN t.id END) AS ongoing
    FROM `document_transaction_logs` AS l
    LEFT JOIN `document_transactions` AS t 
        ON l.document_transaction_id = t.id
    WHERE l.status_id NOT IN (10, 11)";
    $result = find($sql, [$station_id, $station_id, $station_id, $station_id]);
    return [
        'incoming' => (int) ($result['incoming'] ?? 0),
        'pending' => (int) ($result['pending'] ?? 0),
        'outgoing' => (int) ($result['outgoing'] ?? 0),
        'ongoing' => (int) ($result['ongoing'] ?? 0)
    ];
}

function detailedStationTransactionCounts($station_id, $from_date = null, $to_date = null)
{
    $anchor_date = !empty($to_date) ? $to_date : date('Y-m-d H:i:s');
    execute("SET @anchor = ?", [$anchor_date]);

    $sql = "SELECT 
    COUNT(DISTINCT CASE WHEN l.received_from IS NOT NULL AND l.forwarded_to = ? AND l.is_new = 1 THEN l.document_transaction_id END) AS incoming,
    COUNT(DISTINCT CASE WHEN l.received_from = ? AND l.forwarded_to IS NULL AND l.is_new = 1 THEN l.document_transaction_id END) AS pending,
    COUNT(DISTINCT CASE WHEN l.received_from = ? AND l.forwarded_to IS NOT NULL AND l.is_new = 1 THEN l.document_transaction_id END) AS outgoing,
    COUNT(DISTINCT CASE WHEN t.created_from = ? AND l.forwarded_to IS NOT NULL AND l.is_new = 1 THEN t.id END) AS ongoing,
    COUNT(DISTINCT CASE WHEN l.received_from IS NOT NULL AND l.forwarded_to = ? AND l.is_new = 1 AND DATEDIFF(@anchor, l.created_at) BETWEEN 4 AND 6 THEN l.document_transaction_id END) AS inc_3,
    COUNT(DISTINCT CASE WHEN l.received_from IS NOT NULL AND l.forwarded_to = ? AND l.is_new = 1 AND DATEDIFF(@anchor, l.created_at) BETWEEN 7 AND 13 THEN l.document_transaction_id END) AS inc_7,
    COUNT(DISTINCT CASE WHEN l.received_from IS NOT NULL AND l.forwarded_to = ? AND l.is_new = 1 AND DATEDIFF(@anchor, l.created_at) BETWEEN 14 AND 29 THEN l.document_transaction_id END) AS inc_14,
    COUNT(DISTINCT CASE WHEN l.received_from IS NOT NULL AND l.forwarded_to = ? AND l.is_new = 1 AND DATEDIFF(@anchor, l.created_at) BETWEEN 30 AND 59 THEN l.document_transaction_id END) AS inc_30,
    COUNT(DISTINCT CASE WHEN l.received_from IS NOT NULL AND l.forwarded_to = ? AND l.is_new = 1 AND DATEDIFF(@anchor, l.created_at) >= 60 THEN l.document_transaction_id END) AS inc_60,
    COUNT(DISTINCT CASE WHEN l.received_from = ? AND l.forwarded_to IS NULL AND l.is_new = 1 AND DATEDIFF(@anchor, l.created_at) BETWEEN 4 AND 6 THEN l.document_transaction_id END) AS pen_3,
    COUNT(DISTINCT CASE WHEN l.received_from = ? AND l.forwarded_to IS NULL AND l.is_new = 1 AND DATEDIFF(@anchor, l.created_at) BETWEEN 7 AND 13 THEN l.document_transaction_id END) AS pen_7,
    COUNT(DISTINCT CASE WHEN l.received_from = ? AND l.forwarded_to IS NULL AND l.is_new = 1 AND DATEDIFF(@anchor, l.created_at) BETWEEN 14 AND 29 THEN l.document_transaction_id END) AS pen_14,
    COUNT(DISTINCT CASE WHEN l.received_from = ? AND l.forwarded_to IS NULL AND l.is_new = 1 AND DATEDIFF(@anchor, l.created_at) BETWEEN 30 AND 59 THEN l.document_transaction_id END) AS pen_30,
    COUNT(DISTINCT CASE WHEN l.received_from = ? AND l.forwarded_to IS NULL AND l.is_new = 1 AND DATEDIFF(@anchor, l.created_at) >= 60 THEN l.document_transaction_id END) AS pen_60,
    COUNT(DISTINCT CASE WHEN l.received_from = ? AND l.forwarded_to IS NOT NULL AND l.is_new = 1 AND DATEDIFF(@anchor, l.created_at) BETWEEN 4 AND 6 THEN l.document_transaction_id END) AS out_3,
    COUNT(DISTINCT CASE WHEN l.received_from = ? AND l.forwarded_to IS NOT NULL AND l.is_new = 1 AND DATEDIFF(@anchor, l.created_at) BETWEEN 7 AND 13 THEN l.document_transaction_id END) AS out_7,
    COUNT(DISTINCT CASE WHEN l.received_from = ? AND l.forwarded_to IS NOT NULL AND l.is_new = 1 AND DATEDIFF(@anchor, l.created_at) BETWEEN 14 AND 29 THEN l.document_transaction_id END) AS out_14,
    COUNT(DISTINCT CASE WHEN l.received_from = ? AND l.forwarded_to IS NOT NULL AND l.is_new = 1 AND DATEDIFF(@anchor, l.created_at) BETWEEN 30 AND 59 THEN l.document_transaction_id END) AS out_30,
    COUNT(DISTINCT CASE WHEN l.received_from = ? AND l.forwarded_to IS NOT NULL AND l.is_new = 1 AND DATEDIFF(@anchor, l.created_at) >= 60 THEN l.document_transaction_id END) AS out_60,
    COUNT(DISTINCT CASE WHEN t.created_from = ? AND l.forwarded_to IS NOT NULL AND l.is_new = 1 AND DATEDIFF(@anchor, t.created_at) BETWEEN 4 AND 6 THEN t.id END) AS ong_3,
    COUNT(DISTINCT CASE WHEN t.created_from = ? AND l.forwarded_to IS NOT NULL AND l.is_new = 1 AND DATEDIFF(@anchor, t.created_at) BETWEEN 7 AND 13 THEN t.id END) AS ong_7,
    COUNT(DISTINCT CASE WHEN t.created_from = ? AND l.forwarded_to IS NOT NULL AND l.is_new = 1 AND DATEDIFF(@anchor, t.created_at) BETWEEN 14 AND 29 THEN t.id END) AS ong_14,
    COUNT(DISTINCT CASE WHEN t.created_from = ? AND l.forwarded_to IS NOT NULL AND l.is_new = 1 AND DATEDIFF(@anchor, t.created_at) BETWEEN 30 AND 59 THEN t.id END) AS ong_30,
    COUNT(DISTINCT CASE WHEN t.created_from = ? AND l.forwarded_to IS NOT NULL AND l.is_new = 1 AND DATEDIFF(@anchor, t.created_at) >= 60 THEN t.id END) AS ong_60
    FROM `document_transaction_logs` AS l
    LEFT JOIN `document_transactions` AS t 
        ON l.document_transaction_id = t.id
    WHERE l.status_id NOT IN (10, 11)";

    $params = array_merge(
        array_fill(0, 4, $station_id),
        array_fill(0, 5, $station_id),
        array_fill(0, 5, $station_id),
        array_fill(0, 5, $station_id),
        array_fill(0, 5, $station_id)
    );

    if (!empty($from_date)) {
        $sql .= " AND l.created_at >= ?";
        $params[] = $from_date;
    }
    if (!empty($to_date)) {
        $sql .= " AND l.created_at <= ?";
        $params[] = $to_date;
    }

    $result = find($sql, $params);
    $output = [];
    foreach (['incoming', 'pending', 'outgoing', 'ongoing'] as $key) {
        $output[$key] = (int) ($result[$key] ?? 0);
    }

    $intervals = [3, 7, 14, 30, 60];
    foreach (['inc' => 'incoming_lapsed', 'pen' => 'pending_lapsed', 'out' => 'outgoing_lapsed', 'ong' => 'ongoing_lapsed'] as $prefix => $group) {
        $output[$group] = []; // Initialize array group cleanly
        foreach ($intervals as $day) {
            $output[$group][$day] = (int) ($result["{$prefix}_{$day}"] ?? 0);
        }
    }

    return $output;
}