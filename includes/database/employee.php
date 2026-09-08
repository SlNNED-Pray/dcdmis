<?php
// employees & non_regular_employees
function employee($employee_id)
{
    if (empty($employee_id)) {
        return null;
    }
    $emp = find("SELECT * FROM `employees` WHERE `id` = ? LIMIT 1", [$employee_id]);
    if ($emp) {
        return $emp;
    }
    return find("SELECT * FROM `non_regular_employees` WHERE `id` = ? LIMIT 1", [$employee_id]);
}

function findEmployeeByEmail($email)
{
    $email = trim((string) $email);
    if (empty($email)) {
        return null;
    }
    $data = find("SELECT `id` FROM `employees` WHERE `status` <> 'Duplicate' AND LOWER(`email_address`) = LOWER(?) LIMIT 1", [$email]);
    if ($data) {
        return $data['id'];
    }
    $data = find("SELECT `id` FROM `non_regular_employees` WHERE `status` = 'Active' AND LOWER(`email_address`) = LOWER(?) LIMIT 1", [$email]);
    return $data ? $data['id'] : null;
}

// employees, station_assignments
function employees()
{
    $sql = "SELECT p.`id`, p.`last_name`, p.`first_name`, p.`middle_name`, p.`name_extension`, 
                p.`sex`, s.`position_id`, s.`station_id`, p.`profile_picture`, p.`status`
            FROM `employees` AS p
            INNER JOIN `station_assignments` AS s ON p.`id` = s.`employee_id` 
            WHERE p.`status` <> 'Duplicate' ORDER BY p.`last_name` ASC";
    $results = query($sql);
    return is_array($results) ? $results : [];
}

// employees & non_regular_employees
function employeeName($last_name, $first_name, $middle_name, $name_extension)
{
    $sql = "SELECT `id`, `last_name`, `first_name`, `middle_name`, `name_extension` FROM `employees` 
            WHERE `last_name` = ? AND `first_name` = ? AND `middle_name` = ? AND `name_extension` = ? LIMIT 1";
    $emp = find($sql, [$last_name, $first_name, $middle_name, $name_extension]);
    if ($emp) {
        return $emp;
    }
    $sqlNon = "SELECT `id`, `last_name`, `first_name`, `middle_name`, `name_extension` FROM `non_regular_employees` 
               WHERE `last_name` = ? AND `first_name` = ? AND `middle_name` = ? AND `name_extension` = ? LIMIT 1";
    return find($sqlNon, [$last_name, $first_name, $middle_name, $name_extension]);
}

function employeeContactDetails($employee_id)
{
    $data = find("SELECT `id`, `email_address`, `alternate_email_address`, `telephone`, `mobile_number`, `alternate_mobile_number` FROM `employees` WHERE `id` = ? LIMIT 1", [$employee_id]);
    if ($data) {
        return $data;
    }
    return find("SELECT `id`, `email_address`, '' AS `alternate_email_address`, '' AS `telephone`, `mobile_number`, '' AS `alternate_mobile_number` FROM `non_regular_employees` WHERE `id` = ? LIMIT 1", [$employee_id]);
}

function updateEmployeeContactDetails($alternate_mobile_number, $alternate_email_address, $employee_id)
{
    $data = [
        'alternate_mobile_number' => $alternate_mobile_number,
        'alternate_email_address' => $alternate_email_address
    ];
    return update('employees', $data, '`id` = ?', [$employee_id]);
}

// employees, station_assignments
function districtSupervisors($supervisorRoles = ['PSDS', 'SDS'])
{
    $placeholders = implode(',', array_fill(0, count($supervisorRoles), '?'));
    $sql = "SELECT p.`id`, p.`last_name`, p.`first_name`, p.`middle_name`, 
                p.`name_extension`, s.`position_id` 
            FROM `employees` AS p   
            INNER JOIN `station_assignments` AS s ON p.`id` = s.`employee_id` 
            WHERE p.`status` = 'Active' AND s.`position_id` IN ($placeholders) 
            ORDER BY p.`last_name` ASC";
    return query($sql, $supervisorRoles);
}

function activeEmployees($station_id = null)
{
    $params = [];
    $whereClause = "WHERE p.`status` = 'Active'";
    if ($station_id !== null) {
        $whereClause .= " AND s.`station_id` = ?";
        $params[] = $station_id;
    }
    $sql = "SELECT p.`id`, p.`last_name`, p.`first_name`, p.`middle_name`, p.`name_extension`, 
                p.`sex`, p.`birthdate`, p.`agency_id`, 
                s.`position_id`, s.`station_id`, p.`profile_picture`, 
                p.`email_address`, p.`mobile_number` 
            FROM `employees` AS p
            INNER JOIN `station_assignments` AS s ON p.`id` = s.`employee_id` {$whereClause} 
            ORDER BY p.`last_name` ASC";
    $results = query($sql, $params);
    return is_array($results) ? $results : [];
}

