<?php
// trainings
function training($training_id)
{
    return find("SELECT * FROM `trainings` WHERE `id` = ? LIMIT 1", [$training_id]);
}

function countTrainings($year)
{
    $pattern = "%-{$year}-%";
    $sql = "SELECT * FROM `trainings` WHERE `id` LIKE ?";
    $result = query($sql, [$pattern]);
    return is_array($result) ? count($result) : 0;
}

function createTraining($training_id, $program_id, $project_id, $title, $start_date, $end_date, $number_of_hours, $training_type_id, $training_level_id, $sponsored_by, $venue, $unconsecutive_dates, $signatory_id, $has_certificate, $functional_division_id)
{
    $data = [
        'id' => $training_id,
        'title' => $title,
        'program_id' => $program_id,
        'project_id' => $project_id,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'training_level_id' => $training_level_id,
        'functional_division_id' => $functional_division_id,
        'sponsored_by' => $sponsored_by,
        'venue' => $venue,
        'unconsecutive_dates' => $unconsecutive_dates,
        'training_type_id' => $training_type_id,
        'number_of_hours' => $number_of_hours,
        'signatory_id' => $signatory_id,
        'has_certificate' => $has_certificate
    ];
    return insert('trainings', $data);
}

function updateTraining($training_id, $program_id, $project_id, $title, $start_date, $end_date, $number_of_hours, $training_type_id, $training_level_id, $sponsored_by, $venue, $unconsecutive_dates, $signatory_id, $has_certificate, $functional_division_id)
{
    $data = [
        'title' => $title,
        'program_id' => $program_id,
        'project_id' => $project_id,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'number_of_hours' => $number_of_hours,
        'training_type_id' => $training_type_id,
        'training_level_id' => $training_level_id,
        'sponsored_by' => $sponsored_by,
        'venue' => $venue,
        'unconsecutive_dates' => $unconsecutive_dates,
        'signatory_id' => $signatory_id,
        'has_certificate' => $has_certificate,
        'functional_division_id' => $functional_division_id
    ];
    return update('trainings', $data, '`id` = ?', [$training_id]);
}

function scheduledTrainings()
{
    $results = query("SELECT * FROM `trainings` WHERE `end_date` >= CURDATE() ORDER BY `start_date` ASC, `end_date` ASC");
    return is_array($results) ? $results : [];
}

function conductedTrainings($from_date, $to_date)
{
    $sql = "SELECT * FROM trainings  WHERE `end_date` < CURDATE() AND (`start_date` BETWEEN ? AND ? 
            OR `end_date` BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)) ORDER BY `start_date` DESC";
    return query($sql, [$from_date, $to_date, $from_date, $to_date]);
}

// station_assignments, training_attendees
function trainingParticipants($training_id, $employee_id = null)
{
    $params = [$training_id];
    $filter = "";
    if ($employee_id !== null) {
        $filter = " AND p.`id` = ? ";
        $params[] = $employee_id;
    }
    $sql = "SELECT p.`id`, p.`last_name`, p.`first_name`, p.`middle_name`, p.`name_extension`, 
                p.`sex`, p.`birthdate`, p.`agency_id`, s.`position_id`, 
                s.`station_id`, p.`profile_picture`, p.`email_address`, p.`status` 
            FROM `employees` AS p
            INNER JOIN `training_attendees` AS tp ON p.`id` = tp.`employee_id` 
            LEFT JOIN `station_assignments` AS s ON p.`id` = s.`employee_id` 
            WHERE tp.`training_id` = ? {$filter} 
            GROUP BY p.`id` 
            ORDER BY p.`last_name` ASC, p.`first_name` ASC";
    $results = query($sql, $params);
    return is_array($results) ? $results : [];
}

// training_attendees
function createTrainingParticipant($training_id, $employee_id, $control_no)
{
    $data = [
        'training_id' => $training_id,
        'employee_id' => $employee_id,
        'control_no' => $control_no
    ];

    return insert('training_attendees', $data);
}

function deleteTrainingParticipant($training_id, $employee_id)
{
    return delete('training_attendees', '`training_id` = ? AND `employee_id` = ?', [$training_id, $employee_id]);
}

function deleteParticipantTrainings($employee_id)
{
    return delete('training_attendees', '`employee_id` = ?', [$employee_id]);
}

// trainings
function isConductedTraining($training_id)
{
    $sql = "SELECT `id` FROM `trainings` WHERE `id` = ? AND `start_date` < CURDATE() LIMIT 1";
    $result = find($sql, [$training_id]);
    return !empty($result);
}

// training_attendees
function isTrainingParticipant($training_id, $employee_id)
{
    $sql = "SELECT `id` FROM `training_attendees` WHERE `training_id` = ? AND `employee_id` = ? LIMIT 1";
    $result = find($sql, [$training_id, $employee_id]);
    return !empty($result);
}

// training_types
function trainingTypes()
{
    $results = query("SELECT `id`, `name` FROM `training_types`");
    return is_array($results) ? $results : [];
}

function trainingType($training_type_id)
{
    $result = find("SELECT `name` FROM `training_types` WHERE `id` = ? LIMIT 1", [$training_type_id]);
    return $result ? $result['name'] : '';
}

