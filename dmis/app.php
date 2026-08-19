<?php
// dmis/app.php
$activeApp = $_SESSION["{$prefix}activeApp"] = 'dmis';
$page = $appTitle = 'Division Management Information System';

if (!isset($userId)) {
	redirect("{$baseUri}/login");
}

if (!userRole($userId, 'dmis')) {
	redirect("{$baseUri}/" . HOME);
}

if (isset($_SESSION["{$prefix}change_password"])) {
	redirect("{$baseUri}/login/change");
}

if (isset($_POST['save-school'])) {
	$referenceSchoolId = sanitize(decipher($_POST['verifier']));
	$referenceAlias = sanitize(decipher($_POST['data-verifier']));
	$schoolId = sanitize($_POST['school-id']);
	$schoolName = sanitize($_POST['school-name']);
	$alias = sanitize($_POST['alias']);
	$address = sanitize($_POST['address']);
	$districtCode = sanitize($_POST['district']);
	$category = sanitize($_POST['category']);
	$telephone = sanitize($_POST['telephone']);
	$email = sanitize($_POST['email']);
	$website = sanitize($_POST['website']);
	$facebook = sanitize($_POST['facebook']);
	$logo = sanitize(decipher($_POST['image-verifier']));
	$showAlert = true;
	$success = false;
	$link = '[<a href="' . customUri('dmis', 'School Information', $schoolId) . '" title="View ' . $schoolName . ' information">' . strtoupper($schoolName) . '</a>]';

	$stagedFile = null;
	$oldLogo = $logo;

	if (!empty($_FILES['logo-upload']['tmp_name']) && is_uploaded_file($_FILES['logo-upload']['tmp_name'])) {
		try {
			$stagedFile = stageUploadedFile(
				$_FILES['logo-upload'],
				['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif'],
				root() . "/uploads/school_logo/{$schoolId}",
				"LOGO"
			);

			$logo = "uploads/school_logo/{$schoolId}/" . $stagedFile['secure_name'];
		} catch (Exception $e) {
			$message = $e->getMessage();
			return;
		}
	}

	beginTransaction();

	try {
		if (!schoolById($referenceSchoolId)) {
			if (createSchool($schoolId, $schoolName, $alias, $address, $districtCode, $category, $telephone, $email, $website, $facebook, $logo) === false) {
				throw new Exception('Failed to create school');
			}
			$status = 'saved';
			$logMessage = 'Added school';
		} else {
			if (updateUsersStation($schoolId, $referenceSchoolId) === false) {
				throw new Exception('Failed to update user station');
			}

			if (updateStationID($schoolId, $referenceSchoolId) === false) {
				throw new Exception('Failed to update station ID');
			}

			if (updateTransactionLogFrom($alias, $referenceAlias) === false) {
				throw new Exception("Failed to update transaction logs from {$link}.");
			}

			if (updateTransactionLogTo($alias, $referenceAlias) === false) {
				throw new Exception("Failed to update transaction logs to {$link}.");
			}

			if (updateTransactionFrom($alias, $referenceAlias) === false) {
				throw new Exception("Failed to update transactions from {$link}.");
			}

			if (updateSchool($schoolId, $schoolName, $alias, $address, $districtCode, $category, $telephone, $email, $website, $facebook, $logo, $referenceSchoolId) === false) {
				throw new Exception('Failed to update school');
			}

			$status = 'updated';
			$logMessage = 'Updated school';
		}

		commit();
		if ($stagedFile) {
			commitStagedFile($stagedFile);
			if (!empty($oldLogo) && file_exists(root() . "/{$oldLogo}")) {
				unlink(root() . "/{$oldLogo}");
			}
		}
		$success = true;
	} catch (Exception $e) {
		rollBack();
		$message = $e->getMessage();
		return;
	}

	if ($success) {
		$message = "School {$link} has been {$status} successfully.";
		createSystemLog($stationId, $userId, $logMessage, $schoolId, clientIp());
	}
}

if (isset($_POST['delete-school'])) {
	$schoolId = sanitize(decipher($_POST['verifier']));
	$showAlert = true;
	$success = false;
	$schools = schoolById($schoolId);
	$target = $schools ? $schools['name'] : $schoolId;
	$affectedSchool = deleteSchool($schoolId);

	if (!$affectedSchool) {
		$message = 'No changes have been made to school.';
		return;
	}

	$message = 'School has been deleted successfully.';
	$success = true;

	createSystemLog($stationId, $userId, 'Deleted school', $target, clientIp());
}

