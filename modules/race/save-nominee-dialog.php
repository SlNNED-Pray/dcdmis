<?php
// modules/race/save-nominee-dialog.php
require_once('access-check.php');
require_once(root() . '/includes/database/employee.php');
require_once(root() . '/includes/database/school.php');
require_once(root() . '/includes/layout/components.php');
require_once(root() . '/includes/string.php');

$employeeId = isset($_GET['e']) ? sanitize(decipher($_GET['e'])) : null;
$awardIdDecrypted = isset($_GET['award_id']) ? sanitize(decipher($_GET['award_id'])) : null;

$categoryId = null;
$awardId = null;
$awardName = '';
$categoryName = '';
$filterCategory = null;
$isProgramImplementation = false;
$isSchoolAward = false;

if ($awardIdDecrypted) {
    $award = recognitionAward($awardIdDecrypted);
    if ($award) {
        $awardId = $award['id'];
        $categoryId = $award['category_id'];
        $awardName = $award['name'];

        $category = recognitionCategory($categoryId);
        if ($category) {
            $categoryName = $category['name'];
            $catMap = [
                'Teaching Personnel' => 'Teaching',
                'School Administration Personnel (Individual)' => 'School Administration',
                'Related-Teaching Personnel (Individual)' => 'Related-Teaching',
                'Non-Teaching Personnel (Individual)' => 'Non-Teaching',
            ];
            $filterCategory = $catMap[$categoryName] ?? null;
        }

        // Program Implementation: nominee can be either School or Employee
        if ($categoryName === 'Program Implementation') {
            $isProgramImplementation = true;
        }

        // Supervisor awards: only EPS and PSDS positions
        $isSupervisorAward = stripos($awardName, 'supervisor') !== false;
        if ($isSupervisorAward) {
            $filterCategory = 'Supervisor';
        }

        // School Award (Institution): always school
        $lowerAwardName = strtolower($awardName);
        if (
            strpos($lowerAwardName, 'medium school') !== false ||
            strpos($lowerAwardName, 'small school') !== false ||
            strpos($lowerAwardName, 'large school') !== false
        ) {
            $isSchoolAward = true;
        }
    }
}

// For Program Implementation, determine selected nominee type from GET param
$nomineeType = isset($_GET['nominee_type']) ? sanitize($_GET['nominee_type']) : '';
if ($isProgramImplementation && empty($nomineeType)) {
    $nomineeType = 'school';
}
$isSchoolNominee = ($isSchoolAward || ($isProgramImplementation && $nomineeType === 'school'));
$isEmployeeNominee = ($isProgramImplementation && $nomineeType === 'employee');

$modalTitle = 'Add Nominee';
if ($awardName) {
    $modalTitle = 'Add Nominee for ' . $awardName;
}

// Build URL for nominee type switch (preserving existing params)
$baseUrl = uri() . '/modules/race/save-nominee-dialog.php';
$typeSwitchParams = $_GET;
?>

