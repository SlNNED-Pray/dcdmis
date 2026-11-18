<?php
/**
 * bsa/booking-edit.php (dcdmis shell content view)
 * Edit form opened from Booking Approvals -> Booking Schedules -> Edit.
 * Routes as ?v=Booking Edit.
 */
require_once __DIR__ . '/../includes/function.php';
require_once __DIR__ . '/includes/dtcsc-optional.php';
require_once __DIR__ . '/includes/vehicle-units.php';
require_once __DIR__ . '/includes/booking-edit-fields.php';
require_once __DIR__ . '/../includes/database/database.php';
require_once __DIR__ . '/../includes/database/account.php';
require_once __DIR__ . '/includes/bsa-access.php';

if (empty($GLOBALS['userId'] ?? null) || !canAccessBookingApprovals($GLOBALS['userId'])) {
    denyBsaApprovalAccess();
}

$listUrl = uri() . '/bsa/?v=' . encode('Booking Approvals');
$type = trim((string) ($_GET['type'] ?? ''));
$id = (int) ($_GET['id'] ?? 0);
$config = bsaBookingEditConfig($type);
$row = null;
$error = '';

if (!$config || $id <= 0) {
    $error = 'Invalid booking selection.';
} else {
    try {
        if ($config['db'] === 'dcdmis') {
            $targetDb = connection();
        } else {
            if (!$dtcscSiblingAvailable || !class_exists('Database')) {
                $error = 'The facility booking database is not available on this server.';
            } else {
                $targetDb = Database::getInstance()->getConnection();
            }
        }

        if (!$error) {
            $stmt = $targetDb->prepare("SELECT * FROM {$config['table']} WHERE {$config['key']} = ? LIMIT 1");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $error = 'Booking record not found.';
            }
        }
    } catch (Throwable $e) {
        $error = 'Unable to load the booking record.';
    }
}

contentTitle('Edit Booking');
?>
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex align-items-center justify-content-between">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fa fa-edit mr-2"></i><?php echo $config ? htmlspecialchars($config['title']) : 'Edit Booking'; ?>
        </h6>
        <a href="<?php echo $listUrl; ?>" class="btn btn-sm btn-outline-secondary">&larr; Back to Booking Approvals</a>
    </div>
    <div class="card-body">
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div>
        <?php else: ?>
            <form method="POST" action="<?php echo uri(); ?>/bsa/booking-edit-save.php">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="booking_type" value="<?php echo htmlspecialchars($type); ?>">
                <input type="hidden" name="booking_id" value="<?php echo (int) $id; ?>">

                <div class="row">
                    <?php foreach ($config['fields'] as $name => $def): ?>
                        <?php
                        $value = (string) ($row[$name] ?? '');
                        $fieldId = 'edit_' . $name;
                        $isReadonly = !empty($def['readonly']);
                        $isConditional = !empty($def['conditional']);
                        ?>
                        <div class="col-md-6 mb-3" <?php echo $isConditional ? 'id="editVehicleSubtypeRow" style="display:' . ($value !== '' ? 'block' : 'none') . ';"' : ''; ?>>
                            <label for="<?php echo $fieldId; ?>" class="form-label font-weight-bold small"><?php echo htmlspecialchars($def['label']); ?></label>

                            <?php if ($isReadonly): ?>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($value); ?>" readonly>
                            <?php elseif ($def['type'] === 'textarea'): ?>
                                <textarea class="form-control" id="<?php echo $fieldId; ?>" name="<?php echo $name; ?>" rows="3"><?php echo htmlspecialchars($value); ?></textarea>
                            <?php elseif ($def['type'] === 'select'): ?>
                                <select class="form-control" id="<?php echo $fieldId; ?>" name="<?php echo $name; ?>">
                                    <?php if (!empty($def['null_option'])): ?>
                                        <option value=""><?php echo htmlspecialchars($def['null_option']); ?></option>
                                    <?php endif; ?>
                                    <?php foreach ($def['options'] as $option): ?>
                                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $value === $option ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $option))); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="<?php echo htmlspecialchars($def['type']); ?>" class="form-control"
                                       id="<?php echo $fieldId; ?>" name="<?php echo $name; ?>"
                                       value="<?php echo htmlspecialchars($value); ?>"
                                       <?php echo isset($def['min']) ? 'min="' . (int) $def['min'] . '"' : ''; ?>>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <hr>
                <div class="d-flex justify-content-between">
                    <a href="<?php echo $listUrl; ?>" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-save mr-2"></i>Save Changes</button>
                </div>
            </form>

            <script>
                (function () {
                    var typeSelect = document.getElementById('edit_vehicle_type');
                    var subtypeRow = document.getElementById('editVehicleSubtypeRow');
                    var subtypeSelect = document.getElementById('edit_vehicle_subtype');
                    if (!typeSelect || !subtypeRow || !subtypeSelect) {
                        return;
                    }
                    function syncSubtype() {
                        var isPickup = typeSelect.value === 'Pickup Truck';
                        subtypeRow.style.display = isPickup ? 'block' : 'none';
                        if (!isPickup) {
                            subtypeSelect.value = '';
                        }
                    }
                    typeSelect.addEventListener('change', syncSubtype);
                    syncSubtype();
                }());
            </script>
        <?php endif; ?>
    </div>
</div>
