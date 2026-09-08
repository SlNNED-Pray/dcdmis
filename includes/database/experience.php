<?php
// service_records

function experiences($employee_id)
{
    $sql = "SELECT * FROM `service_records` WHERE `employee_id` = ? ORDER BY `from_date` DESC, `to_date` DESC";
    $results = query($sql, [$employee_id]);
    return is_array($results) ? $results : [];
}

function experience($employee_id, $service_record_id)
{
    $sql = "SELECT * FROM `service_records` WHERE `employee_id` = ? AND `id` = ? LIMIT 1";
    return find($sql, [$employee_id, $service_record_id]);
}

function createExperience($from_date, $to_date, $is_present, $designation, $plantilla_item_id, $appointment_status, $is_government_service, $salary_grade_step_increment, $monthly_salary, $agency_company, $leave_wo_pay, $for_separation, $separation_date, $separation_cause, $employee_id)
{
    $data = [
        'from_date' => $from_date,
        'to_date' => $to_date,
        'is_present' => $is_present,
        'designation' => $designation,
        'plantilla_item_id' => $plantilla_item_id,
        'appointment_status' => $appointment_status,
        'is_government_service' => $is_government_service,
        'salary_grade_step_increment' => $salary_grade_step_increment,
        'monthly_salary' => $monthly_salary,
        'agency_company' => $agency_company,
        'leave_wo_pay' => $leave_wo_pay,
        'for_separation' => $for_separation,
        'separation_date' => $separation_date,
        'separation_cause' => $separation_cause,
        'employee_id' => $employee_id
    ];
    return insert('service_records', $data);
}

function updateExperience($from_date, $to_date, $is_present, $designation, $plantilla_item_id, $appointment_status, $is_government_service, $salary_grade_step_increment, $monthly_salary, $agency_company, $leave_wo_pay, $for_separation, $separation_date, $separation_cause, $employee_id, $service_record_id)
{
    $data = [
        'from_date' => $from_date,
        'to_date' => $to_date,
        'is_present' => $is_present,
        'designation' => $designation,
        'plantilla_item_id' => $plantilla_item_id,
        'appointment_status' => $appointment_status,
        'is_government_service' => $is_government_service,
        'salary_grade_step_increment' => $salary_grade_step_increment,
        'monthly_salary' => $monthly_salary,
        'agency_company' => $agency_company,
        'leave_wo_pay' => $leave_wo_pay,
        'for_separation' => $for_separation,
        'separation_date' => $separation_date,
        'separation_cause' => $separation_cause
    ];
    return update('service_records', $data, '`employee_id` = ? AND `id` = ?', [$employee_id, $service_record_id]);
}

function deleteExperience($employee_id, $service_record_id)
{
    return delete('service_records', '`employee_id` = ? AND `id` = ?', [$employee_id, $service_record_id]);
}

function deleteExperiences($employee_id)
{
    return delete('service_records', '`employee_id` = ?', [$employee_id]);
}

function governmentService($id)
{
    $sql = "SELECT * FROM `service_records` WHERE `employee_id` = ? AND `is_government_service` = '1' ORDER BY `from_date` DESC";
    return query($sql, [$id]);
}

function activePlantillaExperience($employeeId)
{
    $sql = "SELECT sr.*, pi.`item_number`, pi.`position_id` 
            FROM `service_records` AS sr
            LEFT JOIN `plantilla_items` AS pi ON sr.`plantilla_item_id` = pi.`id`
            WHERE sr.`employee_id` = ? AND sr.`is_present` = 1 AND sr.`to_date` IS NULL 
            ORDER BY sr.`from_date` DESC LIMIT 1";
    return find($sql, [$employeeId]);
}