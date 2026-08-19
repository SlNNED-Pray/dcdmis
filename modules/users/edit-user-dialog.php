<?php
// modules/users/edit-user-dialog.php
require_once('../../includes/function.php');
require_once(root() . '/includes/database/database.php');
require_once(root() . '/includes/database/employee.php');
require_once(root() . '/includes/database/position.php');
require_once(root() . '/includes/database/section.php');
require_once(root() . '/includes/database/account.php');
require_once(root() . '/includes/database/school.php');
require_once(root() . '/includes/layout/components.php');
require_once(root() . '/includes/string.php');

$employeeId = isset($_GET['id']) ? sanitize(decipher($_GET['id'])) : null;
$employee = employee((int) $employeeId);
$dtsUser = $hrmisUser = $dmisUser = $hrtdmsUser = $imsUser = $dtrUser = $raceUser = $dtsDivisionUser = false;
$stationId = $depedEmail = $dtsUserStation = '';
$modalTitle = 'User not found';
$hasUser = false;
$notFound = true;

if ($employee) {
    $employeeId = $employee['id'];
    $employeeName = toName($employee['last_name'], $employee['first_name'], $employee['middle_name'], $employee['name_extension'], true);
    $sex = $employee['sex'];
    $status = $employee['status'];
    $position = position($employeeId);
    $userStationId = $position['station_id'];
    $station = $position['station'];
    $positionId = $position['position_id'];
    $position = $position['official_title'];
    $depedEmail = $employee['email_address'];
    $picture = file_exists(root() . '/' . $employee['profile_picture']) ? uri() . '/' . $employee['profile_picture'] : uri() . '/assets/img/user.png';
    $modalTitle = 'Edit User';
    $hasUser = true;
    $dts = dtsUser($employeeId);
    $dtsDivisionUser = $userStationId === divisionId();

    if ($dts) {
        $dtsUser = true;
        $dtsUserStation = $dts['access'];
    }

    $hrmisUser = (bool) userRole($employeeId, 'hrmis');
    $dmisUser = (bool) userRole($employeeId, 'dmis');
    $hrtdmsUser = (bool) userRole($employeeId, 'hrtdms');
    $raceUser = (bool) userRole($employeeId, 'race');
    $imsUser = (bool) userRole($employeeId, 'ims');
    $dtrUser = (bool) userRole($employeeId, 'dtr');
}
?>

<div class="modal-dialog <?= !$hasUser ? 'modal-sm' : '' ?>">
    <div class="modal-content">
        <?php modalHeader($modalTitle) ?>

        <form action="" method="POST">
            <?= csrf_field(); ?>
            <div class="modal-body">
                <?php if ($hasUser) {
                    employeeProfile($picture, $employeeName, $sex, $depedEmail, $position, $station, $status) ?>

                    <hr>

                    <div class="text-center text-capitalize h5 px-2 mb-3">User Assignment</div>

                    <div class="form-group mb-0">
                        <div class="form-check">
                            <input class="form-check-input" id="dts" type="checkbox" name="dts" <?= setActiveItem($dtsUser, true, 'checked') ?>>
                            <label class="form-check-label" for="dts">Document Tracking System</label>
                        </div>
                    </div>

                    <?php if ($dtsDivisionUser): ?>
                        <div class="form-group ml-1 pl-3 mb-0">
                            <select name="dts-verifier" class="form-control">
                                <option value="">Select section...</option>
                                <?php
                                $divisions = functionalDivisions();
                                foreach ($divisions as $division): ?>
                                    <optgroup label="<?= e($division['name']) ?>">
                                        <?php
                                        $sections = sections($division['id']);
                                        foreach ($sections as $section) {
                                            if ($section['id'] !== $station) { ?>
                                                <option value="<?= e($section['id']) ?>" <?= setOptionSelected($section['id'], $dtsUserStation) ?>><?= e($section['name']) ?></option>
                                                <?php
                                            }
                                        } ?>
                                    </optgroup>
                                <?php endforeach ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="dts-verifier" value="<?= e($userStationId) ?>">
                    <?php endif ?>

                    <div class="form-group mt-2 mb-0">
                        <div class="form-check">
                            <input class="form-check-input" id="hrmis" type="checkbox" name="hrmis"
                                <?= setActiveItem($hrmisUser, true, 'checked') ?>>
                            <label class="form-check-label" for="hrmis">Human Resource Management Information System</label>
                        </div>
                    </div>

                    <div class="form-group mt-2 mb-0">
                        <div class="form-check">
                            <input class="form-check-input" id="hrtdms" type="checkbox" name="hrtdms"
                                <?= setActiveItem($hrtdmsUser, true, 'checked') ?>>
                            <label class="form-check-label" for="hrtdms">HR Training and Development Management
                                System</label>
                        </div>
                    </div>

                    <div class="form-group mt-2 mb-0">
                        <div class="form-check">
                            <input class="form-check-input" id="race" type="checkbox" name="race"
                                <?= setActiveItem($raceUser, true, 'checked') ?>>
                            <label class="form-check-label" for="race">Rewards and Recognition Management System</label>
                        </div>
                    </div>

                    <div class="form-group mt-2 mb-0">
                        <div class="form-check">
                            <input class="form-check-input" id="dtr" type="checkbox" name="dtr"
                                <?= setActiveItem($dtrUser, true, 'checked') ?>>
                            <label class="form-check-label" for="dtr">Daily Time Record</label>
                        </div>
                    </div>

                    <div class="form-group mt-2 mb-0">
                        <div class="form-check">
                            <input class="form-check-input" id="ims" type="checkbox" name="ims"
                                <?= setActiveItem($imsUser, true, 'checked') ?>>
                            <label class="form-check-label" for="ims">Inventory Management System</label>
                        </div>
                    </div>

                    <div class="form-group mt-2 mb-0">
                        <div class="form-check">
                            <input class="form-check-input" id="dmis" type="checkbox" name="dmis"
                                <?= setActiveItem($dmisUser, true, 'checked') ?>>
                            <label class="form-check-label" for="dmis">Division Management Information System</label>
                        </div>
                    </div>
                <?php } else {
                    missingAlert($modalTitle);
                } ?>
            </div>

            <div class="modal-footer">
                <?php if ($hasUser): ?>
                    <input type="hidden" name="verifier" value="<?= e($_GET['id'] ?? '') ?>">
                    <input type="hidden" name="data-verifier" value="<?= cipher($depedEmail) ?>">
                    <button class="btn btn-primary" name="edit-user" type="submit">Continue</button>
                <?php endif ?>
                <?php cancelModalButton() ?>
            </div>
        </form>
    </div>
</div>