<?php
// vacancies
function vacancy($vacancy_id)
{
    $sql = "SELECT v.`id`, pi.`item_number`, p.`official_title` FROM `vacancies` as V 
            INNER JOIN `plantilla_items` AS pi ON v.`plantilla_item_id` = pi.`id` 
            INNER JOIN `positions` AS p ON pi.`position_id` = p.`id` WHERE v.`id` = ?";
    return find($sql, [$vacancy_id]);
}

// vacancies, plantilla_items, positions
function vacancies($status = 'open')
{
    $sql = "SELECT v.`id`, pi.`item_number`, p.`official_title`, pi.`station_id`, v.`status`, 
                v.`vacated_by_id`, 
                v.`date_vacated`, v.`reason`, v.`created_at`, v.`updated_at` 
            FROM `vacancies` AS v
            INNER JOIN `plantilla_items` AS pi ON v.`plantilla_item_id` = pi.`id` 
            INNER JOIN `positions` AS p ON pi.`position_id` = p.`id` 
            WHERE v.`status` = ? ORDER BY v.`created_at` DESC";
    return query($sql, [$status]);
}

function countVacantItems($position_id = null)
{
    $params = [];
    $filter = "";
    if ($position_id !== null) {
        $filter = " AND pi.`position_id` = ? ";
        $params[] = $position_id;
    }
    $sql = "SELECT COUNT(*) as total
            FROM `vacancies` AS v 
            INNER JOIN `plantilla_items` AS pi ON v.`plantilla_item_id` = pi.`id` 
            WHERE v.`status` = 'open' 
            AND v.`id` NOT IN (SELECT `vacancy_id` FROM `vacancy_publication_items`)
            {$filter}";
    $result = find($sql, $params);
    return (int) ($result['total'] ?? 0);
}

function createVacancy($plantilla_item_id, $status, $vacated_by_id, $date_vacated, $reason)
{
    $data = [
        'plantilla_item_id' => $plantilla_item_id,
        'status' => $status,
        'vacated_by_id' => $vacated_by_id,
        'date_vacated' => $date_vacated,
        'reason' => $reason
    ];
    return insert('vacancies', $data);
}

function vacantItem($plantilla_item_id)
{
    $result = find("SELECT `id` FROM `vacancies` WHERE `plantilla_item_id`=?", [$plantilla_item_id]);
    return $result > 0;
}

// vacancies
function deleteVacancy($vacancy_id)
{
    return delete('vacancies', '`id` = ?', [$vacancy_id]);
}

// vacancies, positions, plantilla_items, vacancy_history
function vacanciesByStatus($status)
{
    if ($status === 'filled') {
        $sql = "SELECT vh.`id`, vh.`date_filled`, vh.`filled_by_id` AS `filled_by`,
                       pi.`item_number`, pi.`station_id`,
                       pos.`official_title` AS `position`, pos.`salary_grade`
                FROM `vacancy_history` AS vh
                INNER JOIN `plantilla_items` AS pi ON vh.`plantilla_item_id` = pi.`id`
                INNER JOIN `positions` AS pos ON pi.`position_id` = pos.`id`
                ORDER BY vh.`date_filled` DESC, pos.`official_title` ASC";
        return query($sql);
    }

    $sql = "SELECT v.`id`, v.`status`, pi.`item_number`, pi.`station_id`,
                   pos.`official_title` AS `position`, pos.`salary_grade`
            FROM `vacancies` AS v
            INNER JOIN `plantilla_items` AS pi ON v.`plantilla_item_id` = pi.`id`
            INNER JOIN `positions` AS pos ON pi.`position_id` = pos.`id`
            WHERE v.`status` = ?
            ORDER BY pos.`official_title` ASC";
    return query($sql, [$status]);
}

// vacancies - for new publication (open and not yet published)
function vacantItems($position_id = null)
{
    $params = [];
    $filter = "";
    if ($position_id !== null) {
        $filter = " AND pi.`position_id` = ? ";
        $params[] = $position_id;
    }
    $sql = "SELECT v.`id`, pi.`position_id`, p.`official_title`, p.`category`, 
                p.`salary_grade`, pi.`station_id`, pi.`item_number`, v.`vacated_by_id`, 
                v.`date_vacated`, v.`reason`, v.`created_at`
            FROM `vacancies` AS v 
            INNER JOIN `plantilla_items` AS pi ON v.`plantilla_item_id` = pi.`id`
            INNER JOIN `positions` AS p ON pi.`position_id` = p.`id` 
            WHERE v.`status` = 'open' 
            AND v.`id` NOT IN (SELECT `vacancy_id` FROM `vacancy_publication_items`)
            {$filter} ORDER BY p.`official_title` ASC";
    return query($sql, $params);
}

// vacancies - for updating publication (already added items + open and not yet published)
function vacantItemsForUpdate($publication_id, $position_id = null)
{
    $params = [$publication_id];
    $filter = "";
    if ($position_id !== null) {
        $filter = " AND pi.`position_id` = ? ";
        $params[] = $position_id;
    }
    $sql = "SELECT v.`id`, pi.`position_id`, p.`official_title`, p.`category`, 
                p.`salary_grade`, pi.`station_id`, pi.`item_number`, v.`vacated_by_id`, 
                v.`date_vacated`, v.`reason`, v.`created_at`, v.`status`
            FROM `vacancies` AS v 
            INNER JOIN `plantilla_items` AS pi ON v.`plantilla_item_id` = pi.`id`
            INNER JOIN `positions` AS p ON pi.`position_id` = p.`id` 
            WHERE (
                v.`id` IN (SELECT `vacancy_id` FROM `vacancy_publication_items` WHERE `publication_id` = ?)
                OR (v.`status` = 'open' AND v.`id` NOT IN (SELECT `vacancy_id` FROM `vacancy_publication_items`))
            )
            {$filter} ORDER BY p.`official_title` ASC";
    return query($sql, $params);
}

function vacantPlantillaItems()
{
    $sql = "SELECT p.`id`, p.`item_number`, p.`position_id`, p.`station_id`,
                pos.`official_title`, pos.`salary_grade`, pos.`category`,
                s.`name` AS `station_name`
            FROM `plantilla_items` AS p
            INNER JOIN `positions` AS pos ON p.`position_id` = pos.`id`
            INNER JOIN `schools` AS s ON p.`station_id` = s.`id`
            WHERE p.`is_dissolve` = 0 AND p.`id` NOT IN (SELECT `plantilla_item_id` FROM `vacancies`)
            ORDER BY pos.`category` ASC, pos.`official_title` ASC, p.`item_number` ASC";
    return query($sql);
}