if (isset($_POST['save-section'])) {
	$referenceSectionId = sanitize(decipher($_POST['verifier']));
	$alias = sanitize($_POST['alias']);
	$section = sanitize($_POST['section']);
	$division = sanitize($_POST['division']);
	$head = sanitize($_POST['head']);
	$showAlert = true;
	$success = false;
	$link = '[<a href="' . customUri('dmis', 'Section Information', $alias) . '" title="View ' . $section . ' information">' . strtoupper($section) . '</a>]';

	beginTransaction();

	try {
		if (!section($referenceSectionId)) {
			if (createSection($alias, $head, $section, $division) === false) {
				throw new Exception('Failed to create section.');
			}

			$status = 'saved';
			$logMessage = 'Added section';
		} else {
			if (updateUsersStation($alias, $referenceSectionId, strtolower("{$alias}_portal")) === false) {
				throw new Exception('Failed to update user station');
			}

			if (updateTransactionLogFrom($alias, $referenceSectionId) === false) {
				throw new Exception("Failed to update transaction logs from {$link}.");
			}

			if (updateTransactionLogTo($alias, $referenceSectionId) === false) {
				throw new Exception("Failed to update transaction logs to {$link}.");
			}

			if (updateTransactionFrom($alias, $referenceSectionId) === false) {
				throw new Exception("Failed to update transactions from {$link}.");
			}

			if (updateSection($alias, $head, $section, $division, $referenceSectionId) === false) {
				throw new Exception('Failed to update section.');
			}

			$status = 'updated';
			$logMessage = 'Updated section';
		}

		commit();
		$success = true;
	} catch (Exception $e) {
		rollBack();
		$message = $e->getMessage();
		return;
	}

	$message = "Section {$link} has been {$status} successfully.";
	$success = true;

	createSystemLog($stationId, $userId, $logMessage, $alias, clientIp());
}

/** Add delete-section and UI for implementation */

if (isset($_POST['save-district'])) {
	$referenceDistrictId = sanitize(decipher($_POST['verifier']));
	$districtCode = sanitize($_POST['code']);
	$districtName = sanitize($_POST['district']);
	$districtHead = sanitize($_POST['head']);
	$showAlert = true;
	$success = false;
	$link = '[<a href="' . customUri('dmis', 'District Information', $districtCode) . '" title="View ' . $districtName . ' information">' . strtoupper($districtName) . '</a>]';

	if (!district($referenceDistrictId)) {
		$affectedDistrict = createDistrict($districtCode, $districtName, $districtHead);
		$status = 'saved';
		$logMessage = 'Added district';
	} else {
		$affectedDistrict = updateDistrict($districtCode, $districtName, $districtHead, $referenceDistrictId);
		$status = 'updated';
		$logMessage = 'Updated district';
	}

	if (!$affectedDistrict) {
		$message = "No changes have been made to district {$link}.";
		return;
	}

	$message = "District {$link} has been {$status} successfully.";
	$success = true;

	createSystemLog($stationId, $userId, $logMessage, $districtCode, clientIp());
}

/** TODO: Add UI for implementation */
if (isset($_POST['delete-district'])) {
	$districtCode = sanitize(decipher($_POST['verifier']));
	$showAlert = true;
	$success = false;

	$districts = district($districtCode);

	$target = $districts ? $districts['name'] : $districtCode;

	$affectedDistrict = deleteDistrict($districtCode);

	if (!$affectedDistrict) {
		$message = 'No changes have been made to district.';
		return;
	}

	$message = 'District has been deleted successfully.';
	$success = true;

	createSystemLog($stationId, $userId, 'Deleted district', $target, clientIp());
}

