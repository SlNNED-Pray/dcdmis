<?php
// hrmis/sidebar-menu.php
$isNonDivision = $stationId !== DIVISION_ID;
$schoolInfo = schoolByHead($userId, DIVISION_ID);

sidebarDivider();

sidebarMenuItem(customUri('pis', 'Employee Information', $userId), 'Employee Information', 'fa-user-tie', isset($url) && str_contains($url, 'Employee Information'));
sidebarMenuItem(customUri('pis', 'Service Record', $userId), 'Service Record', 'fa-file-alt', isset($url) && str_contains($url, 'Service Record'));
sidebarMenuItem(customUri('pis', '201 Files', $userId), '201 Files', 'fa-folder-open', isset($url) && str_contains($url, '201 Files'));
sidebarMenuItem(customUri('pis', 'Trainings', $userId), 'Trainings', 'fa-chalkboard-teacher', isset($url) && str_contains($url, 'Trainings'));
sidebarMenuItem(customUri('pis', 'Payslips', $userId), 'Payslips', 'fa-money-check', isset($url) && str_contains($url, 'Payslips'));
sidebarMenuItem(customUri('pis', 'Purchase Requests'), 'Purchase Requests', 'fa-shopping-cart', isset($url) && str_contains($url, 'Purchase Request'));
if (!$isNonDivision) {
    sidebarMenuItem(customUri('pis', 'IPCRF', $userId), 'IPCRF', 'fa-chart-line', isset($url) && (str_contains($url, 'IPCRF') || str_contains($url, 'Performance') || str_contains($url, 'Phase') || str_contains($url, 'Objective') || str_contains($url, 'MOV')));
}

if ($isNonDivision || $schoolInfo) {
    sidebarDivider();
    if ($isNonDivision) {
        sidebarMenuItem(customUri('pis', 'Request Transfer'), 'Request Transfer', 'fa-exchange-alt', isset($url) && str_contains($url, 'Request Transfer'));
    }
    if ($schoolInfo) {
        sidebarMenuItem(customUri('pis', 'School Employees'), 'School Employees', 'fa-users', isset($url) && ($url === 'Employees' || $url === 'Active Employees' || $url === 'School Employees'));
    }
}

$showMonitoringTools = (dtsUser($userId) && station($userId)['station_id'] === DIVISION_ID) || (bool) $schoolInfo;
$userPositionId = position($userId)['position_id'];
$showOverview = in_array($userPositionId, $allowedMonitoringPositions, true) || $isICT;

if ($showMonitoringTools || $showOverview) {
    sidebarDivider();
    if ($showMonitoringTools) {
        sidebarMenuItem(customUri('pis', 'Monitoring Tools'), 'Monitoring Tools', 'fa-binoculars', isset($url) && str_contains($url, 'Monitoring Tools'));
    }
    if ($showOverview) {
        $primeHrmPillars = [
            'Recruitment, Selection and Placement',
            'Learning and Development',
            'Performance Management',
            'Rewards and Recognition'
        ];

        sidebarMenuItem(customUri('pis', 'System Overview'), 'System Overview', 'fa-network-wired', isset($url) && (str_contains($url, 'System Overview') || in_array($url, $primeHrmPillars, true)));
    }
}