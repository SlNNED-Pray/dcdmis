<?php

require_once __DIR__ . '/../../../includes/function.php';
require_once(root() . '/includes/database/database.php');

function getTrainingAttendees($training_id, $date)
{
    $sql = "SELECT 
        ta.id,
        e.id AS barcode,

        TRIM(
            CONCAT_WS(' ',
                e.first_name,
                NULLIF(CASE WHEN CHAR_LENGTH(TRIM(e.middle_name)) > 0 THEN CONCAT(LEFT(TRIM(e.middle_name), 1), '.') END, ''),
                e.last_name,
                NULLIF(TRIM(e.name_extension), '')
            )
        ) AS fullname,

                p.official_title,
                s.name as school_name,
                ta.control_no,
                ta.created_at,
                ta.img_url,
                ta.status

    FROM training_attendees ta

    LEFT JOIN employees e
        ON e.id = ta.employee_id

    LEFT JOIN station_assignments sa
        ON sa.employee_id = e.id

    LEFT JOIN schools s
        ON s.id = sa.station_id
        
    LEFT JOIN positions p
        ON p.id = sa.position_id

    WHERE ta.training_id = ?
    AND DATE(ta.date_in) = ?

    ORDER BY ta.created_at ASC;";

    return query($sql, [$training_id, $date]);
}

if (isset($_POST['saveTrainingAttendance'])) {

    $training_id = trim((string) ($_POST['training_id'] ?? ''));
    $employee_id = trim((string) ($_POST['employee_id'] ?? ''));
    $date_in = $_POST['date_in'] ?? null;
    $status = $_POST['status'] ?? null;

    if ($training_id === '' || $employee_id === '') {
        echo json_encode([
            "status" => "error",
            "message" => "Invalid request"
        ]);
        exit;
    }

    $emp = find("
        SELECT 
            id,
            TRIM(
                CONCAT_WS(' ',
                    first_name,
                    NULLIF(CASE WHEN CHAR_LENGTH(TRIM(middle_name)) > 0 THEN CONCAT(LEFT(TRIM(middle_name), 1), '.') END, ''),
                    last_name,
                    NULLIF(TRIM(name_extension), '')
                )
            ) AS full_name
        FROM employees
        WHERE id = ?;
    ", [$employee_id]);

    if (!$emp) {
        echo json_encode([
            "status" => "error",
            "message" => "Employee not found. No employee has the ID " . $employee_id . "."
        ]);
        exit;
    }

    $check = find("
        SELECT id
        FROM training_attendees
        WHERE training_id = ?
          AND employee_id = ?
          AND DATE(date_in) = ?
    ", [
        $training_id,
        $employee_id,
        $date_in
    ]);

    if ($check) {
        echo json_encode([
            "status" => "error",
            "message" => "Already marked present"
        ]);
        exit;
    }

    $attendanceId = insert('training_attendees', [
        'training_id' => $training_id,
        'employee_id' => $employee_id,
        'time_in' => date('Y-m-d H:i:s'),
        'date_in' => $date_in,
        'status' => $status,
    ]);

    if (!$attendanceId) {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to save"
        ]);
        exit;
    }

    echo json_encode([
        "status" => "success",
        "message" => "Attendance saved!",
        "employee_name" => $emp['full_name'],
        "employee_id" => (string) $employee_id
    ]);
}


if (isset($_POST['searchEmployeeName']) && $_POST['searchEmployeeName'] === 'true') {
    $search = trim($_POST['search'] ?? '');
    $db = connection();
    $stmt = $db->prepare("
        SELECT 
            id,
            TRIM(
                CONCAT_WS(' ',
                    first_name,
                    NULLIF(CASE WHEN CHAR_LENGTH(TRIM(middle_name)) > 0 THEN CONCAT(LEFT(TRIM(middle_name), 1), '.') END, ''),
                    last_name,
                    NULLIF(TRIM(name_extension), '')
                )
            ) AS fullname
        FROM employees
        WHERE 
            CONCAT_WS(' ',
                first_name,
                NULLIF(CASE WHEN CHAR_LENGTH(TRIM(middle_name)) > 0 THEN CONCAT(LEFT(TRIM(middle_name), 1), '.') END, ''),
                last_name,
                NULLIF(TRIM(name_extension), '')
            ) LIKE ?
        AND `status`='Active' LIMIT 10;
    ");

    $stmt->execute(["%{$search}%"]);

    $employees = $stmt->fetchAll();

    $data = [];

    foreach ($employees as $row) {
        $employeeId = (string) $row['id'];

        $data[] = [
            'label' => $row['fullname'] . ' (' . $employeeId . ')',
            'value' => $row['fullname'],
            'employee_id' => $employeeId
        ];
    }

    echo json_encode($data);
    exit;
}

if (isset($_POST['deleteEmployeeAttendance'])) {

    $id = $_POST['id'] ?? null;

    if (empty($id)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid attendance ID.'
        ]);

        exit;
    }

    $deleted = delete(
        'training_attendees',
        'id = ?',
        [$id]
    );

    if ($deleted !== false) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Attendance deleted successfully.'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to delete attendance.'
        ]);
    }

    exit;
}