// vacancy_publications
function generatePublicationCode()
{
    $year = date('Y');
    $sql = "SELECT COUNT(*) AS `count` FROM `vacancy_publications` WHERE YEAR(`created_at`) = ?";
    $result = find($sql, [$year]);
    $count = ($result ? (int) $result['count'] : 0) + 1;
    return "CFA{$year}" . str_pad($count, 3, '0', STR_PAD_LEFT);
}

function publication($id)
{
    return find("SELECT * FROM `vacancy_publications` WHERE `id` = ? LIMIT 1", [$id]);
}

function publicationByCode($code)
{
    return find("SELECT * FROM `vacancy_publications` WHERE `code` = ? LIMIT 1", [$code]);
}

function activePublications()
{
    $sql = "SELECT * FROM `vacancy_publications` WHERE `status` = 'open' 
            AND (`close_date` > CURDATE() OR (`close_date` = CURDATE() AND CURRENT_TIME() < '17:00:00')) 
            ORDER BY `close_date` DESC";
    return query($sql);
}

function allPublications()
{
    $sql = "SELECT * FROM `vacancy_publications` ORDER BY `close_date` DESC, `created_at` DESC";
    return query($sql);
}

function createPublication($code, $title, $description, $open_date, $close_date, $status)
{
    $data = [
        'code' => $code,
        'title' => $title,
        'description' => $description,
        'open_date' => $open_date,
        'close_date' => $close_date,
        'status' => $status
    ];
    return insert('vacancy_publications', $data);
}

function updatePublication($vacancy_publication_id, $title, $description, $open_date, $close_date, $status)
{
    $data = [
        'title' => $title,
        'description' => $description,
        'open_date' => $open_date,
        'close_date' => $close_date,
        'status' => $status
    ];
    return update('vacancy_publications', $data, '`id` = ?', [$vacancy_publication_id]);
}

function deletePublication($vacancy_publication_id)
{
    return delete('vacancy_publications', '`id` = ?', [$vacancy_publication_id]);
}

function countPublications($status = 'open')
{
    if ($status === null || $status === 'all') {
        $sql = "SELECT COUNT(`id`) AS `total` FROM `vacancy_publications`";
        $result = find($sql);
    } else {
        $sql = "SELECT COUNT(`id`) AS `total` FROM `vacancy_publications` WHERE `status` = ?";
        $result = find($sql, [$status]);
    }
    return $result ? (int) $result['total'] : 0;
}

// vacancy_publication_items, vacancies, positions
function publicationItems($publication_id)
{
    $sql = "SELECT vpi.`id`, vpi.`vacancy_id`, pi.`position_id`, p.`official_title`, pi.`item_number`, p.`salary_grade`,
                pi.`station_id`, v.`date_vacated`, v.`reason`, v.`status`, v.`plantilla_item_id`, v.`vacated_by_id` 
            FROM `vacancy_publication_items` AS vpi 
            INNER JOIN `vacancies` AS v ON vpi.`vacancy_id` = v.`id` 
            INNER JOIN `plantilla_items` AS pi ON v.`plantilla_item_id` = pi.`id` 
            INNER JOIN `positions` AS p ON pi.`position_id` = p.`id` 
            WHERE vpi.`publication_id` = ? ORDER BY p.`official_title` ASC";
    return query($sql, [$publication_id]);
}

function isVacancyReadded($plantilla_item_id)
{
    $sql = "SELECT COUNT(v.`id`) AS `total`
            FROM `vacancies` AS v
            WHERE v.`plantilla_item_id` = ? AND v.`status` = 'open'
            AND v.`id` NOT IN (SELECT `vacancy_id` FROM `vacancy_publication_items`)";
    $result = find($sql, [$plantilla_item_id]);
    return $result && (int) $result['total'] > 0;
}

function readdVacancyToVacancies($publication_id, $vacancy_id)
{
    $pub = publication($publication_id);
    if (!$pub || $pub['status'] !== 'closed') {
        return ['success' => false, 'message' => 'Call for application must be closed to re-add vacant positions.'];
    }

    $sql = "SELECT v.`id`, v.`plantilla_item_id`, v.`vacated_by_id`, v.`date_vacated`, v.`reason`, v.`status`,
                   pi.`position_id`, pi.`item_number`, p.`official_title`
            FROM `vacancy_publication_items` AS vpi
            INNER JOIN `vacancies` AS v ON vpi.`vacancy_id` = v.`id`
            INNER JOIN `plantilla_items` AS pi ON v.`plantilla_item_id` = pi.`id`
            INNER JOIN `positions` AS p ON pi.`position_id` = p.`id`
            WHERE vpi.`publication_id` = ? AND vpi.`vacancy_id` = ? LIMIT 1";
    $item = find($sql, [$publication_id, $vacancy_id]);

    if (!$item) {
        return ['success' => false, 'message' => 'Vacancy item not found in this call for application.'];
    }

    if ($item['status'] === 'filled') {
        return ['success' => false, 'message' => 'Filled positions cannot be re-added as vacant.'];
    }

    $qualifiedCount = countQualifiedApplicants($publication_id, $item['position_id']);
    if ($qualifiedCount > 0) {
        return ['success' => false, 'message' => 'Positions with qualified applicants cannot be re-added.'];
    }

    if (isVacancyReadded($item['plantilla_item_id'])) {
        return ['success' => false, 'message' => 'This position has already been re-added to vacant plantilla positions.'];
    }

    $newVacancyId = createVacancy($item['plantilla_item_id'], 'open', $item['vacated_by_id'], $item['date_vacated'], $item['reason']);
    if ($newVacancyId) {
        return [
            'success' => true,
            'message' => "Position [{$item['official_title']}] (Item {$item['item_number']}) has been successfully re-added to vacant plantilla positions.",
            'item_number' => $item['item_number'],
            'position_title' => $item['official_title']
        ];
    }

    return ['success' => false, 'message' => 'Failed to re-add vacancy item.'];
}

