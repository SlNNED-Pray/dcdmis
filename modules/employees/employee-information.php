<?php
// modules/employees/employee-information.php
if (!$isPis && !$isHrmis) {
    require_once(root() . '/modules/error/403.php');
    return;
}

$employeeId = (int) sanitize(decode($_GET['id'] ?? null));

$isHeadOfThisEmployee = false;
$schoolInfo = schoolByHead($userId);
if ($schoolInfo && $employeeId > 0) {
    $empStation = station($employeeId);
    if ($empStation && $empStation['station_id'] === $schoolInfo['id']) {
        $isHeadOfThisEmployee = true;
    }
}

$userPositionId = null;
if ($isPis) {
    if (!function_exists('position')) {
        require_once(root() . '/includes/database/position.php');
    }
    $userPosition = position($userId);
    $userPositionId = $userPosition['position_id'] ?? null;
}
$isAllowedHigherPosition = $isPis && (in_array($userPositionId, $allowedMonitoringPositions, true) || $isICT);

if ($isPis && $userId !== $employeeId && !$isHeadOfThisEmployee && !$isAllowedHigherPosition) {
    require_once(root() . '/modules/error/no-results-found.php');
    return;
}

$employee = employee($employeeId);

if ($employee) {
    $employeeId = $employee['id'];
} else {
    require_once(root() . '/modules/error/no-results-found.php');
    return;
}

messageAlert($showAlert, $message, $success);
?>

<div class="d-flex align-items-center justify-content-between flex-row mt-2 mb-3">
    <nav class="d-flex align-items-center flex-row m-0">
        <ol class="breadcrumb m-0 p-0 bg-transparent">
            <li class="breadcrumb-item"><a href="<?= uri() . '/' . $activeApp ?>">Dashboard</a></li>
            <li class="breadcrumb-item active">Employee Information</li>
        </ol>
    </nav>
</div>

<?php
if ($isHrmis) {
    require_once(root() . '/modules/employees/employee-tabs.php');
}

$uploadDirectory = root() . '/uploads/images/' . $employeeId;

if (!is_dir($uploadDirectory)) {
    mkdir($uploadDirectory, 0777, true);
}

$editMode = $url === 'Edit Employee Information';
$employeePhoto = '';
?>

<div class="card border-left-primary shadow mb-4">
    <div class="card-header py-3">
        <?php
        if (!$isPis) {
            if (!$editMode) {
                contentTitleWithLink('Employee Information : ' . strtoupper(toName($employee['last_name'], $employee['first_name'], $employee['middle_name'], $employee['name_extension'])), customUri('hrmis', 'Edit Employee Information', $employeeId), 'Edit', 'fa-edit');
            } else {
                $employeePhoto = file_exists(root() . '/' . $employee['profile_picture']) ? uri() . '/' . $employee['profile_picture'] : uri() . '/assets/img/user.png';
                contentTitleWithLink('Update Employee Information : ' . strtoupper(toName($employee['last_name'], $employee['first_name'], $employee['middle_name'], $employee['name_extension'])), customUri('hrmis', 'Employee Information', $employeeId));
            }
        } else {
            contentTitleWithLink('Employee Information : ' . strtoupper(toName($employee['last_name'], $employee['first_name'], $employee['middle_name'], $employee['name_extension'])), uri() . '/pis');
        }

        progressBar(pdsProgress($employeeId));
        ?>
    </div>

    <div class="card-body pb-2">
        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <a class="nav-link text-secondary<?= setActiveNavigation(!isset($activeTab) || $activeTab === 'personal-information') ?>"
                    href="#personal-information" data-toggle="tab">Personal Information</a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary<?= setActiveNavigation(isset($activeTab) && $activeTab === 'family-background') ?>"
                    href="#family-background" data-toggle="tab">Family Background</a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary<?= setActiveNavigation(isset($activeTab) && $activeTab === 'children') ?>"
                    href="#children" data-toggle="tab">Children</a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary<?= setActiveNavigation(isset($activeTab) && $activeTab === 'educational-background') ?>"
                    href="#educational-background" data-toggle="tab">Educational Background</a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary<?= setActiveNavigation(isset($activeTab) && $activeTab === 'civil-service-eligibility') ?>"
                    href="#civil-service-eligibility" data-toggle="tab">Civil Service Eligibility</a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary<?= setActiveNavigation(isset($activeTab) && $activeTab === 'work-experience') ?>"
                    href="#work-experience" data-toggle="tab">Work Experience</a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary<?= setActiveNavigation(isset($activeTab) && $activeTab === 'voluntary-work') ?>"
                    href="#voluntary-work" data-toggle="tab">Voluntary Work</a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary<?= setActiveNavigation(isset($activeTab) && $activeTab === 'learning-development') ?>"
                    href="#learning-development" data-toggle="tab">Learning and Development</a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary<?= setActiveNavigation(isset($activeTab) && $activeTab === 'special-skills') ?>"
                    href="#special-skills" data-toggle="tab">Special Skills and Hobbies</a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary<?= setActiveNavigation(isset($activeTab) && $activeTab === 'recognition') ?>"
                    href="#recognition" data-toggle="tab">Non-Academic Distinctions / Recognition</a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary<?= setActiveNavigation(isset($activeTab) && $activeTab === 'membership') ?>"
                    href="#membership" data-toggle="tab">Membership in Association / Organization</a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary<?= setActiveNavigation(isset($activeTab) && $activeTab === 'other-information') ?>"
                    href="#other-information" data-toggle="tab">Other Information</a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary<?= setActiveNavigation(isset($activeTab) && $activeTab === 'reference') ?>"
                    href="#reference" data-toggle="tab">References</a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary<?= setActiveNavigation(isset($activeTab) && $activeTab === 'government-id') ?>"
                    href="#government-id" data-toggle="tab">Government Issued ID</a>
            </li>
        </ul>

        <?php if (false): ?>
            <div class="d-sm-flex align-items-center flex-row-reverse my-2">
                <div class="d-inline-block">
                    <?php linkButtonSplit(customUri('print', 'Personal Data Sheet', $employeeId), 'Print', 'fa-print', 'Print file', 'success', true) ?>
                </div>
            </div>
        <?php endif ?>

        <div class="tab-content mt-2">
            <?php
            require_once(root() . '/modules/employees/tabs/personal-information.php');
            require_once(root() . '/modules/employees/tabs/family-background.php');
            require_once(root() . '/modules/employees/tabs/children.php');
            require_once(root() . '/modules/employees/tabs/educational-background.php');
            require_once(root() . '/modules/employees/tabs/civil-service-eligibility.php');
            require_once(root() . '/modules/employees/tabs/work-experience.php');
            require_once(root() . '/modules/employees/tabs/voluntary-work.php');
            require_once(root() . '/modules/employees/tabs/learning-development.php');
            require_once(root() . '/modules/employees/tabs/special-skills.php');
            require_once(root() . '/modules/employees/tabs/recognition.php');
            require_once(root() . '/modules/employees/tabs/membership.php');
            require_once(root() . '/modules/employees/tabs/other-information.php');
            require_once(root() . '/modules/employees/tabs/reference.php');
            require_once(root() . '/modules/employees/tabs/government-id.php');
            ?>
        </div>
    </div>
</div>