<?php
/**
 * bsa/booking-edit-save.php — save changes made in the Booking Approvals Edit form.
 * POST handler, redirects back to Booking Approvals.
 */
require_once __DIR__ . '/../includes/function.php';
require_once __DIR__ . '/includes/dtcsc-optional.php';
require_once __DIR__ . '/includes/booking-edit-fields.php';
require_once __DIR__ . '/../includes/database/database.php';
require_once __DIR__ . '/../includes/database/account.php';
require_once __DIR__ . '/includes/bsa-access.php';

if (empty($GLOBALS['userId'] ?? null) || !canAccessBookingApprovals($GLOBALS['userId'])) {
    denyBsaApprovalAccess();
}

verify_csrf_token();

$listUrl = uri() . '/bsa/?v=' . encode('Booking Approvals');
$type = trim((string) ($_POST['booking_type'] ?? ''));
$id = (int) ($_POST['booking_id'] ?? 0);
$config = bsaBookingEditConfig($type);

function bsaEditFail(string $message, string $listUrl): void
{
    $_SESSION['bsa_edit_flash'] = ['type' => 'danger', 'message' => $message];
    header('Location: ' . $listUrl);
    exit;
}

if (!$config || $id <= 0) {
    bsaEditFail('Invalid booking selection.', $listUrl);
}

try {
    if ($config['db'] === 'dcdmis') {
        $targetDb = connection();
    } else {
        if (!$dtcscSiblingAvailable || !class_exists('Database')) {
            bsaEditFail('The facility booking database is not available on this server.', $listUrl);
        }
        $targetDb = Database::getInstance()->getConnection();
    }

    $stmt = $targetDb->prepare("SELECT * FROM {$config['table']} WHERE {$config['key']} = ? LIMIT 1");
    $stmt->execute([$id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        bsaEditFail('Booking record not found.', $listUrl);
    }

    $data = [];
    foreach ($config['fields'] as $name => $def) {
        if (!empty($def['readonly'])) {
            continue;
        }

        $raw = trim((string) ($_POST[$name] ?? ''));

        if ($def['type'] === 'select') {
            if ($raw !== '' && !in_array($raw, $def['options'], true)) {
                bsaEditFail('Invalid value for ' . $def['label'] . '.', $listUrl);
            }
            if ($raw === '' && empty($def['null_option'])) {
                bsaEditFail($def['label'] . ' is required.', $listUrl);
            }
            // Empty optional selects leave the stored value untouched, except vehicle_subtype.
            if ($raw === '' && $name !== 'vehicle_subtype') {
                continue;
            }
            if ($name === 'vehicle_subtype' && $raw !== '') {
                $raw = strtoupper($raw);
            }
        } elseif ($def['type'] === 'number') {
            $min = isset($def['min']) ? (int) $def['min'] : 0;
            $raw = (string) max($min, (int) $raw);
        } elseif ($def['type'] === 'date') {
            if ($raw !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
                bsaEditFail('Invalid date for ' . $def['label'] . '.', $listUrl);
            }
        } elseif ($def['type'] === 'time') {
            if ($raw !== '' && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $raw)) {
                bsaEditFail('Invalid time for ' . $def['label'] . '.', $listUrl);
            }
        } else {
            $max = isset($def['max']) ? (int) $def['max'] : 65535;
            $raw = mb_substr($raw, 0, $max);
        }

        $data[$name] = $raw;
    }

    // Pickup Truck units apply only to the Pickup Truck vehicle type.
    if (isset($config['fields']['vehicle_type'])) {
        $vehicleType = $data['vehicle_type'] ?? (string) ($existing['vehicle_type'] ?? '');
        if ($vehicleType === 'Pickup Truck') {
            $subtype = $data['vehicle_subtype'] ?? (string) ($existing['vehicle_subtype'] ?? '');
            if (!in_array(strtoupper($subtype), ['FOTON', 'STRADA'], true)) {
                bsaEditFail('Please select a Pickup Truck Unit (FOTON or STRADA).', $listUrl);
            }
            $data['vehicle_subtype'] = strtoupper($subtype);
        } else {
            $data['vehicle_subtype'] = null;
        }
    }

    // Editing a vehicle booking must not create a double-booking.
    if ($type === 'vehicle' && in_array($data['status'] ?? ($existing['status'] ?? ''), ['pending', 'available'], true)) {
        $departDate = $data['depart_date'] ?? (string) ($existing['depart_date'] ?? '');
        $departTime = $data['depart_time'] ?? (string) ($existing['depart_time'] ?? '');
        $returnDate = $data['return_date'] ?? (string) ($existing['return_date'] ?? '');
        $returnTime = $data['return_time'] ?? (string) ($existing['return_time'] ?? '');
        $endDate = $returnDate !== '' ? $returnDate : $departDate;
        $endTime = $returnTime !== '' ? $returnTime : '23:59';

        $conflictStmt = $targetDb->prepare("
            SELECT control_no, status
            FROM vehicle_requests
            WHERE status IN ('pending', 'available')
              AND id <> ?
              AND vehicle_type = ?
              AND COALESCE(vehicle_subtype, '') = ?
              AND CONCAT(depart_date, ' ', depart_time) < CONCAT(?, ' ', ?)
              AND CONCAT(COALESCE(return_date, depart_date), ' ', COALESCE(return_time, '23:59')) > CONCAT(?, ' ', ?)
            LIMIT 1
        ");
        $conflictStmt->execute([
            $id,
            $data['vehicle_type'] ?? (string) ($existing['vehicle_type'] ?? ''),
            $data['vehicle_subtype'] ?? (string) ($existing['vehicle_subtype'] ?? ''),
            $endDate, $endTime,
            $departDate, $departTime,
        ]);
        if ($conflictStmt->fetch()) {
            bsaEditFail('Vehicle is not available for that schedule. Select another Vehicle Type or Unit.', $listUrl);
        }
    }

    if (!$data) {
        bsaEditFail('No changes were submitted.', $listUrl);
    }

    $sets = [];
    $params = [];
    foreach ($data as $name => $value) {
        $sets[] = "`{$name}` = ?";
        $params[] = $value;
    }
    $params[] = $id;

    $update = $targetDb->prepare("UPDATE {$config['table']} SET " . implode(', ', $sets) . " WHERE {$config['key']} = ?");
    $update->execute($params);

    $_SESSION['bsa_edit_flash'] = [
        'type' => 'success',
        'message' => $config['title'] . ' updated successfully.',
    ];
    header('Location: ' . $listUrl);
    exit;
} catch (Throwable $e) {
    bsaEditFail('An error occurred while saving the booking.', $listUrl);
}