function readdUnfilledVacanciesFromPublication($publication_id)
{
    $pub = publication($publication_id);
    if (!$pub || $pub['status'] !== 'closed') {
        return ['success' => false, 'message' => 'Call for application must be closed to re-add vacant positions.'];
    }

    $items = publicationItems($publication_id);
    $readdedCount = 0;

    foreach ($items as $item) {
        if ($item['status'] === 'filled') {
            continue;
        }

        $qualifiedCount = countQualifiedApplicants($publication_id, $item['position_id']);
        if ($qualifiedCount > 0) {
            continue;
        }

        if (isVacancyReadded($item['plantilla_item_id'])) {
            continue;
        }

        $newId = createVacancy($item['plantilla_item_id'], 'open', $item['vacated_by_id'] ?? null, $item['date_vacated'] ?? date('Y-m-d'), $item['reason'] ?? 'reopened');
        if ($newId) {
            $readdedCount++;
        }
    }

    if ($readdedCount > 0) {
        return [
            'success' => true,
            'message' => "Successfully re-added {$readdedCount} vacant plantilla position(s) back to vacancies for future Call for Applications.",
            'count' => $readdedCount
        ];
    }

    return ['success' => false, 'message' => 'No eligible vacant positions were available to re-add (either already re-added, filled, or have qualified applicants).'];
}

// vacancy_publication_items
function addPublicationItem($publication_id, $vacancy_id)
{
    $data = [
        'publication_id' => $publication_id,
        'vacancy_id' => $vacancy_id
    ];
    return insert('vacancy_publication_items', $data);
}

function clearPublicationItems($publication_id)
{
    return delete('vacancy_publication_items', '`publication_id` = ?', [$publication_id]);
}

function countPublicationItems($publication_id)
{
    $sql = "SELECT COUNT(`id`) AS `total` FROM `vacancy_publication_items` WHERE `publication_id` = ?";
    $result = find($sql, [$publication_id]);
    return $result ? (int) $result['total'] : 0;
}

function applicantsByPublication($publicationId)
{
    $sql = "SELECT va.id, va.created_at, ac.code AS application_code, p.official_title, p.category AS position_group,
                   va.status, va.application_code_id,
                   va.remarks, s.total_accumulated_score
            FROM vacancy_applications AS va
            INNER JOIN application_codes AS ac ON va.application_code_id = ac.id
            INNER JOIN positions AS p ON va.position_id = p.id
            LEFT JOIN assessment_scores AS s ON va.id = s.application_id
            WHERE va.publication_id = ?
            ORDER BY p.category ASC, p.official_title ASC, va.created_at DESC";

    return query($sql, [$publicationId]);
}

function applicantsForReviewByPublication($publicationId)
{
    $sql = "SELECT va.id, va.created_at, ac.code AS application_code, p.official_title, va.status, va.application_code_id,
                   s.total_accumulated_score
            FROM vacancy_applications AS va
            INNER JOIN application_codes AS ac ON va.application_code_id = ac.id
            INNER JOIN positions AS p ON va.position_id = p.id
            LEFT JOIN assessment_scores AS s ON va.id = s.application_id
            WHERE va.publication_id = ? AND va.status = 'For Review' 
            ORDER BY va.created_at DESC";

    return query($sql, [$publicationId]);
}

function countApplicationsByPublication($publicationId)
{
    $sql = "SELECT COUNT(id) AS total 
            FROM vacancy_applications
            WHERE publication_id = ?;";
    $result = find($sql, [$publicationId]);
    return $result ? (int) $result['total'] : 0;
}

function countApplicantsByPublication($publicationId)
{
    $sql = "SELECT COUNT(DISTINCT `application_code_id`) AS total 
            FROM vacancy_applications
            WHERE publication_id = ?";
    $result = find($sql, [$publicationId]);
    return $result ? (int) $result['total'] : 0;
}

// Check if a vacancy is already published in any publication
function isVacancyPublished($vacancy_id)
{
    $result = find("SELECT COUNT(`id`) AS `count` FROM `vacancy_publication_items` WHERE `vacancy_id` = ?", [$vacancy_id]);
    return $result && (int) $result['count'] > 0;
}

function hasAlreadyApplied($publication_id, $application_code_id, $position_id)
{
    $sql = "SELECT id FROM vacancy_applications 
            WHERE publication_id = ? 
            AND application_code_id = ? 
            AND position_id = ? 
            LIMIT 1";
    $result = find($sql, [$publication_id, $application_code_id, $position_id]);
    return !empty($result);
}

function createApplication($publication_id, $application_code_id, $position_id, $district = null)
{
    $data = [
        'publication_id' => $publication_id,
        'application_code_id' => $application_code_id,
        'position_id' => $position_id,
        'district' => $district
    ];
    return insert('vacancy_applications', $data);
}

function applicant($applicant_id)
{
    return find("SELECT * FROM `applicants` WHERE `id` = ? LIMIT 1", [$applicant_id]);
}

function applicantName($application_code, $uppercase = false)
{
    $applicantId = applicantId($application_code);

    if (!$applicantId) {
        return $application_code;
    }

    $data = employee($applicantId);

    if (!$data) {
        $data = applicant($applicantId);
    }

    if ($data) {
        $formattedName = toName(
            $data['last_name'],
            $data['first_name'],
            $data['middle_name'],
            $data['name_extension'],
            true
        );
        if ($uppercase) {
            return strtoupper($formattedName);
        }
        return $formattedName;
    }
    return $application_code;
}

function createApplicant(array $data)
{
    $required = ['id', 'last_name', 'first_name', 'birthdate', 'sex', 'civil_status', 'barangay', 'city', 'province', 'zip', 'email_address', 'mobile_number', 'undergraduate'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || $data[$field] === null) {
            return false;
        }
    }
    if (empty($data['religion_id']) && empty($data['specify_other_religion'])) {
        return false;
    }
    $data['with_disability'] = isset($data['with_disability']) ? (int) $data['with_disability'] : 0;
    if (!isset($data['eligibilities'])) {
        $data['eligibilities'] = json_encode([]);
    } elseif (is_array($data['eligibilities'])) {
        $data['eligibilities'] = json_encode($data['eligibilities']);
    }
    return insert('applicants', $data);
}

function createApplicantCode($applicant_id, $applicant_code)
{
    $data = [
        'id' => $applicant_id,
        'code' => $applicant_code,
    ];
    return insert('application_codes', $data);
}