//PROGRAM
function getProgramslist()
{
    $sql = "SELECT 
                program_id,
                program_code,
                program_name,
                description,
                created_at,
                updated_at
            FROM programs
            ORDER BY program_name ASC";

    return query($sql);
}

if (isset($_POST['saveProgram'])) {
    $program_id = trim($_POST['program_id'] ?? '');
    $program_code = trim($_POST['program_code'] ?? '');
    $program_name = trim($_POST['program_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($program_name)) {
        echo json_encode([
            "status" => "error",
            "message" => "Program name is required."
        ]);
        exit;
    }

    // UPDATE
    if (!empty($program_id)) {
        $updated = update(
            'programs',
            [
                'program_code' => $program_code,
                'program_name' => $program_name,
                'description' => $description
            ],
            'program_id = ?',
            [$program_id]
        );

        if ($updated === false) {
            echo json_encode([
                "status" => "error",
                "message" => "Failed to update program."
            ]);
            exit;
        }

        echo json_encode([
            "status" => "success",
            "message" => "Program updated successfully."
        ]);
        exit;
    }

    // INSERT
    $programId = insert('programs', [
        'program_code' => $program_code,
        'program_name' => $program_name,
        'description' => $description,
        'created_at' => date('Y-m-d H:i:s')
    ]);

    if (!$programId) {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to save program."
        ]);
        exit;
    }

    echo json_encode([
        "status" => "success",
        "message" => "Program saved successfully.",
        "program_id" => $programId
    ]);
    exit;
}

if (isset($_POST['deleteProgram'])) {
    $program_id = (int) ($_POST['program_id'] ?? 0);

    if ($program_id <= 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid Program ID.'
        ]);
        exit;
    }

    $deleted = delete(
        'programs',
        'program_id = ?',
        [$program_id]
    );

    if ($deleted === false) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to delete program.'
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Program deleted successfully.'
    ]);
    exit;
}

//Program Details / Projects
function getProgram($programid)
{
    return find("SELECT * FROM `programs` WHERE `program_id` = ? LIMIT 1", [$programid]);
}

//Project Details
function getProjectDetail($projectid)
{
    return find("SELECT * FROM `projects` WHERE `project_id` = ? LIMIT 1", [$projectid]);
}

function getProjects($programId)
{
    $sql = "SELECT *
            FROM projects
            WHERE program_id = ?
            ORDER BY project_name ASC";
    return query($sql, [$programId]);
}

function getTrainingByProjects($projectId)
{
    $sql = "SELECT *
            FROM trainings
            WHERE project_id = ?
            ORDER BY created_at ASC";

    return query($sql, [$projectId]);
}

if (isset($_POST['saveProject'])) {
    $project_id = trim($_POST['project_id'] ?? '');
    $program_id = trim($_POST['program_id'] ?? '');
    $project_code = trim($_POST['project_code'] ?? '');
    $project_name = trim($_POST['project_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');

    // Basic validation
    if (empty($program_id)) {
        echo json_encode([
            "status" => "error",
            "message" => "Program ID is required."
        ]);
        exit;
    }

    if (empty($project_name)) {
        echo json_encode([
            "status" => "error",
            "message" => "Project name is required."
        ]);
        exit;
    }

    // UPDATE
    if (!empty($project_id)) {

        $updated = update(
            'projects',
            [
                'program_id' => $program_id,
                'project_code' => $project_code,
                'project_name' => $project_name,
                'description' => $description,
                'start_date' => $start_date,
                'end_date' => $end_date
            ],
            'project_id = ?',
            [$project_id]
        );

        if ($updated === false) {
            echo json_encode([
                "status" => "error",
                "message" => "Failed to update project."
            ]);
            exit;
        }

        echo json_encode([
            "status" => "success",
            "message" => "Project updated successfully."
        ]);
        exit;
    }

    // INSERT
    $newProjectId = insert('projects', [
        'program_id' => $program_id,
        'project_code' => $project_code,
        'project_name' => $project_name,
        'description' => $description,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'created_at' => date('Y-m-d H:i:s')
    ]);

    if (!$newProjectId) {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to save project."
        ]);
        exit;
    }

    echo json_encode([
        "status" => "success",
        "message" => "Project saved successfully.",
        "project_id" => $newProjectId
    ]);
    exit;
}

if (isset($_POST['deleteProject'])) {
    $project_id = (int) ($_POST['project_id'] ?? 0);

    if ($project_id <= 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid Project ID.'
        ]);
        exit;
    }

    $deleted = delete(
        'projects',
        'project_id = ?',
        [$project_id]
    );

    if ($deleted === false) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to delete project.'
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Project deleted successfully.'
    ]);
    exit;
}

if (isset($_POST['sendEmailByAttendance'])) {


}