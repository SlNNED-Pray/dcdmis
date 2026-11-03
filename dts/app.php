<?php
// dts/app.php
$activeApp = $_SESSION["{$prefix}activeApp"] = 'dts';
$page = $appTitle = 'Document Tracking System';

if (!isset($userId)) {
	redirect("{$baseUri}/login");
}

if (!isset($portal) || empty($portal)) {
	redirect("{$baseUri}/" . HOME);
}

if (isset($_SESSION["{$prefix}change_password"])) {
	redirect("{$baseUri}/login/change");
}

if (isset($_POST['primary-search-button'])) {
	$search = sanitize($_POST['primary-search-text']);

	if (document($search)) {
		redirect(customUri('dts', 'Document Information', $search));
	}

	if (documentSearch($search)) {
		redirect(customUri('dts', 'Document Search', $search));
	}
}

$allowedTypes = [
	'image/jpg',
	'image/jpeg',
	'image/png',
	'image/gif',
	'application/pdf',
	'application/msword',
	'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
	'application/vnd.ms-powerpoint',
	'application/vnd.openxmlformats-officedocument.presentationml.presentation',
	'application/vnd.ms-excel',
	'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
];

if (isset($_POST['save-document'])) {
	$purpose = sanitize($_POST['purpose']);
	$details = sanitize($_POST['details']);
	$type = sanitize($_POST['document-type']);
	$destination = $isSchoolPortal ? 'REC' : sanitize($_POST['destination']);
	$description = sanitize($_POST['description']);
	$section = section($code);

	$showAlert = true;
	$success = false;
	$year = date('y');
	$documentId = "{$code}-{$year}-" . sprintf("%05d", countDocumentsFrom($station, $year, $code) + 1);
	$message = 'No new document has been created.';

	if ($section) {
		$headId = $section['head_id'];
	} else {
		$school = schoolById($code);
		$headId = $school ? $school['head_id'] : null;
	}

	if (empty($description)) {
		$message .= ' Please provide a description for the document.';
		return;
	}

	beginTransaction();

	try {
		if (createDocument($documentId, $description, $type, $station, $headId) === false) {
			throw new Exception('Failed to create document.');
		}
		$documentLogId = createDocumentLog($documentId, $userId, $station, $destination, $purpose, 1, $details);
		if (!$documentLogId) {
			throw new Exception('Failed to create document log.');
		}

		$stagedFiles = [];
		$upload_response = '';

		if (!empty($_FILES['file-upload']['name'][0])) {
			$uploadDirectoryPath = 'uploads/attachments/' . cipher($documentId);
			$uploadDirectoryFull = root() . '/' . $uploadDirectoryPath;

			foreach ($_FILES['file-upload']['tmp_name'] as $key => $tmp_name) {
				if (empty($tmp_name)) {
					continue;
				}
				$fileData = [
					'name' => $_FILES['file-upload']['name'][$key],
					'type' => $_FILES['file-upload']['type'][$key],
					'tmp_name' => $_FILES['file-upload']['tmp_name'][$key],
					'error' => $_FILES['file-upload']['error'][$key],
					'size' => $_FILES['file-upload']['size'][$key]
				];

				try {
					$stagedFile = stageUploadedFile(
						$fileData,
						[
							'image/jpeg' => 'jpg',
							'image/jpg' => 'jpg',
							'image/png' => 'png',
							'image/gif' => 'gif',
							'application/pdf' => 'pdf',
							'application/msword' => 'doc',
							'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
							'application/vnd.ms-powerpoint' => 'ppt',
							'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
							'application/vnd.ms-excel' => 'xls',
							'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx'
						],
						$uploadDirectoryFull,
						"ATTACH"
					);

					$targetFilePathRelative = "{$uploadDirectoryPath}/" . $stagedFile['secure_name'];
					$stagedFiles[] = [
						'staged' => $stagedFile,
						'relative_path' => $targetFilePathRelative,
						'name' => $fileData['name'],
						'extension' => '.' . $stagedFile['extension']
					];
				} catch (Exception $e) {
					$upload_response .= "<br>Error staging file " . $fileData['name'] . ": " . $e->getMessage();
				}
			}
		}

		foreach ($stagedFiles as $stagedFileItem) {
			if (createDocumentLogAttachment($documentLogId, $stagedFileItem['relative_path'], $stagedFileItem['extension']) === false) {
				throw new Exception("Failed to attach file: " . $stagedFileItem['name']);
			}
			$upload_response .= "<br>File uploaded: " . $stagedFileItem['name'];
		}

		createSystemLog($stationId, $userId, 'Created document', $documentId, clientIp());
		commit();

		foreach ($stagedFiles as $stagedFileItem) {
			commitStagedFile($stagedFileItem['staged']);
		}

		$message = 'Document code [<a href="' . customUri('dts', 'Document Information', $documentId) . '" title="View ' . $documentId . ' document information">' . strtoupper($documentId) . '</a>] has been saved successfully.' . $upload_response;
		$success = true;
	} catch (Exception $e) {
		rollBack();
		$message = $e->getMessage();
	}
}