function applicantEmailExists($email)
{
    return !empty(find("SELECT `id` FROM `applicants` WHERE `email_address` = ?", [$email]));
}

function applicantMobileExists($mobile)
{
    return !empty(find("SELECT `id` FROM `applicants` WHERE `mobile_number` = ?", [$mobile]));
}

function applicantCode($employee_id)
{
    $result = find("SELECT `code` FROM `application_codes` WHERE `id` = ? LIMIT 1", [$employee_id]);
    return $result ? $result['code'] : null;
}

function applicantId($applicant_code)
{
    $result = find("SELECT `id` FROM `application_codes` WHERE `code` = ? LIMIT 1", [$applicant_code]);
    return $result ? $result['id'] : null;
}

function prepareApplicantData(array $post)
{
    $applicant_code = generateID();
    $eligibilities = [];
    if (isset($post['csc_professional']) && $post['csc_professional']) {
        $eligibilities[] = 'CSC Professional';
    }
    if (isset($post['csc_sub_professional']) && $post['csc_sub_professional']) {
        $eligibilities[] = 'CSC Sub-Professional';
    }
    if (isset($post['let_pbet_lept']) && $post['let_pbet_lept']) {
        $eligibilities[] = 'LET/PBET/LEPT';
    }
    if (isset($post['honor_graduate']) && $post['honor_graduate']) {
        $eligibilities[] = 'Honor Graduate Eligibility';
    }
    if (isset($post['barangay_official']) && $post['barangay_official']) {
        $eligibilities[] = 'Barangay Official Eligibility';
    }
    if (isset($post['other_eligibility']) && $post['other_eligibility']) {
        $other_spec = sanitize($post['other_eligibility_specify'] ?? null);
        $eligibilities[] = !empty($other_spec) ? $other_spec : 'Others';
    }

    $raw_religion = sanitize($post['religion_id'] ?? null);
    $religion_id = null;
    $specify_other_religion = null;
    if ($raw_religion === 'Others') {
        $specify_other_religion = sanitize($post['specify_other_religion'] ?? null);
    } else {
        $religion_id = !empty($raw_religion) ? (int) $raw_religion : null;
    }

    $raw_ethnic = sanitize($post['ethnic_group'] ?? null);
    $ethnic_group_id = null;
    $specify_other_ethnic_group = null;
    if ($raw_ethnic === 'Others') {
        $specify_other_ethnic_group = sanitize($post['ethnic_group_specify'] ?? null);
    } elseif ($raw_ethnic === 'Not Applicable') {
        $specify_other_ethnic_group = 'Not Applicable';
        $ethnic_group_id = null;
    } else {
        $ethnic_group_id = !empty($raw_ethnic) ? (int) $raw_ethnic : null;
    }

    $raw_civil_status = sanitize($post['civil_status'] ?? null);
    $civil_status = $raw_civil_status;
    if ($raw_civil_status === 'Others' && !empty($post['specify_other_civil_status'])) {
        $civil_status = sanitize($post['specify_other_civil_status']);
    }

    $data = [
        'id' => $applicant_code,
        'last_name' => sanitize($post['last_name'] ?? null),
        'first_name' => sanitize($post['first_name'] ?? null),
        'middle_name' => sanitize($post['middle_name'] ?? null),
        'name_extension' => sanitize($post['name_extension'] ?? null),
        'birthdate' => sanitize($post['birth_date'] ?? null),
        'sex' => sanitize($post['sex'] ?? null),
        'civil_status' => $civil_status,
        'religion_id' => $religion_id,
        'specify_other_religion' => $specify_other_religion,
        'ethnic_group_id' => $ethnic_group_id,
        'specify_other_ethnic_group' => $specify_other_ethnic_group,
        'lot' => sanitize($post['lot'] ?? null),
        'street' => sanitize($post['street'] ?? null),
        'subdivision' => sanitize($post['subdivision'] ?? null),
        'barangay' => sanitize($post['barangay'] ?? null),
        'city' => sanitize($post['city'] ?? null),
        'province' => sanitize($post['province'] ?? null),
        'zip' => sanitize($post['zip'] ?? null),
        'with_disability' => isset($post['is_pwd']) ? 1 : 0,
        'email_address' => sanitize($post['email'] ?? null),
        'mobile_number' => sanitize($post['mobile'] ?? null),
        'undergraduate' => sanitize($post['education'] ?? null),
        'graduate_studies' => sanitize($post['graduate_studies'] ?? null),
        'eligibilities' => json_encode($eligibilities)
    ];
    return $data;
}

function saveVacancyApplicationRequirement($publication_id, $application_code_id, $file_name)
{
    $data = [
        'publication_id' => $publication_id,
        'application_code_id' => $application_code_id,
        'file_name' => $file_name
    ];
    return insert('vacancy_application_attachments', $data);
}

function applicantDocument(int $publication_id, int $application_code_id): ?string
{
    $sql = "SELECT `file_name` FROM `vacancy_application_attachments` 
            WHERE `publication_id` = ? AND `application_code_id` = ?
            ORDER BY `created_at` DESC LIMIT 1";
    $result = find($sql, [$publication_id, $application_code_id]);
    return $result['file_name'] ?? null;
}

function applicationRecord($applicationId)
{
    return find("SELECT * FROM `vacancy_applications` WHERE `id` = ?", [$applicationId]);
}

function qualifyApplication($applicationId)
{
    $data = [
        'status' => 'Qualified',
        'remarks' => null
    ];
    return update('vacancy_applications', $data, '`id` = ?', [$applicationId]);
}

function disqualifyApplication($applicationId, $remarks = null)
{
    $data = [
        'status' => 'Disqualified',
        'remarks' => $remarks
    ];
    return update('vacancy_applications', $data, '`id` = ?', [$applicationId]);
}

function forReviewApplication($applicationId)
{
    $data = [
        'status' => 'For Review',
        'remarks' => null
    ];
    return update('vacancy_applications', $data, '`id` = ?', [$applicationId]);
}

function getAssessmentScore($applicationId)
{
    return find("SELECT * FROM `assessment_scores` WHERE `application_id` = ?", [$applicationId]);
}