if (isset($_POST['edit-user'])) {
	$employeeId = sanitize(decipher($_POST['verifier']));

	if (!$employeeId)
		return;

	$dtsStation = sanitize($_POST['dts-verifier'] ?? null);
	$dtsPortal = section($dtsStation) ? strtolower("{$dtsStation}_portal") : 'sch_portal';

	$systems = [
		'HRMIS' => isset($_POST['hrmis']),
		'DMIS' => isset($_POST['dmis']),
		'HRTDMS' => isset($_POST['hrtdms']),
		'RACE' => isset($_POST['race']),
		'DTR' => isset($_POST['dtr']),
		'IMS' => isset($_POST['ims']),
	];

	$showAlert = true;
	$success = false;

	beginTransaction();

	try {
		if (isset($_POST['dts'])) {
			$res = dtsUser($employeeId)
				? updateUserRole($employeeId, $dtsStation, $dtsPortal)
				: createUserRole($employeeId, $dtsStation, $dtsPortal);
			if ($res === false) {
				throw new Exception('Failed to update DTS user role.');
			}
		} else {
			if (deleteUserRole($employeeId, $dtsStation) === false) {
				throw new Exception('Failed to delete DTS user role.');
			}
		}

		foreach ($systems as $systemCode => $isEnabled) {
			if ($isEnabled) {
				if (!userRole($employeeId, $systemCode)) {
					if (createUserRole($employeeId, $systemCode) === false) {
						throw new Exception("Failed to enable system role {$systemCode}.");
					}
				}
			} else {
				if (deleteUserRole($employeeId, $systemCode) === false) {
					throw new Exception("Failed to disable system role {$systemCode}.");
				}
			}
		}

		commit();
	} catch (Exception $e) {
		rollBack();
		$message = 'Failed to update user assignments: ' . $e->getMessage();
		return;
	}

	$employeeLink = '<a href="#" data-toggle="modal" data-target="#modal" class="text-uppercase" onclick="loadData(\'' . "{$baseUri}/modules/users/user-info-dialog.php?id=" . cipher($employeeId) . '\')" title="View ' . userName($employeeId) . ' employee information">' . userName($employeeId, true) . '</a>';
	$message = "Employee [{$employeeLink}] user assignment has been set successfully.";
	$success = true;

	createSystemLog($stationId, $userId, 'Assigned user privileges', $employeeId, clientIp());
}

if (isset($_POST['reset-user'])) {
	$employeeId = sanitize(decipher($_POST['verifier']));
	$temporaryPassword = sanitize(decipher($_POST['data-verifier']));
	$emails = employee($employeeId);
	$userEmail = $emails ? $emails['email_address'] : '';
	$showAlert = true;
	$success = false;
	$employee = '<a href="#" data-toggle="modal" data-target="#modal" class="text-uppercase" onclick="loadData(\'' . "{$baseUri}/modules/users/user-info-dialog.php?id=" . cipher($employeeId) . '\')" title="View ' . userName($employeeId) . ' employee information">' . userName($employeeId, true) . '</a>';

	if (empty($userEmail)) {
		$message = "Employee email address is empty. Cannot reset password.";
		return;
	}

	$affectedAccountPassword = updateAccountPassword($employeeId, hashPassword($temporaryPassword), 'Default');

	if (!$affectedAccountPassword) {
		$message = "No changes have been made to employee [{$employee}]  password.";
		return;
	}

	$message = "Employee [{$employee}] password has been reset successfully.";

	createSystemLog($stationId, $userId, 'Reset user password', $employeeId, clientIp());

	$loginUrl = "$baseUri/login";

	$emailBody = <<<EOT
        A password reset request for your account ({$userEmail}) has been processed.

        Temporary Password: {$temporaryPassword}

        Please log in here to update your credentials: {$loginUrl}

        Security Note: If you did not request this change, please contact system administration immediately.

        Best regards,
        ICT Support Team
        EOT;

	if (!sendMail($userEmail, 'Employee Password Reset', $emailBody)) {
		$message .= " Unfortunately, we encountered an error sending the email. Please try again later.";
		error_log("Failed to send reset email to: {$userEmail}");
		return;
	}

	$message .= " An email has been sent to [{$userEmail}].";
	$success = true;

	createSystemLog($stationId, $userId, 'Password reset code sent', $employeeId, clientIp());
}

if (isset($_POST['remove-user'])) {
	$employeeId = sanitize(decipher($_POST['verifier']));
	$showAlert = true;
	$success = false;
	$employee = '<a href="#" data-toggle="modal" data-target="#modal" class="text-uppercase" onclick="loadData(\'' . "{$baseUri}/modules/users/user-info-dialog.php?id=" . cipher($employeeId) . '\')" title="View ' . userName($employeeId) . ' employee information">' . userName($employeeId, true) . '</a>';

	$affectedUserRoles = deleteUserRoles($employeeId);

	if (!$affectedUserRoles) {
		$message = "No changes have been made to employee [{$employee}] user privileges.";
		return;
	}

	$message = "Employee [{$employee}] user privileges have been removed successfully.";
	$success = true;

	createSystemLog($stationId, $userId, 'Removed user privileges', $employeeId, clientIp());
}