if (isset($_POST['edit-document'])) {
	$documentId = sanitize(decipher($_POST['verifier']));
	$purpose = sanitize($_POST['purpose']);
	$details = sanitize($_POST['details']);
	$type = sanitize($_POST['document-type']);
	$destination = $isSchoolPortal ? 'REC' : sanitize($_POST['destination']);
	$showAlert = true;
	$success = false;

	if (document($documentId) === false) {
		$message = 'No document has been updated.';
		return;
	}

	$updateDescription = false;
	$description = null;

	$documentLogs = documentLogs($documentId);

	if ($documentLogs[0]['received_from'] !== $station || $documentLogs[0]['forwarded_to'] === null) {
		$message = 'Document cannot be updated';
		return;
	}

	if (count($documentLogs) === 1) {
		$updateDescription = true;
		$description = sanitize($_POST['description'] ?? null);

		if (empty($description)) {
			$message = 'Please provide a description for the document.';
			return;
		}
	}

	beginTransaction();

	try {
		$updatedDocument = updateDocument($documentId, $description, $type, $updateDescription);
		$updatedLog = updateDocumentLog($documentId, $userId, $station, $destination, $purpose, 1, $details);

		if ($updatedDocument === false && $updatedLog === false) {
			throw new Exception('Failed to update document.');
		}

		$stagedFiles = [];
		$upload_response = '';

		if (!empty($_FILES['file-upload']['name'][0])) {
			$uploadDirectoryPath = 'uploads/attachments/' . cipher($documentId);
			$uploadDirectoryFull = root() . '/' . $uploadDirectoryPath;

			$latestLog = documentLogs($documentId)[0] ?? null;
			$documentLogId = $latestLog ? $latestLog['id'] : null;

			foreach ($_FILES['file-upload']['tmp_name'] as $key => $tmp_name) {
				if (empty($tmp_name)) {
					continue;
				}
				$fileData = [
					'name' => $_FILES['file-upload']['name'][$key],
					'type' => $_FILES['file-upload']['type'][$key],
					'tmp_name' => $_FILES['file-upload']['tmp_name'][$key],
					'error' => $_FILES['file-upload']['error'][$key],
					'size' => $_FILES['file-upload']['size'][$key]
				];

				try {
					$stagedFile = stageUploadedFile(
						$fileData,
						[
							'image/jpeg' => 'jpg',
							'image/jpg' => 'jpg',
							'image/png' => 'png',
							'image/gif' => 'gif',
							'application/pdf' => 'pdf',
							'application/msword' => 'doc',
							'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
							'application/vnd.ms-powerpoint' => 'ppt',
							'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
							'application/vnd.ms-excel' => 'xls',
							'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx'
						],
						$uploadDirectoryFull,
						"ATTACH"
					);

					$targetFilePathRelative = "{$uploadDirectoryPath}/" . $stagedFile['secure_name'];
					$stagedFiles[] = [
						'staged' => $stagedFile,
						'relative_path' => $targetFilePathRelative,
						'name' => $fileData['name'],
						'extension' => '.' . $stagedFile['extension']
					];
				} catch (Exception $e) {
					$upload_response .= "<br>Error staging file " . $fileData['name'] . ": " . $e->getMessage();
				}
			}

			if ($documentLogId) {
				foreach ($stagedFiles as $stagedFileItem) {
					if (createDocumentLogAttachment($documentLogId, $stagedFileItem['relative_path'], $stagedFileItem['extension']) === false) {
						throw new Exception("Failed to attach file: " . $stagedFileItem['name']);
					}
					$upload_response .= "<br>File uploaded: " . $stagedFileItem['name'];
				}
			}
		}

		createSystemLog($stationId, $userId, 'Updated document', $documentId, clientIp());
		commit();

		foreach ($stagedFiles as $stagedFileItem) {
			commitStagedFile($stagedFileItem['staged']);
		}

		$message = 'Document code [<a href="' . customUri('dts', 'Document Information', $documentId) . '" title="View ' . $documentId . ' document information">' . strtoupper($documentId) . '</a>] has been updated successfully.' . $upload_response;
		$success = true;
	} catch (Exception $e) {
		rollBack();
		$message = $e->getMessage();
	}
}