function countActiveEmployees($station_id = null)
{
    $params = [];
    $whereClause = "WHERE p.`status` = 'Active'";
    if ($station_id !== null) {
        $whereClause .= " AND s.`station_id` = ?";
        $params[] = $station_id;
    }
    $sql = "SELECT COUNT(*) AS `count` FROM `employees` AS p
            INNER JOIN `station_assignments` AS s ON p.`id` = s.`employee_id` {$whereClause}";
    $result = find($sql, $params);
    return (int) ($result['count'] ?? 0);
}

function retirableEmployees($station_id = null)
{
    $retirementAge = 60;
    $params = [$retirementAge];

    $sql = "SELECT p.id, p.last_name, p.first_name, p.middle_name, p.name_extension, 
                   p.sex, p.birthdate, p.agency_id, p.profile_picture,
                   TIMESTAMPDIFF(YEAR, p.birthdate, CURDATE()) AS exact_age, 
                   s.position_id, s.station_id 
            FROM employees AS p 
            INNER JOIN station_assignments AS s ON p.id = s.employee_id 
            WHERE p.status = 'Active' 
            AND TIMESTAMPDIFF(YEAR, p.birthdate, CURDATE()) >= ?";

    if ($station_id !== null) {
        $sql .= " AND s.station_id = ?";
        $params[] = $station_id;
    }

    $sql .= " ORDER BY p.birthdate ASC, p.last_name ASC";

    $results = query($sql, $params);
    return is_array($results) ? $results : [];
}

function countRetirableEmployees($station_id = null)
{
    $currentYear = (int) date('Y');
    $retirementAge = 60;
    $birthYearLimit = $currentYear - $retirementAge;
    $params = [$birthYearLimit];
    $sql = "SELECT COUNT(*) AS `count` FROM `employees` AS p
            INNER JOIN `station_assignments` AS s ON p.`id` = s.`employee_id`
            WHERE p.`status` = 'Active' AND YEAR(p.`birthdate`) <= ?";
    if ($station_id !== null) {
        $sql .= " AND s.`station_id` = ?";
        $params[] = $station_id;
    }
    $result = find($sql, $params);
    return (int) ($result['count'] ?? 0);
}

function archivedEmployees($activeStatuses = ['Active', 'Registered'])
{
    $placeholders = implode(',', array_fill(0, count($activeStatuses), '?'));
    $sql = "SELECT p.`id`, p.`last_name`, p.`first_name`, p.`middle_name`, p.`name_extension`, 
                p.`sex`, p.`birthdate`, p.`agency_id`, 
                s.`position_id`, s.`station_id`, p.`profile_picture`, p.`status`
            FROM `employees` AS p
            INNER JOIN `station_assignments` AS s ON p.`id` = s.`employee_id` 
            WHERE p.`status` NOT IN ($placeholders) 
            ORDER BY p.`last_name` ASC";
    $results = query($sql, $activeStatuses);
    return is_array($results) ? $results : [];
}

function employeeSearch($text)
{
    $text = trim($text);
    if ($text === '') {
        return [];
    }

    $whereClauses = [];
    $params = [];
    $idFields = [
        'p.id',
        'p.agency_id',
        'p.gsis_crn',
        'p.pagibig',
        'p.philhealth',
        'p.sss',
        'p.tin'
    ];

    $idConditions = [];
    foreach ($idFields as $field) {
        $idConditions[] = "{$field} = ?";
        $params[] = $text;
    }

    $whereClauses[] = '(' . implode(' OR ', $idConditions) . ')';
    $cleanText = str_replace([',', '.'], ' ', $text);
    $terms = preg_split('/\s+/', $cleanText, -1, PREG_SPLIT_NO_EMPTY);

    if (!empty($terms)) {
        $nameConditions = [];

        foreach ($terms as $term) {
            $nameConditions[] = "(
                p.first_name LIKE ? 
                OR p.middle_name LIKE ? 
                OR p.last_name LIKE ? 
                OR p.name_extension LIKE ?
            )";

            $wildcard = "%{$term}%";
            array_push($params, $wildcard, $wildcard, $wildcard, $wildcard);
        }

        $whereClauses[] = '(' . implode(' AND ', $nameConditions) . ')';
    }

    $sql = "SELECT 
                p.`id`, p.`last_name`, p.`first_name`, p.`middle_name`, p.`name_extension`,
                p.`sex`, p.`birthdate`, p.`agency_id`, 
                s.`position_id`, s.`station_id`, p.`profile_picture`, p.`status` 
            FROM `employees` AS p 
            INNER JOIN `station_assignments` AS s ON p.`id` = s.`employee_id` 
            WHERE " . implode(' OR ', $whereClauses) . "
            ORDER BY p.`last_name` ASC, p.`first_name` ASC, p.`middle_name` ASC";
    $results = query($sql, $params);
    return is_array($results) ? $results : [];
}