if (isset($_POST['delete-employee'])) {
	$employeeId = sanitize(decipher($_POST['verifier']));
	$showAlert = true;
	$success = false;
	$employee = employee($employeeId);
	$target = $employee ? userName($employeeId) : $employeeId;

	if (!$employeeId || !isDuplicateEmployee($employeeId)) {
		$message = "Employee record not found or already deleted.";
		return;
	}

	beginTransaction();

	try {
		if (deleteFileAttachments($employeeId) === false) {
			throw new Exception('Failed to delete employee 201 file attachments.');
		}

		if (deleteAccount($employeeId) === false) {
			throw new Exception('Failed to delete employee user credentials.');
		}

		if (deleteUserRoles($employeeId) === false) {
			throw new Exception('Failed to delete employee user permissions.');
		}

		if (deleteEducations($employeeId) === false) {
			throw new Exception('Failed to delete employee educational backgrounds.');
		}

		if (deleteEligibilities($employeeId) === false) {
			throw new Exception('Failed to delete employee eligibilities.');
		}

		if (deleteExperiences($employeeId) === false) {
			throw new Exception('Failed to delete employee service records.');
		}

		if (deleteChildren($employeeId) === false) {
			throw new Exception('Failed to delete employee children.');
		}

		if (deleteFamily($employeeId) === false) {
			throw new Exception('Failed to delete employee family background.');
		}

		if (deleteParticipantTrainings($employeeId) === false) {
			throw new Exception('Failed to delete employee attended trainings.');
		}

		if (deleteMemberships($employeeId) === false) {
			throw new Exception('Failed to delete employee memberships.');
		}

		if (deleteOtherInformation($employeeId) === false) {
			throw new Exception('Failed to delete employee other information.');
		}

		if (deleteRecognitions($employeeId) === false) {
			throw new Exception('Failed to delete employee recognitions.');
		}

		if (deleteReferences($employeeId) === false) {
			throw new Exception('Failed to delete employee references.');
		}

		if (deleteSpecialSkills($employeeId) === false) {
			throw new Exception('Failed to delete employee special skills.');
		}

		if (deleteVoluntaryWorks($employeeId) === false) {
			throw new Exception('Failed to delete employee voluntary works.');
		}

		if (deleteStation($employeeId) === false) {
			throw new Exception('Failed to delete employee station.');
		}

		if (deleteEmployee($employeeId) === false) {
			throw new Exception('Failed to delete employee.');
		}
	} catch (Exception $e) {
		rollBack();
		$message = $e->getMessage();
		return;
	}

	$profileDir = root() . "/uploads/images/{$employeeId}";
	$filesDir = root() . "/uploads/201_files/{$employeeId}";

	foreach ([$profileDir, $filesDir] as $dir) {
		if (is_dir($dir)) {
			$files = array_diff(scandir($dir), ['.', '..']);
			foreach ($files as $file) {
				$filePath = "{$dir}/{$file}";
				if (file_exists($filePath)) {
					unlink($filePath);
				}
			}
			rmdir($dir);
		}
	}

	$message = 'Employee has been deleted successfully.';
	$success = true;

	createSystemLog($stationId, $userId, 'Deleted employee', $target, clientIp());
}