if (isset($_POST['delete-attachment'])) {
	$attachmentId = sanitize(decipher($_POST['verifier']));

	$showAlert = true;
	$success = false;

	beginTransaction();

	try {
		$affectedAttachment = deleteDocumentLogAttachment($attachmentId);

		if ($affectedAttachment === false) {
			throw new Exception('No changes have been made to document attachments.');
		}

		createSystemLog($stationId, $userId, 'Deleted document attachment', '', clientIp());
		commit();

		$message = 'Document attachment has been deleted successfully.';
		$success = true;
	} catch (Exception $e) {
		rollBack();
		$message = $e->getMessage();
	}
}

if (isset($_POST['receive-document'])) {
	$documentId = sanitize(decipher($_POST['verifier']));
	$showAlert = true;
	$success = false;

	beginTransaction();

	try {
		$updatedDocument = updateDocumentLogsDone($documentId);

		if ($updatedDocument === false) {
			throw new Exception('No document has been received.');
		}

		if (createDocumentLog($documentId, $userId, $station, null, documentStatusId('Received'), 1) === false) {
			throw new Exception('Failed to create document log.');
		}
		createSystemLog($stationId, $userId, 'Received document', $documentId, clientIp());
		commit();

		$message = 'Document code [<a href="' . customUri('dts', 'Document Information', $documentId) . '" title="View ' . $documentId . ' document information">' . strtoupper($documentId) . '</a>] has been received successfully.';
		$success = true;
	} catch (Exception $e) {
		rollBack();
		$message = $e->getMessage();
	}
}

