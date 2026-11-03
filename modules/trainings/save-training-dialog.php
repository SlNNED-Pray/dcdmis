<?php
// modules/trainings/save-training-dialog.php
require_once('../../includes/function.php');
require_once(root() . '/includes/string.php');
require_once(root() . '/includes/database/database.php');
require_once(root() . '/includes/database/section.php');
require_once(root() . '/includes/database/learning-development.php');
require_once(root() . '/includes/layout/components.php');

$trainingId = isset($_GET['id']) ? sanitize(decipher($_GET['id'])) : null;
$training = training($trainingId);
$title = $hours = $trainingType = $trainingLevel = $sponsorName = $venue = $unconsecutiveDates = $functionalDivisionId = '';
$dateFrom = $dateTo = date('Y-m-d');
$generateCertificate = false;
$modalTitle = 'Add Training';
$notFound = true;
$programs = getPrograms();
$projects = getProjectList();
$project_id = null;
$program_id = null;

if ($training) {
    $trainingId = $training['id'];
    $title = $training['title'];
    $dateFrom = $training['start_date'];
    $dateTo = $training['end_date'];
    $hours = $training['number_of_hours'];
    $trainingType = $training['training_type_id'];
    $trainingLevel = $training['training_level_id'];
    $sponsorName = $training['sponsored_by'];
    $functionalDivisionId = empty($training['functional_division_id']) ? null : $training['functional_division_id'];
    $venue = $training['venue'];
    $unconsecutiveDates = $training['unconsecutive_dates'];
    $generateCertificate = $training['has_certificate'] === 1;
    $modalTitle = 'Edit Training';
    $project_id = $training['project_id'];
    $program_id = $training['program_id'] ?? null;
    $notFound = false;
}
?>

<div class="modal-dialog">
    <div class="modal-content">
        <?php modalHeader($modalTitle) ?>

        <form action="" method="POST">
            <?= csrf_field(); ?>
            <input type="hidden" name="program_id" id="program_id" value="<?= e($program_id) ?>">
            <div class="modal-body">
                <?php if (!$notFound): ?>
                    <div class="form-group">
                        <label for="code" class="mb-0">Code <?php showAsterisk() ?></label>
                        <input type="text" id="code" class="form-control text-uppercase" value="<?= e($trainingId) ?>"
                            disabled>
                    </div>
                <?php endif ?>

                <div class="form-group">
                    <label>Project</label>
                    <select id="project_id" class="form-control" name="project_id">
                        <option value="">Select Project</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= $project['project_id'] ?>" data-program-id="<?= $project['program_id'] ?>"
                                <?= setOptionSelected($project['project_id'], $project_id) ?>>
                                <?= e($project['project_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <script>
                    document.getElementById('project_id')?.addEventListener('change', function () {
                        var selectedOption = this.options[this.selectedIndex];
                        var progId = selectedOption ? selectedOption.getAttribute('data-program-id') : '';
                        var progInput = document.getElementById('program_id');
                        if (progInput) {
                            progInput.value = progId || '';
                        }
                    });
                </script>


                <div class="form-group">
                    <label for="title" class="mb-0">Title <?php showAsterisk() ?></label>
                    <textarea id="title" name="title" class="form-control" rows="3" placeholder="Type title..."
                        title="Type training title..." required><?= e($title) ?></textarea>
                </div>

                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="from" class="mb-0">Date from <?php showAsterisk() ?></label>
                            <input type="date" name="from" id="from" class="form-control"
                                title="Set training start date..." value="<?= e($dateFrom) ?>" required>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="from" class="mb-0">Date to <?php showAsterisk() ?></label>
                            <input type="date" name="to" id="to" class="form-control" title="Set training end date..."
                                value="<?= e($dateTo) ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="unconsecutive-dates" class="mb-0">For non-consecutive days, please specify</label>
                    <input type="text" name="unconsecutive-dates" id="unconsecutive-dates"
                        placeholder="Type non-consecutive days..."
                        title="Type training date for non-consecutive days, Leave blank if not applicable..."
                        class="form-control" value="<?= e($unconsecutiveDates) ?>">
                </div>

                <div class="row">
                    <div class="col-4">
                        <div class="form-group">
                            <label for="hours" class="mb-0">Number of hours <?php showAsterisk() ?></label>
                            <input type="number" name="hours" id="hours" class="form-control"
                                title="Set training number of hours..." value="<?= e($hours) ?>" required>
                        </div>
                    </div>

                    <div class="col-8">
                        <div class="form-group">
                            <label for="type" class="mb-0">Type <?php showAsterisk() ?></label>
                            <select id="type" name="type" class="form-control" title="Select training type..." required>
                                <option value="">Select type...</option>
                                <?php
                                $types = trainingTypes();
                                foreach ($types as $type): ?>
                                    <option value="<?= e($type['id']) ?>" <?= setOptionSelected($type['id'], $trainingType) ?>><?= e($type['name']) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-4">
                        <div class="form-group">
                            <label for="level" class="mb-0">Level <?php showAsterisk() ?></label>
                            <select id="level" name="level" class="form-control" title="Select training level..."
                                required>
                                <option value="">Select level...</option>
                                <?php
                                $sponsors = trainingSponsors();
                                foreach ($sponsors as $sponsor): ?>
                                    <option value="<?= e($sponsor['id']) ?>" <?= setOptionSelected($sponsor['id'], $trainingLevel) ?>><?= e($sponsor['name']) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-8">
                        <div class="form-group">
                            <label for="sponsor" class="mb-0">Sponsor <?php showAsterisk() ?></label>
                            <input type="text" id="sponsor" name="sponsor" class="form-control"
                                placeholder="Type sponsor..." title="Type training sponsor..."
                                value="<?= e($sponsorName) ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="functional-division" class="mb-0">Functional Division <?php showAsterisk() ?></label>
                    <select id="functional-division" name="functional-division" class="form-control"
                        title="Select training functional division..." required>
                        <option value="">Select functional division...</option>
                        <?php
                        $functionalDivisions = functionalDivisions();
                        foreach ($functionalDivisions as $functionalDivision): ?>
                            <option value="<?= e($functionalDivision['id']) ?>"
                                <?= setOptionSelected($functionalDivision['id'], $functionalDivisionId) ?>>
                                <?= e($functionalDivision['name']) ?>
                            </option>
                        <?php endforeach ?>
                        <option values="n/a">Not applicable</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="venue" class="mb-0">Venue <?php showAsterisk() ?></label>
                    <input id="venue" name="venue" type="text" class="form-control" placeholder="Type venue..."
                        title="Type training venue..." value="<?= e($venue) ?>" required>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" id="has-certificate" type="checkbox" name="has-certificate"
                        <?= setItemChecked($generateCertificate) ?>>
                    <label class="form-check-label" for="has-certificate">Generate certificate</label>
                </div>

                <?php requiredLegend(0) ?>
            </div>

            <div class="modal-footer">
                <input type="hidden" name="verifier" value="<?= e($_GET['id'] ?? '') ?>">
                <button class="btn btn-primary" name="save-training" type="submit">Continue</button>
                <?php cancelModalButton() ?>
            </div>
        </form>
    </div>
</div>