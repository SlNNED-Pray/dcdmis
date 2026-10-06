<?php
// bsa/api/vehicle-request-save.php — create a vehicle request (POST)
require_once(__DIR__ . '/../../includes/function.php');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'INVALID_METHOD', 'message' => 'Invalid request method.']);
    exit;
}

if (empty($GLOBALS['userId'] ?? null)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'UNAUTHORIZED', 'message' => 'Please log in to submit a vehicle request.']);
    exit;
}

verify_csrf_token();

$db = connection();

function vehicleInput(string $key, int $maxLen = 65535): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    return mb_substr($value, 0, $maxLen);
}

$requestingOffice    = vehicleInput('requesting_office', 255);
$dateRequest         = vehicleInput('date_request', 10);
$requestingPersonnel = vehicleInput('requesting_personnel', 255);
$contactNumber       = vehicleInput('contact_number', 50);
$purposeOfTravel     = vehicleInput('purpose_of_travel');
$destination         = vehicleInput('destination', 255);
$departDate          = vehicleInput('depart_date', 10);
$departTime          = vehicleInput('depart_time', 5);
$returnDate          = vehicleInput('return_date', 10);
$returnTime          = vehicleInput('return_time', 5);
$noOfPassengers      = (int) ($_POST['no_of_passengers'] ?? 1);
$vehicleType         = vehicleInput('vehicle_type', 30);
$specialRequest      = vehicleInput('special_request');
$requestedByName     = vehicleInput('requested_by_name', 255);
$requestedByPosition = vehicleInput('requested_by_position', 255);
$concurredByName     = vehicleInput('concurred_by_name', 255);
$concurredByPosition = vehicleInput('concurred_by_position', 255);
$divisionChief       = vehicleInput('division_chief', 255);

$vehicleTypes = ['Pickup Truck', 'Utility Van', 'Commuter Van'];

$errors = [];
if ($requestingOffice === '')             $errors[] = 'Requesting Office is required.';
if ($dateRequest === '' || !strtotime($dateRequest))                $errors[] = 'A valid Date of Request is required.';
if ($requestingPersonnel === '')          $errors[] = 'Requesting Personnel is required.';
if ($contactNumber === '')                $errors[] = 'Contact Number is required.';
if ($purposeOfTravel === '')              $errors[] = 'Purpose of Travel is required.';
if ($destination === '')                  $errors[] = 'Destination is required.';
if ($departDate === '' || !strtotime($departDate))                  $errors[] = 'A valid Date of Departure is required.';
if ($departTime === '' || !strtotime($departTime))                  $errors[] = 'A valid Time of Departure is required.';
if ($noOfPassengers < 1)                  $errors[] = 'Number of Passengers must be at least 1.';
if (!in_array($vehicleType, $vehicleTypes, true))                   $errors[] = 'Please select a valid Vehicle Type.';
if ($requestedByName === '')              $errors[] = 'Requested By (Name) is required.';
if ($requestedByPosition === '')          $errors[] = 'Requested By (Position) is required.';
if ($returnDate !== '' && !strtotime($returnDate))                  $errors[] = 'Estimated Date of Return is invalid.';
if ($returnTime !== '' && !strtotime($returnTime))                  $errors[] = 'Estimated Time of Return is invalid.';

if ($errors) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'VALIDATION', 'message' => implode(' ', $errors)]);
    exit;
}

try {
    $year = date('Y');
    beginTransaction();
    query("SELECT GET_LOCK(?, 10)", ['bsa_veh_ref_seq']);
    $rows = query("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(control_no, '-', -1) AS UNSIGNED)), 0) + 1 AS n FROM vehicle_requests WHERE control_no LIKE ?", ["VR-{$year}-%"]);
    $controlNo = sprintf("VR-%s-%05d", $year, (int) ($rows[0]['n'] ?? 1));

    try {
        $id = insert('vehicle_requests', [
        'control_no'            => $controlNo,
        'requesting_office'     => $requestingOffice,
        'date_request'          => $dateRequest,
        'requesting_personnel'  => $requestingPersonnel,
        'contact_number'        => $contactNumber,
        'purpose_of_travel'     => $purposeOfTravel,
        'destination'           => $destination,
        'depart_date'           => $departDate,
        'depart_time'           => $departTime,
        'return_date'           => $returnDate !== '' ? $returnDate : null,
        'return_time'           => $returnTime !== '' ? $returnTime : null,
        'no_of_passengers'      => $noOfPassengers,
        'vehicle_type'          => $vehicleType,
        'special_request'       => $specialRequest,
        'requested_by_name'     => $requestedByName,
        'requested_by_position' => $requestedByPosition,
        'concurred_by_name'     => $concurredByName,
        'concurred_by_position' => $concurredByPosition,
        'division_chief'        => $divisionChief,
        'status'                => 'pending',
        'created_by'            => (string) $GLOBALS['userId'],
    ]);

    if ($id === false) {
        throw new Exception('SAVE_FAILED');
    }

        query("SELECT RELEASE_LOCK(?)", ['bsa_veh_ref_seq']);
        commit();
        echo json_encode(['success' => true, 'id' => (int) $id, 'control_no' => $controlNo, 'message' => 'Vehicle request submitted successfully.']);
    } catch (Throwable $e) {
        query("SELECT RELEASE_LOCK(?)", ['bsa_veh_ref_seq']);
        throw $e;
    }
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'SAVE_FAILED', 'message' => 'An error occurred while saving the vehicle request.']);
}