// employees
function employeeGender()
{
    $sql = "SELECT `sex` AS `name`, COUNT(*) AS `count` FROM `employees` 
            WHERE `status` = 'Active' GROUP BY `sex` ORDER BY `sex` DESC";
    return query($sql);
}

// station_assignments, schools, employees, districts
function employeeStation()
{
    $sql = "SELECT s.`name`, COUNT(p.`id`) AS `count` FROM `station_assignments` AS sa 
            INNER JOIN `schools` AS s ON sa.`station_id` = s.`id` 
            INNER JOIN `employees` AS p ON p.`id` = sa.`employee_id` 
            INNER JOIN `districts` AS d ON s.`district_id` = d.`id` 
            WHERE p.`status` = 'Active' GROUP BY s.`id`, s.`name`, s.`name` 
            ORDER BY s.`name` ASC, s.`name` ASC";
    return query($sql);
}

// station_assignments, positions, employees
function employeePosition()
{
    $sql = "SELECT pos.`official_title` AS `name`, COUNT(p.`id`) AS `count` FROM `station_assignments` AS sa 
            INNER JOIN `positions` AS pos ON sa.`position_id` = pos.`id` 
            INNER JOIN `employees` AS p ON p.`id` = sa.`employee_id` 
            WHERE p.`status` = 'Active' GROUP BY pos.`id`, pos.`official_title`, pos.`salary_grade` 
            ORDER BY pos.`salary_grade` DESC, pos.`official_title` ASC";
    return query($sql);
}

function districtEmployee()
{
    $sql = "SELECT d.`name`, COUNT(p.`id`) AS `count` 
            FROM `station_assignments` AS sa 
            INNER JOIN `schools` AS s ON sa.`station_id` = s.`id` 
            INNER JOIN `employees` AS p ON p.`id` = sa.`employee_id` 
            INNER JOIN `districts` AS d ON s.`district_id` = d.`id` 
            WHERE p.`status` = 'Active' AND d.`id` <> 'SDO' GROUP BY d.`id`, d.`name` ORDER BY d.`name` ASC";
    return query($sql);
}

function employeeCategory()
{
    $sql = "SELECT pos.`category` AS `name`, 
                COUNT(p.`id`) AS `count` 
            FROM `positions` AS pos
            INNER JOIN `station_assignments` AS sa ON sa.`position_id` = pos.`id` 
            INNER JOIN `employees` AS p ON p.`id` = sa.`employee_id` 
            WHERE p.`status` = 'Active' GROUP BY pos.`category` ORDER BY pos.`category` ASC";
    return query($sql);
}

function employeeGenderCategory()
{
    $sql = "SELECT pos.`category` AS `name`, 
                SUM(CASE WHEN p.`sex` = 'Male' THEN 1 ELSE 0 END) AS `male`, 
                SUM(CASE WHEN p.`sex` = 'Female' THEN 1 ELSE 0 END) AS `female`,
                COUNT(p.`id`) AS `total`
            FROM `positions` AS pos 
            INNER JOIN `station_assignments` AS sa ON sa.`position_id` = pos.`id` 
            INNER JOIN `employees` AS p ON p.`id` = sa.`employee_id` 
            WHERE p.`status` = 'Active' GROUP BY pos.`category` ORDER BY pos.`category` ASC";
    return query($sql);
}

function celebrantEmployees($month, $station_id = null)
{
    if ($month < 1 || $month > 12) {
        return [];
    }
    $params = [(int) $month];
    $sql = "SELECT p.`id`, p.`last_name`, p.`first_name`, p.`middle_name`, p.`name_extension`, 
                   p.`sex`, p.`birthdate`, p.`agency_id`, 
                   TIMESTAMPDIFF(YEAR, p.`birthdate`, CURDATE()) AS `current_age`, 
                   sa.`position_id`, sa.`station_id`, p.`profile_picture` 
            FROM `employees` AS p 
            INNER JOIN `station_assignments` AS sa ON p.`id` = sa.`employee_id` 
            WHERE p.`status` = 'Active' 
              AND MONTH(p.`birthdate`) = ?";
    if ($station_id !== null) {
        $sql .= " AND sa.`station_id` = ?";
        $params[] = $station_id;
    }
    $sql .= " ORDER BY DAY(p.`birthdate`) ASC, p.`last_name` ASC";
    $results = query($sql, $params);
    return is_array($results) ? $results : [];
}

