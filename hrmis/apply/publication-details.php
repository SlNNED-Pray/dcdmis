<?php
$error = $publication = $vacancies = null;
$isDeadlineToday = false;

if ($code) {
    $publication = publicationByCode($code);

    if ($publication) {
        $today = date('Y-m-d');
        $closeDate = date('Y-m-d', strtotime($publication['close_date']));
        $openDate = date('Y-m-d', strtotime($publication['open_date']));
        $currentTime = time();
        $deadline5pm = strtotime($closeDate . ' 17:00:00');

        if ($publication['status'] !== 'open') {
            $error = 'This call for application is currently closed.';
        } elseif (strtotime($closeDate) < strtotime($today)) {
            $error = 'The application period for this call for application has ended.';
        } elseif ($today === $closeDate && $currentTime >= $deadline5pm) {
            $error = 'The application period for this call for application ended at 5:00 PM today. Submissions are no longer accepted.';
        } elseif (strtotime($openDate) > strtotime($today)) {
            $error = 'The application period for this call for application has not started yet.';
        } else {
            if ($today === $closeDate) {
                $isDeadlineToday = true;
            }
            $vacancies = publicationItems($publication['id']);
        }
    } else {
        $error = 'Call for application not found.';
    }
} else {
    $error = 'Invalid call for application link.';
}
?>

<div class="card mt-3 mb-4 mx-auto">
    <div class="card-body">
        <div class="text-center">
            <?php if ($publication): ?>
                <h2 class="h2 mt-2">
                    <?= e($publication['title']) ?>
                </h2>
                <p class="mb-4">
                    <?= e($publication['description']) ?>
                </p>
                <p class="small text-muted mb-4">
                    Application Period: <span class="font-weight-bold text-dark">
                        <?= toLongDate($publication['open_date']) ?>
                    </span> to <span class="font-weight-bold text-dark">
                        <?= toLongDate($publication['close_date']) ?>
                    </span>
                </p>
            <?php endif ?>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger text-center">
                <i class="fas fa-exclamation-circle mr-1"></i>
                <?= e($error) ?>
            </div>

            <div class="text-center mt-4">
                <a href="<?= uri() . '/hrmis/apply' ?>" class="btn btn-secondary">Go to Call for Applications</a>
            </div>
        <?php else: ?>
            <?php if ($isDeadlineToday): ?>
                <div class="alert alert-warning text-center font-weight-bold mb-4">
                    <i class="fas fa-clock mr-1"></i>
                    Notice: Receiving of application will end at 5:00 PM today and will no longer accept applications.
                </div>
            <?php endif ?>

            <form class="user" action="" method="POST" enctype="multipart/form-data">
                <?= csrf_field(); ?>
                <input type="hidden" name="publication_id" value="<?= cipher($publication['id']) ?>">

                <div>
                    <?= messageAlert($showAlert, $message, $success) ?>

                    <div class="form-group mb-4">
                        <div>
                            <label for="applicant-id" class="font-weight-bold mb-1">Applicant
                                ID <?= showAsterisk() ?>
                            </label>

                        </div>
                        <input type="text" class="form-control" id="applicant-id" name="applicant_id"
                            placeholder="Enter your 18-digit applicant ID..." required>
                        <div
                            class="small mt-2 d-flex flex-column flex-sm-row justify-content-between align-items-center text-center">
                            <div class="mb-2 mb-sm-0">
                                <a href="<?= uri() . '/hrmis/register' ?>" target="_blank">Don't have an applicant ID
                                    yet?</a>
                            </div>
                            <div>
                                <a href="<?= uri() . '/hrmis/register/forgot' ?>" target="_blank">Forgot your applicant
                                    ID?</a>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <p class="font-weight-bold mb-0">Positions</p>
                        <small class="form-text text-muted">Check the box for each position you wish to apply for.</small>
                    </div>

                    <div class="table-responsive border rounded mb-4">
                        <table class="table table-hover mb-0" cellspacing="0" width="100%">
                            <thead>
                                <tr class="text-center">
                                    <th width="5%"></th>
                                    <th width="35%" class="text-left">Vacant Positions</th>
                                    <th width="45%" class="text-left">Station Assignment</th>
                                    <th width="15%">Available Items</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php if ($vacancies && count($vacancies) > 0) {
                                    $groups = [];
                                    foreach ($vacancies as $row) {
                                        $pid = $row['position_id'];
                                        $stationId = $row['station_id'];

                                        $school = schoolById($stationId);
                                        $schoolName = $school ? $school['name'] : 'N/A';

                                        if (!isset($groups[$pid])) {
                                            $groups[$pid] = [
                                                'position' => $row['official_title'],
                                                'salary_grade' => $row['salary_grade'],
                                                'count' => 0,
                                                'stations' => []
                                            ];
                                        }
                                        $groups[$pid]['count']++;
                                        if (!in_array($schoolName, $groups[$pid]['stations'])) {
                                            $groups[$pid]['stations'][] = $schoolName;
                                        }
                                    }

                                    foreach ($groups as $pid => $group) { ?>
                                        <tr>
                                            <td class="text-center align-middle">
                                                <input type="checkbox" name="position_ids[]" value="<?= cipher($pid) ?>"
                                                    id="pos_<?= cipher($pid) ?>" style="transform: scale(1.5);">
                                            </td>
                                            <td class="align-middle">
                                                <label for="pos_<?= e($pid) ?>"
                                                    class="mb-0 font-weight-bold cursor-pointer"><?= e($group['position']) ?></label>
                                                <div class="small text-muted">Salary Grade <?= e($group['salary_grade']) ?></div>
                                            </td>
                                            <td class="align-middle small">
                                                <?= implode('<br>', $group['stations']) ?>
                                            </td>
                                            <td class="align-middle text-center">
                                                <span class="badge badge-success badge-pill px-3 py-2">
                                                    <?= e($group['count']) ?> Item<?= $group['count'] > 1 ? 's' : '' ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php }
                                } else { ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No vacancies found for this call for application.
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>

                            <tfoot>
                                <tr class="text-center">
                                    <th width="5%"></th>
                                    <th width="35%" class="text-left">Vacant Positions</th>
                                    <th width="45%" class="text-left">Station Assignment</th>
                                    <th width="15%">Available Items</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div>
                        <p class="form-text text-muted small mb-2">Review the <a
                                href="https://www.deped.gov.ph/wp-content/uploads/DO_s2023_007.pdf" target="_blank">DepEd
                                Order
                                No. 007, s. 2023</a>, the Guidelines on
                            Recruitment, Selection, and Appointment in the Deparment of Education for
                            reference.
                        </p>
                        <p class="form-text text-muted small mb-2">Download and accomplish the <a
                                href="https://drive.google.com/file/d/1-t8G_AMDZAVoME4e-i47ZDqXn1gOrLHO/view"
                                target="_blank">checklist
                                of requirements</a> for your application.</p>
                    </div>

                    <button name="submit-application" type="submit" class="btn btn-primary btn-block mt-2">
                        Submit Application
                    </button>
                </div>
            </form>
        <?php endif ?>
    </div>
</div>