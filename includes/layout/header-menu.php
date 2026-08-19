<?php
// includes/layout/header-menu.php
$user = employee($userId);
$displayName = toName($user['last_name'], $user['first_name'], $user['middle_name'], $user['name_extension'], true, true);
$position = position($userId);
$displayPhoto = file_exists(root() . '/' . $user['profile_picture']) ? uri() . '/' . $user['profile_picture'] : uri() . '/assets/img/user.png';
?>

<nav class="navbar navbar-expand navbar-light bg-white topbar static-top shadow">
    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
        <i class="fa fa-bars"></i>
    </button>

    <?php if ($isDts || $isHrmis || $isHrtdms): ?>
        <form class="d-none d-sm-inline-block form-inline mr-auto ml-md-0 my-2 my-md-0 mw-100 navbar-search" method="POST"
            action="">
            <?= csrf_field(); ?>
            <div class="input-group">
                <input type="text" class="form-control bg-light border-0 small" placeholder="Search..." aria-label="Search"
                    name="primary-search-text" <?= $isDts ? 'autofocus' : '' ?> required>

                <div class="input-group-append">
                    <button class="btn btn-primary" type="submit" name="primary-search-button">
                        <i class="fas fa-search fa-sm"></i>
                    </button>
                </div>
            </div>
        </form>
    <?php endif ?>

    <ul class="navbar-nav ml-auto">
        <?php if ($isDts || $isHrmis || $isHrtdms): ?>
            <li class="nav-item dropdown no-arrow d-sm-none">
                <a class="nav-link dropdown-toggle" href="#" id="searchDropdown" role="button" data-toggle="dropdown"
                    aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-search fa-fw"></i>
                </a>

                <div class="dropdown-menu dropdown-menu-right p-3 shadow animated--grow-in"
                    aria-labelledby="searchDropdown">
                    <form class="form-inline mr-auto w-100 navbar-search" method="POST" action="">
                        <?= csrf_field(); ?>
                        <div class="input-group">
                            <input type="text" class="form-control bg-light border-0 small" placeholder="Search..."
                                aria-label="Search" name="primary-search-text" required>

                            <div class="input-group-append">
                                <button class="btn btn-primary" type="submit" name="primary-search-button">
                                    <i class="fas fa-search fa-sm"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </li>
        <?php endif ?>

        <li class="nav-item dropdown no-arrow mx-1">

            <a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button" data-toggle="dropdown"
                aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-bell fa-fw"></i>
            </a>

            <div class="dropdown-list dropdown-menu dropdown-menu-right shadow animated--grow-in"
                aria-labelledby="alertsDropdown">
                <h6 class="dropdown-header">
                    Alerts Center
                </h6>
                <div class="dropdown-item d-flex align-items-center">
                    <div class="font-weight-light text-center my-2">No new alerts at the moment.</div>
                </div>
            </div>
        </li>

        <li class="nav-item dropdown no-arrow mx-1">
            <a class="nav-link dropdown-toggle" href="#" id="completionDropdown" role="button" data-toggle="dropdown"
                aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-tasks fa-fw"></i>
            </a>

            <div class="dropdown-list dropdown-menu dropdown-menu-right shadow animated--grow-in"
                aria-labelledby="completionDropdown">
                <h6 class="dropdown-header bg-primary border-primary">
                    My Employee Information Status
                </h6>

                <a class="dropdown-item py-3" href="<?= customUri('pis', 'Employee Information', $userId) ?>">
                    <div class="font-weight-bold text-left pb-1">
                        <?php
                        $pdsProgress = pdsProgress($userId);
                        echo "$pdsProgress% Complete";
                        ?>
                    </div>

                    <?php progressBar($pdsProgress) ?>
                </a>
            </div>
        </li>

        <div class="topbar-divider d-none d-sm-block"></div>

        <li class="nav-item dropdown no-arrow">
            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown"
                aria-haspopup="true" aria-expanded="false" title="<?= strtoupper($displayName) ?>">
                <span class="mr-2 d-none d-md-inline">
                    <div class="text-gray-600 small"><?= e(strtoupper($displayName)) ?></div>
                    <div class="text-xs text-gray-500"><?= e(strtoupper($position['official_title'] ?? '')) ?></div>
                </span>

                <span class="d-flex justify-content-center align-middle img-profile rounded-circle overflow-hidden">
                    <img src="<?= e($displayPhoto) ?>" alt="<?= e($displayName) ?>" height="100%">
                </span>
            </a>

            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
                <?php
                linkDropdownItem(uri() . '/pis', 'Profile', 'fa-user-tie', 'Personnel Information System');

                if (dtsUser($userId)) {
                    linkDropdownItem(uri() . '/dts', 'Tracking', 'fa-exchange-alt', 'Document Tracking System');
                }

                if (userRole($userId, 'hrmis')) {
                    linkDropdownItem(uri() . '/hrmis', 'HR Management', 'fa-users', 'Human Resource Management Information System');
                }

                if (userRole($userId, 'hrtdms')) {
                    linkDropdownItem(uri() . '/hrtdms', 'HR Trainings', 'fa-chalkboard-teacher', 'Human Resource Training and Development Management System');
                }

                if (userRole($userId, 'race')) {
                    linkDropdownItem(uri() . '/race', 'Rewards and Recognitions', 'fa-trophy', 'Rewards and Recognitions');
                }

                if (userRole($userId, 'dtr')) {
                    linkDropdownItem(uri() . '/dtr', 'Daily Time Record', 'fa-clock', 'Daily Time Records');
                }

                if (userRole($userId, 'ims')) {
                    linkDropdownItem(uri() . '/ims', 'Inventory Management', 'fa-box', 'Inventory Management System');
                }

                if (userRole($userId, 'dmis')) {
                    linkDropdownItem(uri() . '/dmis', 'Division Management', 'fa-industry', 'Division Management Information System');
                }
                ?>

                <div class="dropdown-divider"></div>

                <?php
                linkDropdownItem(customUri($activeApp, 'Activity Log'), 'Activity Log', 'fa-list', 'View activity log');
                linkDropdownItem(customUri($activeApp, 'Edit History'), 'Edit History', 'fa-history', 'View edit history');
                linkDropdownItem(customUri($activeApp, 'Settings'), 'Settings', 'fa-cogs', 'Go to settings');
                ?>

                <div class="dropdown-divider"></div>

                <?php //dd($baseUri);
                modalDropdownItem(uri() . '/modules/auth/logout-dialog.php', 'Logout', 'fa-sign-out-alt', 'Logout') ?>
            </div>
        </li>
    </ul>
</nav>

<div class="background-cover banner text-uppercase text-gray-700">
    <?php
    $school = schoolById($stationId);
    if ($school): ?>
        <h1 class="h3 m-0"><?= e($school['name']) ?></h1>

        <?php if (!empty($school['address'])): ?>
            <div class="small m-0"><?= e($school['address']) ?></div>
        <?php endif;
    endif ?>

    <h2 class="h1 m-0 mt-4"><?= e(strtoupper($appTitle)) ?></h2>

    <?php if ($hasPortal && !$isSchoolPortal && $isDts): ?>
        <h3 class="h4 m-0"><?= stationName($station) ?></h3>
    <?php endif ?>
</div>