function createEmployee($employee_id, $last_name, $first_name, $middle_name, $name_extension, $sex, $birthdate, $email_address, $mobile_number, $profile_picture, $status, $gsis_crn = '', $gsis_bp = '', $pagibig = '', $philhealth = '', $tin = '', $agency_id = '')
{
    $data = [
        'id' => $employee_id,
        'last_name' => $last_name,
        'first_name' => $first_name,
        'middle_name' => $middle_name,
        'name_extension' => $name_extension,
        'sex' => $sex,
        'birthdate' => $birthdate,
        'email_address' => $email_address,
        'mobile_number' => $mobile_number,
        'profile_picture' => $profile_picture,
        'status' => $status,
        'gsis_crn' => $gsis_crn,
        'gsis_bp' => $gsis_bp,
        'pagibig' => $pagibig,
        'philhealth' => $philhealth,
        'tin' => $tin,
        'agency_id' => $agency_id
    ];
    return insert('employees', $data);
}

function updateEmployee($last_name, $first_name, $middle_name, $name_extension, $birthdate, $place_of_birth, $sex, $civil_status, $specify_other_civil_status, $religion_id, $specify_other_religion, $ethnic_group_id, $specify_other_ethnic_group, $citizenship_id, $dual_citizenship_type, $dual_citizenship_country_id, $residence_lot, $residence_street, $residence_subdivision, $residence_barangay, $residence_city, $residence_province, $residence_zip, $permanent_lot, $permanent_street, $permanent_subdivision, $permanent_barangay, $permanent_city, $permanent_province, $permanent_zip, $height, $weight, $blood_type, $umid, $crn, $bp, $pagibig, $philhealth, $philsys, $sss, $telephone, $mobile, $email, $tin, $agency_id, $prc, $photo, $id)
{
    $data = [
        'last_name' => $last_name,
        'first_name' => $first_name,
        'middle_name' => $middle_name,
        'name_extension' => $name_extension,
        'birthdate' => $birthdate,
        'place_of_birth' => $place_of_birth,
        'sex' => $sex,
        'civil_status' => $civil_status,
        'specify_other_civil_status' => $specify_other_civil_status,
        'religion_id' => $religion_id,
        'specify_other_religion' => $specify_other_religion,
        'ethnic_group_id' => $ethnic_group_id,
        'specify_other_ethnic_group' => $specify_other_ethnic_group,
        'citizenship_id' => $citizenship_id,
        'dual_citizenship_type' => $dual_citizenship_type,
        'dual_citizenship_country_id' => $dual_citizenship_country_id,
        'residence_lot' => $residence_lot,
        'residence_street' => $residence_street,
        'residence_subdivision' => $residence_subdivision,
        'residence_barangay' => $residence_barangay,
        'residence_city' => $residence_city,
        'residence_province' => $residence_province,
        'residence_zip' => $residence_zip,
        'permanent_lot' => $permanent_lot,
        'permanent_street' => $permanent_street,
        'permanent_subdivision' => $permanent_subdivision,
        'permanent_barangay' => $permanent_barangay,
        'permanent_city' => $permanent_city,
        'permanent_province' => $permanent_province,
        'permanent_zip' => $permanent_zip,
        'height' => $height,
        'weight' => $weight,
        'blood_type' => $blood_type,
        'umid_id' => $umid,
        'gsis_crn' => $crn,
        'gsis_bp' => $bp,
        'pagibig' => $pagibig,
        'philhealth' => $philhealth,
        'philsys' => $philsys,
        'sss' => $sss,
        'telephone' => $telephone,
        'mobile_number' => $mobile,
        'email_address' => $email,
        'tin' => $tin,
        'agency_id' => $agency_id,
        'prc' => $prc,
        'profile_picture' => $photo
    ];
    return update('employees', $data, '`id` = ?', [$id]);
}

function isDuplicateEmployee($id)
{
    $result = find("SELECT `id` FROM `employees` WHERE `id` = ? AND `status` = 'Duplicate' LIMIT 1", [$id]);
    return !empty($result);
}

function deleteEmployee($id)
{
    return delete('employees', '`id` = ?', [$id]);
}

function updateEmployeeStatus($status, $id)
{
    $data = [
        'status' => $status
    ];
    return update('employees', $data, '`id` = ?', [$id]);
}

function updateProfessionalTitles($name_prefix, $name_suffix, $id)
{
    $data = [
        'name_prefix' => $name_prefix,
        'name_suffix' => $name_suffix
    ];
    return update('employees', $data, '`id` = ?', [$id]);
}

function updateProfilePhoto($photo, $id)
{
    $data = [
        'profile_picture' => $photo
    ];
    return update('employees', $data, '`id` = ?', [$id]);
}

