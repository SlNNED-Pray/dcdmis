<?php
// bsa/includes/dtc-booking-tables.php — ensure the standalone facility-booking tables exist.
// Idempotent: may be called from the embed page and the save endpoint.
if (function_exists('ensureDtcBookingTables')) {
    return;
}

$GLOBALS['__dtcBookingTablesEnsured'] = false;

function ensureDtcBookingColumn($db, string $column, string $definition): void
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'bsa_facility_bookings' AND column_name = ?");
    $stmt->execute([$column]);
    if ((int) $stmt->fetchColumn() === 0) {
        $db->exec("ALTER TABLE `bsa_facility_bookings` ADD COLUMN `$column` $definition");
    }
}

function ensureDtcBookingIndex($db, string $indexName, string $column): void
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'bsa_facility_bookings' AND index_name = ?");
    $stmt->execute([$indexName]);
    if ((int) $stmt->fetchColumn() === 0) {
        $db->exec("ALTER TABLE `bsa_facility_bookings` ADD UNIQUE KEY `$indexName` (`$column`)");
    }
}

function ensureDtcBookingTables(): void
{
    if (!empty($GLOBALS['__dtcBookingTablesEnsured'])) {
        return;
    }
    $db = connection();

    $db->exec("CREATE TABLE IF NOT EXISTS `bsa_facilities` (
      `id` int unsigned NOT NULL AUTO_INCREMENT,
      `facility_name` varchar(150) NOT NULL,
      `venue_name` varchar(50) NULL,
      `sort_order` int NOT NULL DEFAULT 0,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_facility_name` (`facility_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS `bsa_facility_bookings` (
      `id` int unsigned NOT NULL AUTO_INCREMENT,
      `booking_reference` varchar(50) NOT NULL,
      `date_of_request` date NULL,
      `requesting_office` varchar(255) NULL,
      `contact_person` varchar(255) NULL,
      `contact_email` varchar(255) NULL,
      `contact_phone` varchar(50) NULL,
      `activity_title` varchar(255) NOT NULL,
      `activity_type` varchar(50) NULL,
      `activity_level` varchar(50) NULL,
      `venue_option` varchar(80) NULL,
      `start_date` date NOT NULL,
      `end_date` date NULL,
      `start_time` time NOT NULL,
      `end_time` time NULL,
      `participant_count` int unsigned NULL,
      `equipment_needed` varchar(255) NULL,
      `no_of_microphones` int unsigned NULL DEFAULT 0,
      `external_catering` varchar(5) NULL,
      `external_equipment` varchar(5) NULL,
      `external_equipment_details` text NULL,
      `special_requests` text NULL,
      `requested_by_name` varchar(255) NULL,
      `requested_by_position` varchar(255) NULL,
      `requested_by_signature` varchar(255) NULL,
      `concurred_by_name` varchar(255) NULL,
      `concurred_by_position` varchar(255) NULL,
      `concurred_by_signature` varchar(255) NULL,
      `status` varchar(20) NOT NULL DEFAULT 'pending',
      `remarks` text NULL,
      `decided_by` varchar(255) NULL,
      `decided_at` datetime NULL,
      `created_by` varchar(255) NULL,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_bf_start` (`start_date`),
      KEY `idx_bf_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    ensureDtcBookingColumn($db, 'date_of_request', 'date NULL');
    ensureDtcBookingColumn($db, 'activity_level', "varchar(50) NULL");
    ensureDtcBookingColumn($db, 'venue_option', "varchar(80) NULL");
    ensureDtcBookingColumn($db, 'equipment_needed', 'varchar(255) NULL');
    ensureDtcBookingColumn($db, 'no_of_microphones', 'int unsigned NULL DEFAULT 0');
    ensureDtcBookingColumn($db, 'external_catering', "varchar(5) NULL");
    ensureDtcBookingColumn($db, 'external_equipment', "varchar(5) NULL");
    ensureDtcBookingColumn($db, 'external_equipment_details', 'text NULL');
    ensureDtcBookingColumn($db, 'requested_by_name', 'varchar(255) NULL');
    ensureDtcBookingColumn($db, 'requested_by_position', 'varchar(255) NULL');
    ensureDtcBookingColumn($db, 'requested_by_signature', 'varchar(255) NULL');
    ensureDtcBookingColumn($db, 'concurred_by_name', 'varchar(255) NULL');
    ensureDtcBookingColumn($db, 'concurred_by_position', 'varchar(255) NULL');
    ensureDtcBookingColumn($db, 'concurred_by_signature', 'varchar(255) NULL');
    ensureDtcBookingIndex($db, 'uq_bf_booking_reference', 'booking_reference');

    $count = $db->query("SELECT COUNT(*) FROM bsa_facilities")->fetchColumn();
    if ((int) $count === 0) {
        $seed = [
            ['Multi-Purpose Hall', 'DTC', 1],
            ['Training Room 1', 'DTC', 2],
            ['Training Room 2', 'DTC', 3],
            ['Conference Room', 'SDO', 4],
            ['Covered Court', 'SC', 5],
        ];
        $seedStmt = $db->prepare("INSERT INTO bsa_facilities (facility_name, venue_name, sort_order) VALUES (?, ?, ?)");
        foreach ($seed as $row) {
            $seedStmt->execute($row);
        }
    }

    $GLOBALS['__dtcBookingTablesEnsured'] = true;
}