function saveAssessmentScore($applicationId, array $data)
{
    $existing = find("SELECT `id` FROM `assessment_scores` WHERE `application_id` = ?", [$applicationId]);
    if ($existing) {
        return update('assessment_scores', $data, '`application_id` = ?', [$applicationId]);
    } else {
        $data['application_id'] = $applicationId;
        return insert('assessment_scores', $data);
    }
}

function qualifiedApplicantsAssessmentResults($publicationId)
{
    $sql = "SELECT 
                va.id AS application_id,
                va.created_at,
                ac.code AS application_code,
                va.application_code_id,
                p.id AS position_id,
                p.official_title,
                p.category AS position_group,
                p.salary_grade,
                s.education_score,
                s.training_score,
                s.experience_score,
                s.performance_score,
                s.pbet_let_lept_score,
                s.ppst_cot_score,
                s.ppst_reflection_score,
                s.ppst_score,
                s.bei_score,
                s.outstanding_accomplishments_score,
                s.application_of_education_score,
                s.application_of_ld_score,
                s.potential_written_exam_raw,
                s.potential_bei_raw,
                s.potential_wst_raw,
                s.potential_final_score,
                s.total_accumulated_score,
                s.hrmspb_remarks
            FROM vacancy_applications AS va
            INNER JOIN application_codes AS ac ON va.application_code_id = ac.id
            INNER JOIN positions AS p ON va.position_id = p.id
            LEFT JOIN assessment_scores AS s ON va.id = s.application_id
            WHERE va.publication_id = ? AND va.status = 'Qualified'
            ORDER BY s.total_accumulated_score DESC, ac.code ASC";
    return query($sql, [$publicationId]);
}

function countQualifiedApplicants($publicationId, $positionId)
{
    $sql = "SELECT COUNT(id) AS total 
            FROM vacancy_applications 
            WHERE publication_id = ? AND position_id = ? AND status = 'Qualified'";
    $result = find($sql, [$publicationId, $positionId]);
    return $result ? (int) $result['total'] : 0;
}

function countAssessedQualifiedApplicants($publicationId, $positionId)
{
    $sql = "SELECT COUNT(va.id) AS total 
            FROM vacancy_applications AS va
            INNER JOIN assessment_scores AS s ON va.id = s.application_id
            WHERE va.publication_id = ? AND va.position_id = ? AND va.status = 'Qualified' AND s.total_accumulated_score IS NOT NULL";
    $result = find($sql, [$publicationId, $positionId]);
    return $result ? (int) $result['total'] : 0;
}

function applicantsCountByPosition($publicationId)
{
    $sql = "SELECT p.id AS position_id, p.official_title AS name, COUNT(DISTINCT va.application_code_id) AS count
            FROM vacancy_applications AS va
            INNER JOIN positions AS p ON va.position_id = p.id
            WHERE va.publication_id = ?
            GROUP BY p.id, p.official_title
            ORDER BY count DESC, p.official_title ASC";
    return query($sql, [$publicationId]);
}

function applicantEmploymentStatusCount($publicationId)
{
    $sql = "SELECT 
                SUM(CASE WHEN e.id IS NOT NULL THEN 1 ELSE 0 END) AS `internal`,
                SUM(CASE WHEN e.id IS NULL THEN 1 ELSE 0 END) AS `external`
            FROM (
                SELECT DISTINCT application_code_id
                FROM vacancy_applications
                WHERE publication_id = ?
            ) AS unique_applicants
            LEFT JOIN employees AS e ON unique_applicants.application_code_id = e.id";
    return find($sql, [$publicationId]);
}

function applicantsListByPublication($publicationId, $positionId = null, $status = null)
{
    $params = [$publicationId];
    $filters = "";
    if ($positionId !== null && $positionId !== '' && $positionId !== 'all') {
        $filters .= " AND va.position_id = ? ";
        $params[] = $positionId;
    }
    if ($status === 'internal' || $status === 'employed') {
        $filters .= " AND e.id IS NOT NULL ";
    } elseif ($status === 'external' || $status === 'not_employed') {
        $filters .= " AND e.id IS NULL ";
    }
    $sql = "SELECT va.id, va.created_at, ac.code AS application_code, p.official_title, p.category AS position_group,
                   va.status, va.application_code_id,
                   IF(e.id IS NOT NULL, 1, 0) AS is_employed,
                   va.remarks, s.total_accumulated_score,
                   e.`religion_id`, e.`specify_other_religion`, e.`ethnic_group_id`, e.`specify_other_ethnic_group`
            FROM vacancy_applications AS va
            INNER JOIN application_codes AS ac ON va.application_code_id = ac.id
            INNER JOIN positions AS p ON va.position_id = p.id
            LEFT JOIN `employees` AS e ON ac.`id` = e.`id`
            LEFT JOIN `ethnic_groups` AS eg ON e.`ethnic_group_id` = eg.`id`
            LEFT JOIN `religion` AS r ON e.`religion_id` = r.`id`
            LEFT JOIN assessment_scores AS s ON va.id = s.application_id
            WHERE va.publication_id = ?
            {$filters}
            ORDER BY p.category ASC, p.official_title ASC, va.created_at DESC";

    return query($sql, $params);
}

function applicantDiversityGender()
{
    $sql = "SELECT a.`sex` AS `name`, 
                SUM(CASE WHEN a.`sex` = 'Male' THEN 1 ELSE 0 END) AS `male`, 
                SUM(CASE WHEN a.`sex` = 'Female' THEN 1 ELSE 0 END) AS `female`, 
                COUNT(*) AS `total`, COUNT(*) AS `count`
            FROM `applicants` a
            INNER JOIN (SELECT DISTINCT application_code_id FROM vacancy_applications) va ON a.`id` = va.application_code_id
            LEFT JOIN `employees` e ON a.`id` = e.`id`
            WHERE e.`id` IS NULL
            GROUP BY a.`sex` 
            ORDER BY a.`sex` DESC";
    return query($sql);
}