if (isset($_POST['bulk-process-documents'])) {
	$message = '';
	$documentStationId = sanitize(decipher($_POST['verifier']));
	$stationName = strtoupper(stationName($documentStationId));
	$previousYear = date('Y', strtotime('-1 year'));
	$from = sanitize($_POST['from-date'] ?? "$previousYear-01-01");
	$to = sanitize($_POST['to-date'] ?? "$previousYear-12-31");
	$processorId = sanitize($_POST['user']);
	$failedCount = 0;
	$receivedCount = 0;
	$successCount = 0;
	$canceledCount = 0;
	$showAlert = true;
	$user = employee($processorId);

	if ($user === false) {
		$message = "There is currently no assigned user in this station. Please assign a user to continue.";
		return;
	}

	$processorId = $user['id'];
	$documents = incomingDocuments($documentStationId, $from, $to, 5000);

	if (empty($documents)) {
		$message = "No incoming documents found for [$stationName] from [$from] to [$to]. ";
	} else {
		foreach ($documents as $document) {
			$documentId = $document['id'];

			beginTransaction();

			try {
				if (updateDocumentLogsDone($documentId) === false) {
					throw new Exception('Failed to update document logs done.');
				}

				if (createDocumentLog($documentId, $processorId, $documentStationId, null, documentStatusId('Received'), 1) === false) {
					throw new Exception('Failed to create document log (Received).');
				}
				createSystemLog($documentStationId, $processorId, 'Received Document (Bulk)', $documentId, clientIp());

				$receivedCount++;

				if (updateDocumentLogsDone($documentId) === false) {
					throw new Exception('Failed to update document logs done.');
				}

				if (createDocumentLog($documentId, $processorId, $documentStationId, null, documentStatusId('Completed'), 1) === false) {
					throw new Exception('Failed to create document log (Completed).');
				}
				createSystemLog($documentStationId, $processorId, 'Completed Document (Bulk)', $documentId, clientIp());

				$successCount++;

				commit();
			} catch (Exception $e) {
				rollBack();
				$failedCount++;
			}
		}
	}

	$documents = pendingDocuments($documentStationId, $from, $to, 5000);

	if (empty($documents)) {
		$message .= "No pending documents found for [$stationName] from [$from] to [$to]. ";
	} else {
		foreach ($documents as $document) {
			$documentId = $document['id'];

			beginTransaction();

			try {
				if (updateDocumentLogsDone($documentId) === false) {
					throw new Exception('Failed to update document logs done.');
				}

				if (createDocumentLog($documentId, $processorId, $documentStationId, null, documentStatusId('Completed'), 1) === false) {
					throw new Exception('Failed to create document log (Completed).');
				}
				createSystemLog($documentStationId, $processorId, 'Completed Document (Bulk)', $documentId, clientIp());
				commit();

				$successCount++;
			} catch (Exception $e) {
				rollBack();
				$failedCount++;
			}
		}
	}

	$documents = outgoingDocuments($documentStationId, $from, $to, 5000);

	if (empty($documents)) {
		$message .= "No outgoing documents for [$stationName] from [$from] to [$to]. ";
	} else {
		foreach ($documents as $document) {
			if ($document['created_from'] !== $documentStationId) {
				continue;
			}
			$documentId = $document['id'];

			beginTransaction();

			try {
				if (updateDocumentLogsDone($documentId) === false) {
					throw new Exception('Failed to update document logs done.');
				}

				if (createDocumentLog($documentId, $processorId, $documentStationId, null, documentStatusId('Canceled'), 1, 'Bulk processed: Cancelled as not received by destination.') === false) {
					throw new Exception('Failed to create document log (Canceled).');
				}
				createSystemLog($documentStationId, $processorId, 'Canceled Document (Bulk)', $documentId, clientIp());
				commit();

				$canceledCount++;
			} catch (Exception $e) {
				rollBack();
				$failedCount++;
			}
		}
	}

	$success = $receivedCount > 0 || $successCount > 0 || $canceledCount > 0;
	$message = $success ? "Bulk processing for [$stationName] successfully completed. " : $message;

	if ($receivedCount > 0) {
		$message .= '<br>' . number_format($receivedCount) . " document" . ($receivedCount > 1 ? 's' : '') . " received.";
	}

	if ($successCount > 0) {
		$message .= '<br>' . number_format($successCount) . " document" . ($successCount > 1 ? 's' : '') . " completed.";
	}

	if ($canceledCount > 0) {
		$message .= '<br>' . number_format($canceledCount) . " document" . ($canceledCount > 1 ? 's' : '') . " canceled.";
	}

	if ($failedCount > 0) {
		$message .= "<br>Failed to process " . number_format($failedCount) . " document" . ($failedCount > 1 ? 's' : '') . ".";
	}
}

$from = isset($_GET['from']) ? sanitize($_GET['from']) : date('Y') . '-01-01';
$to = isset($_GET['to']) ? sanitize($_GET['to']) : date('Y-m-d');

if (isset($_POST['transactions-summary-filter'])) {
	$from = date('Y-m-d', strtotime($_POST['date-from']));
	$to = date('Y-m-d', strtotime($_POST['date-to']));
	redirect(customUri('dmis', sanitize(decipher($_GET['v']))) . '&from=' . $from . '&to=' . $to);
}