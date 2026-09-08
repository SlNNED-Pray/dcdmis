<?php
$activeApp = $_SESSION["{$prefix}activeApp"] = 'ims';
$page = $appTitle = 'Inventory Management System';
require_once(root() . '/ims/helpers.php');

if (!isset($userId)) {
    redirect("{$baseUri}/login");
}

if (isset($_SESSION["{$prefix}stock_flash"])) {
    $flash = $_SESSION["{$prefix}stock_flash"];
    $showAlert = true;
    $success = $flash['success'];
    $message = $flash['message'];
    unset($_SESSION["{$prefix}stock_flash"]);
}

if (isset($_SESSION["{$prefix}change_password"])) {
    redirect("{$baseUri}/login/change");
}

if (isset($_POST['primary-search-button'])) {
    redirect(customUri('ims', 'Stock and Inventory', sanitize($_POST['primary-search-text'])));
}

if (isset($_POST['update-identification'])) {
    $card = sanitize($_POST['card-type']);
    $number = sanitize($_POST['card-number']);
    $place = sanitize($_POST['card-place']);
    $date = sanitize($_POST['card-date']);
    $showAlert = true;
    $result = !employeeIdentification($userId) ?
        createIdentification($card, $number, $place, $date, $userId) :
        updateIdentification($card, $number, $place, $date, $userId);

    if ($result === false) {
        $success = false;
        $message = 'We encountered an error on our end. Please try again later.';
        return;
    }


    if ($result === 0) {
        $message = 'No changes have been made to government issued ID.';
    } else {
        $message = 'Government issued ID has been updated successfully.';
        $success = true;

        createSystemLog($stationId, $userId, 'Updated identification details', $userId, clientIp());
    }
}

if (isset($_POST['save-payslip'])) {
    $employeeId = sanitize(decipher($_POST['verifier'] ?? null));
    $payslipId = sanitize(decipher($_POST['data-verifier'] ?? null));
    $description = sanitize($_POST['description']);
    $oldFilename = sanitize(decipher($_POST['file-verifier'] ?? null));
    $showAlert = true;
    $stagedFile = null;

    try {
        if (empty($employeeId)) {
            throw new Exception('Invalid or expired transaction.');
        }

        if (!empty($_FILES['file-upload']['tmp_name']) && is_uploaded_file($_FILES['file-upload']['tmp_name'])) {
            $stagedFile = stageUploadedFile(
                $_FILES['file-upload'],
                ['application/pdf' => 'pdf'],
                root() . "/uploads/201_files/{$employeeId}",
                "PAYSLIP"
            );
        }

        beginTransaction();

        $newFilename = $stagedFile ? "uploads/201_files/{$employeeId}/{$stagedFile['secure_name']}" : $oldFilename;

        if (empty($newFilename)) {
            throw new Exception('No changes have been made to payslips.');
        }

        $ext = pathinfo($newFilename, PATHINFO_EXTENSION);
        $hasExistingRecord = fileAttachment($employeeId, $payslipId);

        if (!$hasExistingRecord) {
            $result = createFileAttachment(20, $description, $newFilename, $ext, $employeeId);
            $logMessage = 'Added payslip.';
        } else {
            $result = updateFileAttachment(20, $description, $newFilename, $ext, $employeeId, $payslipId);
            $logMessage = 'Updated payslip.';
        }

        if ($result === false) {
            throw new Exception('We encountered an error on our end. Please try again later.');
        }

        if ($stagedFile) {
            commitStagedFile($stagedFile);
        }

        commit();

        $success = true;
        $actionText = $hasExistingRecord ? 'updated' : 'added';
        $message = "Payslip has been {$actionText} successfully.";

        createSystemLog($stationId, $userId, $logMessage, $employeeId, clientIp());

        if ($stagedFile && !empty($oldFilename) && file_exists(root() . "/{$oldFilename}")) {
            unlink(root() . "/{$oldFilename}");
        }
    } catch (Exception $e) {
        rollBack();

        if ($stagedFile && file_exists($stagedFile['full_path'])) {
            unlink($stagedFile['full_path']);
        }

        $success = false;
        $message = $e->getMessage();
    }
}

if (isset($_POST['delete-payslip'])) {
    $employeeId = sanitize(decipher($_POST['verifier'] ?? null));
    $payslipId = sanitize(decipher($_POST['data-verifier'] ?? null));
    $showAlert = true;
    $success = false;
    $file = fileAttachment($employeeId, $payslipId);

    if (!$file) {
        $message = 'The requested payslip file does not exist.';
        return;
    }

    $filename = $file['file_name'];
    $filePath = root() . "/{$filename}";

    if (file_exists($filePath)) {
        if (!unlink($filePath)) {
            $message = 'We encountered an error deleting the physical file. Please try again.';
            return;
        }
    }

    $result = deleteFileAttachment($employeeId, $payslipId);

    if ($result === false) {
        $message = 'We encountered an error updating the database. Please try again later.';
        return;
    }

    if ($result === 0) {
        $message = 'No changes have been made to the payslip database record.';
        return;
    }

    $success = true;
    $message = 'Payslip has been deleted successfully.';

    createSystemLog($stationId, $userId, 'Deleted employee payslip', $employeeId, clientIp());
}

if (isset($_POST['submit-transfer-request'])) {
    $targetStationId = sanitize($_POST['target-station']);
    $reason = sanitize($_POST['reason']);
    $showAlert = true;
    $success = false;
    $stagedFile = null;

    try {
        if (empty($targetStationId)) {
            throw new Exception('Please select a preferred station assignment.');
        }
        if (empty($reason)) {
            throw new Exception('Please state your reason for the transfer request.');
        }
        if (empty($_FILES['attachment']['tmp_name']) || !is_uploaded_file($_FILES['attachment']['tmp_name'])) {
            throw new Exception('Please upload a supporting document.');
        }

        $currStation = station($userId);
        $currentStationId = $currStation ? $currStation['station_id'] : '';

        if (empty($currentStationId)) {
            throw new Exception('Your current station assignment could not be resolved. Please contact HR.');
        }

        if ($currentStationId === $targetStationId) {
            throw new Exception('Your target station must be different from your current station.');
        }

        $isTeaching = false;
        if ($currStation) {
            $pos = positions($currStation['position_id']);
            if ($pos && $pos['category'] === 'Teaching') {
                $isTeaching = true;
            }
        }

        $specialization = null;
        if ($isTeaching) {
            $specialization = sanitize($_POST['specialization'] ?? '');
            if (empty($specialization)) {
                throw new Exception('Please fill up your major subject / area of specialization.');
            }
        }

        // Stage the uploaded file
        $stagedFile = stageUploadedFile(
            $_FILES['attachment'],
            [
                'application/pdf' => 'pdf',
            ],
            root() . "/uploads/transfer_requests/{$userId}",
            "TRANSFER"
        );

        beginTransaction();

        $attachmentPath = "uploads/transfer_requests/{$userId}/" . $stagedFile['secure_name'];
        $result = createTransferRequest($userId, $currentStationId, $targetStationId, $reason, $attachmentPath, $specialization);

        if ($result === false) {
            throw new Exception('We encountered an error saving your request. Please try again later.');
        }

        commitStagedFile($stagedFile);
        commit();

        $success = true;
        $message = 'Your transfer request has been submitted successfully.';
        createSystemLog($stationId, $userId, 'Submitted transfer request', $userId, clientIp());

    } catch (Exception $e) {
        rollBack();
        if ($stagedFile && file_exists($stagedFile['full_path'])) {
            unlink($stagedFile['full_path']);
        }
        $success = false;
        $message = $e->getMessage();
    }
}

if (isset($_POST['cancel-transfer-request'])) {
    $requestId = sanitize(decipher($_POST['data-verifier'] ?? null));
    $showAlert = true;
    $success = false;

    try {
        if (empty($requestId)) {
            throw new Exception('Invalid transfer request selected.');
        }

        $request = getTransferRequest($requestId);
        if (!$request || $request['employee_id'] != $userId) {
            throw new Exception('The requested transfer request could not be found.');
        }

        if ($request['status'] !== 'Pending') {
            throw new Exception('Only pending transfer requests can be canceled.');
        }

        beginTransaction();

        $result = deleteTransferRequest($requestId, $userId);

        if ($result === false) {
            throw new Exception('We encountered an error canceling your request. Please try again later.');
        }

        commit();

        // Unlink attachment
        if (!empty($request['attachment_path']) && file_exists(root() . "/" . $request['attachment_path'])) {
            unlink(root() . "/" . $request['attachment_path']);
        }

        $success = true;
        $message = 'Your transfer request has been canceled successfully.';
        createSystemLog($stationId, $userId, 'Canceled transfer request', $userId, clientIp());

    } catch (Exception $e) {
        rollBack();
        $success = false;
        $message = $e->getMessage();
    }
}

// ========== IPCRF (Individual Performance Commitment and Review Form) ==========

// Create Rating Period (for ratee when no active cycle)
if (isset($_POST['create-rating-period'])) {
    $cycleTitle = sanitize($_POST['cycle_title'] ?? '');
    $cycleSchoolYear = sanitize($_POST['cycle_school_year'] ?? '');
    $showAlert = true;
    $success = false;

    try {
        if (empty($cycleTitle) || empty($cycleSchoolYear)) {
            throw new Exception('Title and School Year are required.');
        }

        // Derive start and end dates from school year (e.g. 2025-2026)
        $cycleDateStart = date('Y') . '-06-01';
        $cycleDateEnd = ((int) date('Y') + 1) . '-03-31';

        $years = explode('-', $cycleSchoolYear);
        if (count($years) === 2 && is_numeric($years[0]) && is_numeric($years[1])) {
            $startYear = (int) $years[0];
            $endYear = (int) $years[1];
            if ($endYear === $startYear + 1) {
                $cycleDateStart = $startYear . '-06-01';
                $cycleDateEnd = $endYear . '-03-31';
            }
        }

        createPmCycle($cycleTitle, $cycleSchoolYear, $cycleDateStart, $cycleDateEnd, $userId);

        $success = true;
        $message = 'Rating period has been created successfully.';
        createSystemLog($stationId, $userId, 'Created IPCRF rating period', null, clientIp());

    } catch (Exception $e) {
        $message = $e->getMessage();
    }
}