function employeePositions()
{
    $sql = "SELECT pos.`official_title`, 
                SUM(CASE WHEN p.`sex` = 'Male' THEN 1 ELSE 0 END) AS `male`, 
                SUM(CASE WHEN p.`sex` = 'Female' THEN 1 ELSE 0 END) AS `female`, 
                COUNT(p.id) AS `total` 
            FROM `employees` AS p
            INNER JOIN `station_assignments` AS sa ON p.`id` = sa.`employee_id` 
            INNER JOIN `positions` AS pos ON sa.`position_id` = pos.`id` 
            WHERE p.`status` = 'Active' 
            GROUP BY pos.`id`, pos.`official_title`, pos.`salary_grade` 
            ORDER BY pos.`salary_grade` DESC, pos.`official_title` ASC";
    return query($sql);
}

function demographicsGender()
{
    $sql = "SELECT `sex` AS `name`, 
                SUM(CASE WHEN `sex` = 'Male' THEN 1 ELSE 0 END) AS `male`, 
                SUM(CASE WHEN `sex` = 'Female' THEN 1 ELSE 0 END) AS `female`, 
                COUNT(*) AS `total`, COUNT(*) AS `count`
            FROM `employees` 
            WHERE `status` = 'Active' 
            GROUP BY `sex` 
            ORDER BY `sex` DESC";
    return query($sql);
}

function demographicsCategory()
{
    $sql = "SELECT pos.`category` AS `name`, 
                SUM(CASE WHEN p.`sex` = 'Male' THEN 1 ELSE 0 END) AS `male`, 
                SUM(CASE WHEN p.`sex` = 'Female' THEN 1 ELSE 0 END) AS `female`, 
                COUNT(p.`id`) AS `total`, COUNT(p.`id`) AS `count`
            FROM `positions` AS pos
            INNER JOIN `station_assignments` AS sa ON sa.`position_id` = pos.`id` 
            INNER JOIN `employees` AS p ON p.`id` = sa.`employee_id` 
            WHERE p.`status` = 'Active' 
            GROUP BY pos.`category` 
            ORDER BY pos.`category` ASC";
    return query($sql);
}

function demographicsPosition()
{
    $sql = "SELECT pos.`official_title` AS `name`, 
                SUM(CASE WHEN p.`sex` = 'Male' THEN 1 ELSE 0 END) AS `male`, 
                SUM(CASE WHEN p.`sex` = 'Female' THEN 1 ELSE 0 END) AS `female`, 
                COUNT(p.id) AS `total`, COUNT(p.id) AS `count`
            FROM `employees` AS p
            INNER JOIN `station_assignments` AS sa ON p.`id` = sa.`employee_id` 
            INNER JOIN `positions` AS pos ON sa.`position_id` = pos.`id` 
            WHERE p.`status` = 'Active' 
            GROUP BY pos.`id`, pos.`official_title`, pos.`salary_grade` 
            ORDER BY pos.`salary_grade` DESC, pos.`official_title` ASC";
    return query($sql);
}

function demographicsGeneration()
{
    $sql = "SELECT 
                CASE 
                    WHEN YEAR(p.birthdate) BETWEEN 1946 AND 1964 THEN 'Baby Boomers (1946-1964)'
                    WHEN YEAR(p.birthdate) BETWEEN 1965 AND 1980 THEN 'Generation X (1965-1980)'
                    WHEN YEAR(p.birthdate) BETWEEN 1981 AND 1996 THEN 'Generation Y / Millennials (1981-1996)'
                    WHEN YEAR(p.birthdate) BETWEEN 1997 AND 2012 THEN 'Generation Z (1997-2012)'
                    WHEN YEAR(p.birthdate) >= 2013 THEN 'Generation Alpha (2013-Present)'
                    ELSE 'Silent Generation / Other'
                END AS `name`,
                SUM(CASE WHEN p.sex = 'Male' THEN 1 ELSE 0 END) AS male,
                SUM(CASE WHEN p.sex = 'Female' THEN 1 ELSE 0 END) AS female,
                COUNT(*) AS total,
                COUNT(*) AS count
            FROM employees p
            WHERE p.status = 'Active' AND p.birthdate IS NOT NULL AND p.birthdate <> '0000-00-00'
            GROUP BY `name`
            ORDER BY MIN(p.birthdate) ASC";
    return query($sql);
}

