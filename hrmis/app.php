<?php
// hrmis/app.php
$activeApp = $_SESSION["{$prefix}activeApp"] = 'hrmis';
$page = $appTitle = 'Human Resource Management Information System';

if (!isset($userId)) {
    redirect("{$baseUri}/login");
}

if (!userRole($userId, $activeApp)) {
    redirect("{$baseUri}/" . HOME);
}

if (isset($_SESSION["{$prefix}change_password"])) {
    redirect("{$baseUri}/login/change");
}

if (isset($_POST['primary-search-button'])) {
    redirect(customUri('hrmis', 'Employee Search', sanitize($_POST['primary-search-text'])));
}

if (isset($_POST['add-employee'])) {
    $success = false;
    $employeeId = generateID();
    $lname = sanitize($_POST['lname']);
    $fname = sanitize($_POST['fname']);
    $mname = sanitize($_POST['mname']);
    $ext = sanitize($_POST['ext']);
    $sex = sanitize($_POST['sex']);
    $bdate = sanitize($_POST['bdate']);
    $ePositionId = sanitize($_POST['position']);
    $eStationId = sanitize($_POST['station']);
    $email = sanitize($_POST['email']);
    $mobile = sanitize($_POST['mobile']);
    $image = 'assets/img/user.png';
    $status = sanitize($_POST['status']);
    $crn = sanitize($_POST['gsis_id']);
    $bp = sanitize($_POST['gsis_bp']);
    $pagibig = sanitize($_POST['pagibig']);
    $philhealth = sanitize($_POST['philhealth']);
    $tin = sanitize($_POST['tin']);
    $agencyId = sanitize($_POST['agency_id']);
    $showAlert = true;
    $employee = toName($lname, $fname, $mname, $ext, true);
    $today = date('Y-m-d');

    if (!isValidEmail($email, 'deped.gov.ph')) {
        $message = 'The DepEd Email Address you entered is invalid!';
        return;
    }

    $name = employeeName($lname, $fname, $mname, $ext);

    if ($name) {
        $message = 'Employee [<a href="' . customUri('hrmis', 'Employee Information', $name['id']) . '" title="View ' . $employee . ' employee information">' . strtoupper($employee) . '</a>] already exist!';
        return;
    }

    beginTransaction();

    try {
        if (createEmployee($employeeId, $lname, $fname, $mname, $ext, $sex, $bdate, $email, $mobile, $image, $status, $crn, $bp, $pagibig, $philhealth, $tin, $agencyId) === false) {
            throw new Exception('Failed to save employee information.');
        }

        if (createFamily('', '', '', '', '', '', '', '', '', '', '', '', '', '', '', $employeeId) === false) {
            throw new Exception('Failed to create family background.');
        }

        if (createOtherInformation(0, 0, null, 0, null, 0, null, null, 0, null, 0, null, 0, null, 0, null, 0, null, 0, null, 0, null, 0, null, $employeeId) === false) {
            throw new Exception('Failed to create other information.');
        }

        if (createStation($today, $eStationId, $ePositionId, $employeeId) === false) {
            throw new Exception('Failed to assign employee station.');
        }

        if (createIdentification(null, '', '', $today, $employeeId) === false) {
            throw new Exception('Failed to create identification.');
        }

        if (createAccount($employeeId, hashPassword(generateStrongRandomPassword())) === false) {
            throw new Exception('Failed to create user account.');
        }

        createSystemLog($stationId, $userId, 'Registered employee', $employeeId, clientIp());
        commit();

        $message = 'Employee [<a href="' . customUri('hrmis', 'Employee Information', $employeeId) . '" title="View ' . $employee . ' employee information">' . strtoupper($employee) . '</a>] was saved successfully.';
        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['add-non-regular-employee'])) {
    $success = false;
    $employeeId = generateID();
    $employmentType = sanitize($_POST['employment_type']);
    $lname = sanitize($_POST['lname']);
    $fname = sanitize($_POST['fname']);
    $mname = sanitize($_POST['mname']);
    $ext = sanitize($_POST['ext']);
    $sex = sanitize($_POST['sex']);
    $bdate = sanitize($_POST['bdate']);
    $ePositionId = sanitize($_POST['position']);
    $eStationId = sanitize($_POST['station']);
    $email = sanitize($_POST['email']);
    $mobile = sanitize($_POST['mobile']);
    $image = 'assets/img/user.png';
    $status = sanitize($_POST['status']);
    $startDate = !empty($_POST['start_date']) ? sanitize($_POST['start_date']) : null;
    $endDate = !empty($_POST['end_date']) ? sanitize($_POST['end_date']) : null;
    $fundSource = !empty($_POST['fund_source']) ? sanitize($_POST['fund_source']) : '';
    $crn = sanitize($_POST['gsis_id']);
    $bp = sanitize($_POST['gsis_bp']);
    $pagibig = sanitize($_POST['pagibig']);
    $philhealth = sanitize($_POST['philhealth']);
    $tin = sanitize($_POST['tin']);
    $agencyId = sanitize($_POST['agency_id']);
    $showAlert = true;
    $employee = toName($lname, $fname, $mname, $ext, true);
    $today = date('Y-m-d');

    if (!isValidEmail($email)) {
        $message = 'The Email Address you entered is invalid!';
        return;
    }

    $name = employeeName($lname, $fname, $mname, $ext);

    if ($name) {
        $message = 'Employee [' . strtoupper($employee) . '] already exists!';
        return;
    }

    beginTransaction();

    try {
        if (createNonRegularEmployee($employeeId, $employmentType, $lname, $fname, $mname, $ext, $sex, $bdate, $email, $mobile, $image, $status, $crn, $bp, $pagibig, $philhealth, $tin, $agencyId, $startDate, $endDate, $fundSource) === false) {
            throw new Exception('Failed to save non-regular employee information.');
        }

        if (createStation($today, $eStationId, $ePositionId, $employeeId) === false) {
            throw new Exception('Failed to assign station.');
        }

        $posData = positions($ePositionId);
        $posTitle = $posData ? $posData['official_title'] : 'Non-Regular Staff';
        $stationInfo = schoolById($eStationId);
        $stationName = $stationInfo['name'] ?? '';

        createExperience(
            $startDate ?: $today,
            $endDate,
            ($status === 'Active') ? '1' : '0',
            $posTitle,
            null,
            $employmentType,
            '1',
            null,
            null,
            $stationName,
            null,
            '0',
            null,
            null,
            $employeeId
        );

        if (createAccount($employeeId, hashPassword(generateStrongRandomPassword())) === false) {
            throw new Exception('Failed to create user account.');
        }

        createSystemLog($stationId, $userId, "Registered {$employmentType} employee", $employeeId, clientIp());
        commit();

        $message = "{$employmentType} employee [" . strtoupper($employee) . "] was saved successfully.";
        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['update-personal-information'])) {
    $success = false;
    $employeeId = sanitize(decipher($_POST['verifier']));
    $employeePhoto = sanitize(decipher($_POST['image-verifier']));
    $oldPhoto = $employeePhoto;
    $showAlert = true;
    $activeTab = $_SESSION[alias() . '_activeTab'] = 'personal-information';
    $countryIdInput = (int) sanitize($_POST['dual-citizenship-country']);
    $countryData = country($countryIdInput);
    $dualCitizenshipCountry = $countryData['id'] ?? null;

    beginTransaction();

    try {
        $stagedFile = null;

        if (!empty($_FILES['image-upload']['tmp_name']) && is_uploaded_file($_FILES['image-upload']['tmp_name'])) {
            try {
                $stagedFile = stageUploadedFile(
                    $_FILES['image-upload'],
                    ['image/png' => 'png', 'image/jpeg' => 'jpg'],
                    root() . "/uploads/images/{$employeeId}",
                    "USER"
                );

                $employeePhoto = "uploads/images/{$employeeId}/" . $stagedFile['secure_name'];
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        }

        $raw_religion = sanitize($_POST['religion_id'] ?? null);
        $religion_id = null;
        $specify_other_religion = null;
        if ($raw_religion === 'Others') {
            $specify_other_religion = sanitize($_POST['specify_other_religion'] ?? null);
        } else {
            $religion_id = !empty($raw_religion) ? (int) $raw_religion : null;
        }

        $raw_ethnic = sanitize($_POST['ethnic_group'] ?? null);
        $ethnic_group_id = null;
        $specify_other_ethnic_group = null;
        if ($raw_ethnic === 'Others') {
            $specify_other_ethnic_group = sanitize($_POST['ethnic_group_specify'] ?? null);
        } elseif ($raw_ethnic === 'Not Applicable') {
            $specify_other_ethnic_group = 'Not Applicable';
            $ethnic_group_id = null;
        } else {
            $ethnic_group_id = !empty($raw_ethnic) ? (int) $raw_ethnic : null;
        }

        $affectedEmployee = updateEmployee(sanitize($_POST['lname']), sanitize($_POST['fname']), sanitize($_POST['mname']), sanitize($_POST['ext']), sanitize($_POST['dob']), sanitize($_POST['pob']), sanitize($_POST['sex']), sanitize($_POST['civil-status']), sanitize($_POST['civil-status-specify']), $religion_id, $specify_other_religion, $ethnic_group_id, $specify_other_ethnic_group, sanitize($_POST['citizenship']), sanitize($_POST['dual-citizenship']), $dualCitizenshipCountry, sanitize($_POST['rlot']), sanitize($_POST['rstreet']), sanitize($_POST['rsubdivision']), sanitize($_POST['rbarangay']), sanitize($_POST['rcity']), sanitize($_POST['rprovince']), sanitize($_POST['rzip']), sanitize($_POST['plot']), sanitize($_POST['pstreet']), sanitize($_POST['psubdivision']), sanitize($_POST['pbarangay']), sanitize($_POST['pcity']), sanitize($_POST['pprovince']), sanitize($_POST['pzip']), sanitize($_POST['height']), sanitize($_POST['weight']), sanitize($_POST['blood-type']), sanitize($_POST['umid']), sanitize($_POST['crn']), sanitize($_POST['bp']), sanitize($_POST['pagibig']), sanitize($_POST['philhealth']), sanitize($_POST['philsys']), sanitize($_POST['sss']), sanitize($_POST['telephone']), sanitize($_POST['mobile']), sanitize($_POST['email']), sanitize($_POST['tin']), sanitize($_POST['agency-id']), sanitize($_POST['prc-id']), $employeePhoto, $employeeId);

        $existingOther = otherInformation($employeeId);
        if ($ethnic_group_id) {
            $all_ethnics = ethnic_groups();
            $matched_is_indigenous = 0;
            $matched_name = '';
            foreach ($all_ethnics as $eg_item) {
                if ((int) $eg_item['id'] === (int) $ethnic_group_id) {
                    $matched_is_indigenous = !empty($eg_item['is_indigenous']) ? 1 : 0;
                    $matched_name = $eg_item['name'];
                    break;
                }
            }
            if ($existingOther) {
                update('other_informations', [
                    'is_indigenous' => $matched_is_indigenous,
                    'indigenous_group' => $matched_is_indigenous ? $matched_name : 'N/A'
                ], '`employee_id` = ?', [$employeeId]);
            }
        } elseif ($specify_other_ethnic_group === 'Not Applicable' || $raw_ethnic === 'Others') {
            if ($existingOther) {
                update('other_informations', [
                    'is_indigenous' => 0,
                    'indigenous_group' => 'N/A'
                ], '`employee_id` = ?', [$employeeId]);
            }
        }

        if ($affectedEmployee == false && !$stagedFile) {
            throw new Exception('No changes have been made to personal information.');
        }

        createSystemLog($stationId, $userId, 'Updated employee personal information', $employeeId, clientIp());
        commit();
        if ($stagedFile) {
            commitStagedFile($stagedFile);
            if (!empty($oldPhoto) && file_exists(root() . '/' . $oldPhoto) && basename(root() . '/' . $oldPhoto) !== 'user.png') {
                unlink(root() . '/' . $oldPhoto);
            }
        }

        $message = 'Personal information has been updated successfully.';
        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['update-family-background'])) {
    $success = false;
    $employeeId = sanitize(decipher($_POST['verifier']));
    $slast = sanitize($_POST['slast']);
    $sfirst = sanitize($_POST['sfirst']);
    $sext = sanitize($_POST['sext']);
    $smiddle = sanitize($_POST['smiddle']);
    $swork = sanitize($_POST['swork']);
    $sbusiness = sanitize($_POST['sbusiness']);
    $sbusinessAddress = sanitize($_POST['sbusiness-address']);
    $stelephone = sanitize($_POST['stelephone']);
    $flast = sanitize($_POST['flast']);
    $ffirst = sanitize($_POST['ffirst']);
    $fext = sanitize($_POST['fext']);
    $fmiddle = sanitize($_POST['fmiddle']);
    $mlast = sanitize($_POST['mlast']);
    $mfirst = sanitize($_POST['mfirst']);
    $mmiddle = sanitize($_POST['mmiddle']);
    $showAlert = true;
    $activeTab = $_SESSION[alias() . '_activeTab'] = 'family-background';

    beginTransaction();

    try {
        $affectedFamily = !family($employeeId) ?
            createFamily($slast, $sfirst, $sext, $smiddle, $swork, $sbusiness, $sbusinessAddress, $stelephone, $flast, $ffirst, $fext, $fmiddle, $mlast, $mfirst, $mmiddle, $employeeId) :
            updateFamily($slast, $sfirst, $sext, $smiddle, $swork, $sbusiness, $sbusinessAddress, $stelephone, $flast, $ffirst, $fext, $fmiddle, $mlast, $mfirst, $mmiddle, $employeeId);

        if ($affectedFamily === false) {
            throw new Exception('No changes have been made to family background.');
        }

        createSystemLog($stationId, $userId, 'Updated employee family background', $employeeId, clientIp());
        commit();

        $message = 'Family background has been updated successfully.';
        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['save-child'])) {
    $success = false;
    $employeeId = sanitize(decipher($_POST['verifier']));
    $childId = sanitize(decipher($_POST['data-verifier']));
    $clast = sanitize($_POST['clast']);
    $cfirst = sanitize($_POST['cfirst']);
    $cext = sanitize($_POST['cext']);
    $cmiddle = sanitize($_POST['cmiddle']);
    $cdob = sanitize($_POST['cdob']);
    $logMessage = '';
    $showAlert = true;
    $activeTab = $_SESSION[alias() . '_activeTab'] = 'children';

    beginTransaction();

    try {
        if (!child($employeeId, $childId)) {
            $affectedChild = createChild($clast, $cfirst, $cext, $cmiddle, $cdob, $employeeId);
            $logMessage = 'Added employee child';
            $message = 'Child has been added successfully.';
        } else {
            $affectedChild = updateChild($clast, $cfirst, $cext, $cmiddle, $cdob, $employeeId, $childId);
            $logMessage = 'Updated employee child';
            $message = 'Child has been updated successfully.';
        }

        if ($affectedChild === false) {
            throw new Exception('No changes have been made to children.');
        }

        createSystemLog($stationId, $userId, $logMessage, $employeeId, clientIp());
        commit();

        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['delete-child'])) {
    $success = false;
    $employeeId = sanitize(decipher($_POST['verifier']));
    $childId = sanitize(decipher($_POST['data-verifier']));
    $showAlert = true;
    $activeTab = $_SESSION[alias() . '_activeTab'] = 'children';

    beginTransaction();

    try {
        $deletedChild = deleteChild($employeeId, $childId);

        if ($deletedChild === false) {
            throw new Exception('No changes have been made to children.');
        }

        createSystemLog($stationId, $userId, 'Deleted employee child', $employeeId, clientIp());
        commit();

        $message = 'Child has been deleted successfully.';
        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['save-education'])) {
    $success = false;
    $employeeId = sanitize(decipher($_POST['verifier']));
    $educationId = sanitize(decipher($_POST['data-verifier']));
    $level = sanitize($_POST['level']);
    $school = sanitize($_POST['school']);
    $course = sanitize($_POST['course']);
    $from = sanitize($_POST['from']);
    $to = sanitize($_POST['to']);
    $isPresent = isset($_POST['is-present']) ? '1' : '0';
    $highest = sanitize($_POST['highest']);
    $year = $isPresent ? null : sanitize($_POST['year']);
    $scholarship = sanitize($_POST['scholarship']);
    $logMessage = '';
    $showAlert = true;
    $activeTab = $_SESSION[alias() . '_activeTab'] = 'educational-background';

    beginTransaction();

    try {
        if (empty($educationId)) {
            $affectedEducation = createEducation($level, $school, $course, $from, $to, $isPresent, $highest, $year, $scholarship, $employeeId);
            $logMessage = 'Added employee education';
            $message = 'Educational background has been added successfully.';
        } else {
            $affectedEducation = updateEducation($level, $school, $course, $from, $to, $isPresent, $highest, $year, $scholarship, $employeeId, $educationId);
            $logMessage = 'Updated employee education';
            $message = 'Educational background has been updated successfully.';
        }

        if ($affectedEducation === false) {
            throw new Exception('No changes have been made to educational background.');
        }

        createSystemLog($stationId, $userId, $logMessage, $employeeId, clientIp());
        commit();

        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['delete-education'])) {
    $success = false;
    $employeeId = sanitize(decipher($_POST['verifier']));
    $educationId = sanitize(decipher($_POST['data-verifier']));
    $showAlert = true;
    $activeTab = $_SESSION[alias() . '_activeTab'] = 'educational-background';

    beginTransaction();

    try {
        $affectedEducation = deleteEducation($employeeId, $educationId);

        if ($affectedEducation === false) {
            throw new Exception('No changes have been made to educational background.');
        }

        createSystemLog($stationId, $userId, 'Deleted employee education', $employeeId, clientIp());
        commit();

        $message = 'Educational background has been deleted successfully.';
        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }

}

if (isset($_POST['save-eligibility'])) {
    $employeeId = sanitize(decipher($_POST['verifier']));
    $eligibilityId = sanitize(decipher($_POST['data-verifier']));
    $career = sanitize($_POST['career']);
    $rating = sanitize($_POST['rating']);
    $examDate = sanitize($_POST['exam-date']);
    $examPlace = sanitize($_POST['exam-place']);
    $license = sanitize($_POST['license']);
    $isApplicable = isset($_POST['is-applicable']) ? '1' : '0';
    $validity = sanitize($_POST['validity']);
    $logMessage = '';
    $showAlert = true;
    $success = false;
    $activeTab = $_SESSION[alias() . '_activeTab'] = 'civil-service-eligibility';

    beginTransaction();

    try {
        if (empty($eligibilityId)) {
            $affectedEligibility = createEligibility($career, $rating, $examDate, $examPlace, $license, $isApplicable, $validity, $employeeId);
            $logMessage = 'Added employee eligibility';
            $message = 'Civil service eligibility has been added successfully.';
        } else {
            $affectedEligibility = updateEligibility($career, $rating, $examDate, $examPlace, $license, $isApplicable, $validity, $employeeId, $eligibilityId);
            $logMessage = 'Updated employee eligibility';
            $message = 'Civil service eligibility has been updated successfully.';
        }

        if ($affectedEligibility === false) {
            throw new Exception('No changes have been made to civil service eligibility.');
        }

        createSystemLog($stationId, $userId, $logMessage, $employeeId, clientIp());
        commit();

        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['delete-eligibility'])) {
    $employeeId = sanitize(decipher($_POST['verifier']));
    $eligibilityId = sanitize(decipher($_POST['data-verifier']));
    $showAlert = true;
    $success = false;
    $activeTab = $_SESSION[alias() . '_activeTab'] = 'civil-service-eligibility';

    beginTransaction();

    try {
        $affectedEligibility = deleteEligibility($employeeId, $eligibilityId);

        if ($affectedEligibility === false) {
            throw new Exception('No changes have been made to civil service eligibility.');
        }

        createSystemLog($stationId, $userId, 'Deleted employee eligibility', $employeeId, clientIp());
        commit();

        $message = 'Civil service eligibility has been deleted successfully.';
        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['save-voluntary-work'])) {
    $employeeId = sanitize(decipher($_POST['verifier']));
    $voluntaryId = sanitize(decipher($_POST['data-verifier']));
    $organization = sanitize($_POST['organization']);
    $from = sanitize($_POST['from']);
    $isPresent = isset($_POST['is-present']) ? '1' : '0';
    $to = sanitize($_POST['to']);
    $hours = $_POST['hours'] ?? 0;
    $position = sanitize($_POST['position']);
    $logMessage = '';
    $showAlert = true;
    $success = false;
    $activeTab = $_SESSION[alias() . '_activeTab'] = 'voluntary-work';

    beginTransaction();

    try {
        if (empty($voluntaryId)) {
            $affectedVoluntaryWork = createVoluntaryWork($organization, $from, $to, $isPresent, $hours, $position, $employeeId);
            $logMessage = 'Added employee voluntary work';
            $message = 'Voluntary work has been added successfully.';
        } else {
            $affectedVoluntaryWork = updateVoluntaryWork($organization, $from, $to, $isPresent, $hours, $position, $employeeId, $voluntaryId);
            $logMessage = 'Updated employee voluntary work';
            $message = 'Voluntary work has been updated successfully.';
        }

        if ($affectedVoluntaryWork === false) {
            throw new Exception('No changes have been made to voluntary work.');
        }

        createSystemLog($stationId, $userId, $logMessage, $employeeId, clientIp());
        commit();

        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['delete-voluntary-work'])) {
    $employeeId = sanitize(decipher($_POST['verifier']));
    $voluntaryId = sanitize(decipher($_POST['data-verifier']));
    $showAlert = true;
    $success = false;
    $activeTab = $_SESSION[alias() . '_activeTab'] = 'voluntary-work';

    beginTransaction();

    try {
        $affectedVoluntaryWork = deleteVoluntaryWork($employeeId, $voluntaryId);

        if ($affectedVoluntaryWork === false) {
            throw new Exception('No changes have been made to voluntary work.');
        }

        createSystemLog($stationId, $userId, 'Deleted employee voluntary work', $employeeId, clientIp());
        commit();

        $message = 'Voluntary work has been deleted successfully.';
        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['save-special-skill'])) {
    $employeeId = sanitize(decipher($_POST['verifier']));
    $skillId = sanitize(decipher($_POST['data-verifier']));
    $skill = sanitize($_POST['skill']);
    $logMessage = '';
    $showAlert = true;
    $success = false;
    $activeTab = $_SESSION[alias() . '_activeTab'] = 'special-skills';

    beginTransaction();

    try {
        if (empty($skillId)) {
            $affectedSkill = createSpecialSkill($skill, $employeeId);
            $logMessage = 'Added employee special skill';
            $message = 'Special skill / hobby has been added successfully.';
        } else {
            $affectedSkill = updateSpecialSkill($skill, $employeeId, $skillId);
            $logMessage = 'Updated employee special skill';
            $message = 'Special skill / hobby has been updated successfully.';
        }

        if ($affectedSkill === false) {
            throw new Exception('No changes have been made to special skill / hobby.');
        }

        createSystemLog($stationId, $userId, $logMessage, $employeeId, clientIp());
        commit();

        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['delete-special-skill'])) {
    $employeeId = sanitize(decipher($_POST['verifier']));
    $skillId = sanitize(decipher($_POST['data-verifier']));
    $showAlert = true;
    $success = false;
    $activeTab = $_SESSION[alias() . '_activeTab'] = 'special-skills';

    beginTransaction();

    try {
        $affectedSkill = deleteSpecialSkill($employeeId, $skillId);

        if ($affectedSkill === false) {
            throw new Exception('No changes have been made to special skill / hobby.');
        }

        createSystemLog($stationId, $userId, 'Deleted employee special skill', $employeeId, clientIp());
        commit();

        $message = 'Special skill / hobby has been deleted successfully.';
        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['save-recognition'])) {
    $employeeId = sanitize(decipher($_POST['verifier']));
    $recognitionId = sanitize(decipher($_POST['data-verifier']));
    $recognition = sanitize($_POST['recognition']);
    $logMessage = '';
    $showAlert = true;
    $success = false;
    $activeTab = $_SESSION[alias() . '_activeTab'] = 'recognition';

    beginTransaction();

    try {
        if (empty($recognitionId)) {
            $affectedRecognition = createRecognition($recognition, $employeeId);
            $logMessage = 'Added employee recognition';
            $message = 'Non-academic distinction / recognition has been added successfully.';
        } else {
            $affectedRecognition = updateRecognition($recognition, $employeeId, $recognitionId);
            $logMessage = 'Updated employee recognition';
            $message = 'Non-academic distinction / recognition has been updated successfully.';
        }

        if ($affectedRecognition === false) {
            throw new Exception('No changes have been made to non-academic distinction / recognition.');
        }

        createSystemLog($stationId, $userId, $logMessage, $employeeId, clientIp());
        commit();

        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['delete-recognition'])) {
    $employeeId = sanitize(decipher($_POST['verifier']));
    $recognitionId = sanitize(decipher($_POST['data-verifier']));
    $showAlert = true;
    $success = false;
    $activeTab = $_SESSION[alias() . '_activeTab'] = 'recognition';

    beginTransaction();

    try {
        $affectedRecognition = deleteRecognition($employeeId, $recognitionId);

        if ($affectedRecognition === false) {
            throw new Exception('No changes have been made to non-academic distinction / recognition.');
        }

        createSystemLog($stationId, $userId, 'Deleted employee recognition', $employeeId, clientIp());
        commit();

        $message = 'Non-academic distinction / recognition has been deleted successfully.';
        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }

}

if (isset($_POST['save-membership'])) {
    $employeeId = sanitize(decipher($_POST['verifier']));
    $membershipId = sanitize(decipher($_POST['data-verifier']));
    $membership = sanitize($_POST['membership']);
    $logMessage = '';
    $showAlert = true;
    $success = false;
    $activeTab = $_SESSION[alias() . '_activeTab'] = 'membership';

    beginTransaction();

    try {
        if (empty($membershipId)) {
            $affectedMembership = createMembership($membership, $employeeId);
            $logMessage = 'Added employee membership';
            $message = 'Membership in Association / Organization has been added successfully.';
        } else {
            $affectedMembership = updateMembership($membership, $employeeId, $membershipId);
            $logMessage = 'Updated employee membership';
            $message = 'Membership in association / organization has been updated successfully.';
        }

        if ($affectedMembership === false) {
            throw new Exception('No changes have been made to membership in association / organization.');
        }

        createSystemLog($stationId, $userId, $logMessage, $employeeId, clientIp());
        commit();

        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['delete-membership'])) {
    $employeeId = sanitize(decipher($_POST['verifier']));
    $membershipId = sanitize(decipher($_POST['data-verifier']));
    $showAlert = true;
    $success = false;
    $activeTab = $_SESSION[alias() . '_activeTab'] = 'membership';

    beginTransaction();

    try {
        $affectedMembership = deleteMembership($employeeId, $membershipId);

        if ($affectedMembership === false) {
            throw new Exception('No changes have been made to membership in association / organization.');
        }

        createSystemLog($stationId, $userId, 'Deleted employee membership', $employeeId, clientIp());
        commit();

        $message = 'Membership in association / organization has been deleted successfully.';
        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['update-other-information'])) {
    $employeeId = sanitize(decipher($_POST['verifier']));
    $hasThirdDegree = sanitize($_POST['has-third-degree']);
    $hasFourthDegree = sanitize($_POST['has-fourth-degree']);
    $relatedDetails = $hasFourthDegree ? sanitize($_POST['related-details']) : 'N/A';
    $wasGuilty = sanitize($_POST['was-guilty']);
    $guiltyDetails = $wasGuilty ? sanitize($_POST['guilty-details']) : 'N/A';
    $wasCharged = sanitize($_POST['was-charged']);
    $dateFiled = sanitize($_POST['date-filed']);
    $caseStatus = $wasCharged ? sanitize($_POST['case-status']) : 'N/A';
    $wasConvicted = sanitize($_POST['was-convicted']);
    $convictedDetails = $wasConvicted ? sanitize($_POST['convicted-details']) : 'N/A';
    $wasSeparated = sanitize($_POST['was-separated']);
    $separatedDetails = $wasSeparated ? sanitize($_POST['separated-details']) : 'N/A';
    $wasCandidate = sanitize($_POST['was-candidate']);
    $candidateDetails = $wasCandidate ? sanitize($_POST['candidate-details']) : 'N/A';
    $resigned = sanitize($_POST['resigned']);
    $resignedDetails = $resigned ? sanitize($_POST['resigned-details']) : 'N/A';
    $immigrant = sanitize($_POST['immigrant']);
    $immigrantCountry = $immigrant ? sanitize($_POST['immigrant-country']) : null;
    $isIndigenous = sanitize($_POST['is-indigenous'] ?? '0');
    $raw_indigenous_specify = $isIndigenous ? sanitize($_POST['indigenous-specify'] ?? '') : 'N/A';
    if ($isIndigenous && ($raw_indigenous_specify === 'Others' || !empty($_POST['indigenous_group_specify']))) {
        $indigenousSpecify = !empty($_POST['indigenous_group_specify']) ? sanitize($_POST['indigenous_group_specify']) : $raw_indigenous_specify;
    } else {
        $indigenousSpecify = $raw_indigenous_specify;
    }
    $isDifferentlyAbled = sanitize($_POST['is-differently-abled']);
    $differentlyAbledSpecify = $isDifferentlyAbled ? sanitize($_POST['differently-abled-specify']) : 'N/A';
    $isSoloParent = sanitize($_POST['is-solo-parent']);
    $soloParentSpecify = $isSoloParent ? sanitize($_POST['solo-parent-specify']) : 'N/A';
    $showAlert = true;
    $success = false;
    $activeTab = $_SESSION[alias() . '_activeTab'] = 'other-information';

    beginTransaction();

    try {
        $affectedOtherInformation = !otherInformation($employeeId) ?
            createOtherInformation($hasThirdDegree, $hasFourthDegree, $relatedDetails, $wasGuilty, $guiltyDetails, $wasCharged, $dateFiled, $caseStatus, $wasConvicted, $convictedDetails, $wasSeparated, $separatedDetails, $wasCandidate, $candidateDetails, $resigned, $resignedDetails, $immigrant, $immigrantCountry, $isIndigenous, $indigenousSpecify, $isDifferentlyAbled, $differentlyAbledSpecify, $isSoloParent, $soloParentSpecify, $employeeId) :
            updateOtherInformation($hasThirdDegree, $hasFourthDegree, $relatedDetails, $wasGuilty, $guiltyDetails, $wasCharged, $dateFiled, $caseStatus, $wasConvicted, $convictedDetails, $wasSeparated, $separatedDetails, $wasCandidate, $candidateDetails, $resigned, $resignedDetails, $immigrant, $immigrantCountry, $isIndigenous, $indigenousSpecify, $isDifferentlyAbled, $differentlyAbledSpecify, $isSoloParent, $soloParentSpecify, $employeeId);

        if ($isIndigenous == 1) {
            if ($raw_indigenous_specify === 'Others' || !empty($_POST['indigenous_group_specify'])) {
                $custom_group_val = !empty($_POST['indigenous_group_specify']) ? sanitize($_POST['indigenous_group_specify']) : $indigenousSpecify;
                update('employees', [
                    'ethnic_group_id' => null,
                    'specify_other_ethnic_group' => $custom_group_val
                ], '`id` = ?', [$employeeId]);
            } else {
                $ethnic_groups_all = ethnic_groups();
                $matched_ethnic_id = null;
                foreach ($ethnic_groups_all as $eg_item) {
                    if ($eg_item['name'] === $indigenousSpecify) {
                        $matched_ethnic_id = $eg_item['id'];
                        break;
                    }
                }
                if ($matched_ethnic_id) {
                    update('employees', [
                        'ethnic_group_id' => $matched_ethnic_id,
                        'specify_other_ethnic_group' => null
                    ], '`id` = ?', [$employeeId]);
                }
            }
        } else {
            update('employees', [
                'ethnic_group_id' => null,
                'specify_other_ethnic_group' => 'Not Applicable'
            ], '`id` = ?', [$employeeId]);
        }

        if ($affectedOtherInformation === false) {
            throw new Exception('No changes have been made to other information.');
        }

        createSystemLog($stationId, $userId, 'Updated employee other information', $employeeId, clientIp());
        commit();

        $message = 'Other information has been updated successfully.';
        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['save-reference'])) {
    $employeeId = sanitize(decipher($_POST['verifier']));
    $referenceId = sanitize(decipher($_POST['data-verifier']));
    $name = sanitize($_POST['name']);
    $address = sanitize($_POST['address']);
    $contact = sanitize($_POST['telephone']);
    $logMessage = '';
    $showAlert = true;
    $success = false;
    $activeTab = $_SESSION[alias() . '_activeTab'] = 'reference';

    beginTransaction();

    try {
        if (empty($referenceId)) {
            $affectedReference = createReference($name, $address, $contact, $employeeId);
            $logMessage = 'Added employee reference';
            $message = 'Reference has been added successfully.';
        } else {
            $affectedReference = updateReference($name, $address, $contact, $employeeId, $referenceId);
            $logMessage = 'Updated employee reference';
            $message = 'Reference has been updated successfully.';
        }

        if ($affectedReference === false) {
            throw new Exception('No changes have been made to reference.');
        }

        createSystemLog($stationId, $userId, $logMessage, $employeeId, clientIp());
        commit();

        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['delete-reference'])) {
    $employeeId = sanitize(decipher($_POST['verifier']));
    $referenceId = sanitize(decipher($_POST['data-verifier']));
    $activeTab = $_SESSION[alias() . '_activeTab'] = 'reference';
    $success = false;

    beginTransaction();

    try {
        $affectedReference = deleteReference($employeeId, $referenceId);

        if ($affectedReference === false) {
            throw new Exception('No changes have been made to reference.');
        }

        createSystemLog($stationId, $userId, 'Deleted employee reference', $employeeId, clientIp());
        commit();

        $message = 'Reference has been deleted successfully.';
        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['reassign-employee'])) {
    $employeeId = sanitize(decipher($_POST['verifier']));
    $positions = position($employeeId);
    $positionId = $positions ? $positions['position_id'] : '';
    $eStationId = sanitize($_POST['assignment']);
    $date = sanitize($_POST['assignment-date']);
    $showAlert = true;
    $success = false;

    if (empty($employeeId) || empty($positionId) || empty($eStationId) || empty($date)) {
        return;
    }

    beginTransaction();

    try {
        if (user($employeeId)) {
            deleteUserRoles($employeeId);
        }

        if (!station($employeeId)) {
            $affectedStation = createStation($date, $eStationId, $positionId, $employeeId);
        } else {
            updateEmployeeStatus('Active', $employeeId);
            $affectedStation = updateStation($date, $eStationId, $positionId, $employeeId);
        }

        if ($affectedStation === false) {
            throw new Exception('No changes to employee [<a href="#" title="View ' . userName($employeeId) . ' employee information">' . userName($employeeId, true) . '</a>] assignment has been made.');
        }

        createSystemLog($stationId, $userId, 'Reassigned employee', $employeeId, clientIp());
        commit();

        $message = 'Employee [<a href="' . customUri('hrmis', 'Employee Information', $employeeId) . '" title="View ' . userName($employeeId) . ' employee information">' . userName($employeeId, true) . '</a>] has been reassigned successfully to [<a href="' . customUri('hrmis', 'School Information', $eStationId) . '" title="View ' . stationName($eStationId) . ' information">' . strtoupper(stationName($eStationId)) . '</a>].';
        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['promote-employee'])) {
    $employeeId = sanitize(decipher($_POST['verifier']));
    $positionId = sanitize($_POST['position']);
    $position = strtoupper(positions($positionId)['official_title']);
    $station = station($employeeId);
    $eStationId = $station ? $station['station_id'] : '';
    $datePromoted = sanitize($_POST['effectivity-date']);
    $skipVacancy = isset($_POST['skip_vacancy']);

    if (empty($employeeId) || empty($positionId) || empty($eStationId) || empty($datePromoted)) {
        return;
    }

    $showAlert = true;
    $success = false;

    beginTransaction();

    try {
        if (!$station) {
            $affectedStation = createStation($datePromoted, $eStationId, $positionId, $employeeId);
        } else {
            updateEmployeeStatus('Active', $employeeId);
            $affectedStation = updateStation($datePromoted, $eStationId, $positionId, $employeeId);
        }

        if ($affectedStation === false) {
            throw new Exception('No changes to employee [<a href="#" title="View ' . userName($employeeId) . ' employee information">' . userName($employeeId, true) . '</a>] information has been made.');
        }

        createSystemLog($stationId, $userId, 'Promoted employee', $employeeId, clientIp());

        $message = 'Employee [<a href="' . customUri('hrmis', 'Employee Information', $employeeId) . '" title="View ' . userName($employeeId) . ' employee information">' . userName($employeeId, true) . '</a>] has been promoted successfully to [' . $position . '].';
        $success = true;

        $plantillaItemData = employeeItemNumber($employeeId);
        $plantillaItemId = $plantillaItemData['id'] ?? null;

        update(
            'service_records',
            ['is_present' => '0', 'to_date' => $datePromoted],
            '`employee_id` = ? AND `is_present` = 1 AND `to_date` IS NULL',
            [$employeeId]
        );

        if (!$skipVacancy && $plantillaItemId) {
            if (createVacancy($plantillaItemId, 'open', $employeeId, $datePromoted, 'promoted')) {
                $message .= ' A vacant item has been created for this position.';

                createSystemLog($stationId, $userId, 'Created vacant item', $employeeId, clientIp());
            }
        }

        commit();
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['remove-employee'])) {
    $employeeId = sanitize(decipher($_POST['verifier']));
    $reason = sanitize($_POST['reason']);
    $skipVacancy = isset($_POST['skip_vacancy']);
    $showAlert = true;
    $success = false;

    if (empty($employeeId) || empty($reason)) {
        return;
    }

    $dateVacated = sanitize($_POST['effectivity_date'] ?? date('Y-m-d'));

    beginTransaction();

    try {
        if (!employee($employeeId)) {
            throw new Exception("Employee not found.");
        }

        $plantillaItemData = employeeItemNumber($employeeId);
        $plantillaItemId = $plantillaItemData['id'] ?? null;

        $affectedEmployeeStatus = updateEmployeeStatus($reason, $employeeId);

        if ($affectedEmployeeStatus === false) {
            throw new Exception('No changes to employee [<a href="' . customUri('hrmis', 'Employee Information', $employeeId) . '" title="View ' . userName($employeeId) . ' employee information">' . userName($employeeId, true) . '</a>] status has been made.');
        }

        update(
            'service_records',
            [
                'is_present' => '0',
                'to_date' => $dateVacated,
                'for_separation' => '1',
                'separation_date' => $dateVacated,
                'separation_cause' => $reason
            ],
            '`employee_id` = ? AND `is_present` = 1 AND `to_date` IS NULL',
            [$employeeId]
        );

        $message = 'Employee [<a href="' . customUri('hrmis', 'Employee Information', $employeeId) . '" title="View ' . userName($employeeId) . ' employee information">' . userName($employeeId, true) . '</a>] has been removed successfully.';
        $success = true;
        createSystemLog($stationId, $userId, 'Removed employee', $employeeId, clientIp());

        if (!$skipVacancy && strtolower($reason) !== 'duplicate' && $plantillaItemId) {
            if (createVacancy($plantillaItemId, 'open', $employeeId, $dateVacated, $reason)) {
                $message .= ' A vacant item has been created for this position.';
                createSystemLog($stationId, $userId, 'Created vacant item', $employeeId, clientIp());
            }
        }

        commit();
        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['set-school-head'])) {
    $employeeId = sanitize(decipher($_POST['verifier']));
    $schoolId = sanitize(decipher($_POST['data-verifier']));
    $showAlert = true;
    $success = false;

    beginTransaction();

    try {
        if (employee($employeeId)) {
            $affectedSchoolHead = updateSchoolHead($schoolId, $employeeId);
        }

        if ($affectedSchoolHead === false) {
            throw new Exception('Employee [<a href="' . customUri('hrmis', 'Employee Information', $employeeId) . '" title="View ' . userName($employeeId) . ' employee information">' . userName($employeeId, true) . '</a>] was not set as school head of [<a href="#" title="View ' . stationName($schoolId) . ' school information">' . strtoupper(stationName($schoolId)) . '</a>].');
        }

        createSystemLog($stationId, $userId, 'Set School Head', $employeeId, clientIp());
        commit();

        $message = 'Employee [<a href="' . customUri('hrmis', 'Employee Information', $employeeId) . '" title="View ' . userName($employeeId) . ' employee information">' . userName($employeeId, true) . '</a>] has been successfully set as school head of [<a href="#" title="View ' . stationName($schoolId) . ' school information">' . strtoupper(stationName($schoolId)) . '</a>].';
        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['save-service-record'])) {
    $employeeId = sanitize(decipher($_POST['verifier']));
    $serviceId = sanitize(decipher($_POST['data-verifier']));
    $from = sanitize($_POST['from']);
    $isPresent = isset($_POST['is-present']) ? '1' : '0';
    $to = sanitize($_POST['to']);
    $position = sanitize($_POST['position']);
    $status = sanitize($_POST['status']);
    $isGovernment = sanitize($_POST['is-government']) === 'Y' ? '1' : '0';
    $sg = sanitize($_POST['sg-step']);
    $salary = sanitize($_POST['salary'] ?? '0');
    $station = sanitize($_POST['station']);
    $leaveDates = sanitize($_POST['leave']);
    $isSeparation = isset($_POST['is-separation']) ? '1' : '0';
    $separationDate = $separationCause = null;
    $logMessage = '';
    $showAlert = true;
    $success = false;
    $activeTab = $_SESSION[alias() . '_activeTab'] = 'work-experience';

    if ($isSeparation === '1') {
        $separationDate = sanitize($_POST['separation-date']);
        $separationCause = sanitize($_POST['separation-cause']);
    }

    beginTransaction();

    try {
        if (empty($serviceId)) {
            $affectedExperience = createExperience($from, $to, $isPresent, $position, null, $status, $isGovernment, $sg, $salary, $station, $leaveDates, $isSeparation, $separationDate, $separationCause, $employeeId);
            $logMessage = 'Added service record';
            $message = 'Service record has been added successfully.';
        } else {
            $affectedExperience = updateExperience($from, $to, $isPresent, $position, null, $status, $isGovernment, $sg, $salary, $station, $leaveDates, $isSeparation, $separationDate, $separationCause, $employeeId, $serviceId);
            $logMessage = 'Updated service record';
            $message = 'Service record has been updated successfully.';
        }

        if ($affectedExperience === false) {
            throw new Exception('No changes have been made to service record.');
        }

        createSystemLog($stationId, $userId, $logMessage, $employeeId, clientIp());
        commit();

        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['delete-service-record'])) {
    $employeeId = sanitize(decipher($_POST['verifier']));
    $serviceId = sanitize(decipher($_POST['data-verifier']));
    $showAlert = true;
    $success = false;
    $activeTab = $_SESSION[alias() . '_activeTab'] = 'work-experience';

    beginTransaction();

    try {
        $affectedExperience = deleteExperience($employeeId, $serviceId);

        if ($affectedExperience === false) {
            throw new Exception('No changes have been made to service record.');
        }

        createSystemLog($stationId, $userId, 'Deleted employee service record', $employeeId, clientIp());
        commit();

        $message = 'Service record has been deleted successfully.';
        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['save-201-file'])) {
    $employeeId = sanitize(decipher($_POST['verifier']));
    $fileId = sanitize(decipher($_POST['data-verifier']));
    $fileTypeId = sanitize($_POST['type']);
    $description = sanitize($_POST['description']);
    $oldFilename = sanitize(decipher($_POST['file-verifier']));
    $newFilename = $oldFilename;
    $showAlert = true;
    $success = false;

    beginTransaction();

    try {
        $stagedFile = null;

        if (!empty($_FILES['file-upload']['tmp_name']) && is_uploaded_file($_FILES['file-upload']['tmp_name'])) {
            try {
                $stagedFile = stageUploadedFile(
                    $_FILES['file-upload'],
                    ['application/pdf' => 'pdf'],
                    root() . "/uploads/201_files/{$employeeId}",
                    "201"
                );

                $newFilename = "uploads/201_files/{$employeeId}/" . $stagedFile['secure_name'];
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        }

        if (empty($newFilename)) {
            throw new Exception('No changes have been made to 201 file.');
        }

        $ext = pathinfo($newFilename, PATHINFO_EXTENSION);
        $hasExistingRecord = fileAttachment($employeeId, $fileId);

        if (!$hasExistingRecord) {
            $affectedFileAttachment = createFileAttachment($fileTypeId, $description, $newFilename, $ext, $employeeId);
            $logMessage = 'Added 201 file';
        } else {
            $affectedFileAttachment = updateFileAttachment($fileTypeId, $description, $newFilename, $ext, $employeeId, $fileId);
            $logMessage = 'Updated 201 file';
        }

        if ($affectedFileAttachment === false && !$stagedFile) {
            throw new Exception('No changes have been made to 201 file.');
        }

        createSystemLog($stationId, $userId, $logMessage, $employeeId, clientIp());
        commit();
        if ($stagedFile) {
            commitStagedFile($stagedFile);
            if (!empty($oldFilename) && file_exists(root() . "/{$oldFilename}")) {
                unlink(root() . "/{$oldFilename}");
            }
        }

        $message = "201 file has been " . ($hasExistingRecord ? "updated" : "added") . " successfully.";
        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['delete-201-file'])) {
    $employeeId = sanitize(decipher($_POST['verifier']));
    $fileId = sanitize(decipher($_POST['data-verifier']));
    $showAlert = true;
    $success = false;
    $filename = null;
    $file = fileAttachment($employeeId, $fileId);
    $affectedFile = false;

    beginTransaction();

    try {
        if ($file) {
            $filename = $file['file_name'];
            $affectedFile = deleteFileAttachment($employeeId, $fileId);
        }

        if ($affectedFile === false) {
            throw new Exception('No changes have been made to 201 file.');
        }

        createSystemLog($stationId, $userId, 'Deleted employee 201 file', $employeeId, clientIp());
        commit();
        if (!empty($filename) && file_exists(root() . '/' . $filename)) {
            unlink(root() . '/' . $filename);
        }

        $message = '201 file has been deleted successfully.';
        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['save-position'])) {
    $referencePositionId = sanitize(decipher($_POST['verifier']));
    $positionId = sanitize($_POST['position-id']);
    $official_title = sanitize($_POST['official-title']);
    $salary_grade = sanitize($_POST['salary-grade']);
    $category = sanitize($_POST['category']);
    $showAlert = true;
    $success = false;

    if (empty($official_title)) {
        $message = 'Please enter the official title.';
        return;
    }

    if (empty($salary_grade)) {
        $message = 'Please enter the salary grade.';
        return;
    }

    if (empty($category)) {
        $message = 'Please select a category.';
        return;
    }

    beginTransaction();

    try {
        $existingPosition = $referencePositionId ? positions($referencePositionId) : null;

        if (!$existingPosition) {
            $affectedPosition = createPosition($positionId, $official_title, $salary_grade, $category);
            $status = 'saved';
            $logMessage = 'Saved position';
        } else {
            $affectedPosition = updatePosition($positionId, $official_title, $salary_grade, $category, $referencePositionId);
            $status = 'updated';
            $logMessage = 'Updated position';
        }

        if ($affectedPosition === false) {
            throw new Exception('No changes have been made to positions.');
        }

        createSystemLog($stationId, $userId, 'Updated position', $positionId, clientIp());
        commit();

        $official_title = strtoupper($official_title);
        $message = "Position [$official_title] (SG $salary_grade) has been updated successfully.";
        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['delete-position'])) {
    $positionId = sanitize(decipher($_POST['verifier']));
    $showAlert = true;
    $success = false;
    $message = 'Position was not deleted successfully.';

    if (empty($positionId)) {
        $message = 'Invalid position selected.';
        return;
    }

    beginTransaction();

    try {
        $position = positions($positionId);

        if (!$position) {
            $message = 'Position not found.';
            return;
        }

        $positionTitle = strtoupper($position['official_title']);
        $affectedPosition = deletePosition($positionId);

        if ($affectedPosition === false) {
            throw new Exception('Position was not deleted successfully.');
        }

        createSystemLog($stationId, $userId, 'Deleted position', $positionId, clientIp());
        commit();

        $message = "Position [$positionTitle] has been deleted successfully.";
        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['save-vacancy'])) {
    $plantillaItemId = sanitize($_POST['item_number']);
    $datePosted = $_POST['date_posted'] ?? date('Y-m-d');
    $showAlert = true;
    $success = false;

    if (empty($plantillaItemId)) {
        $message = 'Please select a plantilla item number.';
        return;
    }

    beginTransaction();

    try {
        $affectedVacancy = createVacancy($plantillaItemId, 'open', null, $datePosted, 'new');

        if ($affectedVacancy === false) {
            throw new Exception('Vacancy was not saved successfully.');
        }

        $position = itemPosition($plantillaItemId);
        $positionTitle = strtoupper($position['official_title'] ?? 'Unknown');
        $itemNumber = $position['item_number'] ?? 'N/A';

        $message = "Vacancy for [{$positionTitle}] with item number [{$itemNumber}] has been added successfully.";

        createSystemLog($stationId, $userId, 'Added vacancy', $itemNumber, clientIp());
        commit();

        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['delete-vacancy'])) {
    $vacancyId = sanitize(decipher($_POST['verifier']));
    $showAlert = true;
    $success = false;

    if (empty($vacancyId)) {
        $message = 'Invalid vacancy selected.';
        return;
    }

    beginTransaction();

    try {
        $vacancy = vacancy($vacancyId);
        $position = strtoupper($vacancy['official_title']);
        $itemNumber = $vacancy['item_number'];
        $deletedVacancy = deleteVacancy($vacancyId);

        if ($deletedVacancy === false) {
            throw new Exception('Vacancy was not deleted successfully.');
        }

        createSystemLog($stationId, $userId, 'Deleted vacancy', $itemNumber, clientIp());
        commit();

        $message = "Vacancy for [$position] with item number [$itemNumber] has been deleted successfully.";
        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['save-plantilla-item'])) {
    $plantillaItemId = sanitize(decipher($_POST['verifier']));
    $item_number = sanitize($_POST['item-number']);
    $position_id = sanitize($_POST['position']);
    $station_id = sanitize($_POST['station']);
    $employment_status = sanitize($_POST['employment-status']);
    $is_dissolve = isset($_POST['is-dissolve']) ? 1 : 0;
    $showAlert = true;
    $success = false;

    beginTransaction();

    try {
        if (!plantillaItem($plantillaItemId)) {
            $affectedPlantillaItem = createPlantillaItem($position_id, $item_number, $employment_status, $station_id, $is_dissolve);
            $message = "Plantilla item [$item_number] created successfully.";
        } else {
            $affectedPlantillaItem = updatePlantillaItem($plantillaItemId, $position_id, $item_number, $employment_status, $station_id, $is_dissolve);
            $message = "Plantilla item [$item_number] updated successfully.";
        }

        if ($affectedPlantillaItem === false) {
            throw new Exception(doesItemNumberExist($item_number, $plantillaItemId) ? 'Item number already exists.' : 'No changes have been made to plantilla items.');
        }

        createSystemLog($stationId, $userId, 'Added Plantilla Item', $item_number, clientIp());
        commit();

        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['delete-plantilla-item'])) {
    $plantillaItemId = sanitize(decipher($_POST['verifier']));
    $showAlert = true;
    $success = false;
    $message = 'No changes have been made to plantilla items.';

    beginTransaction();

    try {
        $plantillaItemNumber = plantillaItem($plantillaItemId)['item_number'] ?? null;
        $affectedPlantillaItem = deletePlantillaItem($plantillaItemId);

        if ($affectedPlantillaItem === false) {
            throw new Exception('No changes have been made to plantilla items.');
        }

        createSystemLog($stationId, $userId, 'Deleted plantilla items', $plantillaItemNumber, clientIp());
        commit();

        $message = 'Plantilla Item has been deleted successfully.';
        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['fill-plantilla-employee'])) {
    $plantillaItemId = sanitize(decipher($_POST['verifier']));
    $employeeId = sanitize($_POST['employee_id'] ?? null);
    $startDate = sanitize($_POST['start_date'] ?? date('Y-m-d'));
    $showAlert = true;
    $success = false;

    if (empty($plantillaItemId)) {
        $message = 'Invalid plantilla item.';
        return;
    }

    if (empty($employeeId)) {
        $message = 'Please select an employee to fill this position.';
        return;
    }

    beginTransaction();

    try {
        $plantilla = plantillaItem($plantillaItemId);

        if (!$plantilla) {
            throw new Exception('Plantilla item not found.');
        }

        // Check if already filled (has a current service record linked)
        $existingRecord = find(
            "SELECT `id` FROM `service_records` WHERE `plantilla_item_id` = ? AND `is_present` = 1 AND `to_date` IS NULL LIMIT 1",
            [$plantillaItemId]
        );

        if ($existingRecord) {
            throw new Exception('This plantilla item is already filled by another employee.');
        }

        // Get position and station details
        $positionDetail = itemPosition($plantillaItemId);
        $positionTitle = $positionDetail['official_title'] ?? '';
        $itemNumber = $positionDetail['item_number'] ?? 'N/A';
        $stationId = $plantilla['station_id'];
        $stationInfo = schoolById($stationId);
        $stationName = $stationInfo['name'] ?? '';
        $employmentStatus = ucfirst($plantilla['employment_status']);

        if (empty($positionTitle)) {
            throw new Exception('Position details not found for this plantilla item.');
        }

        // Close all existing present service records for this employee before adding the new one
        update(
            'service_records',
            ['is_present' => '0', 'to_date' => $startDate],
            '`employee_id` = ? AND `is_present` = 1 AND `to_date` IS NULL',
            [$employeeId]
        );

        // Create service record
        $affectedRecord = createExperience(
            $startDate,        // from_date
            null,              // to_date (present)
            '1',               // is_present
            $positionTitle,    // designation
            $plantillaItemId,  // plantilla_item_id
            $employmentStatus, // appointment_status
            '1',               // is_government_service (DepEd is government)
            null,              // salary_grade_step_increment
            null,              // monthly_salary
            $stationName,      // agency_company (station name)
            null,              // leave_wo_pay
            '0',               // for_separation
            null,              // separation_date
            null,              // separation_cause
            $employeeId
        );

        if ($affectedRecord === false) {
            throw new Exception('Service record could not be created.');
        }

        // $affectedRecord is the new service record ID (returned by insert())
        $newServiceRecordId = is_numeric($affectedRecord) ? (int) $affectedRecord : null;

        if (!$newServiceRecordId) {
            throw new Exception('Could not retrieve the new service record ID.');
        }

        $employeeName = userName($employeeId, true);

        createSystemLog($stationId, $userId, 'Filled plantilla item', "$itemNumber - $employeeName", clientIp());
        commit();

        $message = 'Employee [<a href="' . customUri('hrmis', 'Employee Information', $employeeId) . '" title="View ' . userName($employeeId) . ' employee information">' . strtoupper(userName($employeeId, true)) . '</a>] has been successfully assigned to plantilla item [' . e($itemNumber) . '] as ' . strtoupper(e($positionTitle)) . ' at ' . strtoupper(e($stationName)) . '.';
        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['fill-vacancy'])) {
    $vacancyId = sanitize(decipher($_POST['verifier']));
    $applicantId = sanitize($_POST['employee_id'] ?? null);
    $effectivityDate = sanitize($_POST['effectivity_date'] ?? date('Y-m-d'));
    $showAlert = true;
    $success = false;

    if (empty($vacancyId)) {
        $message = 'Invalid vacancy selected.';
        return;
    }

    if (empty($applicantId)) {
        $message = 'Please select an applicant to fill this position.';
        return;
    }

    beginTransaction();

    try {
        // 1. Fetch vacancy details with position and station
        $sql = "SELECT v.`id`, v.`plantilla_item_id`, v.`vacated_by_id`, v.`date_vacated`, v.`reason`, v.`status`,
                       pi.`item_number`, pi.`station_id`, pi.`position_id`, pi.`employment_status`,
                       p.`official_title`, p.`salary_grade`
                FROM `vacancies` AS v
                INNER JOIN `plantilla_items` AS pi ON v.`plantilla_item_id` = pi.`id`
                INNER JOIN `positions` AS p ON pi.`position_id` = p.`id`
                WHERE v.`id` = ? LIMIT 1";
        $vacancy = find($sql, [$vacancyId]);
        if (!$vacancy) {
            throw new Exception('Vacancy record not found.');
        }

        $plantillaItemId = $vacancy['plantilla_item_id'];
        $itemNumber = $vacancy['item_number'];
        $stationId = $vacancy['station_id'];
        $positionId = $vacancy['position_id'];
        $positionTitle = $vacancy['official_title'];
        $employmentStatus = ucfirst($vacancy['employment_status'] ?: 'Permanent');

        $stationInfo = schoolById($stationId);
        $stationName = $stationInfo['name'] ?? '';

        // 2. Check applicant category: regular employee, non-regular employee, or outside applicant
        $isRegularEmployee = find("SELECT `id` FROM `employees` WHERE `id` = ? LIMIT 1", [$applicantId]) !== false;
        $nonRegular = nonRegularEmployee($applicantId);

        if ($nonRegular) {
            // Non-regular employee (COS / JO / Casual) selected to fill plantilla item: Migrate data to employees table
            $nonRegCols = query("DESCRIBE `non_regular_employees`");
            $empCols = query("DESCRIBE `employees`");
            if (!$nonRegCols || !$empCols) {
                throw new Exception('Failed to retrieve database schema during non-regular employee migration.');
            }

            $nonRegColNames = array_column($nonRegCols, 'Field');
            $empColNames = array_column($empCols, 'Field');
            $commonCols = array_intersect($nonRegColNames, $empColNames);

            $employeeData = [];
            foreach ($commonCols as $col) {
                if (!in_array($col, ['employment_type', 'created_at', 'updated_at'], true)) {
                    $employeeData[$col] = $nonRegular[$col];
                }
            }
            $employeeData['status'] = 'Active';

            // Insert into employees table
            $insertRes = insert('employees', $employeeData);
            if ($insertRes === false) {
                throw new Exception('Failed to migrate non-regular employee to employees table.');
            }

            // Remove from non_regular_employees table
            if (delete('non_regular_employees', '`id` = ?', [$applicantId]) === false) {
                throw new Exception('Failed to remove non-regular employee record.');
            }

            // Ensure family background, other information, and identification exist
            if (!find("SELECT `id` FROM `family_backgrounds` WHERE `employee_id` = ? LIMIT 1", [$applicantId])) {
                createFamily('', '', '', '', '', '', '', '', '', '', '', '', '', '', '', $applicantId);
            }
            if (!find("SELECT `id` FROM `other_informations` WHERE `employee_id` = ? LIMIT 1", [$applicantId])) {
                createOtherInformation(0, 0, null, 0, null, 0, null, null, 0, null, 0, null, 0, null, 0, null, 0, null, 0, null, 0, null, 0, null, $applicantId);
            }
            if (!find("SELECT `id` FROM `valid_ids` WHERE `employee_id` = ? LIMIT 1", [$applicantId])) {
                createIdentification(null, '', '', $effectivityDate, $applicantId);
            }
        } elseif (!$isRegularEmployee) {
            // Fetch applicant details
            $applicant = find("SELECT * FROM `applicants` WHERE `id` = ? LIMIT 1", [$applicantId]);
            if (!$applicant) {
                throw new Exception('Applicant record not found.');
            }

            // Transfer columns that have the same name in both applicants and employees tables
            $applicantCols = query("DESCRIBE `applicants`");
            $employeeCols = query("DESCRIBE `employees`");
            if (!$applicantCols || !$employeeCols) {
                throw new Exception('Failed to retrieve table schemas.');
            }

            $applicantColNames = array_column($applicantCols, 'Field');
            $employeeColNames = array_column($employeeCols, 'Field');
            $commonCols = array_intersect($applicantColNames, $employeeColNames);

            $employeeData = [];
            foreach ($commonCols as $col) {
                $employeeData[$col] = $applicant[$col];
            }

            // Force override specific required employee fields
            $employeeData['status'] = 'Active';
            $employeeData['profile_picture'] = 'assets/img/user.png';

            // Insert into employees table
            $insertRes = insert('employees', $employeeData);
            if ($insertRes === false) {
                throw new Exception('Failed to copy applicant details to employees table.');
            }

            // Create family background
            if (createFamily('', '', '', '', '', '', '', '', '', '', '', '', '', '', '', $applicantId) === false) {
                throw new Exception('Failed to create family background.');
            }

            // Create other information
            if (createOtherInformation(0, 0, null, 0, null, 0, null, null, 0, null, 0, null, 0, null, 0, null, 0, null, 0, null, 0, null, 0, null, $applicantId) === false) {
                throw new Exception('Failed to create other information.');
            }

            // Create identification
            if (createIdentification(null, '', '', $effectivityDate, $applicantId) === false) {
                throw new Exception('Failed to create identification.');
            }

            // Create credentials account
            if (createAccount($applicantId, hashPassword(generateStrongRandomPassword())) === false) {
                throw new Exception('Failed to create credentials account.');
            }

            // Delete applicant from applicants table
            if (delete('applicants', '`id` = ?', [$applicantId]) === false) {
                throw new Exception('Failed to delete applicant record.');
            }
        } else {
            // Already a regular employee: activate
            updateEmployeeStatus('Active', $applicantId);
        }

        // 3. Update/Assign Station Assignment
        if (!station($applicantId)) {
            $affectedStation = createStation($effectivityDate, $stationId, $positionId, $applicantId);
        } else {
            $affectedStation = updateStation($effectivityDate, $stationId, $positionId, $applicantId);
        }

        if ($affectedStation === false) {
            throw new Exception('Failed to assign station assignment.');
        }

        // 4. Close previous present service records and open the new one
        update(
            'service_records',
            ['is_present' => '0', 'to_date' => $effectivityDate],
            '`employee_id` = ? AND `is_present` = 1 AND `to_date` IS NULL',
            [$applicantId]
        );

        $affectedRecord = createExperience(
            $effectivityDate,  // from_date
            null,              // to_date
            '1',               // is_present
            $positionTitle,    // designation
            $plantillaItemId,  // plantilla_item_id
            $employmentStatus, // appointment_status
            '1',               // is_government_service
            null,              // salary_grade_step_increment
            null,              // monthly_salary
            $stationName,      // agency_company
            null,              // leave_wo_pay
            '0',               // for_separation
            null,              // separation_date
            null,              // separation_cause
            $applicantId
        );

        if ($affectedRecord === false) {
            throw new Exception('Failed to create new service record.');
        }

        // 5. Save to vacancy history
        $historyData = [
            'vacancy_id' => $vacancyId,
            'plantilla_item_id' => $plantillaItemId,
            'vacated_by_id' => $vacancy['vacated_by_id'],
            'date_vacated' => $vacancy['date_vacated'],
            'date_filled' => $effectivityDate,
            'reason' => $vacancy['reason'],
            'filled_by_id' => $applicantId
        ];

        if (insert('vacancy_history', $historyData) === false) {
            throw new Exception('Failed to write to vacancy history.');
        }

        // 6. Update vacancy status to 'filled' in vacancies table
        $updateVacancy = update('vacancies', ['status' => 'filled'], '`id` = ?', [$vacancyId]);
        if ($updateVacancy === false) {
            throw new Exception('Failed to update vacancy status to filled.');
        }

        $employeeName = userName($applicantId, true);
        createSystemLog($stationId, $userId, 'Filled vacancy position', "Vacancy: $itemNumber - Filled by: $employeeName", clientIp());
        commit();

        $message = "Vacancy for plantilla item <strong>" . e($itemNumber) . "</strong> has been successfully filled by <strong>" . e($employeeName) . "</strong>.";
        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['publish-vacancies'])) {
    $showAlert = true;
    $success = false;
    $title = sanitize($_POST['pub_title']);
    $description = sanitize($_POST['pub_description']);
    $openDate = sanitize($_POST['open_date'] ?? date('Y-m-d'));
    $closeDate = sanitize($_POST['close_date'] ?? date('Y-m-d', strtotime('+30 days')));
    $pubStatus = sanitize($_POST['pub_status'] ?? 'open');
    $vacancyIds = $_POST['vacancy_ids'] ?? [];
    $message = 'Failed to create call for application.';

    if (empty($title)) {
        $message = 'Please enter a call for application title.';
        return;
    }

    if (empty($vacancyIds)) {
        $message = 'Please select at least one vacancy to publish.';
        return;
    }

    if (strtotime($closeDate) < strtotime($openDate)) {
        $message = 'Closing date cannot be before opening date.';
        return;
    }

    $code = generatePublicationCode();

    beginTransaction();

    try {
        $publicationId = createPublication($code, $title, $description, $openDate, $closeDate, $pubStatus);

        if (!$publicationId) {
            throw new Exception('Failed to create call for application.');
        }

        $totalVacanciesLinked = 0;

        foreach ($vacancyIds as $vacancyId) {
            addPublicationItem($publicationId, $vacancyId);
            $totalVacanciesLinked++;
        }

        createSystemLog($stationId, $userId, 'Created call for application', "$code - $totalVacanciesLinked items linked", clientIp());
        commit();

        $applicationUrl = uri() . '/hrmis/apply?p=' . $code;
        $success = true;
        $message = "Call for application created successfully!<br><br>
                <strong>Application URL:</strong><br>
                <a href=\"{$applicationUrl}\" target=\"_blank\" class=\"text-primary\">{$applicationUrl}</a><br><br>
                <small class=\"text-muted\">Share this link to accept applications.</small>";

    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['delete-publication'])) {
    $publicationId = sanitize(decipher($_POST['verifier']));
    $showAlert = true;
    $success = false;

    if (empty($publicationId)) {
        $message = 'Invalid call for application selected.';
        return;
    }

    $publication = publication($publicationId);
    $code = $publication['code'] ?? 'N/A';

    beginTransaction();

    try {
        if (clearPublicationItems($publicationId) === false) {
            throw new Exception('Failed to delete vacancy call for application items.');
        }

        if (deletePublication($publicationId) === false) {
            throw new Exception('Failed to delete vacancy call for application.');
        }

        createSystemLog($stationId, $userId, 'Deleted call for application', $code, clientIp());
        commit();

        $message = "Call for application [$code] has been deleted successfully.";
        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['save-publication'])) {
    $publicationId = sanitize(decipher($_POST['verifier'] ?? null));
    $code = $statusMessage = null;
    $showAlert = true;
    $success = false;
    $title = sanitize($_POST['pub_title']);
    $description = sanitize($_POST['pub_description']);
    $openDate = sanitize($_POST['open_date'] ?? date('Y-m-d'));
    $closeDate = sanitize($_POST['close_date'] ?? date('Y-m-d', strtotime('+30 days')));
    $pubStatus = sanitize($_POST['pub_status']);
    $vacancyIds = $_POST['vacancy_ids'] ?? [];

    if (empty($title)) {
        $message = 'Please enter a call for application title.';
        return;
    }

    if (empty($vacancyIds)) {
        $message = 'Please select at least one vacancy to call for application.';
        return;
    }

    if (strtotime($closeDate) < strtotime($openDate)) {
        $message = 'Closing date cannot be before opening date.';
        return;
    }

    if ($pubStatus === 'closed' && !empty($publicationId)) {
        $canClose = canClosePublication($publicationId);
        if (!$canClose['can_close']) {
            $message = $canClose['reason'];
            return;
        }
    }

    beginTransaction();

    try {
        if (!empty($publicationId)) {
            $existingFilled = query("SELECT vpi.vacancy_id FROM vacancy_publication_items AS vpi INNER JOIN vacancies AS v ON vpi.vacancy_id = v.id WHERE vpi.publication_id = ? AND v.status = 'filled'", [$publicationId]);
            if (!empty($existingFilled)) {
                $filledIds = array_column($existingFilled, 'vacancy_id');
                $vacancyIds = array_unique(array_merge($vacancyIds, $filledIds));
            }

            if (clearPublicationItems($publicationId) === false) {
                throw new Exception('Failed to clear call for application vacancy items.');
            }
        }

        if (empty($publicationId)) {
            $code = generatePublicationCode();
            $publicationId = createPublication($code, $title, $description, $openDate, $closeDate, $pubStatus);
            if (!$publicationId) {
                throw new Exception('Failed to create call for application.');
            }
            $statusMessage = 'create';
        } else {
            $code = publication($publicationId)['code'];
            updatePublication($publicationId, $title, $description, $openDate, $closeDate, $pubStatus);
            $statusMessage = 'update';
        }

        $totalVacanciesLinked = 0;
        foreach ($vacancyIds as $vacancyId) {
            addPublicationItem($publicationId, $vacancyId);
            $totalVacanciesLinked++;
        }

        createSystemLog($stationId, $userId, "{$statusMessage}d vacancy call for application", "$code - $totalVacanciesLinked items linked", clientIp());
        commit();

        $applicationUrl = uri() . '/hrmis/apply?p=' . $code;
        $success = true;
        $message = "Call for application has been successfully {$statusMessage}d!<br><br>
                <strong>Application URL:</strong><br>
                <a href=\"{$applicationUrl}\" target=\"_blank\" class=\"text-primary\">{$applicationUrl}</a><br><br>
                <small class=\"text-muted\">Share this link to accept applications.</small>";
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['readd-vacancy'])) {
    $publicationId = sanitize(decipher($_POST['verifier'] ?? null));
    $vacancyId = sanitize(decipher($_POST['data-verifier'] ?? null));
    $showAlert = true;
    $success = false;

    if (empty($publicationId) || empty($vacancyId)) {
        $message = 'Invalid request parameters.';
    } else {
        beginTransaction();
        try {
            $res = readdVacancyToVacancies($publicationId, $vacancyId);
            if (!$res['success']) {
                throw new Exception($res['message']);
            }
            createSystemLog($stationId, $userId, 'Re-added vacancy to plantilla vacancies', "Publication ID: {$publicationId}, Vacancy ID: {$vacancyId}", clientIp());
            commit();
            $success = true;
            $message = $res['message'];
        } catch (Exception $e) {
            rollBack();
            $message = $e->getMessage();
        }
    }
}

if (isset($_POST['readd-all-vacancies'])) {
    $publicationId = sanitize(decipher($_POST['verifier'] ?? null));
    $showAlert = true;
    $success = false;

    if (empty($publicationId)) {
        $message = 'Invalid call for application selected.';
    } else {
        beginTransaction();
        try {
            $res = readdUnfilledVacanciesFromPublication($publicationId);
            if (!$res['success']) {
                throw new Exception($res['message']);
            }
            createSystemLog($stationId, $userId, 'Re-added all unfilled vacancies from closed publication', "Publication ID: {$publicationId}, Readded count: {$res['count']}", clientIp());
            commit();
            $success = true;
            $message = $res['message'];
        } catch (Exception $e) {
            rollBack();
            $message = $e->getMessage();
        }
    }
}

if (isset($_POST['qualify-application'])) {
    $applicationId = sanitize(decipher($_POST['verifier']));
    $showAlert = true;
    $success = false;

    if (empty($applicationId)) {
        $message = 'Invalid application selected.';
        return;
    }

    beginTransaction();

    try {
        $application = applicationRecord($applicationId);

        if (empty($application)) {
            throw new Exception('Application record not found.');
        }

        $applicationCode = applicantCode($application['application_code_id']);
        $applicantName = applicantName($applicationCode, true);
        $positionData = find("SELECT `official_title` FROM `positions` WHERE `id` = ?", [$application['position_id']]);
        $position = strtoupper($positionData ? $positionData['official_title'] : 'Unknown Position');

        $result = qualifyApplication($applicationId);

        if ($result === false) {
            throw new Exception('Application was not qualified successfully.');
        }

        createSystemLog($stationId, $userId, 'Qualified application', "$applicantName - $position", clientIp());
        commit();

        $message = "Application of {$applicantName} for {$position} has been marked as qualified.";
        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['disqualify-application'])) {
    $applicationId = sanitize(decipher($_POST['verifier']));
    $remarks = sanitize($_POST['remarks']);
    $showAlert = true;
    $success = false;

    if (empty($applicationId)) {
        $message = 'Invalid application selected.';
        return;
    }

    if (empty($remarks)) {
        $message = 'Remarks is required.';
        return;
    }

    beginTransaction();

    try {
        $application = applicationRecord($applicationId);

        if (empty($application)) {
            throw new Exception('Application record not found.');
        }

        $applicationCode = applicantCode($application['application_code_id']);
        $applicantName = applicantName($applicationCode, true);
        $positionData = find("SELECT `official_title` FROM `positions` WHERE `id` = ?", [$application['position_id']]);
        $position = strtoupper($positionData ? $positionData['official_title'] : 'Unknown Position');

        $result = disqualifyApplication($applicationId, $remarks);

        if ($result === false) {
            throw new Exception('Application was not disqualified successfully.');
        }

        createSystemLog($stationId, $userId, 'Disqualified application', "$applicantName - $position - Reason: $remarks", clientIp());
        commit();

        $message = "Application of {$applicantName} for {$position} has been marked as disqualified.";
        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['for-review-application'])) {
    $applicationId = sanitize(decipher($_POST['verifier']));
    $showAlert = true;
    $success = false;

    if (empty($applicationId)) {
        $message = 'Invalid application selected.';
        return;
    }

    beginTransaction();

    try {
        $application = applicationRecord($applicationId);

        if (empty($application)) {
            throw new Exception('Application record not found.');
        }

        $applicationCode = applicantCode($application['application_code_id']);
        $applicantName = applicantName($applicationCode, true);
        $positionData = find("SELECT `official_title` FROM `positions` WHERE `id` = ?", [$application['position_id']]);
        $position = strtoupper($positionData ? $positionData['official_title'] : 'Unknown Position');

        $result = forReviewApplication($applicationId);

        if ($result === false) {
            throw new Exception('Application was not successfully marked for initial screening.');
        }

        createSystemLog($stationId, $userId, 'Marked application for initial screening', "$applicantName - $position", clientIp());
        commit();

        $message = "Application of {$applicantName} for {$position} has been marked for initial screening.";
        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['save-assessment-score'])) {
    $applicationId = sanitize(decipher($_POST['verifier']));
    $showAlert = true;
    $success = false;

    if (empty($applicationId)) {
        $message = 'Invalid application selected.';
        return;
    }

    beginTransaction();

    try {
        $application = applicationRecord($applicationId);

        if (empty($application)) {
            throw new Exception('Application record not found.');
        }

        $positionData = find("SELECT `official_title`, `salary_grade`, `category` FROM `positions` WHERE `id` = ?", [$application['position_id']]);
        $position = strtoupper($positionData ? $positionData['official_title'] : 'Unknown Position');
        $positionSG = $positionData ? (int) $positionData['salary_grade'] : 0;
        $positionCat = $positionData ? $positionData['category'] : '';

        $positionTitle = $positionData ? $positionData['official_title'] : '';
        $sgLabel = getScoringCategoryLabel($positionSG, $positionCat, $positionTitle);

        $scoringWeights = find(
            "SELECT * FROM `scoring_criteria_weights` WHERE `scoring_category` = ? LIMIT 1",
            [$sgLabel]
        );

        $w = [
            'education' => $scoringWeights ? (float) $scoringWeights['education_max_points'] : 5,
            'training' => $scoringWeights ? (float) $scoringWeights['training_max_points'] : 10,
            'experience' => $scoringWeights ? (float) $scoringWeights['experience_max_points'] : 15,
            'performance' => $scoringWeights ? (float) $scoringWeights['performance_max_points'] : 20,
            'accomplishments' => $scoringWeights ? (float) $scoringWeights['accomplishments_max_points'] : 10,
            'app_edu' => $scoringWeights ? (float) $scoringWeights['application_education_max_points'] : 10,
            'app_ld' => $scoringWeights ? (float) $scoringWeights['application_ld_max_points'] : 10,
            'potential' => $scoringWeights ? (float) $scoringWeights['potential_max_points'] : 20,
            'pbet_let_lept' => $scoringWeights ? (float) ($scoringWeights['pbet_let_lept_max_points'] ?? 0) : 0,
            'ppst_cot' => $scoringWeights ? (float) ($scoringWeights['ppst_cot_max_points'] ?? 0) : 0,
            'ppst_reflection' => $scoringWeights ? (float) ($scoringWeights['ppst_reflection_max_points'] ?? 0) : 0,
            'ppst' => $scoringWeights ? (float) ($scoringWeights['ppst_max_points'] ?? 0) : 0,
            'bei' => $scoringWeights ? (float) ($scoringWeights['bei_max_points'] ?? 0) : 0,
            'total' => $scoringWeights ? (float) $scoringWeights['total_max_points'] : 100,
        ];

        $educationScore = round((float) ($_POST['education_score'] ?? 0), 3);
        $trainingScore = round((float) ($_POST['training_score'] ?? 0), 3);
        $experienceScore = round((float) ($_POST['experience_score'] ?? 0), 3);
        $performanceScore = round((float) ($_POST['performance_score'] ?? 0), 3);
        $accomplishmentsScore = round((float) ($_POST['outstanding_accomplishments_score'] ?? 0), 3);
        $appEduScore = round((float) ($_POST['application_of_education_score'] ?? 0), 3);
        $appLdScore = round((float) ($_POST['application_of_ld_score'] ?? 0), 3);

        $pbetScore = round((float) ($_POST['pbet_let_lept_score'] ?? 0), 3);
        $ppstCotScore = round((float) ($_POST['ppst_cot_score'] ?? 0), 3);
        $ppstReflectionScore = round((float) ($_POST['ppst_reflection_score'] ?? 0), 3);
        $ppstScore = round((float) ($_POST['ppst_score'] ?? $ppstCotScore), 3);

        $examRaw = round((float) ($_POST['potential_written_exam_raw'] ?? 0), 3);
        $beiRaw = round((float) ($_POST['potential_bei_raw'] ?? $_POST['bei_score'] ?? 0), 3);
        $wstRaw = round((float) ($_POST['potential_wst_raw'] ?? 0), 3);
        $beiScore = round((float) ($_POST['bei_score'] ?? $beiRaw), 3);

        if ($sgLabel === 'Teacher II to Teacher III and Master Teacher I to Master Teacher III') {
            $ppstMax = $w['ppst'] > 0 ? $w['ppst'] : $w['ppst_cot'];
            $beiMax = $w['bei'] > 0 ? $w['bei'] : $w['potential'];

            if ($educationScore < 0 || $educationScore > $w['education']) {
                throw new Exception("Education score must be between 0 and {$w['education']}.");
            }
            if ($trainingScore < 0 || $trainingScore > $w['training']) {
                throw new Exception("Training score must be between 0 and {$w['training']}.");
            }
            if ($experienceScore < 0 || $experienceScore > $w['experience']) {
                throw new Exception("Experience score must be between 0 and {$w['experience']}.");
            }
            if ($performanceScore < 0 || $performanceScore > $w['performance']) {
                throw new Exception("Performance score must be between 0 and {$w['performance']}.");
            }
            if ($ppstScore < 0 || $ppstScore > $ppstMax) {
                throw new Exception("PPST score must be between 0 and {$ppstMax}.");
            }
            if ($beiScore < 0 || $beiScore > $beiMax) {
                throw new Exception("BEI score must be between 0 and {$beiMax}.");
            }

            $ppstCotScore = $ppstScore;
            $potentialFinal = $beiScore;
            $beiRaw = $beiScore;

            $totalAccumulated = $educationScore + $trainingScore + $experienceScore + $performanceScore + $ppstScore + $beiScore;
        } elseif ($sgLabel === 'Teacher I (DO 007 s. 2023)') {
            if ($educationScore < 0 || $educationScore > $w['education']) {
                throw new Exception("Education score must be between 0 and {$w['education']}.");
            }
            if ($trainingScore < 0 || $trainingScore > $w['training']) {
                throw new Exception("Training score must be between 0 and {$w['training']}.");
            }
            if ($experienceScore < 0 || $experienceScore > $w['experience']) {
                throw new Exception("Experience score must be between 0 and {$w['experience']}.");
            }
            if ($pbetScore < 0 || $pbetScore > $w['pbet_let_lept']) {
                throw new Exception("PBET/LET/LEPT score must be between 0 and {$w['pbet_let_lept']}.");
            }
            if ($ppstCotScore < 0 || $ppstCotScore > $w['ppst_cot']) {
                throw new Exception("PPST COI score must be between 0 and {$w['ppst_cot']}.");
            }
            if ($ppstReflectionScore < 0 || $ppstReflectionScore > $w['ppst_reflection']) {
                throw new Exception("PPST Reflection score must be between 0 and {$w['ppst_reflection']}.");
            }

            $totalAccumulated = $educationScore + $trainingScore + $experienceScore + $pbetScore + $ppstCotScore + $ppstReflectionScore;
            $potentialFinal = 0;
        } else {
            if ($educationScore < 0 || $educationScore > $w['education']) {
                throw new Exception("Education score must be between 0 and {$w['education']}.");
            }
            if ($trainingScore < 0 || $trainingScore > $w['training']) {
                throw new Exception("Training score must be between 0 and {$w['training']}.");
            }
            if ($experienceScore < 0 || $experienceScore > $w['experience']) {
                throw new Exception("Experience score must be between 0 and {$w['experience']}.");
            }
            if ($performanceScore < 0 || $performanceScore > $w['performance']) {
                throw new Exception("Performance score must be between 0 and {$w['performance']}.");
            }
            if ($accomplishmentsScore < 0 || $accomplishmentsScore > $w['accomplishments']) {
                throw new Exception("Accomplishments score must be between 0 and {$w['accomplishments']}.");
            }
            if ($appEduScore < 0 || $appEduScore > $w['app_edu']) {
                throw new Exception("Application of Education score must be between 0 and {$w['app_edu']}.");
            }
            if ($appLdScore < 0 || $appLdScore > $w['app_ld']) {
                throw new Exception("Application of L&D score must be between 0 and {$w['app_ld']}.");
            }

            if ($examRaw < 0 || $examRaw > $w['potential']) {
                throw new Exception("Written Exam raw score must be between 0 and {$w['potential']}.");
            }
            if ($beiRaw < 0 || $beiRaw > $w['potential']) {
                throw new Exception("BEI raw score must be between 0 and {$w['potential']}.");
            }
            if ($wstRaw < 0 || $wstRaw > $w['potential']) {
                throw new Exception("WST raw score must be between 0 and {$w['potential']}.");
            }

            $potentialFinal = round($examRaw + $beiRaw + $wstRaw, 3);
            if ($potentialFinal > $w['potential']) {
                throw new Exception("Combined Potential score ({$potentialFinal}) exceeds the maximum allowed ({$w['potential']}).");
            }

            $totalAccumulated = $educationScore + $trainingScore + $experienceScore
                + $performanceScore + $accomplishmentsScore + $appEduScore + $appLdScore
                + $potentialFinal;
        }

        $totalAccumulated = round(min($totalAccumulated, $w['total']), 3);

        $applicationCodeId = $application['application_code_id'];
        $applicationCode = applicantCode($applicationCodeId);
        $applicantName = applicantName($applicationCode, true);

        $data = [
            'education_score' => $educationScore,
            'training_score' => $trainingScore,
            'experience_score' => $experienceScore,
            'pbet_let_lept_score' => $pbetScore,
            'ppst_cot_score' => $ppstCotScore,
            'ppst_reflection_score' => $ppstReflectionScore,
            'ppst_score' => $ppstScore,
            'performance_score' => $performanceScore,
            'outstanding_accomplishments_score' => $accomplishmentsScore,
            'application_of_education_score' => $appEduScore,
            'application_of_ld_score' => $appLdScore,
            'potential_written_exam_raw' => $examRaw,
            'potential_bei_raw' => $beiRaw,
            'potential_wst_raw' => $wstRaw,
            'potential_final_score' => $potentialFinal,
            'bei_score' => $beiScore,
            'total_accumulated_score' => $totalAccumulated,
            'hrmspb_remarks' => sanitize($_POST['hrmspb_remarks'] ?? '')
        ];

        $result = saveAssessmentScore($applicationId, $data);

        if ($result === false) {
            throw new Exception('Assessment score was not saved successfully.');
        }

        createSystemLog($stationId, $userId, 'Saved applicant assessment score', "$applicantName - $position - Score: {$data['total_accumulated_score']}", clientIp());

        $applyToOtherApps = $_POST['apply_to_other_apps'] ?? [];
        if (!is_array($applyToOtherApps)) {
            $applyToOtherApps = [];
        }

        foreach ($applyToOtherApps as $cipheredOtherAppId) {
            $otherAppId = sanitize(decipher($cipheredOtherAppId));
            if (empty($otherAppId)) {
                continue;
            }

            // Safety check
            $otherApp = applicationRecord($otherAppId);
            if (empty($otherApp) || $otherApp['publication_id'] != $application['publication_id'] || $otherApp['application_code_id'] != $application['application_code_id']) {
                throw new Exception('Invalid application specified for score copying.');
            }

            // Verify weights match
            $otherPositionData = find("SELECT `official_title`, `salary_grade`, `category` FROM `positions` WHERE `id` = ?", [$otherApp['position_id']]);
            $otherPosition = strtoupper($otherPositionData ? $otherPositionData['official_title'] : 'Unknown Position');
            $otherSG = $otherPositionData ? (int) $otherPositionData['salary_grade'] : 0;
            $otherCat = $otherPositionData ? $otherPositionData['category'] : '';
            $otherTitle = $otherPositionData ? $otherPositionData['official_title'] : '';

            $otherSgLabel = getScoringCategoryLabel($otherSG, $otherCat, $otherTitle);

            if ($otherSgLabel !== $sgLabel) {
                throw new Exception("Position '{$otherPosition}' does not have the same scoring criteria weights.");
            }

            $otherResult = saveAssessmentScore($otherAppId, $data);
            if ($otherResult === false) {
                throw new Exception("Assessment score for other position '{$otherPosition}' was not saved successfully.");
            }

            createSystemLog($stationId, $userId, 'Saved applicant assessment score', "$applicantName - $otherPosition - Score: {$data['total_accumulated_score']} (Copied)", clientIp());
        }

        commit();

        $message = "Assessment score for {$applicantName} has been saved successfully.";
        $success = true;
    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

if (isset($_POST['approve-transfer-request'])) {
    $requestId = sanitize(decipher($_POST['data-verifier'] ?? null));
    $remarks = sanitize($_POST['remarks'] ?? '');
    $showAlert = true;
    $success = false;

    try {
        if (empty($requestId)) {
            throw new Exception('Invalid transfer request selected.');
        }

        $request = getTransferRequest($requestId);
        if (!$request) {
            throw new Exception('Transfer request not found.');
        }

        if ($request['status'] !== 'Pending') {
            throw new Exception('This transfer request has already been processed.');
        }

        $eId = $request['employee_id'];
        $eTargetStationId = $request['target_station_id'];

        $pos = position($eId);
        $positionId = $pos ? $pos['position_id'] : '';

        if (empty($positionId)) {
            throw new Exception('Employee position could not be resolved. Please set a position for this employee first.');
        }

        beginTransaction();

        // Update transfer request status
        $res1 = updateTransferRequestStatus($requestId, 'Approved', $remarks);
        if ($res1 === false) {
            throw new Exception('Failed to update transfer request status.');
        }

        if (user($eId)) {
            deleteUserRoles($eId);
        }

        // Update station assignment
        $date = date('Y-m-d');
        if (!station($eId)) {
            $res2 = createStation($date, $eTargetStationId, $positionId, $eId);
        } else {
            updateEmployeeStatus('Active', $eId);
            $res2 = updateStation($date, $eTargetStationId, $positionId, $eId);
        }

        if ($res2 === false) {
            throw new Exception('Failed to update employee station assignment.');
        }

        createSystemLog($stationId, $userId, 'Approved transfer request', $eId, clientIp());
        commit();

        $success = true;
        $message = 'Transfer request has been approved successfully and the employee assignment has been updated.';
    } catch (Exception $e) {
        rollBack();
        $success = false;
        $message = $e->getMessage();
    }
}

if (isset($_POST['disapprove-transfer-request'])) {
    $requestId = sanitize(decipher($_POST['data-verifier'] ?? null));
    $remarks = sanitize($_POST['remarks'] ?? '');
    $showAlert = true;
    $success = false;

    try {
        if (empty($requestId)) {
            throw new Exception('Invalid transfer request selected.');
        }
        if (empty($remarks)) {
            throw new Exception('Remarks are required when disapproving a request.');
        }

        $request = getTransferRequest($requestId);
        if (!$request) {
            throw new Exception('Transfer request not found.');
        }

        if ($request['status'] !== 'Pending') {
            throw new Exception('This transfer request has already been processed.');
        }

        $eId = $request['employee_id'];

        beginTransaction();

        $res1 = updateTransferRequestStatus($requestId, 'Disapproved', $remarks);
        if ($res1 === false) {
            throw new Exception('Failed to update transfer request status.');
        }

        createSystemLog($stationId, $userId, 'Disapproved transfer request', $eId, clientIp());
        commit();

        $success = true;
        $message = 'Transfer request has been disapproved successfully.';
    } catch (Exception $e) {
        rollBack();
        $success = false;
        $message = $e->getMessage();
    }
}

if (isset($_POST['update-applicant'])) {
    $showAlert = true;
    $success = false;
    $applicantId = sanitize(decipher($_POST['verifier'] ?? ''));

    if (empty($applicantId)) {
        $message = 'Invalid applicant selected.';
    } else {
        $existingApplicant = applicant($applicantId);
        if (!$existingApplicant) {
            $message = 'Applicant record not found.';
        } else {
            $data = prepareApplicantData($_POST);
            unset($data['id']);

            if (!empty($data['email_address']) && $data['email_address'] !== $existingApplicant['email_address']) {
                if (applicantEmailExists($data['email_address'])) {
                    $message = 'The email address provided is already registered by another applicant.';
                }
            }

            if (!empty($data['mobile_number']) && $data['mobile_number'] !== $existingApplicant['mobile_number']) {
                if (applicantMobileExists($data['mobile_number'])) {
                    $message = 'The mobile number provided is already registered by another applicant.';
                }
            }

            if (empty($message)) {
                if (updateExternalApplicant($applicantId, $data) !== false) {
                    createSystemLog($stationId, $userId, 'Updated external applicant details', $applicantId, clientIp());
                    $success = true;
                    $message = 'External applicant information has been updated successfully.';
                } else {
                    $message = 'Failed to update external applicant details or no changes were made.';
                }
            }
        }
    }
}

if (isset($_POST['delete-applicant'])) {
    $showAlert = true;
    $success = false;
    $applicantId = sanitize(decipher($_POST['verifier'] ?? ''));

    if (empty($applicantId)) {
        $message = 'Invalid applicant selected for removal.';
    } else {
        $result = deleteExternalApplicant($applicantId);
        if ($result['success']) {
            createSystemLog($stationId, $userId, 'Removed external applicant', $applicantId, clientIp());
            $success = true;
            $message = $result['message'];
        } else {
            $message = $result['message'];
        }
    }
}