// training_levels
function trainingSponsors()
{
    return query("SELECT `id`, `name` FROM `training_levels`");
}

function trainingSponsor($training_sponsor_id)
{
    $result = find("SELECT `name` FROM `training_levels` WHERE `id` = ? LIMIT 1", [$training_sponsor_id]);
    return $result ? $result['name'] : '';
}

// trainings, training_attendees, training_types, training_levels
function attendedTrainings($employee_id)
{
    $sql = "SELECT t.`id`, t.`title`, t.`start_date`, t.`end_date`, ts.`name` AS `training_sponsor`, t.`sponsored_by`, 
                t.`venue`, tt.`name` AS `training_type`, t.`number_of_hours`, t.`unconsecutive_dates`, t.`signatory_id`, 
                t.`has_certificate`, tp.`employee_id` 
            FROM `trainings` AS t 
            INNER JOIN `training_attendees` AS tp ON t.`id` = tp.`training_id` 
            INNER JOIN `training_types` AS tt ON t.`training_type_id` = tt.`id` 
            INNER JOIN `training_levels` AS ts ON t.`training_level_id` = ts.`id` 
            WHERE tp.`employee_id` = ? ORDER BY t.`end_date` DESC";

    $results = query($sql, [$employee_id]);
    return is_array($results) ? $results : [];
}

function attendedTraining($training_id, $employee_id)
{
    $sql = "SELECT t.`id`, t.`title`, t.`start_date`, t.`end_date`, ts.`name`, t.`sponsored_by`, 
                t.`venue`, tt.`name`, t.`number_of_hours`, t.`unconsecutive_dates`, t.`signatory_id`, 
                t.`has_certificate`, tp.`employee_id`, tp.`control_no` 
            FROM `trainings` AS t 
            INNER JOIN `training_attendees` AS tp ON t.`id` = tp.`training_id` 
            INNER JOIN `training_types` AS tt ON t.`training_type_id` = tt.`id` 
            INNER JOIN `training_levels` AS ts ON t.`training_level_id` = ts.`id` 
            WHERE t.`id` = ? AND tp.`employee_id` = ? LIMIT 1";
    return find($sql, [$training_id, $employee_id]);
}

// trainings
function conductedTrainingsByYear($limit = null)
{
    $sql = "SELECT YEAR(`end_date`) AS `name`, COUNT(*) AS `count` FROM `trainings` 
            WHERE `end_date` IS NOT NULL GROUP BY YEAR(`end_date`) ORDER BY `name` DESC";
    if ($limit !== null) {
        $sql .= " LIMIT " . (int) $limit;
    }
    return query($sql);
}

// trainings, training_attendees
function trainedEmployeesByYear($limit = null)
{
    $sql = "SELECT YEAR(t.`end_date`) AS `name`, COUNT(DISTINCT tp.`employee_id`) AS `count` 
            FROM `trainings` AS t 
            INNER JOIN `training_attendees` AS tp ON t.`id` = tp.`training_id` 
            WHERE t.`end_date` IS NOT NULL
            GROUP BY YEAR(t.`end_date`) 
            ORDER BY `name` DESC";
    if ($limit !== null) {
        $sql .= " LIMIT " . (int) $limit;
    }
    return query($sql);
}

function trainedEmployeesForYear($year = null)
{
    $params = [];
    $where = "WHERE t.`end_date` IS NOT NULL";
    if ($year !== null && $year !== '' && strtolower((string)$year) !== 'all') {
        $where .= " AND YEAR(t.`end_date`) = ?";
        $params[] = (int) $year;
    }

    $sql = "SELECT p.`id`, p.`last_name`, p.`first_name`, p.`middle_name`, p.`name_extension`, 
                   p.`sex`, p.`birthdate`, p.`agency_id`, p.`profile_picture`, p.`email_address`, p.`status`,
                   s.`position_id`, s.`station_id`,
                   COUNT(DISTINCT tp.`training_id`) AS `training_count` 
            FROM `employees` AS p
            INNER JOIN `training_attendees` AS tp ON p.`id` = tp.`employee_id`
            INNER JOIN `trainings` AS t ON tp.`training_id` = t.`id`
            LEFT JOIN `station_assignments` AS s ON p.`id` = s.`employee_id`
            {$where}
            GROUP BY p.`id` 
            ORDER BY `training_count` DESC, p.`last_name` ASC, p.`first_name` ASC";

    $results = query($sql, $params);
    return is_array($results) ? $results : [];
}

function getPrograms()
{
    $sql = "SELECT * FROM `programs`";
    return query($sql);
}

function getProjectList()
{
    $sql = "SELECT * FROM `projects`";
    return query($sql);
}

function getProgramByID($progID)
{
    if (empty($progID)) {
        return null;
    }
    return find("SELECT * FROM `programs` WHERE `program_id` = ? LIMIT 1", [$progID]);
}

function getProjectByID($projID)
{
    if (empty($projID)) {
        return null;
    }
    return find("SELECT * FROM `projects` WHERE `project_id` = ? LIMIT 1", [$projID]);
}