function demographicsEducation()
{
    $sql = "SELECT 
                CASE highest_val
                    WHEN 6 THEN 'Doctoral'
                    WHEN 5 THEN 'Masteral'
                    WHEN 4 THEN 'College'
                    WHEN 3 THEN 'Vocational'
                    WHEN 2 THEN 'Secondary'
                    WHEN 1 THEN 'Elementary'
                    ELSE 'Not Specified'
                END AS name,
                SUM(CASE WHEN sex = 'Male' THEN 1 ELSE 0 END) AS male,
                SUM(CASE WHEN sex = 'Female' THEN 1 ELSE 0 END) AS female,
                COUNT(*) AS total,
                COUNT(*) AS count
            FROM (
                SELECT 
                    p.id,
                    p.sex,
                    MAX(CASE eb.level 
                        WHEN 'Graduate Studies' THEN 
                            CASE 
                                WHEN eb.course LIKE '%doctor%' OR eb.course LIKE '%phd%' OR eb.course LIKE '%ph.d%' OR eb.course LIKE '%dem%' OR eb.course LIKE '%doctoral%'
                                THEN 6 
                                ELSE 5 
                            END
                        WHEN 'College' THEN 4 
                        WHEN 'Vocational' THEN 3 
                        WHEN 'Secondary' THEN 2 
                        WHEN 'Elementary' THEN 1 
                        ELSE 0 
                    END) AS highest_val
                FROM employees p
                LEFT JOIN educational_backgrounds eb ON p.id = eb.employee_id
                WHERE p.status = 'Active'
                GROUP BY p.id, p.sex
            ) AS employee_highest
            GROUP BY highest_val
            ORDER BY highest_val DESC";
    return query($sql);
}

function demographicsReligion()
{
    $sql = "SELECT 
                COALESCE(NULLIF(TRIM(r.`name`), ''), NULLIF(TRIM(e.`specify_other_religion`), ''), 'Not Specified') AS name,
                SUM(CASE WHEN e.`sex` = 'Male' THEN 1 ELSE 0 END) AS male,
                SUM(CASE WHEN e.`sex` = 'Female' THEN 1 ELSE 0 END) AS female,
                COUNT(*) AS total,
                COUNT(*) AS count
            FROM employees e
            LEFT JOIN religion r ON e.religion_id = r.id
            WHERE e.status = 'Active'
            GROUP BY name
            ORDER BY total DESC";
    return query($sql);
}

function demographicsIndigenous()
{
    $sql = "SELECT 
                CASE 
                    WHEN o.`is_indigenous` = 1 AND o.`indigenous_group` > '' 
                    THEN o.`indigenous_group` 
                    ELSE 'Non-Indigenous' 
                END AS `name`, 
                SUM(CASE WHEN p.`sex` = 'Male' THEN 1 ELSE 0 END) AS `male`,
                SUM(CASE WHEN p.`sex` = 'Female' THEN 1 ELSE 0 END) AS `female`,
                COUNT(p.`id`) AS `total`,
                COUNT(p.`id`) AS `count`
            FROM `employees` p 
            LEFT JOIN `other_informations` o ON p.`id` = o.`employee_id` 
            WHERE p.`status` = 'Active' 
            GROUP BY `name` 
            ORDER BY `total` DESC";
    return query($sql);
}

function demographicsPwd()
{
    $sql = "SELECT 
                CASE 
                    WHEN o.`with_disability` = 1 AND o.`disability` > '' 
                    THEN o.`disability` 
                    ELSE 'Non-PWD' 
                END AS `name`, 
                SUM(CASE WHEN p.`sex` = 'Male' THEN 1 ELSE 0 END) AS `male`,
                SUM(CASE WHEN p.`sex` = 'Female' THEN 1 ELSE 0 END) AS `female`,
                COUNT(p.`id`) AS `total`,
                COUNT(p.`id`) AS `count`
            FROM `employees` p 
            LEFT JOIN `other_informations` o ON p.`id` = o.`employee_id` 
            WHERE p.`status` = 'Active' 
            GROUP BY `name` 
            ORDER BY `total` DESC";
    return query($sql);
}

function demographicsSoloParent()
{
    $sql = "SELECT 
                CASE WHEN o.`is_solo_parent` = 1 THEN 'Solo Parent' ELSE 'Not Solo Parent' END AS `name`, 
                SUM(CASE WHEN p.`sex` = 'Male' THEN 1 ELSE 0 END) AS `male`,
                SUM(CASE WHEN p.`sex` = 'Female' THEN 1 ELSE 0 END) AS `female`,
                COUNT(p.`id`) AS `total`,
                COUNT(p.`id`) AS `count`
            FROM `employees` p 
            LEFT JOIN `other_informations` o ON p.`id` = o.`employee_id` 
            WHERE p.`status` = 'Active' 
            GROUP BY `name` 
            ORDER BY `total` DESC";
    return query($sql);
}

function demographicsDistrict()
{
    $sql = "SELECT d.`name`, 
                   SUM(CASE WHEN p.`sex` = 'Male' THEN 1 ELSE 0 END) AS `male`,
                   SUM(CASE WHEN p.`sex` = 'Female' THEN 1 ELSE 0 END) AS `female`,
                   COUNT(p.`id`) AS `total`,
                   COUNT(p.`id`) AS `count`
            FROM `station_assignments` AS sa 
            INNER JOIN `schools` AS s ON sa.`station_id` = s.`id` 
            INNER JOIN `employees` AS p ON p.`id` = sa.`employee_id` 
            INNER JOIN `districts` AS d ON s.`district_id` = d.`id` 
            WHERE p.`status` = 'Active' AND d.`id` <> 'SDO' 
            GROUP BY d.`id`, d.`name` 
            ORDER BY d.`name` ASC";
    return query($sql);
}