// Create IPCRF with KRAs and Objectives
if (isset($_POST['create-ipcrf'])) {
    $employeeId = (int) sanitize(decipher($_POST['verifier'] ?? null));
    $cycleId = (int) sanitize(decipher($_POST['cycle-verifier'] ?? null));
    $validatorId = !empty($_POST['validator_id']) ? (int) sanitize($_POST['validator_id']) : null;
    $approvingOfficerId = !empty($_POST['approving_officer_id']) ? (int) sanitize($_POST['approving_officer_id']) : null;
    $positionTitle = sanitize($_POST['position_title'] ?? '');
    $reviewPeriod = sanitize($_POST['review_period'] ?? '');
    $kraIds = $_POST['kra_id'] ?? [];
    $kraTitles = $_POST['kra_title'] ?? [];
    $showAlert = true;
    $success = false;

    try {
        if (empty($employeeId) || empty($cycleId)) {
            throw new Exception('Invalid request parameters.');
        }

        if (empty($reviewPeriod)) {
            throw new Exception('Review Period is required.');
        }

        if (empty($kraTitles) || empty(array_filter($kraTitles))) {
            throw new Exception('Please define at least one Key Result Area.');
        }

        if (pmIpcrfByEmployee($employeeId, $cycleId)) {
            throw new Exception('You already have an IPCRF for this cycle.');
        }

        beginTransaction();

        $ipcrfId = createPmIpcrf($cycleId, $employeeId, $validatorId, $positionTitle, $reviewPeriod);
        if (!$ipcrfId) {
            throw new Exception('Failed to create IPCRF record.');
        }

        // Set approving officer if selected
        if ($approvingOfficerId) {
            updatePmIpcrfApprovingOfficer($ipcrfId, $approvingOfficerId);
        }

        // Process each KRA and its objectives
        // KRA indices in form are 1-based (kraCount starts at 1)
        $kraIndex = 0;
        foreach ($kraIds as $i => $kraId) {
            $kraIndex++;
            $kraId = (int) sanitize($kraId);
            $kraTitle = sanitize($kraTitles[$i] ?? '');
            
            // Allow custom KRAs (kra_id = 0) as long as title is provided
            if (empty($kraTitle)) continue;

            // Get objectives for this KRA - form uses 1-based kraCount
            $objectives = $_POST["objective_{$kraIndex}"] ?? [];
            $timelines = $_POST["timeline_{$kraIndex}"] ?? [];
            $objWeights = $_POST["obj_weight_{$kraIndex}"] ?? [];
            $performanceIndicators = $_POST["performance_indicator_{$kraIndex}"] ?? [];

            foreach ($objectives as $j => $objective) {
                $objective = sanitize($objective);
                $timeline = sanitize($timelines[$j] ?? '');
                $objWeight = (int) sanitize($objWeights[$j] ?? 0);
                $performanceIndicator = sanitize($performanceIndicators[$j] ?? '');

                if (empty($objective)) continue;

                $result = createPmObjective($ipcrfId, $kraId, $kraTitle, 0, $objective, $timeline, $objWeight, $performanceIndicator, '', '', '', '', $j + 1);
                if (!$result) {
                    throw new Exception('Failed to create objective.');
                }
            }
        }

        // Assign validator if provided
        if ($validatorId) {
            $existingAssignment = pmValidator($validatorId, $employeeId, $cycleId);
            if (!$existingAssignment) {
                assignPmValidator($validatorId, $employeeId, $cycleId);
            }
        }

        commit();

        $success = true;
        $message = 'IPCRF has been created successfully.';
        createSystemLog($stationId, $userId, 'Created IPCRF', $employeeId, clientIp());

        redirect(customUri('pis', 'IPCRF Details', $ipcrfId));

    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

// Save single objective
if (isset($_POST['save-objective'])) {
    $ipcrfId = (int) sanitize(decipher($_POST['verifier'] ?? null));
    $kraId = (int) sanitize($_POST['kra_id'] ?? 0);
    $kraTitle = sanitize($_POST['kra_title'] ?? '');
    $objective = sanitize($_POST['objective'] ?? '');
    $timeline = sanitize($_POST['timeline'] ?? '');
    $weight = (int) sanitize($_POST['weight'] ?? 0);
    $performanceIndicator = sanitize($_POST['performance_indicator'] ?? '');
    $showAlert = true;
    $success = false;

    try {
        if (empty($ipcrfId) || empty($kraTitle) || empty($objective)) {
            throw new Exception('Required fields are missing.');
        }

        $ipcrf = pmIpcrf($ipcrfId);
        if (!$ipcrf || (int) $ipcrf['employee_id'] !== $userId) {
            throw new Exception('Invalid request.');
        }

        if ($ipcrf['status'] !== 'Draft' && $ipcrf['status'] !== 'Returned') {
            throw new Exception('Cannot add objectives to a submitted IPCRF.');
        }

        $existingCount = count(pmObjectives($ipcrfId));
        $result = createPmObjective($ipcrfId, $kraId, $kraTitle, 0, $objective, $timeline, $weight, $performanceIndicator, '', '', '', '', $existingCount + 1);

        if (!$result) {
            throw new Exception('Failed to save objective.');
        }

        $success = true;
        $message = 'Objective has been added successfully.';
        createSystemLog($stationId, $userId, 'Added IPCRF objective', $userId, clientIp());

    } catch (Exception $e) {
        $message = $e->getMessage();
    }
}

// Edit objective
if (isset($_POST['edit-objective'])) {
    $objectiveId = (int) sanitize(decipher($_POST['verifier'] ?? null));
    $kraId = (int) sanitize($_POST['kra_id'] ?? 0);
    $kraTitle = sanitize($_POST['kra_title'] ?? '');
    $objective = sanitize($_POST['objective'] ?? '');
    $timeline = sanitize($_POST['timeline'] ?? '');
    $weight = (int) sanitize($_POST['weight'] ?? 0);
    $performanceIndicator = sanitize($_POST['performance_indicator'] ?? '');
    $showAlert = true;
    $success = false;

    try {
        if (empty($objectiveId) || empty($kraTitle) || empty($objective)) {
            throw new Exception('Required fields are missing.');
        }

        $obj = pmObjective($objectiveId);
        if (!$obj) {
            throw new Exception('Objective not found.');
        }

        $ipcrf = pmIpcrf((int) $obj['ipcrf_id']);
        if (!$ipcrf || (int) $ipcrf['employee_id'] !== $userId) {
            throw new Exception('Unauthorized.');
        }

        if ($ipcrf['status'] !== 'Draft' && $ipcrf['status'] !== 'Returned') {
            throw new Exception('Cannot edit objectives from a submitted IPCRF.');
        }

        $result = updatePmObjective($objectiveId, $kraId, $kraTitle, $objective, $timeline, $weight, $performanceIndicator);
        if (!$result) {
            throw new Exception('Failed to update objective.');
        }

        $success = true;
        $message = 'Objective has been updated successfully.';
        createSystemLog($stationId, $userId, 'Edited IPCRF objective', $userId, clientIp());

    } catch (Exception $e) {
        $message = $e->getMessage();
    }
}

// Delete objective
if (isset($_POST['delete-objective'])) {
    $ipcrfId = (int) sanitize(decipher($_POST['verifier'] ?? null));
    $objectiveId = (int) sanitize(decipher($_POST['objective-verifier'] ?? null));
    $showAlert = true;
    $success = false;

    try {
        if (empty($ipcrfId) || empty($objectiveId)) {
            throw new Exception('Invalid request.');
        }

        $obj = pmObjective($objectiveId);
        if (!$obj || (int) $obj['ipcrf_id'] !== $ipcrfId) {
            throw new Exception('Objective not found.');
        }

        $ipcrf = pmIpcrf($ipcrfId);
        if (!$ipcrf || (int) $ipcrf['employee_id'] !== $userId) {
            throw new Exception('Unauthorized.');
        }

        if ($ipcrf['status'] !== 'Draft' && $ipcrf['status'] !== 'Returned') {
            throw new Exception('Cannot delete objectives from a submitted IPCRF.');
        }

        $result = deletePmObjective($objectiveId);
        if (!$result) {
            throw new Exception('Failed to delete objective.');
        }

        $success = true;
        $message = 'Objective has been deleted.';
        createSystemLog($stationId, $userId, 'Deleted IPCRF objective', $userId, clientIp());

    } catch (Exception $e) {
        $message = $e->getMessage();
    }
}

// Submit IPCRF for validation
if (isset($_POST['submit-ipcrf'])) {
    $ipcrfId = (int) sanitize(decipher($_POST['verifier'] ?? null));
    $remarks = sanitize($_POST['ratee_remarks'] ?? '');
    $showAlert = true;
    $success = false;

    try {
        $ipcrf = pmIpcrf($ipcrfId);
        if (!$ipcrf || (int) $ipcrf['employee_id'] !== $userId) {
            throw new Exception('Invalid request.');
        }

        if ($ipcrf['status'] !== 'Draft' && $ipcrf['status'] !== 'Returned') {
            throw new Exception('This IPCRF cannot be submitted.');
        }

        $objectives = pmObjectives($ipcrfId);
        if (empty($objectives)) {
            throw new Exception('Cannot submit an IPCRF without objectives.');
        }

        $result = updatePmIpcrfStatus($ipcrfId, 'Submitted', $remarks, 'ratee_remarks');
        if ($result === false) {
            throw new Exception('Failed to submit IPCRF.');
        }

        $success = true;
        $message = 'IPCRF has been submitted for validation.';
        createSystemLog($stationId, $userId, 'Submitted IPCRF for validation', $userId, clientIp());

    } catch (Exception $e) {
        $message = $e->getMessage();
    }
}

// Update Approving Officer
if (isset($_POST['update-approving-officer'])) {
    $ipcrfId = (int) sanitize(decipher($_POST['verifier'] ?? null));
    $approvingOfficerId = (int) sanitize(decipher($_POST['approving_officer_id'] ?? null));
    $showAlert = true;
    $success = false;

    try {
        $ipcrf = pmIpcrf($ipcrfId);
        if (!$ipcrf || (int) $ipcrf['employee_id'] !== $userId) {
            throw new Exception('Invalid request.');
        }

        $allowedStatuses = ['Draft', 'Returned', 'Submitted', 'Approved'];
        if (!in_array($ipcrf['status'], $allowedStatuses)) {
            throw new Exception('Approving authority can only be changed before validation.');
        }

        if (!$approvingOfficerId) {
            throw new Exception('Please select an approving authority.');
        }

        updatePmIpcrfApprovingOfficer($ipcrfId, $approvingOfficerId);

        $success = true;
        $message = 'Approving authority has been updated.';
        createSystemLog($stationId, $userId, 'Updated IPCRF approving authority', $userId, clientIp());

    } catch (Exception $e) {
        $message = $e->getMessage();
    }
}

// Save actual results and ratings (Phase 2)
if (isset($_POST['save-actual-results'])) {
    $ipcrfId = (int) sanitize(decipher($_POST['verifier'] ?? null));
    $objIds = $_POST['obj_id'] ?? [];
    $actualResults = $_POST['actual_result'] ?? [];
    $ratingQs = $_POST['rating_q'] ?? [];
    $ratingEs = $_POST['rating_e'] ?? [];
    $ratingTs = $_POST['rating_t'] ?? [];
    $averageRatings = $_POST['average_rating'] ?? [];
    $scores = $_POST['score'] ?? [];
    $showAlert = true;
    $success = false;

    try {
        $ipcrf = pmIpcrf($ipcrfId);
        if (!$ipcrf || (int) $ipcrf['employee_id'] !== $userId) {
            throw new Exception('Unauthorized.');
        }

        beginTransaction();

        foreach ($objIds as $i => $encId) {
            $objId = (int) sanitize(decipher($encId));
            $obj = pmObjective($objId);
            if (!$obj || (int) $obj['ipcrf_id'] !== $ipcrfId) {
                throw new Exception('Invalid objective.');
            }

            $result = sanitize($actualResults[$i] ?? '');
            $q = sanitize($ratingQs[$i] ?? '');
            $e2 = sanitize($ratingEs[$i] ?? '');
            $t = sanitize($ratingTs[$i] ?? '');
            $avg = sanitize($averageRatings[$i] ?? '');
            $score = sanitize($scores[$i] ?? '');

            $qVal = $q !== '' ? (float) $q : null;
            $eVal = $e2 !== '' ? (float) $e2 : null;
            $tVal = $t !== '' ? (float) $t : null;

            if ($avg !== '') {
                $avgVal = (float) $avg;
            } elseif ($qVal !== null && $eVal !== null && $tVal !== null) {
                $avgVal = round(($qVal + $eVal + $tVal) / 3, 2);
            } else {
                $avgVal = null;
            }

            if ($score !== '') {
                $scoreVal = (float) $score;
            } elseif ($avgVal !== null && $obj['weight']) {
                $scoreVal = round($avgVal * ((float) $obj['weight'] / 100), 2);
            } else {
                $scoreVal = null;
            }

            updatePmObjectivePhase2($objId, $result, $qVal, $eVal, $tVal, $avgVal, $scoreVal);
        }

        commit();

        $success = true;
        $message = 'Phase 2 updates have been saved successfully.';
        createSystemLog($stationId, $userId, 'Updated IPCRF Phase 2 results', $userId, clientIp());

    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

// Save ratings (Validator)
if (isset($_POST['save-ratings'])) {
    $ipcrfId = (int) sanitize(decipher($_POST['verifier'] ?? null));
    $validatorRemarks = sanitize($_POST['validator_remarks'] ?? '');
    $objIds = $_POST['obj_id'] ?? [];
    $ratingsQ = $_POST['rating_q'] ?? [];
    $ratingsE = $_POST['rating_e'] ?? [];
    $ratingsT = $_POST['rating_t'] ?? [];
    $objRemarks = $_POST['obj_remarks'] ?? [];
    $showAlert = true;
    $success = false;

    try {
        $ipcrf = pmIpcrf($ipcrfId);
        if (!$ipcrf || (int) $ipcrf['validator_id'] !== $userId) {
            throw new Exception('Unauthorized.');
        }

        beginTransaction();

        foreach ($objIds as $i => $encId) {
            $objId = (int) sanitize(decipher($encId));
            $q = !empty($ratingsQ[$i]) ? (float) $ratingsQ[$i] : null;
            $e2 = !empty($ratingsE[$i]) ? (float) $ratingsE[$i] : null;
            $t = !empty($ratingsT[$i]) ? (float) $ratingsT[$i] : null;
            $rem = sanitize($objRemarks[$i] ?? '');

            if ($q !== null && $e2 !== null && $t !== null) {
                updatePmObjectiveRating($objId, $q, $e2, $t, $rem);
            }
        }

        if (!empty($validatorRemarks)) {
            update('pm_ipcrf', ['validator_remarks' => $validatorRemarks], '`id` = ?', [$ipcrfId]);
        }

        commit();

        $success = true;
        $message = 'Ratings have been saved successfully.';
        createSystemLog($stationId, $userId, 'Saved IPCRF ratings', $ipcrf['employee_id'], clientIp());

    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

// Validate IPCRF
if (isset($_POST['validate-ipcrf'])) {
    $ipcrfId = (int) sanitize(decipher($_POST['verifier'] ?? null));
    $validatorRemarks = sanitize($_POST['validator_remarks'] ?? '');
    $objIds = $_POST['obj_id'] ?? [];
    $ratingsQ = $_POST['rating_q'] ?? [];
    $ratingsE = $_POST['rating_e'] ?? [];
    $ratingsT = $_POST['rating_t'] ?? [];
    $objRemarks = $_POST['obj_remarks'] ?? [];
    $showAlert = true;
    $success = false;

    try {
        $ipcrf = pmIpcrf($ipcrfId);
        if (!$ipcrf || (int) $ipcrf['validator_id'] !== $userId) {
            throw new Exception('Unauthorized.');
        }

        if ($ipcrf['status'] !== 'Approved' && $ipcrf['status'] !== 'Submitted' && $ipcrf['status'] !== 'Validated') {
            throw new Exception('This IPCRF cannot be validated.');
        }

        beginTransaction();

        foreach ($objIds as $i => $encId) {
            $objId = (int) sanitize(decipher($encId));
            $q = !empty($ratingsQ[$i]) ? (float) $ratingsQ[$i] : null;
            $e2 = !empty($ratingsE[$i]) ? (float) $ratingsE[$i] : null;
            $t = !empty($ratingsT[$i]) ? (float) $ratingsT[$i] : null;
            $rem = sanitize($objRemarks[$i] ?? '');

            if ($q === null || $e2 === null || $t === null) {
                throw new Exception('All objectives must be rated (Q, E, T) before validation.');
            }

            updatePmObjectiveRating($objId, $q, $e2, $t, $rem);
        }

        $finalRating = pmComputeFinalRating($ipcrfId);
        $adjectival = pmAdjectivalRating($finalRating);

        updatePmIpcrfFinalRating($ipcrfId, $finalRating, $adjectival);
        updatePmIpcrfStatus($ipcrfId, 'Validated', $validatorRemarks, 'validator_remarks');
        updatePmIpcrfPhase($ipcrfId, 4);

        commit();

        $success = true;
        $message = "IPCRF has been validated and is now in Phase 4. Final Rating: {$finalRating} ({$adjectival}).";
        createSystemLog($stationId, $userId, 'Validated IPCRF', $ipcrf['employee_id'], clientIp());

    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

// Return IPCRF to ratee
if (isset($_POST['return-ipcrf'])) {
    $ipcrfId = (int) sanitize(decipher($_POST['verifier'] ?? null));
    $validatorRemarks = sanitize($_POST['validator_remarks'] ?? '');
    $showAlert = true;
    $success = false;

    try {
        $ipcrf = pmIpcrf($ipcrfId);
        if (!$ipcrf || (int) $ipcrf['validator_id'] !== $userId) {
            throw new Exception('Unauthorized.');
        }

        if (empty($validatorRemarks)) {
            throw new Exception('Please enter your remarks before returning this IPCRF.');
        }

        $result = updatePmIpcrfStatus($ipcrfId, 'Returned', $validatorRemarks, 'validator_remarks');
        if ($result === false) {
            throw new Exception('Failed to return IPCRF.');
        }

        $success = true;
        $message = 'IPCRF has been returned to the ratee for revision.';
        createSystemLog($stationId, $userId, 'Returned IPCRF to ratee', $ipcrf['employee_id'], clientIp());

    } catch (Exception $e) {
        $message = $e->getMessage();
    }
}

// Approve IPCRF (Rater approves commitment or rating)
if (isset($_POST['approve-ipcrf'])) {
    $ipcrfId = (int) sanitize(decipher($_POST['verifier'] ?? null));
    $validatorRemarks = sanitize($_POST['validator_remarks'] ?? '');
    $showAlert = true;
    $success = false;

    try {
        $ipcrf = pmIpcrf($ipcrfId);
        if (!$ipcrf || (int) $ipcrf['validator_id'] !== $userId) {
            throw new Exception('Unauthorized.');
        }

        $currentPhase = (int) $ipcrf['phase'];

        // Phase 1: Approve commitment -> Phase 2
        if ($currentPhase === 1) {
            if ($ipcrf['status'] !== 'Submitted') {
                throw new Exception('This IPCRF cannot be approved.');
            }

            beginTransaction();
            updatePmIpcrfStatus($ipcrfId, 'Approved', $validatorRemarks, 'validator_remarks');
            updatePmIpcrfPhase($ipcrfId, 2);
            commit();

            $success = true;
            $message = 'IPCRF commitment has been approved and moved to Phase 2 (Monitoring).';
            createSystemLog($stationId, $userId, 'Approved IPCRF commitment', $ipcrf['employee_id'], clientIp());
        }
        // Phase 3: Approve rating -> Phase 4
        elseif ($currentPhase === 3) {
            if ($ipcrf['status'] !== 'Validated' && $ipcrf['status'] !== 'Submitted') {
                throw new Exception('This IPCRF rating cannot be approved.');
            }

            beginTransaction();
            updatePmIpcrfStatus($ipcrfId, 'Completed', $validatorRemarks, 'validator_remarks');
            updatePmIpcrfPhase($ipcrfId, 4);
            commit();

            $success = true;
            $message = 'IPCRF rating has been approved and moved to Phase 4 (Rewarding and Development Planning).';
            createSystemLog($stationId, $userId, 'Approved IPCRF rating', $ipcrf['employee_id'], clientIp());
        }
        else {
            throw new Exception('This IPCRF cannot be approved at this phase.');
        }

    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

// Upload MOV
if (isset($_POST['upload-mov'])) {
    $ipcrfId = (int) sanitize(decipher($_POST['verifier'] ?? null));
    $objectiveId = (int) sanitize(decipher($_POST['objective_id'] ?? null));
    $description = sanitize($_POST['description'] ?? '');
    $showAlert = true;
    $success = false;

    try {
        $ipcrf = pmIpcrf($ipcrfId);
        if (!$ipcrf || (int) $ipcrf['employee_id'] !== $userId) {
            throw new Exception('Unauthorized.');
        }

        if (!isset($_FILES['mov_file']) || $_FILES['mov_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Please select a file to upload.');
        }

        $file = $_FILES['mov_file'];
        $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'image/jpeg', 'image/png'];
        $maxSize = FILE_UPLOAD_SIZE_LIMIT;

        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('Invalid file type. Allowed: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG.');
        }

        if ($file['size'] > $maxSize) {
            throw new Exception('File size exceeds ' . UPLOAD_MAX_FILESIZE . 'B limit.');
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newFileName = 'mov_' . $ipcrfId . '_' . $objectiveId . '_' . time() . '.' . $ext;
        $uploadDir = root() . '/uploads/mov/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $newFileName)) {
            throw new Exception('Failed to upload file.');
        }

        $result = createPmMov($objectiveId, $ipcrfId, $newFileName, $file['name'], $file['type'], $file['size'], $description, $userId);
        if (!$result) {
            unlink($uploadDir . $newFileName);
            throw new Exception('Failed to save MOV record.');
        }

        $success = true;
        $message = 'Means of Verification uploaded successfully.';
        createSystemLog($stationId, $userId, 'Uploaded IPCRF MOV', $userId, clientIp());

    } catch (Exception $e) {
        $message = $e->getMessage();
    }
}

// Delete MOV
if (isset($_POST['delete-mov'])) {
    $ipcrfId = (int) sanitize(decipher($_POST['verifier'] ?? null));
    $movId = (int) sanitize(decipher($_POST['mov-verifier'] ?? null));
    $showAlert = true;
    $success = false;

    try {
        $ipcrf = pmIpcrf($ipcrfId);
        if (!$ipcrf || (int) $ipcrf['employee_id'] !== $userId) {
            throw new Exception('Unauthorized.');
        }

        $mov = pmMov($movId);
        if (!$mov || (int) $mov['ipcrf_id'] !== $ipcrfId) {
            throw new Exception('MOV not found.');
        }

        $filePath = root() . '/uploads/mov/' . $mov['file_name'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        deletePmMov($movId);

        $success = true;
        $message = 'MOV has been deleted.';
        createSystemLog($stationId, $userId, 'Deleted IPCRF MOV', $userId, clientIp());

    } catch (Exception $e) {
        $message = $e->getMessage();
    }
}

// Add Coaching Entry
if (isset($_POST['add-coaching'])) {
    $ipcrfId = (int) sanitize(decipher($_POST['verifier'] ?? null));
    $objectiveId = (int) sanitize(decipher($_POST['objective_id'] ?? null));
    $coachingDate = sanitize($_POST['coaching_date'] ?? '');
    $incident = sanitize($_POST['incident'] ?? '');
    $feedback = sanitize($_POST['feedback'] ?? '');
    $actionAgreed = sanitize($_POST['action_agreed'] ?? '');
    $showAlert = true;
    $success = false;

    try {
        $ipcrf = pmIpcrf($ipcrfId);
        if (!$ipcrf) {
            throw new Exception('IPCRF not found.');
        }

        $isValidator = ($userId === (int) $ipcrf['validator_id']);

        if (!$isValidator) {
            throw new Exception('Unauthorized. Only the rater can add coaching entries.');
        }

        if ($ipcrf['phase'] < 2) {
            throw new Exception('Coaching entries can only be added starting Phase 2.');
        }

        $obj = pmObjective($objectiveId);
        if (!$obj || (int) $obj['ipcrf_id'] !== $ipcrfId) {
            throw new Exception('Invalid objective selected.');
        }

        if (empty($coachingDate) || empty($incident) || empty($feedback) || empty($actionAgreed)) {
            throw new Exception('All fields are required.');
        }

        createPmCoaching($ipcrfId, $objectiveId, $coachingDate, $incident, $feedback, $actionAgreed, null, null, $userId);

        $success = true;
        $message = 'Coaching entry has been added successfully.';
        createSystemLog($stationId, $userId, 'Added IPCRF coaching entry', $ipcrf['employee_id'], clientIp());

    } catch (Exception $e) {
        $message = $e->getMessage();
    }
}

// Edit Coaching Entry
if (isset($_POST['edit-coaching'])) {
    $ipcrfId = (int) sanitize(decipher($_POST['verifier'] ?? null));
    $coachingId = (int) sanitize(decipher($_POST['coaching_id'] ?? null));
    $coachingDate = sanitize($_POST['coaching_date'] ?? '');
    $incident = sanitize($_POST['incident'] ?? '');
    $feedback = sanitize($_POST['feedback'] ?? '');
    $actionAgreed = sanitize($_POST['action_agreed'] ?? '');
    $showAlert = true;
    $success = false;

    try {
        $ipcrf = pmIpcrf($ipcrfId);
        if (!$ipcrf) {
            throw new Exception('IPCRF not found.');
        }

        $isOwner = ($userId === (int) $ipcrf['employee_id']);
        $isValidator = ($userId === (int) $ipcrf['validator_id']);

        if (!$isOwner && !$isValidator) {
            throw new Exception('Unauthorized.');
        }

        $coaching = pmCoachingEntry($coachingId);
        if (!$coaching || (int) $coaching['ipcrf_id'] !== $ipcrfId) {
            throw new Exception('Coaching entry not found.');
        }

        if (empty($coachingDate) || empty($incident) || empty($feedback) || empty($actionAgreed)) {
            throw new Exception('All fields are required.');
        }

        updatePmCoaching($coachingId, $coachingDate, $incident, $feedback, $actionAgreed);

        $success = true;
        $message = 'Coaching entry has been updated successfully.';
        createSystemLog($stationId, $userId, 'Updated IPCRF coaching entry', $ipcrf['employee_id'], clientIp());

    } catch (Exception $e) {
        $message = $e->getMessage();
    }
}

// Delete Coaching Entry
if (isset($_POST['delete-coaching'])) {
    $ipcrfId = (int) sanitize(decipher($_POST['verifier'] ?? null));
    $coachingId = (int) sanitize(decipher($_POST['coaching_id'] ?? null));
    $showAlert = true;
    $success = false;

    try {
        $ipcrf = pmIpcrf($ipcrfId);
        if (!$ipcrf) {
            throw new Exception('IPCRF not found.');
        }

        $isOwner = ($userId === (int) $ipcrf['employee_id']);
        $isValidator = ($userId === (int) $ipcrf['validator_id']);

        if (!$isOwner && !$isValidator) {
            throw new Exception('Unauthorized.');
        }

        $coaching = pmCoachingEntry($coachingId);
        if (!$coaching || (int) $coaching['ipcrf_id'] !== $ipcrfId) {
            throw new Exception('Coaching entry not found.');
        }

        deletePmCoaching($coachingId);

        $success = true;
        $message = 'Coaching entry has been deleted.';
        createSystemLog($stationId, $userId, 'Deleted IPCRF coaching entry', $ipcrf['employee_id'], clientIp());

    } catch (Exception $e) {
        $message = $e->getMessage();
    }
}

// Save Competency Ratings (Phase 4)
if (isset($_POST['save-competencies'])) {
    $ipcrfId = (int) sanitize(decipher($_POST['verifier'] ?? null));
    $competencies = $_POST['competency'] ?? [];
    $showAlert = true;
    $success = false;

    try {
        $ipcrf = pmIpcrf($ipcrfId);
        if (!$ipcrf) {
            throw new Exception('IPCRF not found.');
        }

        $isOwner = ($userId === (int) $ipcrf['employee_id']);
        $isValidator = ($userId === (int) $ipcrf['validator_id']);

        if (!$isOwner && !$isValidator) {
            throw new Exception('Unauthorized.');
        }

        if ($ipcrf['phase'] < 4) {
            throw new Exception('Competency ratings can only be saved during Phase 4.');
        }

        beginTransaction();

        foreach ($competencies as $category => $subcategories) {
            foreach ($subcategories as $subKey => $items) {
                foreach ($items as $num => $rating) {
                    if (!empty($rating)) {
                        $rating = (int) $rating;
                        if ($rating < 1 || $rating > 5) {
                            throw new Exception('Rating must be between 1 and 5.');
                        }
                        $competencyNumber = $subKey . '_' . $num;
                        upsertPmCompetencyRating($ipcrfId, $category, $competencyNumber, $rating);
                    }
                }
            }
        }

        commit();

        $success = true;
        $message = 'Competency ratings have been saved successfully.';
        createSystemLog($stationId, $userId, 'Saved IPCRF competency ratings', $ipcrf['employee_id'], clientIp());

    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

// Add Development Plan (Phase 4)
if (isset($_POST['add-plan'])) {
    $ipcrfId = (int) sanitize(decipher($_POST['verifier'] ?? null));
    $strengths = sanitize($_POST['strengths'] ?? '');
    $developmentNeeds = sanitize($_POST['development_needs'] ?? '');
    $actionPlan = sanitize($_POST['action_plan'] ?? '');
    $timeline = sanitize($_POST['timeline'] ?? '');
    $resources = sanitize($_POST['resources'] ?? '');
    $showAlert = true;
    $success = false;

    try {
        $ipcrf = pmIpcrf($ipcrfId);
        if (!$ipcrf) {
            throw new Exception('IPCRF not found.');
        }

        $isOwner = ($userId === (int) $ipcrf['employee_id']);
        $isValidator = ($userId === (int) $ipcrf['validator_id']);

        if (!$isOwner && !$isValidator) {
            throw new Exception('Unauthorized.');
        }

        if ($ipcrf['phase'] < 4) {
            throw new Exception('Development plans can only be added during Phase 4.');
        }

        if (empty($strengths) || empty($developmentNeeds) || empty($actionPlan) || empty($timeline) || empty($resources)) {
            throw new Exception('All fields are required.');
        }

        createPmDevelopmentPlan($ipcrfId, $strengths, $developmentNeeds, $actionPlan, $timeline, $resources);

        $success = true;
        $message = 'Development plan has been added successfully.';
        createSystemLog($stationId, $userId, 'Added IPCRF development plan', $ipcrf['employee_id'], clientIp());

    } catch (Exception $e) {
        $message = $e->getMessage();
    }
}

// Edit Development Plan (Phase 4)
if (isset($_POST['edit-plan'])) {
    $ipcrfId = (int) sanitize(decipher($_POST['verifier'] ?? null));
    $planId = (int) sanitize(decipher($_POST['plan_id'] ?? null));
    $strengths = sanitize($_POST['strengths'] ?? '');
    $developmentNeeds = sanitize($_POST['development_needs'] ?? '');
    $actionPlan = sanitize($_POST['action_plan'] ?? '');
    $timeline = sanitize($_POST['timeline'] ?? '');
    $resources = sanitize($_POST['resources'] ?? '');
    $showAlert = true;
    $success = false;

    try {
        $ipcrf = pmIpcrf($ipcrfId);
        if (!$ipcrf) {
            throw new Exception('IPCRF not found.');
        }

        $isOwner = ($userId === (int) $ipcrf['employee_id']);
        $isValidator = ($userId === (int) $ipcrf['validator_id']);

        if (!$isOwner && !$isValidator) {
            throw new Exception('Unauthorized.');
        }

        $plan = pmDevelopmentPlan($planId);
        if (!$plan || (int) $plan['ipcrf_id'] !== $ipcrfId) {
            throw new Exception('Development plan not found.');
        }

        if (empty($strengths) || empty($developmentNeeds) || empty($actionPlan) || empty($timeline) || empty($resources)) {
            throw new Exception('All fields are required.');
        }

        updatePmDevelopmentPlan($planId, $strengths, $developmentNeeds, $actionPlan, $timeline, $resources);

        $success = true;
        $message = 'Development plan has been updated successfully.';
        createSystemLog($stationId, $userId, 'Updated IPCRF development plan', $ipcrf['employee_id'], clientIp());

    } catch (Exception $e) {
        $message = $e->getMessage();
    }
}

// Delete Development Plan (Phase 4)
if (isset($_POST['delete-plan'])) {
    $ipcrfId = (int) sanitize(decipher($_POST['verifier'] ?? null));
    $planId = (int) sanitize(decipher($_POST['plan_id'] ?? null));
    $showAlert = true;
    $success = false;

    try {
        $ipcrf = pmIpcrf($ipcrfId);
        if (!$ipcrf) {
            throw new Exception('IPCRF not found.');
        }

        $isOwner = ($userId === (int) $ipcrf['employee_id']);
        $isValidator = ($userId === (int) $ipcrf['validator_id']);

        if (!$isOwner && !$isValidator) {
            throw new Exception('Unauthorized.');
        }

        $plan = pmDevelopmentPlan($planId);
        if (!$plan || (int) $plan['ipcrf_id'] !== $ipcrfId) {
            throw new Exception('Development plan not found.');
        }

        deletePmDevelopmentPlan($planId);

        $success = true;
        $message = 'Development plan has been deleted.';
        createSystemLog($stationId, $userId, 'Deleted IPCRF development plan', $ipcrf['employee_id'], clientIp());

    } catch (Exception $e) {
        $message = $e->getMessage();
    }
}

// ========== Stock and Inventory ==========

if (isset($_POST['save-pr'])) {
    $employeeId = (int) ($_POST['employee_id'] ?? 0);
    $itemId = (int) ($_POST['item_id'] ?? 0);
    $quantity = (int) ($_POST['quantity'] ?? 0);
    $unitCost = (float) ($_POST['unit_cost'] ?? 0);
    $purpose = trim($_POST['purpose'] ?? '');
    $supplierId = (int) ($_POST['supplier_id'] ?? 0) ?: null;
    $showAlert = true;
    $success = false;

    if ($employeeId <= 0 || !find('SELECT id FROM employees WHERE id = ?', [$employeeId])) {
        $message = 'Please select a valid employee.';
        return;
    }
    if ($itemId <= 0 || !find('SELECT id FROM items WHERE id = ?', [$itemId])) {
        $message = 'Please select a valid item.';
        return;
    }
    if ($quantity <= 0) {
        $message = 'Quantity must be greater than zero.';
        return;
    }
    if ($unitCost < 0) {
        $message = 'Unit cost cannot be negative.';
        return;
    }
    if ($supplierId !== null && !find('SELECT id FROM suppliers WHERE id = ?', [$supplierId])) {
        $message = 'Please select a valid supplier.';
        return;
    }

    $prNo = imsNextDocumentNumber('PR', 'purchase_requests', 'pr_no');
    $result = insert('purchase_requests', [
        'pr_no' => $prNo,
        'employee_id' => $employeeId,
        'item_id' => $itemId,
        'quantity' => $quantity,
        'unit_cost' => $unitCost,
        'total_cost' => $quantity * $unitCost,
        'purpose' => $purpose,
        'supplier_id' => $supplierId,
        'po_no' => trim($_POST['po_no'] ?? ''),
        'po_date' => trim($_POST['po_date'] ?? '') ?: null,
        'status' => 'pending',
    ]);

    if ($result === false) {
        $message = 'The purchase request could not be saved.';
        return;
    }

    createSystemLog($stationId, $userId, 'Created purchase request', $result, clientIp());
    $_SESSION["{$prefix}ims_flash"] = ['success' => true, 'message' => "Purchase request {$prNo} created successfully."];
    redirect(customUri('ims', 'Purchase Requests'));
}

if (isset($_POST['add-item-unit'])) {
    $itemId = (int) sanitize(decipher($_POST['item_id_id'] ?? ''));
    $unit = strtolower(trim($_POST['new_unit'] ?? ''));
    $pcs = (int) ($_POST['new_pcs'] ?? 1);
    $showAlert = true;
    $success = false;

    if ($itemId <= 0) { $message = 'Invalid item.'; return; }
    if ($unit === '') { $message = 'Please select a unit.'; return; }
    if ($pcs < 1)     { $message = 'Pieces per unit must be at least 1.'; return; }

    $existing = find('SELECT id FROM item_units WHERE item_id = ? AND LOWER(unit) = ?', [$itemId, $unit]);
    if ($existing) { $message = 'That unit is already added for this item.'; return; }

    $result = insert('item_units', ['item_id' => $itemId, 'unit' => $unit, 'pcs_per_unit' => $pcs]);
    if ($result !== false) {
        createSystemLog($stationId, $userId, 'Added unit ' . ucfirst($unit) . ' (' . $pcs . ' pcs/unit) for stock item #' . $itemId, $itemId, clientIp());
        $_SESSION["{$prefix}stock_flash"] = ['success' => true, 'message' => 'Unit added.'];
        redirect(customUri('ims', 'Edit Stock Item', $itemId));
    } else {
        $message = 'Failed to add the unit. Please try again.';
    }
}

if (isset($_POST['remove-item-unit'])) {
    $unitId = (int) sanitize(decipher($_POST['item_unit_id'] ?? ''));
    $showAlert = true;
    $success = false;

    $unitRow = find('SELECT id, item_id, unit FROM item_units WHERE id = ?', [$unitId]);
    if (!$unitRow) { $message = 'Unit not found.'; return; }

    $result = delete('item_units', '`id` = ?', [$unitId]);
    if ($result !== false) {
        createSystemLog($stationId, $userId, 'Removed unit ' . ucfirst($unitRow['unit']) . ' from stock item #' . $unitRow['item_id'], (int) $unitRow['item_id'], clientIp());
        $_SESSION["{$prefix}stock_flash"] = ['success' => true, 'message' => 'Unit removed.'];
        redirect(customUri('ims', 'Edit Stock Item', (int) $unitRow['item_id']));
    } else {
        $message = 'Failed to remove the unit. Please try again.';
    }
}

if (isset($_POST['save-item'])) {
    $editId = !empty($_POST['item_id']) ? (int) sanitize(decipher($_POST['item_id'])) : 0;
    $showAlert = true;
    $success = false;

    $stockNo = trim($_POST['stock_no'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $unit = trim($_POST['unit'] ?? '');
    $existing = $editId > 0 ? find('SELECT pcs_per_unit, number_of_units, number_units_unit, total_units FROM items WHERE id = ?', [$editId]) : null;
    $pcsPerUnit = array_key_exists('pcs_per_unit', $_POST) ? (int) $_POST['pcs_per_unit'] : (int) ($existing['pcs_per_unit'] ?? 0);
    $numberOfUnits = array_key_exists('number_of_units', $_POST) ? (int) $_POST['number_of_units'] : (int) ($existing['number_of_units'] ?? 0);
    $numberUnitsUnit = array_key_exists('number_units_unit', $_POST) ? strtolower(trim($_POST['number_units_unit'] ?? '')) : (isset($existing['number_units_unit']) ? strtolower(trim($existing['number_units_unit'])) : '');
    $quantity = (int) ($_POST['quantity'] ?? 0);
    $minQty = (int) ($_POST['min_qty'] ?? 0);
    $unitCost = (float) ($_POST['unit_cost'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');
    $totalUnits = array_key_exists('total_units', $_POST) ? (int) $_POST['total_units'] : (int) ($existing['total_units'] ?? 0);
    $personnel = trim($_POST['personnel'] ?? '');
    $office = trim($_POST['office'] ?? '');
    $itemStatus = trim($_POST['item_status'] ?? 'Functional');
    if (!in_array($itemStatus, ['Functional', 'Transferred'], true)) { $itemStatus = 'Functional'; }
    $transferTo = trim($_POST['transfer_to'] ?? '');

    if ($stockNo === '')    { $message = 'Stock No. is required.'; return; }
    if ($description === '') { $message = 'Description is required.'; return; }
    if ($quantity < 0)     { $message = 'Quantity cannot be negative.'; return; }
    if ($minQty < 0)      { $message = 'Minimum QTY cannot be negative.'; return; }
    if ($pcsPerUnit < 0)   { $message = 'Pieces per unit cannot be negative.'; return; }
    if ($numberOfUnits < 0) { $message = 'Number of units cannot be negative.'; return; }
    if ($unitCost < 0)     { $message = 'Unit cost cannot be negative.'; return; }
    if ($itemStatus === 'Transferred' && $transferTo === '') { $message = 'Transferred-to personnel name is required when status is Transferred.'; return; }

    if ($editId > 0) {
        $existingItem = find('SELECT quantity, unit_cost FROM items WHERE id = ?', [$editId]);
        $dup = connection()->prepare('SELECT id FROM items WHERE stock_no = ? AND id <> ?');
        $dup->execute([$stockNo, $editId]);
    } else {
        $dup = connection()->prepare('SELECT id FROM items WHERE stock_no = ?');
        $dup->execute([$stockNo]);
    }
    if ($dup->fetch()) { $message = 'Stock No. already exists.'; return; }

    $data = [
        'stock_no'    => $stockNo,
        'description' => $description,
        'unit'        => $unit,
        'pcs_per_unit' => $pcsPerUnit,
        'number_of_units' => $numberOfUnits,
        'number_units_unit' => $numberUnitsUnit,
        'total_units' => $totalUnits,
        'quantity'    => $quantity,
        'min_qty'     => $minQty,
        'unit_cost'   => $unitCost,
        'personnel'   => $personnel,
        'office'      => $office,
        'item_status' => $itemStatus,
        'transfer_to' => $itemStatus === 'Transferred' ? $transferTo : null,
        'remarks'     => $remarks,
    ];

    if ($editId > 0) {
        $result = update('items', $data, '`id` = ?', [$editId]);
        if ($result !== false) {
            $base = find('SELECT id FROM item_units WHERE item_id = ? AND LOWER(unit) = ?', [$editId, strtolower($unit)]);
            if ($base) {
                update('item_units', ['pcs_per_unit' => max($pcsPerUnit, 1)], '`id` = ?', [$base['id']]);
            } else {
                insert('item_units', ['item_id' => $editId, 'unit' => strtolower($unit), 'pcs_per_unit' => max($pcsPerUnit, 1)]);
            }
            createSystemLog($stationId, $userId, 'Updated stock item', $editId, clientIp());
            if ($existingItem && (int) $existingItem['quantity'] !== $quantity) {
                insert('stock_movements', [
                    'item_id' => $editId,
                    'movement_type' => 'Adjustment',
                    'quantity' => $quantity - (int) $existingItem['quantity'],
                    'reference_no' => 'Physical Count',
                    'personnel' => userName((int) $userId),
                    'office' => 'Inventory Office',
                    'remarks' => 'Stock adjusted from ' . (int) $existingItem['quantity'] . ' to ' . $quantity,
                ]);
            }
            imsRecordCostHistory($editId, $unitCost, (float) ($existingItem['unit_cost'] ?? 0), 'Manual Edit', null, null, (int) $userId);
            $_SESSION["{$prefix}stock_flash"] = ['success' => true, 'message' => 'Item updated successfully.'];
            redirect(customUri('ims', 'Stock and Inventory'));
        } else {
            $message = 'Failed to update item. Please try again.';
        }
    } else {
        $result = insert('items', $data);
        if ($result !== false) {
            insert('item_units', ['item_id' => $result, 'unit' => strtolower($unit), 'pcs_per_unit' => max($pcsPerUnit, 1)]);
            imsRecordCostHistory((int) $result, $unitCost, 0.0, 'Opening', 'Initial unit cost on creation', null, (int) $userId);
            if ($quantity > 0) {
                insert('stock_movements', [
                    'item_id' => $result,
                    'movement_type' => 'Receipt',
                    'quantity' => $quantity,
                    'reference_no' => 'Opening Stock',
                    'personnel' => userName((int) $userId),
                    'office' => 'Inventory Office',
                    'remarks' => 'Initial stock for new item',
                ]);
            }
            createSystemLog($stationId, $userId, 'Added stock item', $result, clientIp());
            $_SESSION["{$prefix}stock_flash"] = ['success' => true, 'message' => 'Item added successfully.'];
            redirect(customUri('ims', 'Stock and Inventory'));
        } else {
            $message = 'Failed to add item. Please try again.';
        }
    }
}

if (isset($_POST['delete-item'])) {
    $deleteId = !empty($_POST['verifier']) ? (int) sanitize(decipher($_POST['verifier'])) : 0;
    $showAlert = true;
    $success = false;

    if ($deleteId <= 0) {
        $message = 'Invalid item selected.';
        return;
    }

    $stmt = connection()->prepare('SELECT id FROM items WHERE id = ?');
    $stmt->execute([$deleteId]);
    if (!$stmt->fetch()) {
        $message = 'Item not found.';
        return;
    }

    $result = delete('items', '`id` = ?', [$deleteId]);
    if ($result !== false) {
        createSystemLog($stationId, $userId, 'Deleted stock item', $deleteId, clientIp());
        $_SESSION["{$prefix}stock_flash"] = ['success' => true, 'message' => 'Item deleted successfully.'];
        redirect(customUri('ims', 'Stock and Inventory'));
    } else {
        $message = 'Failed to delete item. Please try again.';
    }
}

// ========== Requisition and Issue Slip ==========

if (isset($_POST['save-ris'])) {
    $employeeId = (int) ($_POST['employee_id'] ?? 0);
    $itemIds = $_POST['item_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $units = $_POST['unit'] ?? [];
    $unitPcs = $_POST['unit_pcs'] ?? [];
    if (!is_array($itemIds)) { $itemIds = [$itemIds]; }
    if (!is_array($quantities)) { $quantities = [$quantities]; }
    if (!is_array($units)) { $units = [$units]; }
    if (!is_array($unitPcs)) { $unitPcs = [$unitPcs]; }
    $purpose = trim($_POST['purpose'] ?? '');
    $division = trim((string) ($_POST['division'] ?? ''));
    $showAlert = true;
    $success = false;

    $lines = [];
    foreach ($itemIds as $index => $itemId) {
        $itemId = (int) $itemId;
        $quantity = (int) ($quantities[$index] ?? 0);
        if ($itemId <= 0 || $quantity <= 0) {
            continue;
        }
        $unit = strtolower(trim((string) ($units[$index] ?? '')));
        if ($unit === '') { $message = 'Please select a unit for every item.'; return; }
        $lines[] = ['item_id' => $itemId, 'quantity' => $quantity, 'unit' => $unit];
    }

    if ($employeeId <= 0)         { $message = 'Please select an employee.'; return; }
    if (empty($lines))            { $message = 'Please select at least one item with a quantity.'; return; }
    if ($purpose === '')          { $message = 'Purpose is required.'; return; }
    if ($division === '')         { $message = 'Please select a Division.'; return; }

    $risNo = 'RIS-' . date('Ymd') . '-' . str_pad(
        (int) query("SELECT COUNT(*) as cnt FROM requisition_slips WHERE DATE(created_at) = CURDATE()")[0]['cnt'] + 1,
        4, '0', STR_PAD_LEFT
    );

    beginTransaction();
    $result = insert('requisition_slips', [
        'ris_no' => $risNo,
        'employee_id' => $employeeId,
        'division' => $division,
        'office' => risDivisionOfficeFromEmployee($employeeId)['office'],
        'purpose' => $purpose,
    ]);

    $allOk = $result !== false;
    if ($allOk) {
        foreach ($lines as $line) {
            $item = find('SELECT id, quantity FROM items WHERE id = ?', [$line['item_id']]);
            if (!$item) { $allOk = false; break; }
            $iu = find('SELECT unit, pcs_per_unit FROM item_units WHERE item_id = ? AND LOWER(unit) = ?', [$line['item_id'], $line['unit']]);
            $factor = $iu ? max((int) $iu['pcs_per_unit'], 1) : 1;
            $requestedPieces = (int) $line['quantity'];
            $hasStock = (int) $item['quantity'] >= $requestedPieces;
            $detailResult = insert('requisition_slip_items', [
                'ris_id' => $result,
                'item_id' => $line['item_id'],
                'unit' => $line['unit'],
                'pcs_per_unit' => $factor,
                'quantity' => $line['quantity'],
                'has_stock' => $hasStock ? 1 : 0,
                'status' => 'Pending',
            ]);
            if ($detailResult === false) { $allOk = false; break; }
        }
    }

    if ($allOk && $result !== false) {
        commit();
        createSystemLog($stationId, $userId, 'Created requisition slip', $result, clientIp());
        $_SESSION["{$prefix}stock_flash"] = ['success' => true, 'message' => 'Requisition slip ' . $risNo . ' created successfully.'];
        redirect(customUri('ims', 'Requisition Slips'));
    } else {
        rollBack();
        $message = 'Failed to create requisition slip. Please try again.';
    }
}

// ========== Purchase Order ==========

if (isset($_POST['generate-po'])) {
    $prId = (int) sanitize(decipher($_POST['pr_id'] ?? null));
    $showAlert = true;
    $success = false;

    $pr = find('SELECT id, pr_no, po_no FROM purchase_requests WHERE id = ?', [$prId]);
    if (!$pr) { $message = 'Purchase request not found.'; return; }

    $poNo = trim($pr['po_no'] ?? '');
    if ($poNo === '') {
        $poNo = imsNextDocumentNumber('PO', 'purchase_requests', 'po_no');
    }

    $result = update('purchase_requests', ['po_no' => $poNo, 'po_date' => date('Y-m-d')], '`id` = ?', [$prId]);
    if ($result === false) { $message = 'Failed to generate the purchase order. Please try again.'; return; }

    createSystemLog($stationId, $userId, 'Generated purchase order ' . $poNo . ' for PR ' . $pr['pr_no'], $prId, clientIp());
    $_SESSION["{$prefix}stock_flash"] = ['success' => true, 'message' => 'Purchase order ' . $poNo . ' generated successfully.'];
    redirect(customUri('ims', 'Purchase Order'));
}

// ========== Inspection and Acceptance Report ==========

if (isset($_POST['save-iar'])) {
    $risId = (int) sanitize(decipher($_POST['ris_id'] ?? null));
    $itemsInput = $_POST['items'] ?? [];
    $inspectorName = trim($_POST['inspector_name'] ?? '');
    $supplier = trim($_POST['supplier'] ?? '');
    $poNo = trim($_POST['po_no'] ?? '');
    $poDate = !empty($_POST['po_date']) ? $_POST['po_date'] : null;
    $officeDept = trim($_POST['office_dept'] ?? '');
    $rcc = trim($_POST['responsibility_center_code'] ?? '');
    $invoiceNo = trim($_POST['invoice_no'] ?? '');
    $invoiceDate = !empty($_POST['invoice_date']) ? $_POST['invoice_date'] : null;
    $showAlert = true;
    $success = false;

    if ($risId <= 0)            { $message = 'Invalid requisition slip.'; return; }
    if ($inspectorName === '')  { $message = 'Inspector name is required.'; return; }
    if (!is_array($itemsInput) || empty($itemsInput)) { $message = 'No items to accept.'; return; }

    $lines = [];
    foreach ($itemsInput as $line) {
        $detailId = (int) ($line['detail_id'] ?? 0);
        $itemId = (int) ($line['item_id'] ?? 0);
        $quantity = (int) ($line['quantity'] ?? 0);
        if ($itemId <= 0 || $quantity <= 0) { continue; }
        $lines[] = ['detail_id' => $detailId, 'item_id' => $itemId, 'quantity' => $quantity];
    }
    if (empty($lines)) { $message = 'No valid items to accept.'; return; }

    $ris = find('SELECT r.id, d.id AS detail_id, d.item_id, d.quantity, d.status FROM requisition_slips r JOIN requisition_slip_items d ON d.ris_id = r.id WHERE r.id = ?', [$risId]);
    if (!$ris)                  { $message = 'Requisition slip not found.'; return; }
    if ($ris['status'] !== 'Pending') { $message = 'This requisition slip has already been processed.'; return; }

    beginTransaction();

    try {
        $allOk = true;
        $createdNos = [];
        foreach ($lines as $line) {
            $item = find('SELECT id, stock_no, description, unit, quantity, pcs_per_unit FROM items WHERE id = ?', [$line['item_id']]);
            if (!$item) { throw new Exception('Item not found.'); }

            $iarNo = 'IAR-' . date('Ymd') . '-' . str_pad(
                (int) query("SELECT COUNT(*) as cnt FROM iar WHERE DATE(created_at) = CURDATE()")[0]['cnt'] + 1,
                4, '0', STR_PAD_LEFT
            );

            $iarResult = insert('iar', [
                'iar_no'                    => $iarNo,
                'ris_id'                    => $risId,
                'inspector_name'            => $inspectorName,
                'supplier'                  => $supplier,
                'po_no'                     => $poNo,
                'po_date'                   => $poDate,
                'office_dept'               => $officeDept,
                'responsibility_center_code' => $rcc,
                'invoice_no'                => $invoiceNo,
                'invoice_date'              => $invoiceDate,
                'stock_no'                  => $item['stock_no'],
                'description'               => $item['description'],
                'unit'                      => $item['unit'],
                'quantity'                  => $line['quantity'],
                'status'                    => 'Accepted',
            ]);

            if ($iarResult === false) {
                throw new Exception('Failed to create IAR record.');
            }
            $createdNos[] = $iarNo;

            $newQty = (int) $item['quantity'] + $line['quantity'];
            $basePcs = max((int) ($item['pcs_per_unit'] ?? 0), 1);
            $newUnits = (int) floor($newQty / $basePcs);
            $newTotalUnits = $newUnits * $basePcs;
            update('items', ['quantity' => $newQty, 'number_of_units' => $newUnits, 'total_units' => $newTotalUnits], '`id` = ?', [$line['item_id']]);
            insert('stock_movements', [
                'item_id' => $line['item_id'],
                'movement_type' => 'Receipt',
                'quantity' => $line['quantity'],
                'reference_no' => $iarNo,
                'personnel' => $inspectorName,
                'office' => $officeDept,
                'remarks' => 'IAR accepted',
            ]);
        }

        foreach ($lines as $line) {
            if ($line['detail_id'] > 0) {
                update('requisition_slip_items', ['status' => 'Issued'], '`id` = ?', [$line['detail_id']]);
            }
        }

        commit();

        createSystemLog($stationId, $userId, 'Created IAR and issued stock', $risId, clientIp());
        $_SESSION["{$prefix}stock_flash"] = ['success' => true, 'message' => 'IAR ' . implode(', ', $createdNos) . ' created. Stock issued successfully.'];
        redirect(customUri('ims', 'Requisition Slips'));

    } catch (Exception $e) {
        rollBack();
        $message = $e->getMessage();
    }
}

// Disapprove an out-of-stock RIS item
if (isset($_POST['disapprove-ris-item'])) {
    $detailId = (int) sanitize(decipher($_POST['disapprove-ris-item'] ?? null));
    $showAlert = true;
    $success = false;

    if ($detailId <= 0) { $message = 'Invalid item.'; return; }

    $detail = find(
        "SELECT d.id, d.ris_id, d.item_id, d.quantity, d.status
         FROM requisition_slip_items d
         WHERE d.id = ?",
        [$detailId]
    );
    if (!$detail) { $message = 'Requisition slip item not found.'; return; }
    if (strtolower((string) $detail['status']) !== 'pending') {
        $message = 'This item has already been processed.';
        return;
    }

    $updateResult = update('requisition_slip_items', ['status' => 'Disapproved'], '`id` = ?', [$detailId]);
    if ($updateResult !== false) {
        createSystemLog($stationId, $userId, 'Disapproved RIS item', (int) $detail['ris_id'], clientIp());
        $_SESSION["{$prefix}stock_flash"] = ['success' => true, 'message' => 'Item disapproved.'];
        redirect(customUri('ims', 'Requisition Slips'));
    } else {
        $message = 'Failed to disapprove item.';
    }
}

// Issue an in-stock pending RIS item (release stock to the requester)
if (isset($_POST['issue-ris-item'])) {
    $detailId = (int) sanitize(decipher($_POST['issue-ris-item'] ?? null));
    $showAlert = true;
    $success = false;

    if ($detailId <= 0) { $message = 'Invalid item.'; return; }

    $detail = find(
        "SELECT d.id, d.ris_id, d.item_id, d.quantity, d.unit, d.pcs_per_unit, d.status,
                CONCAT(e.first_name, ' ', e.last_name) AS requesting_employee,
                r.office AS ris_office
         FROM requisition_slip_items d
         JOIN requisition_slips r ON r.id = d.ris_id
         LEFT JOIN employees e ON e.id = r.employee_id
         WHERE d.id = ?",
        [$detailId]
    );
    if (!$detail) { $message = 'Requisition slip item not found.'; return; }
    if (strtolower((string) $detail['status']) !== 'pending') {
        $message = 'This item has already been processed.';
        return;
    }

    $userUnit = strtolower((string) ($detail['unit'] ?? ''));
    $item = find('SELECT id, stock_no, description, unit, quantity, pcs_per_unit, number_of_units, total_units FROM items WHERE id = ?', [$detail['item_id']]);
    if (!$item) { $message = 'Item not found.'; return; }

    $requestedQty = (int) $detail['quantity'];
    $factor = max((int) ($detail['pcs_per_unit'] ?? 1), 1);
    $requestedPieces = $requestedQty;
    $available = (int) $item['quantity'];
    if ($requestedQty <= 0) {
        $message = 'Invalid quantity on the requisition slip item.';
        return;
    }
    if ($available < $requestedPieces) {
        $message = 'Insufficient stock to issue. Available: ' . $available . ' pieces (need ' . $requestedPieces . ').';
        return;
    }

    $basePcs = max((int) ($item['pcs_per_unit'] ?? 0), 1);
    $newQty = $available - $requestedPieces;
    $newUnits = (int) floor($newQty / $basePcs);
    $newTotalUnits = $newUnits * $basePcs;
    beginTransaction();
    $statusResult = update('requisition_slip_items', ['status' => 'Issued'], '`id` = ?', [$detailId]);
    $stockResult = $statusResult !== false
        ? update(
            'items',
            ['quantity' => $newQty, 'number_of_units' => $newUnits, 'total_units' => $newTotalUnits],
            '`id` = ?',
            [$detail['item_id']]
        )
        : false;

    if ($statusResult !== false && $stockResult !== false) {
        commit();
        $risRef = find('SELECT ris_no FROM requisition_slips WHERE id = ?', [(int) $detail['ris_id']]);
        $personnelName = trim((string) ($detail['requesting_employee'] ?? ''));
        if ($personnelName === '') {
            $personnelName = $userUnit !== '' ? $userUnit : $item['unit'];
        }
        insert('stock_movements', [
            'item_id' => $detail['item_id'],
            'movement_type' => 'Issue',
            'quantity' => -$requestedPieces,
            'reference_no' => $risRef ? $risRef['ris_no'] : 'RIS-' . $detail['ris_id'],
            'personnel' => $personnelName,
            'office' => trim((string) ($detail['ris_office'] ?? '')),
            'remarks' => 'Issued ' . $requestedQty . ' ' . ($userUnit !== '' ? $userUnit : $item['unit']),
        ]);
        createSystemLog(
            $stationId,
            $userId,
            'Issued ' . $requestedQty . ' ' . ($userUnit !== '' ? $userUnit : $item['unit']) . ' of ' . $item['stock_no'] . ' (' . $item['description'] . ')',
            (int) $detail['ris_id'],
            clientIp()
        );
        $_SESSION["{$prefix}stock_flash"] = ['success' => true, 'message' => 'Item issued. Stock reduced to ' . $newQty . ' pieces (' . $newUnits . ' ' . (empty($item['unit']) ? 'units' : $item['unit']) . ').'];
        redirect(customUri('ims', 'Requisition Slips'));
    } else {
        rollBack();
        $message = 'Failed to issue the item.';
    }
}

// Approve a disapproved RIS item via MOOE (creates a purchase request)
if (isset($_POST['approve-mooe-ris-item'])) {
    $detailId = (int) sanitize(decipher($_POST['approve-mooe-ris-item'] ?? null));
    $showAlert = true;
    $success = false;

    if ($detailId <= 0) { $message = 'Invalid item.'; return; }

    $detail = find(
        "SELECT d.id, d.ris_id, d.item_id, d.quantity, d.status,
                r.ris_no, r.purpose, r.employee_id
         FROM requisition_slip_items d
         JOIN requisition_slips r ON r.id = d.ris_id
         WHERE d.id = ?",
        [$detailId]
    );
    if (!$detail) { $message = 'Requisition slip item not found.'; return; }
    if (strtolower((string) $detail['status']) !== 'disapproved') {
        $message = 'This item has already been processed.';
        return;
    }

    $item = find('SELECT id, stock_no, description, unit FROM items WHERE id = ?', [$detail['item_id']]);
    if (!$item) { $message = 'Item not found.'; return; }

    $prNo = imsNextDocumentNumber('PR', 'purchase_requests', 'pr_no');

    beginTransaction();
    $prResult = insert('purchase_requests', [
        'pr_no'           => $prNo,
        'employee_id'     => (int) $detail['employee_id'],
        'item_id'         => (int) $detail['item_id'],
        'quantity'        => (int) $detail['quantity'],
        'unit_cost'       => 0,
        'total_cost'      => 0,
        'purpose'         => 'From RIS ' . $detail['ris_no'] . ($detail['purpose'] ? ' - ' . $detail['purpose'] : ''),
        'supplier_id'     => null,
        'source_of_funds' => 'MOOE',
        'status'          => 'pending',
    ]);

    $updateResult = $prResult !== false
        ? update('requisition_slip_items', ['status' => 'Approved'], '`id` = ?', [$detailId])
        : false;

    if ($prResult !== false && $updateResult !== false) {
        commit();
        createSystemLog($stationId, $userId, 'Approved RIS item via MOOE, created PR ' . $prNo, (int) $detail['ris_id'], clientIp());
        $_SESSION["{$prefix}stock_flash"] = ['success' => true, 'message' => 'Item approved via MOOE. Purchase request ' . $prNo . ' created.'];
        redirect(customUri('ims', 'Purchase Requests'));
    } else {
        rollBack();
        $message = 'Failed to approve item via MOOE and create purchase request.';
    }
}

// Save physical count adjustments: update recorded quantity to the actual count.
if (isset($_POST['save-physical-count'])) {
    $actualCounts = $_POST['actual_count'] ?? [];
    $remarks = $_POST['remarks'] ?? [];
    $showAlert = true;
    $success = false;

    if (!is_array($actualCounts)) { $actualCounts = []; }
    if (!is_array($remarks)) { $remarks = []; }

    if (!$actualCounts) { $message = 'No item counts were submitted.'; return; }

    $updated = 0;
    beginTransaction();
    try {
        foreach ($actualCounts as $rawId => $rawQty) {
            $itemId = (int) $rawId;
            $qty = max(0, (int) $rawQty);
            $note = trim((string) ($remarks[$rawId] ?? ''));
            if ($itemId <= 0) { continue; }

            $item = find('SELECT id, stock_no, description, quantity, pcs_per_unit FROM items WHERE id = ?', [$itemId]);
            if (!$item) { continue; }

            $oldQty = (int) $item['quantity'];
            $basePcs = max((int) ($item['pcs_per_unit'] ?? 0), 1);
            $newUnits = (int) floor($qty / $basePcs);
            $newTotalUnits = $newUnits * $basePcs;
            $result = update('items', ['quantity' => $qty, 'number_of_units' => $newUnits, 'total_units' => $newTotalUnits], '`id` = ?', [$itemId]);
            if ($result !== false) {
                $delta = $qty - $oldQty;
                if ($delta !== 0) {
                    insert('stock_movements', [
                        'item_id' => $itemId,
                        'movement_type' => 'Adjustment',
                        'quantity' => $delta,
                        'reference_no' => 'Physical Count',
                        'personnel' => userName((int) $userId),
                        'office' => 'Inventory Office',
                        'remarks' => ($note !== '' ? $note . ': ' : '') . 'Adjusted from ' . $oldQty . ' to ' . $qty,
                    ]);
                }
                $logDetail = 'Item ' . $item['stock_no'] . ' (' . $item['description'] . ') adjusted from ' . $oldQty . ' to ' . $qty;
                if ($note !== '') { $logDetail .= ' - ' . $note; }
                createSystemLog($stationId, $userId, 'Physical count adjustment: ' . $logDetail, $itemId, clientIp());
                $updated++;
            }
        }
        commit();
    } catch (Throwable $ex) {
        rollBack();
        $message = 'Failed to save physical count adjustments.';
        return;
    }

    $success = true;
    $_SESSION["{$prefix}stock_flash"] = ['success' => true, 'message' => 'Physical count adjustments saved for ' . $updated . ' item(s).'];
    redirect(customUri('ims', 'Physical Count'));
}

// Add Recalibration Entry (Phases 2 & 3)
if (isset($_POST['add-recalibration'])) {
    $ipcrfId = (int) sanitize(decipher($_POST['verifier'] ?? null));
    $ipcrfContent = sanitize($_POST['ipcrf_content'] ?? '');
    $proposedAmendment = sanitize($_POST['proposed_amendment'] ?? '');
    $justification = sanitize($_POST['justification'] ?? '');
    $showAlert = true;
    $success = false;

    try {
        $ipcrf = pmIpcrf($ipcrfId);
        if (!$ipcrf) {
            throw new Exception('IPCRF not found.');
        }

        $isOwner = ($userId === (int) $ipcrf['employee_id']);

        if (!$isOwner) {
            throw new Exception('Unauthorized.');
        }

        if ($ipcrf['phase'] < 2 || $ipcrf['phase'] > 3) {
            throw new Exception('Recalibration is only allowed in Phase 2 and Phase 3.');
        }

        if (empty($ipcrfContent) || empty($proposedAmendment) || empty($justification)) {
            throw new Exception('All fields are required.');
        }

        createPmRecalibration($ipcrfId, $ipcrfContent, $proposedAmendment, $justification, $userId);

        $success = true;
        $message = 'Recalibration entry has been added.';
        createSystemLog($stationId, $userId, 'Added IPCRF recalibration entry', $ipcrf['employee_id'], clientIp());

    } catch (Exception $e) {
        $message = $e->getMessage();
    }
}

// Update Rater Remarks on Recalibration Entry
if (isset($_POST['update-recalibration-rater'])) {
    $ipcrfId = (int) sanitize(decipher($_POST['verifier'] ?? null));
    $recalibrationId = (int) sanitize(decipher($_POST['recalibration_id'] ?? null));
    $raterStatus = sanitize($_POST['rater_status'] ?? 'Pending');
    $raterRemarks = sanitize($_POST['rater_remarks'] ?? '');
    $showAlert = true;
    $success = false;

    try {
        $ipcrf = pmIpcrf($ipcrfId);
        if (!$ipcrf) {
            throw new Exception('IPCRF not found.');
        }

        $isValidator = ($userId === (int) $ipcrf['validator_id']);

        if (!$isValidator) {
            throw new Exception('Unauthorized.');
        }

        if ($ipcrf['phase'] < 2 || $ipcrf['phase'] > 3) {
            throw new Exception('Recalibration is only allowed in Phase 2 and Phase 3.');
        }

        $entry = pmRecalibration($recalibrationId);
        if (!$entry || (int) $entry['ipcrf_id'] !== $ipcrfId) {
            throw new Exception('Recalibration entry not found.');
        }

        if (!in_array($raterStatus, ['Pending', 'Approved', 'Disapproved'])) {
            throw new Exception('Invalid rater status.');
        }

        updatePmRecalibrationRater($recalibrationId, $raterStatus, $raterRemarks);

        $success = true;
        $message = 'Rater remarks have been updated.';
        createSystemLog($stationId, $userId, 'Updated IPCRF recalibration rater remarks', $ipcrf['employee_id'], clientIp());

    } catch (Exception $e) {
        $message = $e->getMessage();
    }
}