if (isset($_POST['forward-document'])) {
	$documentId = sanitize(decipher($_POST['verifier']));
	$purpose = sanitize($_POST['purpose']);
	$details = sanitize($_POST['details']);
	$destination = sanitize($_POST['destination']);
	$showAlert = true;
	$success = false;

	beginTransaction();

	try {
		$updatedDocuments = updateDocumentLogsDone($documentId);

		if ($updatedDocuments === false) {
			throw new Exception('No document has been forwarded.');
		}

		if (createDocumentLog($documentId, $userId, $station, $destination, $purpose, 1, $details) === false) {
			throw new Exception('Failed to create document log.');
		}

		$stagedFiles = [];
		$upload_response = '';

		if (!empty($_FILES['file-upload']['name'][0])) {
			$uploadDirectoryPath = 'uploads/attachments/' . cipher($documentId);
			$uploadDirectoryFull = root() . '/' . $uploadDirectoryPath;

			$latestLog = documentLogs($documentId)[0] ?? null;
			$documentLogId = $latestLog ? $latestLog['id'] : null;

			foreach ($_FILES['file-upload']['tmp_name'] as $key => $tmp_name) {
				if (empty($tmp_name)) {
					continue;
				}
				$fileData = [
					'name' => $_FILES['file-upload']['name'][$key],
					'type' => $_FILES['file-upload']['type'][$key],
					'tmp_name' => $_FILES['file-upload']['tmp_name'][$key],
					'error' => $_FILES['file-upload']['error'][$key],
					'size' => $_FILES['file-upload']['size'][$key]
				];

				try {
					$stagedFile = stageUploadedFile(
						$fileData,
						[
							'image/jpeg' => 'jpg',
							'image/jpg' => 'jpg',
							'image/png' => 'png',
							'image/gif' => 'gif',
							'application/pdf' => 'pdf',
							'application/msword' => 'doc',
							'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
							'application/vnd.ms-powerpoint' => 'ppt',
							'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
							'application/vnd.ms-excel' => 'xls',
							'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx'
						],
						$uploadDirectoryFull,
						"ATTACH"
					);

					$targetFilePathRelative = "{$uploadDirectoryPath}/" . $stagedFile['secure_name'];
					$stagedFiles[] = [
						'staged' => $stagedFile,
						'relative_path' => $targetFilePathRelative,
						'name' => $fileData['name'],
						'extension' => '.' . $stagedFile['extension']
					];
				} catch (Exception $e) {
					$upload_response .= "<br>Error staging file " . $fileData['name'] . ": " . $e->getMessage();
				}
			}

			if ($documentLogId) {
				foreach ($stagedFiles as $stagedFileItem) {
					if (createDocumentLogAttachment($documentLogId, $stagedFileItem['relative_path'], $stagedFileItem['extension']) === false) {
						throw new Exception("Failed to attach file: " . $stagedFileItem['name']);
					}
					$upload_response .= "<br>File uploaded: " . $stagedFileItem['name'];
				}
			}
		}

		createSystemLog($stationId, $userId, 'Forwarded document', $documentId, clientIp());
		commit();

		foreach ($stagedFiles as $stagedFileItem) {
			commitStagedFile($stagedFileItem['staged']);
		}

		$message = 'Document code [<a href="' . customUri('dts', 'Document Information', $documentId) . '" title="View ' . $documentId . ' document information">' . strtoupper($documentId) . '</a>] has been forwarded successfully.' . $upload_response;
		$success = true;
	} catch (Exception $e) {
		rollBack();
		$message = $e->getMessage();
	}
}

if (isset($_POST['complete-document'])) {
	$documentId = sanitize(decipher($_POST['verifier']));
	$remarks = sanitize($_POST['remarks']);
	$status = documentStatusId('Completed');
	$showAlert = true;
	$success = false;

	beginTransaction();

	try {
		$updatedDocument = updateDocumentLogsDone($documentId);

		if ($updatedDocument === false) {
			throw new Exception('No document has been marked complete.');
		}

		if (createDocumentLog($documentId, $userId, $station, null, $status, 1, $remarks) === false) {
			throw new Exception('Failed to create document log.');
		}
		createSystemLog($stationId, $userId, "Completed document", $documentId, clientIp());
		commit();

		$message = 'Document code [<a href="' . customUri('dts', 'Document Information', $documentId) . '" title="View ' . $documentId . ' document information">' . strtoupper($documentId) . '</a>] has been successfully marked complete.';
		$success = true;
	} catch (Exception $e) {
		rollBack();
		$message = $e->getMessage();
	}
}

if (isset($_POST['reopen-document'])) {
	$documentId = sanitize(decipher($_POST['verifier']));
	$remarks = sanitize($_POST['remarks']);
	$status = documentStatusId('Reopened');
	$showAlert = true;
	$success = false;

	beginTransaction();

	try {
		$updatedDocument = updateDocumentLogsDone($documentId);

		if ($updatedDocument === false) {
			throw new Exception('No document has been reopened.');
		}

		if (createDocumentLog($documentId, $userId, $station, null, $status, 1, $remarks) === false) {
			throw new Exception('Failed to create document log.');
		}
		createSystemLog($stationId, $userId, 'Reopened document', $documentId, clientIp());
		commit();

		$message = 'Document code [<a href="' . customUri('dts', 'Document Information', $documentId) . '" title="View ' . $documentId . ' document information">' . strtoupper($documentId) . '</a>] has been reopened successfully.';
		$success = true;
	} catch (Exception $e) {
		rollBack();
		$message = $e->getMessage();
	}
}

