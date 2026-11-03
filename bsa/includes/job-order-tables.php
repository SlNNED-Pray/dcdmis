<?php
// bsa/includes/job-order-tables.php — ensure Job Order/Request tables exist.
if (function_exists('ensureJobOrderTables')) {
    return;
}

$GLOBALS['__jobOrderTablesEnsured'] = false;

/**
 * Original request columns of the Job Order Request form.
 * Kept separate from the tracking columns below. Idempotent; safe to call on every request.
 */
function jobOrderRequestColumns(): array
{
    return [
        // Present on the official DepEd Region IX Annex 8 "Job Order/Request Form"
        // print sheet, so it is collected with the rest of the request details.
        'requesting_office'     => 'varchar(255) NULL',
    ];
}

/**
 * Tracking/progress columns added to the Job Order Request form.
 * Column => SQL definition. Idempotent; safe to call on every request.
 */
function jobOrderTrackingColumns(): array
{
    return [
        'actions_taken'          => 'text NULL',
        'recommendation'         => 'text NULL',
        'assigned_personnel'     => 'varchar(255) NULL',
        'prepared_by'            => 'varchar(255) NULL',
        'date_time_started'      => 'datetime NULL',
        'date_time_completed'    => 'datetime NULL',
        'tracking_remarks'       => "varchar(20) NOT NULL DEFAULT 'work_in_progress'",
        'job_proponent_name'     => 'varchar(255) NULL',
        'job_proponent_signature' => 'varchar(255) NULL',
        'noted_by'               => 'varchar(255) NULL',
        'noted_at'               => 'datetime NULL',
        'tracking_updated_by'    => 'varchar(255) NULL',
        'tracking_updated_at'    => 'datetime NULL',
    ];
}

function ensureJobOrderColumn($db, string $column, string $definition): void
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'bsa_job_order_requests' AND column_name = ?");
    $stmt->execute([$column]);
    if ((int) $stmt->fetchColumn() === 0) {
        $db->exec("ALTER TABLE `bsa_job_order_requests` ADD COLUMN `$column` $definition");
    }
}

function ensureJobOrderTables(): void
{
    if (!empty($GLOBALS['__jobOrderTablesEnsured'])) {
        return;
    }

    $db = connection();
    $db->exec("CREATE TABLE IF NOT EXISTS `bsa_job_order_requests` (
      `id` int unsigned NOT NULL AUTO_INCREMENT,
      `order_no` varchar(50) NOT NULL,
      `date_request` date NOT NULL,
      `requesting_office` varchar(255) NULL,
      `requesting_personnel` varchar(255) NOT NULL,
      `location_of_work` varchar(255) NOT NULL,
      `description_of_work` varchar(255) NOT NULL,
      `other_scope` text NULL,
      `requestor_name` varchar(255) NOT NULL,
    `requestor_signature` varchar(255) NULL,
      `status` varchar(20) NOT NULL DEFAULT 'pending',
      `remarks` text NULL,
      `decided_by` varchar(255) NULL,
      `decided_at` datetime NULL,
      `created_by` varchar(255) NULL,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_job_order_no` (`order_no`),
      KEY `idx_job_order_status` (`status`),
      KEY `idx_job_order_date` (`date_request`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("ALTER TABLE `bsa_job_order_requests` MODIFY COLUMN `requestor_signature` varchar(255) NULL");

    foreach (jobOrderRequestColumns() as $column => $definition) {
        ensureJobOrderColumn($db, $column, $definition);
    }

    foreach (jobOrderTrackingColumns() as $column => $definition) {
        ensureJobOrderColumn($db, $column, $definition);
    }

    $db->exec("CREATE TABLE IF NOT EXISTS `bsa_settings` (
      `setting_key` varchar(100) NOT NULL,
      `setting_value` varchar(255) NULL,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $GLOBALS['__jobOrderTablesEnsured'] = true;
}

/**
 * Tracking/progress columns for the Job Order Request form.
 * Populated after the request is approved; stays "Work in progress" until marked Completed.
 */
function jobOrderTrackingRemarks(): array
{
    return ['work_in_progress', 'backlog', 'on_hold', 'completed'];
}

function jobOrderTrackingRemarksLabel(string $value): string
{
    switch ($value) {
        case 'work_in_progress': return 'Work in progress';
        case 'backlog':          return 'Backlog';
        case 'on_hold':          return 'On Hold';
        case 'completed':        return 'Completed';
        default:                 return 'Work in progress';
    }
}

/**
 * Read a BSA setting (e.g. the GSS Focal employee id). Returns '' when unset.
 */
function bsaSetting(string $key, string $default = ''): string
{
    try {
        $row = find('SELECT `setting_value` FROM `bsa_settings` WHERE `setting_key` = ?', [$key]);
    } catch (Throwable $e) {
        return $default;
    }
    $value = trim((string) ($row['setting_value'] ?? ''));
    return $value !== '' ? $value : $default;
}

function setBsaSetting(string $key, string $value): bool
{
    ensureJobOrderTables();
    return query(
        'INSERT INTO `bsa_settings` (`setting_key`, `setting_value`) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)',
        [$key, $value]
    ) !== false;
}

/**
 * Resolve the configured GSS Focal's display name from the employees table.
 * Returns ['id' => ..., 'name' => ...] or empty values when not configured.
 */
function bsaGssFocal(): array
{
    $id = bsaSetting('gss_focal_employee_id');
    if ($id === '' || !ctype_digit($id)) {
        return ['id' => '', 'name' => ''];
    }
    $emp = find(
        'SELECT `id`, `first_name`, `middle_name`, `last_name` FROM dcdmis.employees WHERE `id` = ?',
        [(int) $id]
    );
    if (!$emp) {
        return ['id' => $id, 'name' => ''];
    }
    $first = trim((string) ($emp['first_name'] ?? ''));
    $middle = trim((string) ($emp['middle_name'] ?? ''));
    $last = trim((string) ($emp['last_name'] ?? ''));
    $name = trim($first . ($middle !== '' ? ' ' . substr($middle, 0, 1) . '.' : '') . ' ' . $last);
    return ['id' => (string) $emp['id'], 'name' => strtoupper($name)];
}