<div class="modal-dialog modal-lg">
    <div class="modal-content">
        <?php modalHeader($modalTitle); ?>

        <form action="" method="POST">
            <?= csrf_field(); ?>
            <div class="modal-body">
                <input type="hidden" name="category" value="<?= e($categoryId) ?>">
                <input type="hidden" name="award" value="<?= e($awardId) ?>">
                <?php if ($isProgramImplementation): ?>
                    <input type="hidden" name="nominee-type" value="<?= $isSchoolNominee ? 'school' : 'employee' ?>">
                <?php endif; ?>

                <div class="form-group mb-3">
                    <label class="mb-0 text-gray-800">Award Details</label>
                    <div class="font-weight-bold text-uppercase text-xs mt-1">
                        <span class="text-primary"><?= e($categoryName) ?></span> &raquo; <span
                            class="text-danger"><?= e($awardName) ?></span>
                    </div>
                </div>

                <?php if ($isProgramImplementation): ?>
                    <div class="form-group mb-3">
                        <label class="mb-0">Nominee Type <?php showAsterisk() ?></label>
                        <div class="btn-group btn-group-toggle d-flex">
                            <a href="#"
                                onclick="loadData('<?= $baseUrl ?>?e=<?= urlencode($_GET['e'] ?? '') ?>&award_id=<?= urlencode($_GET['award_id'] ?? '') ?>&nominee_type=school'); return false;"
                                class="btn btn-sm <?= $isSchoolNominee ? 'btn-primary' : 'btn-outline-primary' ?>">
                                <i class="fas fa-school fa-fw"></i> School
                            </a>
                            <a href="#"
                                onclick="loadData('<?= $baseUrl ?>?e=<?= urlencode($_GET['e'] ?? '') ?>&award_id=<?= urlencode($_GET['award_id'] ?? '') ?>&nominee_type=employee'); return false;"
                                class="btn btn-sm <?= $isEmployeeNominee ? 'btn-primary' : 'btn-outline-primary' ?>">
                                <i class="fas fa-user fa-fw"></i> Employee
                            </a>
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="is-sdo" name="is_sdo" value="1"
                                onchange="
                                var lg = document.getElementById('level-group');
                                if (lg) {
                                    lg.style.display = this.checked ? 'none' : '';
                                    var ls = lg.querySelector('select');
                                    if (ls) {
                                        ls.required = !this.checked;
                                        if (this.checked) ls.value = '';
                                    }
                                }
                            ">
                            <label class="custom-control-label" for="is-sdo">This nominee belongs to the SDO (Schools
                                Division Office)</label>
                            <small class="text-muted d-block mt-1">If checked, no level selection is required.</small>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($isSchoolNominee):
                    $isPrincipalUser = isPrincipal($userId);
                    $mySchool = $isPrincipalUser ? schoolByHead($userId) : null;
                    $isDistSupervisor = isDistrictSupervisor($userId);
                    $myDistrict = $isDistSupervisor ? districtBySupervisor($userId) : null;
                    ?>
                    <div class="form-group" id="school-group">
                        <label for="employee-id" class="mb-0">School <?php showAsterisk() ?></label>
                        <?php if ($isPrincipalUser): ?>
                            <small class="text-info d-block mb-1"><i class="fas fa-info-circle"></i> You can only nominate your
                                own school.</small>
                        <?php elseif ($isDistSupervisor && $myDistrict): ?>
                            <small class="text-info d-block mb-1"><i class="fas fa-info-circle"></i> You can only nominate
                                schools in your district (<?= e($myDistrict['name']) ?>).</small>
                        <?php endif; ?>
                        <select id="employee-id" name="employee-id" class="form-control" title="Select school..." required>
                            <option value="">Select School...</option>
                            <?php
                            if ($isPrincipalUser && $mySchool): ?>
                                <option value="<?= e($mySchool['id']) ?>" selected>
                                    <?= e($mySchool['name']) ?> &mdash; (<?= e($mySchool['alias'] ?: 'No Alias') ?>)
                                </option>
                            <?php elseif ($isDistSupervisor && $myDistrict):
                                $districtSchools = districtSchools($myDistrict['id']);
                                foreach ($districtSchools as $sch): ?>
                                    <option value="<?= e($sch['id']) ?>">
                                        <?= e($sch['name']) ?> &mdash; (<?= e($sch['alias'] ?: 'No Alias') ?>)
                                    </option>
                                <?php endforeach;
                            else:
                                $allSchools = schools();
                                foreach ($allSchools as $sch): ?>
                                    <option value="<?= e($sch['id']) ?>">
                                        <?= e($sch['name']) ?> &mdash; (<?= e($sch['alias'] ?: 'No Alias') ?>)
                                    </option>
                                <?php endforeach;
                            endif; ?>
                        </select>
                    </div>
                <?php else:
                    $isPrincipalUser = isPrincipal($userId);
                    $mySchool = $isPrincipalUser ? schoolByHead($userId) : null;
                    $isDistSupervisor = isDistrictSupervisor($userId);
                    $myDistrict = $isDistSupervisor ? districtBySupervisor($userId) : null;
                    if ($filterCategory === 'School Administration' || $filterCategory === 'Supervisor') {
                        $employees = [];
                    } elseif ($isPrincipalUser && $mySchool) {
                        $employees = activeEmployeesWithPosition($mySchool['id'], $filterCategory);
                    } elseif ($isDistSupervisor && $myDistrict) {
                        $employees = activeEmployeesInDistrict($myDistrict['id'], $filterCategory);
                    } else {
                        $employees = activeEmployeesWithPosition(null, $filterCategory);
                    }

                    $includePrincipals = stripos($awardName, 'ulirang guro') !== false;
                    if ($includePrincipals) {
                        if ($isPrincipalUser && $mySchool) {
                            $principals = activePrincipalEmployees($mySchool['id']);
                        } elseif ($isDistSupervisor && $myDistrict) {
                            $principals = activePrincipalEmployees(null, $myDistrict['id']);
                        } else {
                            $principals = activePrincipalEmployees();
                        }

                        $combined = [];
                        foreach ($principals as $p) {
                            $combined[$p['employee_id']] = $p;
                        }
                        foreach ($employees as $emp) {
                            if (!isset($combined[$emp['employee_id']])) {
                                $combined[$emp['employee_id']] = $emp;
                            }
                        }
                        $employees = array_values($combined);
                    }

                    if ($filterCategory === 'School Administration') {
                        if ($isPrincipalUser && $mySchool) {
                            $employees = activePrincipalEmployees($mySchool['id']);
                            $headTeachers = activeHeadTeacherEmployees($mySchool['id']);
                        } elseif ($isDistSupervisor && $myDistrict) {
                            $employees = activePrincipalEmployees(null, $myDistrict['id']);
                            $headTeachers = activeHeadTeacherEmployees(null, $myDistrict['id']);
                        } else {
                            $employees = activePrincipalEmployees();
                            $headTeachers = activeHeadTeacherEmployees();
                        }
                        $combined = [];
                        foreach ($employees as $p) {
                            $combined[$p['employee_id']] = $p;
                        }
                        foreach ($headTeachers as $ht) {
                            if (!isset($combined[$ht['employee_id']])) {
                                $combined[$ht['employee_id']] = $ht;
                            }
                        }
                        $employees = array_values($combined);
                    } elseif ($filterCategory === 'Supervisor') {
                        $employees = activeSupervisorEmployees();
                    } elseif ($filterCategory === 'Related-Teaching') {
                        if ($isPrincipalUser && $mySchool) {
                            $guidanceCounselors = activeRelatedTeachingEmployees($mySchool['id']);
                        } elseif ($isDistSupervisor && $myDistrict) {
                            $guidanceCounselors = activeRelatedTeachingEmployees(null, $myDistrict['id']);
                        } else {
                            $guidanceCounselors = activeRelatedTeachingEmployees();
                        }

                        $combined = [];
                        foreach ($guidanceCounselors as $gc) {
                            $combined[$gc['employee_id']] = $gc;
                        }
                        foreach ($employees as $emp) {
                            if (!isset($combined[$emp['employee_id']])) {
                                $combined[$emp['employee_id']] = $emp;
                            }
                        }
                        $employees = array_values($combined);
                    }

                    usort($employees, function ($a, $b) {
                        $cmp = strcasecmp($a['last_name'] ?? '', $b['last_name'] ?? '');
                        if ($cmp !== 0)
                            return $cmp;
                        $cmp = strcasecmp($a['first_name'] ?? '', $b['first_name'] ?? '');
                        if ($cmp !== 0)
                            return $cmp;
                        return strcasecmp($a['middle_name'] ?? '', $b['middle_name'] ?? '');
                    });
                    ?>
                    <div class="form-group" id="employee-group">
                        <label for="employee-id" class="mb-0">Select Employee <?php showAsterisk() ?></label>
                        <?php if ($isPrincipalUser): ?>
                            <small class="text-info d-block mb-1"><i class="fas fa-info-circle"></i> You can only nominate
                                personnel under your school.</small>
                        <?php elseif ($isDistSupervisor && $myDistrict): ?>
                            <small class="text-info d-block mb-1"><i class="fas fa-info-circle"></i> You can only nominate
                                personnel in your district (<?= e($myDistrict['name']) ?>).</small>
                        <?php elseif ($filterCategory !== null): ?>
                            <small class="text-info d-block mb-1"><i class="fas fa-info-circle"></i> Showing
                                <?= e($filterCategory) ?> personnel only.</small>
                        <?php endif; ?>
                        <?php if ($includePrincipals): ?>
                            <small class="text-info d-block mb-1"><i class="fas fa-info-circle"></i> Current school principals
                                are included so former teachers now serving as principals can be nominated.</small>
                        <?php endif; ?>
                        <?php if ($filterCategory === 'School Administration'): ?>
                            <small class="text-info d-block mb-1"><i class="fas fa-info-circle"></i> Showing School Heads
                                (Principals and Head Teachers) only.</small>
                        <?php endif; ?>
                        <?php if ($filterCategory === 'Supervisor'): ?>
                            <small class="text-info d-block mb-1"><i class="fas fa-info-circle"></i> Showing Supervisors
                                (Education Program Supervisors and Public Schools District Supervisors) only.</small>
                        <?php endif; ?>
                        <select id="employee-id" name="employee-id" class="form-control" title="Select employee..."
                            required>
                            <option value="">Select Employee...</option>
                            <?php foreach ($employees as $emp):
                                $fullName = toName($emp['last_name'], $emp['first_name'], $emp['middle_name'], $emp['name_extension']);
                                ?>
                                <option value="<?= e($emp['employee_id']) ?>">
                                    <?= e($fullName) ?> &mdash; <?= e($emp['official_title']) ?>
                                    <?php if (!empty($emp['school_name'])): ?>
                                        &mdash; <?= e($emp['school_name']) ?><?php if (!empty($emp['school_alias'])): ?>
                                            (<?= e($emp['school_alias']) ?>)<?php endif; ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <?php
                $needsLevel = isset($award['has_level']) && (int) $award['has_level'] === 1;
                if ($needsLevel): ?>
                    <div class="form-group mt-3" id="level-group">
                        <label for="level" class="mb-0">Level <?php showAsterisk() ?></label>
                        <select id="level" name="level" class="form-control" title="Select level..." required>
                            <option value="">Select Level...</option>
                            <option value="Elementary">Elementary</option>
                            <option value="Secondary">Secondary</option>
                        </select>
                    </div>
                <?php endif; ?>

                <?php requiredLegend(0) ?>
            </div>

            <div class="modal-footer">
                <input type="hidden" name="verifier" value="<?= $_GET['e'] ?? '' ?>">
                <button class="btn btn-primary" name="save-nominee" type="submit">Continue</button>
                <?php cancelModalButton() ?>
            </div>
        </form>
    </div>
</div>