if (isset($_POST['restore-document'])) {
	$documentId = sanitize(decipher($_POST['verifier']));
	$remarks = sanitize($_POST['remarks']);
	$status = documentStatusId($isSchoolPortal ? 'For submission' : 'Restored');
	$destination = $isSchoolPortal ? 'REC' : null;
	$showAlert = true;
	$success = false;

	beginTransaction();

	try {
		$updatedDocument = updateDocumentLogsDone($documentId);

		if ($updatedDocument === false) {
			throw new Exception('No document has been restored.');
		}

		if (createDocumentLog($documentId, $userId, $station, $destination, $status, 1, $remarks) === false) {
			throw new Exception('Failed to create document log.');
		}
		createSystemLog($stationId, $userId, 'Restored document', $documentId, clientIp());
		commit();

		$message = 'Document code [<a href="' . customUri('dts', 'Document Information', $documentId) . '" title="View ' . $documentId . ' document information">' . strtoupper($documentId) . '</a>] has been restored successfully.';
		$success = true;
	} catch (Exception $e) {
		rollBack();
		$message = $e->getMessage();
	}
}

if (isset($_POST['cancel-document'])) {
	$documentId = sanitize(decipher($_POST['verifier']));
	$remarks = sanitize($_POST['remarks']);
	$status = documentStatusId('Canceled');
	$showAlert = true;
	$success = false;

	beginTransaction();

	try {
		$updatedDocument = updateDocumentLogsDone($documentId);

		if ($updatedDocument === false) {
			throw new Exception('No document has been canceled.');
		}

		if (createDocumentLog($documentId, $userId, $station, null, $status, 1, $remarks) === false) {
			throw new Exception('Failed to create document log.');
		}
		createSystemLog($stationId, $userId, "Canceled document", $documentId, clientIp());
		commit();

		$message = 'Document code [<a href="' . customUri('dts', 'Document Information', $documentId) . '" title="View ' . $documentId . ' document information">' . strtoupper($documentId) . '</a>] has been canceled successfully.';
		$success = true;
	} catch (Exception $e) {
		rollBack();
		$message = $e->getMessage();
	}
}

if (isset($_POST['save-document-type'])) {
	$documentTypeId = sanitize(decipher($_POST['verifier']));
	$name = sanitize($_POST['type']);
	$description = sanitize($_POST['description']);
	$for_school = isset($_POST['for_school']) ? '1' : '0';
	$showAlert = true;

	if (!documentType($documentTypeId)) {
		$affectedDocumentType = createDocumentType($name, $description, $for_school);
		$status = 'saved';
		$logMessage = 'Added document type';
	} else {
		$affectedDocumentType = updateDocumentType($name, $description, $for_school, $documentTypeId);
		$status = 'updated';
		$logMessage = 'Updated document type';
	}

	if (!$affectedDocumentType) {
		$message = "No changes have been made to document type.";
		return;
	}

	$message = "Document type has been {$status} successfully.";
	$success = true;

	createSystemLog($stationId, $userId, $logMessage, $documentTypeId, clientIp());
}

if (isset($_POST['delete-document-type'])) {
	$documentTypeId = sanitize(decipher($_POST['verifier']));
	$showAlert = true;
	$documentType = documentType($documentTypeId);
	$target = $documentType ? $documentType['name'] : 'Document Type';
	$affectedDocumentType = deleteDocumentType($documentTypeId);

	if (!$affectedDocumentType) {
		$message = 'No changes have been made to document types.';
		return;
	}

	$message = 'Document type has been deleted successfully.';
	$success = true;

	createSystemLog($stationId, $userId, 'Deleted document type', $target, clientIp());
}

if (isset($_POST['save-document-type-station-step'])) {
	$documentTypeStepId = sanitize(decipher($_POST['verifier']));
	$documentTypeId = sanitize(decipher($_POST['data-verifier']));
	$stepNumber = sanitize($_POST['step_number']);
	$destination = sanitize($_POST['destination']);
	$description = sanitize($_POST['description']);
	$note = sanitize($_POST['note']);
	$idealProcessingTime = sanitize($_POST['ideal_processing_time']);
	$showAlert = true;
	$documentType = documentType($documentTypeId);

	if (!$documentType) {
		$message = 'Invalid document type. No changes have been saved.';
		return;
	}

	$documentTypeName = $documentType['name'];

	if (!documentTypeStationStep($documentTypeStepId)) {
		$affectedDocumentTypeStep = createDocumentTypeStationStep($documentTypeId, $stepNumber, $destination, $description, $note, $idealProcessingTime);
		$status = 'saved';
		$logMessage = "Added document type station step for {$documentTypeName}.";
	} else {
		$affectedDocumentTypeStep = updateDocumentTypeStationStep($documentTypeId, $stepNumber, $destination, $description, $note, $idealProcessingTime, $documentTypeStepId);
		$status = 'updated';
		$logMessage = "Updated document type station step for {$documentTypeName}.";
	}

	if (!$affectedDocumentTypeStep) {
		$message = "No changes have been made to document type {$documentTypeName}.";
		return;
	}

	$message = "Station step for document type {$documentTypeName} has been {$status} successfully.";
	$success = true;

	createSystemLog($stationId, $userId, $logMessage, $documentTypeStepId, clientIp());
}