function demographicsSchool()
{
    $sql = "SELECT s.`name`, 
                   SUM(CASE WHEN p.`sex` = 'Male' THEN 1 ELSE 0 END) AS `male`,
                   SUM(CASE WHEN p.`sex` = 'Female' THEN 1 ELSE 0 END) AS `female`,
                   COUNT(p.`id`) AS `total`,
                   COUNT(p.`id`) AS `count`
            FROM `station_assignments` AS sa 
            INNER JOIN `schools` AS s ON sa.`station_id` = s.`id` 
            INNER JOIN `employees` AS p ON p.`id` = sa.`employee_id` 
            INNER JOIN `districts` AS d ON s.`district_id` = d.`id` 
            WHERE p.`status` = 'Active' 
            GROUP BY s.`id`, s.`name` 
            ORDER BY s.`name` ASC";
    return query($sql);
}

function demographicsEmployeeList()
{
    $sql = "SELECT p.`id`, p.`last_name`, p.`first_name`, p.`middle_name`, p.`name_extension`, 
                p.`sex`, p.`birthdate`, p.`religion_id`, p.`specify_other_religion`, p.`ethnic_group_id`, p.`specify_other_ethnic_group`,
                r.`name` AS `religion_name`, eg.`name` AS `ethnic_group_name`, p.`profile_picture`,
                pos.`official_title` AS `position`, pos.`category`, pos.`salary_grade`,
                d.`name` AS `district`, s.`name` AS `school`,
                o.`is_indigenous`, o.`indigenous_group`, o.`with_disability`, o.`disability`, o.`is_solo_parent`,
                (
                    SELECT MAX(CASE eb.level 
                        WHEN 'Graduate Studies' THEN 
                            CASE 
                                WHEN eb.course LIKE '%doctor%' OR eb.course LIKE '%phd%' OR eb.course LIKE '%ph.d%' OR eb.course LIKE '%dem%' OR eb.course LIKE '%doctoral%'
                                THEN 6 
                                ELSE 5 
                            END
                        WHEN 'College' THEN 4 
                        WHEN 'Vocational' THEN 3 
                        WHEN 'Secondary' THEN 2 
                        WHEN 'Elementary' THEN 1 
                        ELSE 0 
                    END) 
                    FROM educational_backgrounds eb 
                    WHERE eb.employee_id = p.id
                ) AS highest_education_val
            FROM `employees` AS p
            INNER JOIN `station_assignments` AS sa ON p.`id` = sa.`employee_id` 
            INNER JOIN `positions` AS pos ON sa.`position_id` = pos.`id`
            INNER JOIN `schools` AS s ON sa.`station_id` = s.`id`
            INNER JOIN `districts` AS d ON s.`district_id` = d.`id`
            LEFT JOIN `ethnic_groups` AS eg ON p.`ethnic_group_id` = eg.`id`
            LEFT JOIN `religion` AS r ON p.`religion_id` = r.`id`
            LEFT JOIN `other_informations` o ON p.`id` = o.`employee_id`
            WHERE p.`status` = 'Active'
            ORDER BY p.`last_name` ASC, p.`first_name` ASC";
    $results = query($sql);
    return is_array($results) ? $results : [];
}

function getEmployeeDemographicGroup($row, $exportId)
{
    switch ($exportId) {
        case 'gender':
            return $row['sex'] ?? 'Not Specified';
        case 'category':
        case 'category-gender':
            return $row['category'] ?? 'Not Specified';
        case 'position':
            return $row['position'] ?? 'Not Specified';
        case 'generation':
            if (empty($row['birthdate']) || $row['birthdate'] === '0000-00-00') {
                return 'Silent Generation / Other';
            }
            $year = (int) date('Y', strtotime($row['birthdate']));
            if ($year >= 1946 && $year <= 1964)
                return 'Baby Boomers (1946-1964)';
            if ($year >= 1965 && $year <= 1980)
                return 'Generation X (1965-1980)';
            if ($year >= 1981 && $year <= 1996)
                return 'Generation Y / Millennials (1981-1996)';
            if ($year >= 1997 && $year <= 2012)
                return 'Generation Z (1997-2012)';
            if ($year >= 2013)
                return 'Generation Alpha (2013-Present)';
            return 'Silent Generation / Other';
        case 'education':
            $val = isset($row['highest_education_val']) ? (int) $row['highest_education_val'] : 0;
            switch ($val) {
                case 6:
                    return 'Doctoral';
                case 5:
                    return 'Masteral';
                case 4:
                    return 'College';
                case 3:
                    return 'Vocational';
                case 2:
                    return 'Secondary';
                case 1:
                    return 'Elementary';
                default:
                    return 'Not Specified';
            }
        case 'religion':
            if (!empty($row['religion_name'])) {
                return trim($row['religion_name']);
            }
            if (!empty($row['religion_id'])) {
                $rel = religion($row['religion_id']);
                if ($rel && !empty($rel['name'])) {
                    return trim($rel['name']);
                }
            }
            $specify = trim($row['specify_other_religion'] ?? '');
            return $specify !== '' ? $specify : 'Not Specified';
        case 'indigenous':
            if (isset($row['is_indigenous']) && $row['is_indigenous'] == 1 && trim($row['indigenous_group'] ?? '') !== '') {
                return trim($row['indigenous_group']);
            }
            return 'Non-Indigenous';
        case 'pwd':
            if (isset($row['with_disability']) && $row['with_disability'] == 1 && trim($row['disability'] ?? '') !== '') {
                return trim($row['disability']);
            }
            return 'Non-PWD';
        case 'solo-parents':
            if (isset($row['is_solo_parent']) && $row['is_solo_parent'] == 1) {
                return 'Solo Parent';
            }
            return 'Not Solo Parent';
        case 'districts':
            return $row['district'] ?? 'Not Specified';
        case 'schools':
            return $row['school'] ?? 'Not Specified';
        default:
            return 'Other';
    }
}

