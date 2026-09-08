<?php
// modules/trainings/training-details.php
require_once(root() . '../includes/database/database.php');
require_once(root() . '/modules/trainings/modules/action.php');

if (!$isHrtdms) {
    require_once(root() . '/modules/error/403.php');
    return;
}

$trainingId = isset($_GET['id']) ? sanitize(decode($_GET['id'])) : null;
$training = training($trainingId);

if (!$training) {
    require_once(root() . '/modules/error/no-results-found.php');
    return;
}

$trainingId = $training['id'];
$participants = trainingParticipants($trainingId);
$participantsCount = count($participants);

$program = !empty($training['program_id']) ? getProgramByID($training['program_id']) : null;
$project = !empty($training['project_id']) ? getProjectByID($training['project_id']) : null;

//DATE TAB
$dates = [];
$start = new DateTime($training['start_date']);
$end = new DateTime($training['end_date']);

for ($d = $start; $d <= $end; $d->modify('+1 day')) {
    $dates[] = $d->format('Y-m-d');
}

if ($training) {
    $trainingId = $training['id'];
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
            <li class="breadcrumb-item">
                <?php if (strtotime($training['start_date']) < strtotime(date('Y-m-d'))): ?>
                    <a href="<?= customUri('hrtdms', 'Conducted Trainings') ?>">Conducted Trainings</a>
                <?php else: ?>
                    <a href="<?= customUri('hrtdms', 'Scheduled Trainings') ?>">Scheduled Trainings</a>
                <?php endif ?>
            </li>
            <li class="breadcrumb-item"><a
                    href="<?= customUri('hrtdms', 'Training Details', $training['id']) ?>"><?= e($training['id']) ?></a>
            </li>
            <li class="breadcrumb-item active">Attendance</li>
        </ol>
    </nav>
</div>

<div class="card border-left-primary shadow mb-4">
    <div class="card-header py-3">
        <?php contentTitleWithModal('Training Attendance', uri() . '/modules/trainings/save-training-dialog.php?id=' . cipher($training['id']), 'Edit', 'fa-edit') ?>
    </div>

    <div class="card-body">
        <div class="d-sm-flex align-items-center flex-row-reverse mb-2">
            <div class="d-inline-block">
                <?php linkButtonSplit(customUri('export', 'training-details', $training['id']), 'Export', 'fa-file-excel', 'Export as Excel file', 'success'); ?>
            </div>
        </div>

        <div class="table-responsive mb-3">
            <table cellspacing="0">
                <?php if (isset($program[0])): ?>
                    <tr>
                        <th class="pr-5" scope="row">Program</th>
                        <td class="text-uppercase"><?= e($program[0]['program_name']) ?>
                        </td>
                    </tr>
                <?php endif;
                if (isset($project[0])): ?>
                    <tr>
                        <th class="pr-5" scope="row">Project</th>
                        <td class="text-uppercase"><?= e($project[0]['project_name']) ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <th class="pr-5" scope="row">Code</th>
                    <td class="text-uppercase"><?= e($training['id']) ?></td>
                </tr>
                <tr>
                    <th class="align-top pr-5" scope="row">Title</th>
                    <td class="text-uppercase"><?= e($training['title']) ?></td>
                </tr>
                <tr>
                    <th class="pr-5" scope="row">Date</th>
                    <td class="text-uppercase">
                        <?= empty($training['unconsecutive_date']) ? toDateRange($training['start_date'], $training['end_date']) : toHandleEncoding($training['unconsecutive_date']) ?>
                    </td>
                </tr>
                <?php if (!empty($training['hours'])): ?>
                    <tr>
                        <th class="pr-5" scope="row">Hours</th>
                        <td class="text-uppercase"><?= e($training['hours']) ?></td>
                    </tr>
                <?php endif ?>
                <tr>
                    <th class="pr-5" scope="row">Type</th>
                    <td class="text-uppercase"><?= trainingType($training['training_type_id']) ?></td>
                </tr>
                <tr>
                    <th class="pr-5" scope="row">Level</th>
                    <?php
                    $functional_division = $training['functional_division_id'];
                    $functional_divisions = functionalDivision($functional_division);
                    $training_functional_division = '';
                    if ($functional_divisions && count($functional_divisions) > 0) {
                        $training_functional_division = $functional_divisions['name'];
                    }
                    $functional_division = (!empty($functional_division) && strtolower($functional_division) !== 'n/a') ? " ($training_functional_division)" : '';
                    ?>
                    <td class="text-uppercase">
                        <?= trainingSponsor($training['training_level_id']) . $functional_division ?>
                    </td>
                </tr>
                <?php if (!empty($training['sponsor'])): ?>
                    <tr>
                        <th class="align-top pr-5" scope="row">Sponsor</th>
                        <td class="text-uppercase"><?= e($training['sponsor']) ?></td>
                    </tr>
                <?php endif ?>
                <?php if (!empty($training['venue'])): ?>
                    <tr>
                        <th class="align-top pr-5" scope="row">Venue</th>
                        <td class="text-uppercase"><?= e($training['venue']) ?></td>
                    </tr>
                <?php endif ?>
                <tr>
                    <th class="align-top pr-5" scope="row">Participants</th>
                    <td class="text-uppercase"><?= count($participants) ?></td>
                </tr>
            </table>
        </div>

        <?php
        $dates = [];
        $start = new DateTime($training['start_date']);
        $end = new DateTime($training['end_date']);

        for ($d = $start; $d <= $end; $d->modify('+1 day')) {
            $dates[] = $d->format('Y-m-d');
        }

        $todayDate = date('Y-m-d');
        $showAttendanceActionsToday = in_array($todayDate, $dates, true);
        ?>

        <ul class="nav nav-tabs" id="attendanceTabs" role="tablist">
            <?php foreach ($dates as $i => $date): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $i == 0 ? 'active' : '' ?>" id="tab-<?= $i ?>" data-toggle="tab"
                        href="#day<?= $i ?>" role="tab" data-date="<?= $date ?>">

                        <i class="fas fa-calendar-alt me-1"></i>
                        <strong>Day</strong> <?= $i + 1 ?> (<?= date('M d, Y', strtotime($date)) ?>)
                    </a>
                </li>
            <?php endforeach ?>
        </ul>

        <!-- PROJECT AND ACTIVITIES -->
        <input type="hidden" id="training_id" value="<?php echo $trainingId ?>">
        <input type="hidden" id="url_view" value="<?php echo base64_decode($_GET['v']) ?>">
        <input type="hidden" id="selected_date" value="">

        <!-- Activities List -->
        <div
            class="my-3 d-flex flex-column flex-md-row <?= $showAttendanceActionsToday ? 'justify-content-between align-items-md-center' : 'justify-content-end align-items-end' ?> gap-2">
            <?php if ($showAttendanceActionsToday): ?>
                <div class="d-flex align-items-center flex-grow-1 mb-2 mb-md-0 me-md-3">
                    <input type="hidden" id="csrf_token" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="text" id="qrInput" class="form-control me-2 mr-1" style="width: 100%; max-width: 320px;"
                        placeholder="Scan ID or type employee name..." autofocus>
                    <button id="addAttendanceBtn" class="btn btn-primary me-2 mr-1"><i
                            class="fas fa-user-plus"></i></button>
                    <button id="showAddModalInfo" class="btn btn-warning mr-1"><i class="fas fa-user-secret"></i></button>
                </div>
            <?php endif; ?>

            <div class="d-flex align-items-center justify-content-end">
                <button id="viewAttendanceSum" class="btn btn-info mr-1" title="View Attendees">
                    <i class="fas fa-list"></i>
                </button>

                <button id="generatePDF" class="btn btn-info mr-1" title="Generate PDF per Day">
                    <i class="fas fa-file-pdf"></i>
                </button>
                <button id="generatePDFDays" class="btn btn-success mr-1" title="Generate PDF by Trainings">
                    <i class="fas fa-file-export"></i>
                </button>

                <a href="http://<?= $_SERVER['HTTP_HOST'] ?>/hrtdms/?v=<?= base64_encode('Attendance Summary') ?>&id=<?= base64_encode($training['id']) ?>"
                    target="_blank" class="btn btn-info" title="View Summary">
                    <i class="fas fa-chart-line"></i>
                </a>
            </div>
        </div>

        <div class="tab-content mt-3 px-0 py-2">
            <?php foreach ($dates as $i => $date): ?>
                <div class="tab-pane fade <?= $i == 0 ? 'show active' : '' ?>" id="day<?= $i ?>" role="tabpanel">
                    <table class="table table-hover text-center attendance-table" id="">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Position</th>
                                <th>Station</th>
                                <th>Time In</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            $trainingAttendanceArr = getTrainingAttendees($trainingId, $date);
                            foreach ($trainingAttendanceArr as $trainingAttendance):
                                ?>
                                <tr id="employeeAttendanceID<?= $trainingAttendance['id'] ?>" class="text-uppercase">
                                    <td class="align-middle">
                                        <?php if (!empty($trainingAttendance['img_url'])): ?>
                                            <i class="fas fa-image text-primary view-img" style="cursor:pointer;"
                                                data-img="<?= $trainingAttendance['img_url'] ?>" title="View Image"></i>
                                        <?php else: ?>
                                            <span class="text-muted">No image</span>
                                        <?php endif; ?>
                                    </td>

                                    <td
                                        class="align-middle text-left <?= ($trainingAttendance['status'] == 0) ? 'text-warning' : '' ?>">
                                        <b><?= strtoupper($trainingAttendance['fullname']) ?></b>
                                    </td>

                                    <td class="align-middle text-left">
                                        <?= $trainingAttendance['official_title'] ?>
                                    </td>

                                    <td class="align-middle">
                                        <?= $trainingAttendance['school_name'] ?>
                                    </td>

                                    <td class="align-middle">
                                        <?= date('h:i A', strtotime($trainingAttendance['created_at'])) ?>
                                    </td>

                                    <td class="align-middle">
                                        <div class="dropdown no-arrow">
                                            <?php dropdownEllipsis(); ?>

                                            <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in">
                                                <a href="javascript:void(0);" class="dropdown-item"
                                                    onclick="showDeleteAttendance('<?= $trainingAttendance['id'] ?>')">

                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="small">
                                <th>Image</th>
                                <th>QR Code</th>
                                <th>Name</th>
                                <th>Position</th>
                                <th>Date Registered</th>
                                <th>Action</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</div>

<style>
    .ui-autocomplete {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        max-height: 250px;
        width: 100%;
        max-width: 325px;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 5px 0;
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.1);
        z-index: 9999;
    }

    .ui-helper-hidden-accessible {
        position: absolute !important;
        left: -9999px !important;
    }

    /* each item */
    .ui-menu-item-wrapper {
        padding: 10px 15px;
        font-size: 14px;
        cursor: pointer;
        transition: 0.2s;
        text-transform: uppercase;
    }

    /* hover effect */
    .ui-menu-item-wrapper:hover {
        background: #007bff;
        color: #fff;
    }
</style>

<div class="modal fade" id="attendanceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Attendance Verification</h5>
            </div>

            <div class="modal-body text-center">
                <h4 id="modalName"></h4>
                <p id="modalQR"></p>
            </div>

            <div class="modal-footer">
                <button class="btn btn-dark" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteAttendanceModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                Are you sure you want to remove this Employee's Attendance? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <input type="hidden" name="AttendanceT_id_hidden" id="AttendanceT_id_hidden" value="">
                <button type="button" class="btn btn-danger" onclick="deleteAttendanceTraining()">Confirm
                    Delete</button>
            </div>
        </div>
    </div>
</div>