if (isset($_POST['delete-document-type-station-step'])) {
	$documentTypeStepId = sanitize(decipher($_POST['verifier']));
	$showAlert = true;
	$documentTypeStationStep = documentTypeStationStep($documentTypeStepId);
	$target = $documentTypeStationStep ? $documentTypeStationStep['description'] : 'Document Type Station Step';

	$affectedDocumentTypStationStepRequirements = deleteDocumentTypeStationStepRequirements($documentTypeStepId);
	$affectedDocumentTypeStationStep = deleteDocumentTypeStationStep($documentTypeStepId);

	if (!$affectedDocumentTypeStationStep) {
		$message = 'No changes have made to document type station step.';
		return;
	}

	$message = 'Document type station step has been deleted successfully.';
	$success = true;

	createSystemLog($stationId, $userId, 'Delete document type station step and requirements', $documentTypeStepId, clientIp());
}

if (isset($_POST['save-document-type-station-step-requirement'])) {
	$documentTypeStepRequirementId = sanitize(decipher($_POST['verifier']));
	$documentTypeStepId = sanitize(decipher($_POST['data-verifier']));
	$description = sanitize($_POST['description']);
	$note = sanitize($_POST['note']);
	$isMandatory = isset($_POST['is-mandatory']) ? '1' : '0';
	$showAlert = true;

	if (!documentTypeStationStepRequirement($documentTypeStepRequirementId)) {
		$affectedRequirement = createDocumentTypeStationStepRequirement($documentTypeStepId, $description, $note, $isMandatory);
		$status = 'saved';
		$logMessage = 'Added document type station step requirement';
	} else {
		$affectedRequirement = updateDocumentTypeStationStepRequirement($description, $note, $isMandatory, $documentTypeStepRequirementId);
		$status = 'updated';
		$logMessage = 'Updated document type station step requirement';
	}

	if (!$affectedRequirement) {
		$message = 'No changes have been made to document type station step.';
		return;
	}

	$message = "Document type has been {$status} successfully.";
	$success = true;

	createSystemLog($stationId, $userId, $logMessage, $documentTypeStepRequirementId, clientIp());
}

if (isset($_POST['delete-document-type-station-step-requirement'])) {
	$documentTypeStepRequirementId = sanitize(decipher($_POST['verifier']));
	$showAlert = true;
	$requirement = documentTypeStationStepRequirement($documentTypeStepRequirementId);
	$affectedDocumentTypStationStepRequirements = deleteDocumentTypeStationStepRequirement($documentTypeStepRequirementId);

	if (!$affectedDocumentTypStationStepRequirements) {
		$message = 'No changes have made to document type station step requirements.';
		return;
	}

	$message = 'Document type station step requirement has been deleted successfully.';
	$success = true;

	createSystemLog($stationId, $userId, 'Delete document type station step requirement', $documentTypeStepRequirementId, clientIp());
}

$from = isset($_GET['from']) ? sanitize($_GET['from']) : date('Y') . '-01-01';
$to = isset($_GET['to']) ? sanitize($_GET['to']) : date('Y-m-d');

if (isset($_POST['transactions-summary-filter'])) {
	$from = date('Y-m-d', strtotime($_POST['date-from']));
	$to = date('Y-m-d', strtotime($_POST['date-to']));
	redirect(customUri('dts', sanitize(decipher($_GET['v']))) . '&from=' . $from . '&to=' . $to);
}