function applicantDiversityGeneration()
{
    $sql = "SELECT 
                CASE 
                    WHEN YEAR(a.`birthdate`) BETWEEN 1946 AND 1964 THEN 'Baby Boomers (1946-1964)'
                    WHEN YEAR(a.`birthdate`) BETWEEN 1965 AND 1980 THEN 'Generation X (1965-1980)'
                    WHEN YEAR(a.`birthdate`) BETWEEN 1981 AND 1996 THEN 'Generation Y / Millennials (1981-1996)'
                    WHEN YEAR(a.`birthdate`) BETWEEN 1997 AND 2012 THEN 'Generation Z (1997-2012)'
                    WHEN YEAR(a.`birthdate`) >= 2013 THEN 'Generation Alpha (2013-Present)'
                    ELSE 'Silent Generation / Other'
                END AS `name`,
                SUM(CASE WHEN a.`sex` = 'Male' THEN 1 ELSE 0 END) AS `male`,
                SUM(CASE WHEN a.`sex` = 'Female' THEN 1 ELSE 0 END) AS `female`,
                COUNT(*) AS `total`,
                COUNT(*) AS `count`
            FROM `applicants` a
            INNER JOIN (SELECT DISTINCT application_code_id FROM vacancy_applications) va ON a.`id` = va.application_code_id
            LEFT JOIN `employees` e ON a.`id` = e.`id`
            WHERE e.`id` IS NULL AND a.`birthdate` IS NOT NULL AND a.`birthdate` <> '0000-00-00'
            GROUP BY `name`
            ORDER BY MIN(a.`birthdate`) ASC";
    return query($sql);
}

function applicantDiversityReligion()
{
    $sql = "SELECT 
                COALESCE(NULLIF(TRIM(r.`name`), ''), NULLIF(TRIM(a.`specify_other_religion`), ''), 'Not Specified') AS `name`,
                SUM(CASE WHEN a.`sex` = 'Male' THEN 1 ELSE 0 END) AS `male`,
                SUM(CASE WHEN a.`sex` = 'Female' THEN 1 ELSE 0 END) AS `female`,
                COUNT(*) AS `total`,
                COUNT(*) AS `count`
            FROM `applicants` a
            INNER JOIN (SELECT DISTINCT application_code_id FROM vacancy_applications) va ON a.`id` = va.application_code_id
            LEFT JOIN `employees` e ON a.`id` = e.`id`
            LEFT JOIN `religion` r ON a.`religion_id` = r.`id`
            WHERE e.`id` IS NULL
            GROUP BY `name`
            ORDER BY `total` DESC";
    return query($sql);
}

function applicantDiversityEthnic()
{
    $sql = "SELECT 
                COALESCE(NULLIF(TRIM(eg.`name`), ''), NULLIF(TRIM(a.`specify_other_ethnic_group`), ''), 'Not Specified') AS `name`, 
                SUM(CASE WHEN a.`sex` = 'Male' THEN 1 ELSE 0 END) AS `male`,
                SUM(CASE WHEN a.`sex` = 'Female' THEN 1 ELSE 0 END) AS `female`,
                COUNT(a.`id`) AS `total`,
                COUNT(a.`id`) AS `count`
            FROM `applicants` a
            INNER JOIN (SELECT DISTINCT application_code_id FROM vacancy_applications) va ON a.`id` = va.application_code_id
            LEFT JOIN `employees` e ON a.`id` = e.`id`
            LEFT JOIN `ethnic_groups` eg ON a.`ethnic_group_id` = eg.`id`
            WHERE e.`id` IS NULL
            GROUP BY `name` 
            ORDER BY `total` DESC";
    return query($sql);
}

function applicantDiversityPwd()
{
    $sql = "SELECT 
                CASE 
                    WHEN a.`with_disability` = 1 THEN 'PWD' 
                    ELSE 'Non-PWD' 
                END AS `name`, 
                SUM(CASE WHEN a.`sex` = 'Male' THEN 1 ELSE 0 END) AS `male`,
                SUM(CASE WHEN a.`sex` = 'Female' THEN 1 ELSE 0 END) AS `female`,
                COUNT(a.`id`) AS `total`,
                COUNT(a.`id`) AS `count`
            FROM `applicants` a
            INNER JOIN (SELECT DISTINCT application_code_id FROM vacancy_applications) va ON a.`id` = va.application_code_id
            LEFT JOIN `employees` e ON a.`id` = e.`id`
            WHERE e.`id` IS NULL
            GROUP BY `name` 
            ORDER BY `total` DESC";
    return query($sql);
}

function applicantDiversityUndergraduate()
{
    $sql = "SELECT 
                COALESCE(NULLIF(TRIM(a.`undergraduate`), ''), 'Not Specified') AS `name`,
                SUM(CASE WHEN a.`sex` = 'Male' THEN 1 ELSE 0 END) AS `male`,
                SUM(CASE WHEN a.`sex` = 'Female' THEN 1 ELSE 0 END) AS `female`,
                COUNT(a.`id`) AS `total`,
                COUNT(a.`id`) AS `count`
            FROM `applicants` a
            INNER JOIN (SELECT DISTINCT application_code_id FROM vacancy_applications) va ON a.`id` = va.application_code_id
            LEFT JOIN `employees` e ON a.`id` = e.`id`
            WHERE e.`id` IS NULL
            GROUP BY `name`
            ORDER BY `total` DESC";
    return query($sql);
}

function applicantDiversityPostGraduate()
{
    $sql = "SELECT 
                COALESCE(NULLIF(TRIM(a.`graduate_studies`), ''), 'Not Specified') AS `name`,
                SUM(CASE WHEN a.`sex` = 'Male' THEN 1 ELSE 0 END) AS `male`,
                SUM(CASE WHEN a.`sex` = 'Female' THEN 1 ELSE 0 END) AS `female`,
                COUNT(a.`id`) AS `total`,
                COUNT(a.`id`) AS `count`
            FROM `applicants` a
            INNER JOIN (SELECT DISTINCT application_code_id FROM vacancy_applications) va ON a.`id` = va.application_code_id
            LEFT JOIN `employees` e ON a.`id` = e.`id`
            WHERE e.`id` IS NULL
            GROUP BY `name`
            ORDER BY `total` DESC";
    return query($sql);
}

function applicantDiversityRegistration()
{
    $sql = "SELECT 
                CASE 
                    WHEN va.application_code_id IS NOT NULL THEN 'Applied to Vacancy' 
                    ELSE 'No Application Made' 
                END AS `name`,
                SUM(CASE WHEN a.`sex` = 'Male' THEN 1 ELSE 0 END) AS `male`,
                SUM(CASE WHEN a.`sex` = 'Female' THEN 1 ELSE 0 END) AS `female`,
                COUNT(*) AS `total`,
                COUNT(*) AS `count`
            FROM `applicants` a
            LEFT JOIN (SELECT DISTINCT application_code_id FROM vacancy_applications) va ON a.`id` = va.application_code_id
            LEFT JOIN `employees` e ON a.`id` = e.`id`
            WHERE e.`id` IS NULL
            GROUP BY `name`
            ORDER BY `total` DESC";
    return query($sql);
}