// non_regular_employees
function createNonRegularEmployee($employee_id, $employment_type, $last_name, $first_name, $middle_name, $name_extension, $sex, $birthdate, $email_address, $mobile_number, $profile_picture, $status, $gsis_crn = '', $gsis_bp = '', $pagibig = '', $philhealth = '', $tin = '', $agency_id = '', $start_date = null, $end_date = null, $fund_source = '')
{
    $data = [
        'id' => $employee_id,
        'employment_type' => $employment_type,
        'last_name' => $last_name,
        'first_name' => $first_name,
        'middle_name' => $middle_name,
        'name_extension' => $name_extension,
        'sex' => $sex,
        'birthdate' => $birthdate,
        'email_address' => $email_address,
        'mobile_number' => $mobile_number,
        'profile_picture' => $profile_picture,
        'status' => $status,
        'start_date' => $start_date ?: null,
        'end_date' => $end_date ?: null,
        'fund_source' => $fund_source,
        'gsis_crn' => $gsis_crn,
        'gsis_bp' => $gsis_bp,
        'pagibig' => $pagibig,
        'philhealth' => $philhealth,
        'tin' => $tin,
        'agency_id' => $agency_id
    ];
    return insert('non_regular_employees', $data);
}

function nonRegularEmployee($employee_id)
{
    if (empty($employee_id)) {
        return null;
    }
    return find("SELECT * FROM `non_regular_employees` WHERE `id` = ? LIMIT 1", [$employee_id]);
}

function nonRegularEmployees($employment_type = null, $station_id = null)
{
    $params = [];
    $whereConditions = [];

    if ($employment_type !== null && $employment_type !== '') {
        $whereConditions[] = "p.`employment_type` = ?";
        $params[] = $employment_type;
    }

    if ($station_id !== null && $station_id !== '') {
        $whereConditions[] = "s.`station_id` = ?";
        $params[] = $station_id;
    }

    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

    $sql = "SELECT p.`id`, p.`employment_type`, p.`last_name`, p.`first_name`, p.`middle_name`, p.`name_extension`, 
                   p.`sex`, p.`birthdate`, p.`email_address`, p.`mobile_number`, p.`start_date`, p.`end_date`, p.`fund_source`, s.`position_id`, s.`station_id`, 
                   p.`profile_picture`, p.`status`
            FROM `non_regular_employees` AS p
            LEFT JOIN `station_assignments` AS s ON p.`id` = s.`employee_id` {$whereClause} 
            ORDER BY p.`last_name` ASC, p.`first_name` ASC";
    $results = query($sql, $params);
    return is_array($results) ? $results : [];
}

function employeeStepIncrement()
{
    $sql = "SELECT e.`id`, e.`lname`, e.`fname`, e.`mname`, e.`ext`, e.`picture`, e.`sex`,
                   sa.`position_id` AS `position`, sa.`station_id` AS `station`,
                   pos.`salary_grade` AS `sg`,
                   COALESCE(si.`step`, 1) AS `step`,
                   COALESCE(si.`last_step_date`, sa.`assignment_date`, '2020-01-01') AS `last_step_date`
            FROM `employees` AS e
            INNER JOIN `station_assignments` AS sa ON sa.`employee_id` = e.`id`
            INNER JOIN `positions` AS pos ON pos.`id` = sa.`position_id`
            LEFT JOIN `step_increments` AS si ON si.`employee_id` = e.`id`
            WHERE e.`status` = 'Active'
            ORDER BY e.`lname` ASC, e.`fname` ASC";
    return query($sql);
}