function applicantDiversityList($onlyApplied = false)
{
    $join = "";
    if ($onlyApplied) {
        $join = " INNER JOIN (SELECT DISTINCT application_code_id FROM vacancy_applications) va ON a.`id` = va.application_code_id ";
    }
    $sql = "SELECT a.`id`, a.`last_name`, a.`first_name`, a.`middle_name`, a.`name_extension`, 
                a.`sex`, a.`birthdate`, a.`civil_status`,
                a.`religion_id`, a.`specify_other_religion`, a.`ethnic_group_id`, a.`specify_other_ethnic_group`,
                COALESCE(NULLIF(TRIM(r.`name`), ''), NULLIF(TRIM(a.`specify_other_religion`), ''), 'Not Specified') AS `religion`,
                COALESCE(NULLIF(TRIM(eg.`name`), ''), NULLIF(TRIM(a.`specify_other_ethnic_group`), ''), 'Not Specified') AS `ethnic_group`,
                a.`specify_other_ethnic_group` AS `ethnic_group_specify`,
                a.`email_address`, a.`mobile_number`, a.`with_disability`, a.`undergraduate`, a.`graduate_studies`,
                IF(va_status.application_code_id IS NOT NULL, 1, 0) AS `has_applied`
            FROM `applicants` AS a
            {$join}
            LEFT JOIN (SELECT DISTINCT application_code_id FROM vacancy_applications) va_status ON a.`id` = va_status.application_code_id
            LEFT JOIN `employees` AS e ON a.`id` = e.`id`
            LEFT JOIN `religion` AS r ON a.`religion_id` = r.`id`
            LEFT JOIN `ethnic_groups` AS eg ON a.`ethnic_group_id` = eg.`id`
            WHERE e.`id` IS NULL
            ORDER BY a.`last_name` ASC, a.`first_name` ASC";
    $results = query($sql);
    return is_array($results) ? $results : [];
}

function getApplicantDemographicGroup($row, $exportId)
{
    switch ($exportId) {
        case 'gender':
            return $row['sex'] ?? 'Not Specified';
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
        case 'religion':
            $religion = trim($row['religion'] ?? '');
            return $religion !== '' ? $religion : 'Not Specified';
        case 'ethnic':
            $ethnic = trim($row['ethnic_group'] ?? '');
            if ($ethnic === 'Others' && !empty($row['ethnic_group_specify'])) {
                return trim($row['ethnic_group_specify']);
            }
            return $ethnic !== '' ? $ethnic : 'Not Specified';
        case 'pwd':
            if (isset($row['with_disability']) && $row['with_disability'] == 1) {
                return 'PWD';
            }
            return 'Non-PWD';
        case 'undergraduate':
            $undergrad = trim($row['undergraduate'] ?? '');
            return $undergrad !== '' ? $undergrad : 'Not Specified';
        case 'postgraduate':
            $postgrad = trim($row['graduate_studies'] ?? '');
            return $postgrad !== '' ? $postgrad : 'Not Specified';
        case 'registration':
            return (isset($row['has_applied']) && $row['has_applied'] == 1) ? 'Applied to Vacancy' : 'No Application Made';
        default:
            return 'Other';
    }
}

function externalApplicantsList()
{
    $sql = "SELECT a.`id`, a.`last_name`, a.`first_name`, a.`middle_name`, a.`name_extension`, 
                a.`sex`, a.`birthdate`, a.`civil_status`,
                a.`religion_id`, a.`specify_other_religion`, a.`ethnic_group_id`, a.`specify_other_ethnic_group`,
                COALESCE(NULLIF(TRIM(r.`name`), ''), NULLIF(TRIM(a.`specify_other_religion`), ''), 'Not Specified') AS `religion`,
                COALESCE(NULLIF(TRIM(eg.`name`), ''), NULLIF(TRIM(a.`specify_other_ethnic_group`), ''), 'Not Specified') AS `ethnic_group`,
                a.`lot`, a.`street`, a.`subdivision`, a.`barangay`, a.`city`, a.`province`, a.`zip`,
                a.`email_address`, a.`mobile_number`, a.`with_disability`, a.`undergraduate`, a.`graduate_studies`,
                a.`eligibilities`, a.`created_at`,
                COUNT(va.`id`) AS `application_count`
            FROM `applicants` AS a
            LEFT JOIN `employees` AS e ON a.`id` = e.`id`
            LEFT JOIN `religion` AS r ON a.`religion_id` = r.`id`
            LEFT JOIN `ethnic_groups` AS eg ON a.`ethnic_group_id` = eg.`id`
            LEFT JOIN `vacancy_applications` AS va ON a.`id` = va.`application_code_id`
            WHERE e.`id` IS NULL
            GROUP BY a.`id`
            ORDER BY a.`last_name` ASC, a.`first_name` ASC";
    $results = query($sql);
    return is_array($results) ? $results : [];
}

function updateExternalApplicant($id, array $data)
{
    $allowed = [
        'last_name',
        'first_name',
        'middle_name',
        'name_extension',
        'birthdate',
        'sex',
        'civil_status',
        'religion_id',
        'specify_other_religion',
        'ethnic_group_id',
        'specify_other_ethnic_group',
        'lot',
        'street',
        'subdivision',
        'barangay',
        'city',
        'province',
        'zip',
        'with_disability',
        'email_address',
        'mobile_number',
        'undergraduate',
        'graduate_studies',
        'eligibilities'
    ];
    $updateData = [];
    foreach ($allowed as $field) {
        if (array_key_exists($field, $data)) {
            $updateData[$field] = $data[$field];
        }
    }
    if (empty($updateData)) {
        return false;
    }
    return update('applicants', $updateData, '`id` = ?', [$id]);
}

function deleteExternalApplicant($id)
{
    $hasApps = find("SELECT COUNT(`id`) AS `total` FROM `vacancy_applications` WHERE `application_code_id` = ?", [$id]);
    if ($hasApps && (int) $hasApps['total'] > 0) {
        return ['success' => false, 'message' => 'Cannot remove external applicant because they have submitted job applications.'];
    }

    beginTransaction();
    try {
        delete('vacancy_application_attachments', '`application_code_id` = ?', [$id]);
        delete('application_codes', '`id` = ?', [$id]);
        delete('applicants', '`id` = ?', [$id]);
        commit();
        return ['success' => true, 'message' => 'External applicant removed successfully.'];
    } catch (Exception $e) {
        rollBack();
        return ['success' => false, 'message' => 'Failed to remove external applicant: ' . $e->getMessage()];
    }
}

function applicantApplications($applicant_id)
{
    $sql = "SELECT va.id, va.created_at, va.status, va.district,
                   p.official_title, p.category AS position_group, p.salary_grade,
                   vp.title AS publication_title, vp.code AS publication_code, va.publication_id
            FROM vacancy_applications AS va
            INNER JOIN positions AS p ON va.position_id = p.id
            LEFT JOIN vacancy_publications AS vp ON va.publication_id = vp.id
            WHERE va.application_code_id = ?
            ORDER BY va.created_at DESC";
    return query($sql, [$applicant_id]) ?: [];
}



function allApplicantsList($typeFilter = 'all')
{
    $where = "";
    if ($typeFilter === 'internal') {
        $where = " AND e.id IS NOT NULL ";
    } elseif ($typeFilter === 'external') {
        $where = " AND e.id IS NULL ";
    }

    $sql = "SELECT 
                ac.id,
                ac.code,
                IF(e.id IS NOT NULL, 1, 0) AS is_employed,
                COALESCE(NULLIF(TRIM(e.last_name), ''), NULLIF(TRIM(a.last_name), '')) AS last_name,
                COALESCE(NULLIF(TRIM(e.first_name), ''), NULLIF(TRIM(a.first_name), '')) AS first_name,
                COALESCE(NULLIF(TRIM(e.middle_name), ''), NULLIF(TRIM(a.middle_name), '')) AS middle_name,
                COALESCE(NULLIF(TRIM(e.name_extension), ''), NULLIF(TRIM(a.name_extension), '')) AS name_extension,
                COALESCE(NULLIF(TRIM(e.sex), ''), NULLIF(TRIM(a.sex), '')) AS sex,
                COALESCE(e.birthdate, a.birthdate) AS birthdate,
                COALESCE(NULLIF(TRIM(e.civil_status), ''), NULLIF(TRIM(a.civil_status), '')) AS civil_status,
                COALESCE(NULLIF(TRIM(e.email_address), ''), NULLIF(TRIM(a.email_address), '')) AS email_address,
                COALESCE(NULLIF(TRIM(e.mobile_number), ''), NULLIF(TRIM(a.mobile_number), '')) AS mobile_number,
                a.undergraduate,
                a.graduate_studies,
                a.eligibilities,
                a.lot, a.street, a.subdivision, a.barangay, a.city, a.province, a.zip,
                a.with_disability,
                ac.created_at,
                COUNT(va.id) AS application_count
            FROM application_codes AS ac
            LEFT JOIN employees AS e ON ac.id = e.id
            LEFT JOIN applicants AS a ON ac.id = a.id
            LEFT JOIN vacancy_applications AS va ON ac.id = va.application_code_id
            WHERE ((e.last_name IS NOT NULL AND e.last_name <> '') OR (a.last_name IS NOT NULL AND a.last_name <> ''))
            {$where}
            GROUP BY ac.id
            ORDER BY last_name ASC, first_name ASC";
    $results = query($sql);
    return is_array($results) ? $results : [];
}

function canClosePublication($publication_id)
{
    $forReviewApps = applicantsForReviewByPublication($publication_id);
    if (!empty($forReviewApps) && count($forReviewApps) > 0) {
        $count = count($forReviewApps);
        return [
            'can_close' => false,
            'reason' => "Cannot close call for application because there " . ($count === 1 ? "is 1 application" : "are {$count} applications") . " still pending initial screening."
        ];
    }

    $items = publicationItems($publication_id);
    $unassessedPositions = [];

    foreach ($items as $item) {
        $positionId = $item['position_id'];
        $qualifiedCount = countQualifiedApplicants($publication_id, $positionId);
        $assessedCount = countAssessedQualifiedApplicants($publication_id, $positionId);

        if ($qualifiedCount > 0 && $qualifiedCount !== $assessedCount) {
            $unassessedPositions[] = $item['official_title'];
        }
    }

    if (!empty($unassessedPositions)) {
        $unassessedPositions = array_unique($unassessedPositions);
        $posList = implode(', ', $unassessedPositions);
        return [
            'can_close' => false,
            'reason' => "Cannot close call for application because there are qualified applicants who have not been assessed for the following position(s): {$posList}."
        ];
    }

    return [
        'can_close' => true,
        'reason' => null
    ];
}

function getScoringCategoryLabel($salaryGrade, $category, $title = '')
{
    $title = trim($title);

    if (
        preg_match('/Master\s+Teacher\s+(I|II|III|1|2|3)\b/i', $title) ||
        preg_match('/Teacher\s+(II|III|IV|V|VI|VII|2|3|4|5|6|7)\b/i', $title)
    ) {
        return 'Teacher II to Teacher III and Master Teacher I to Master Teacher III';
    }

    if (preg_match('/\bTeacher\s+(I|1)\b/i', $title) && !preg_match('/\bTeacher\s+(I[IVX]|V|X|1[0-9])\b/i', $title)) {
        return 'Teacher I (DO 007 s. 2023)';
    }

    $isPrincipal = stripos($title, 'Principal') !== false;
    if ($isPrincipal && $salaryGrade >= 17 && $salaryGrade <= 22) {
        return 'SG 17-22 (School Administration Positions)';
    } elseif ($salaryGrade >= 1 && $salaryGrade <= 9) {
        return stripos($category, 'general service') !== false
            ? 'SG 1-9 (General Services)'
            : 'SG 1-9 (Non-General Services)';
    } elseif ($salaryGrade >= 10 && $salaryGrade <= 22) {
        return 'SG 10-22';
    } elseif ($salaryGrade == 24) {
        return 'SG 24 (Chief Positions)';
    } else {
        return 'SG 10-22';
    }
}