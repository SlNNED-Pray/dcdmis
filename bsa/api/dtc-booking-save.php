<?php
// bsa/api/dtc-booking-save.php — create a Division Training Center (DTC) facility booking (POST, standalone)
require_once(__DIR__ . '/../../includes/function.php');
require_once(__DIR__ . '/../includes/dtc-booking-tables.php');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'INVALID_METHOD', 'message' => 'Invalid request method.']);
    exit;
}

if (empty($GLOBALS['userId'] ?? null)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'UNAUTHORIZED', 'message' => 'Please log in to submit a facility booking.']);
    exit;
}

verify_csrf_token();

$db = connection();
ensureDtcBookingTables();

function dtcBookingInput(string $key, int $maxLen = 65535): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    return mb_substr($value, 0, $maxLen);
}

$requestingOffice     = dtcBookingInput('requesting_office', 255);
$dateOfRequest        = dtcBookingInput('date_request', 10);
$contactPerson        = dtcBookingInput('contact_person', 255);
$contactEmail         = dtcBookingInput('email_address', 255);
$contactNumber        = dtcBookingInput('contact_number', 50);
$activityTitle        = dtcBookingInput('activity_title', 255);
$activityType         = dtcBookingInput('activity_type', 50);
$activityLevel        = dtcBookingInput('activity_level', 50);
$startDate            = dtcBookingInput('start_date', 10);
$endDate              = dtcBookingInput('end_date', 10);
$startTime            = dtcBookingInput('start_time', 5);
$endTime              = dtcBookingInput('end_time', 5);
$venueOption          = dtcBookingInput('venue_option', 80);
$participantCount     = (int) ($_POST['participant_count'] ?? 0);
$noOfMicrophones      = (int) ($_POST['no_of_microphones'] ?? 0);
$externalCatering     = dtcBookingInput('external_catering', 5);
$externalEquipment    = dtcBookingInput('external_equipment', 5);
$externalEquipDetails = dtcBookingInput('external_equipment_details', 5000);
$specialRequests      = dtcBookingInput('special_requests', 5000);
$requestedByName      = dtcBookingInput('requested_by_name', 255);
$requestedByPosition  = dtcBookingInput('requested_by_position', 255);
$requestedBySignature = dtcBookingInput('requested_by_signature', 255);
$concurredByName      = dtcBookingInput('concurred_by_name', 255);
$concurredByPosition  = dtcBookingInput('concurred_by_position', 255);
$concurredBySignature = dtcBookingInput('concurred_by_signature', 255);

// Equipment checklist
$equipmentWhitelist = ['Tables', 'Chairs', 'LCD Projector', 'Projector Screen', 'Audio/Sound System'];
$equipmentSelected = isset($_POST['equipment_needed']) && is_array($_POST['equipment_needed'])
    ? array_values(array_filter($_POST['equipment_needed'], function ($item) use ($equipmentWhitelist) {
        return in_array(trim((string) $item), $equipmentWhitelist, true);
    }))
    : [];
$equipmentNeeded = implode(', ', array_map(function ($item) {
    return mb_substr(trim((string) $item), 0, 50);
}, $equipmentSelected));

// Validation
$activityTypes = ['Official', 'External'];
$activityLevels = ['School', 'Division', 'Region', 'Central Office'];
$venueOptions = ['Main Hall', 'Inner Room', 'TV Room'];

$errors = [];
if ($requestingOffice === '')  $errors[] = 'Requesting Office is required.';
if ($dateOfRequest === '' || !strtotime($dateOfRequest))  $errors[] = 'A valid Date is required.';
if ($contactPerson === '')     $errors[] = 'Contact Person is required.';
if ($contactEmail === '')      $errors[] = 'Email Address is required.';
if ($contactNumber === '')     $errors[] = 'Contact Number is required.';
if ($activityTitle === '')     $errors[] = 'Name of Activity is required.';
if (!in_array($activityType, $activityTypes, true))  $errors[] = 'Please select a valid Type of Activity.';
if ($activityType === 'Official' && !in_array($activityLevel, $activityLevels, true))  $errors[] = 'Please select the official activity level.';
if ($startDate === '' || !strtotime($startDate))  $errors[] = 'A valid Start Date (Inclusive Dates of Use) is required.';
if ($endDate === '' || strtotime($endDate) < strtotime($startDate))  $errors[] = 'End Date must be on or after the Start Date.';
if ($startTime === '' || !strtotime($startTime))  $errors[] = 'A valid Start Time (Inclusive Time of Use) is required.';
if (!in_array($venueOption, $venueOptions, true))  $errors[] = 'Please select a valid Venue Option.';
if ($participantCount < 1)     $errors[] = 'No. of Participants must be at least 1.';
if ($noOfMicrophones < 0 || $noOfMicrophones > 4)  $errors[] = 'No. of Microphone must be between 0 and 4 only.';
if (!in_array($externalCatering, ['Yes', 'No'], true))  $errors[] = 'Please answer: With external catering services?';
if (!in_array($externalEquipment, ['Yes', 'No'], true))  $errors[] = 'Please answer: With external equipment?';
if ($externalEquipment === 'Yes' && $externalEquipDetails === '')  $errors[] = 'Please specify the external equipment.';
if ($requestedByName === '')      $errors[] = 'Requested by (Name) is required.';
if ($requestedByPosition === '')  $errors[] = 'Requested by (Position) is required.';
if ($requestedBySignature === '') $errors[] = 'Requested by (Signature) is required.';
if ($concurredByName === '')      $errors[] = 'Concurred by (Name) is required.';
if ($concurredByPosition === '')  $errors[] = 'Concurred by (Position) is required.';
if ($concurredBySignature === '') $errors[] = 'Concurred by (Signature) is required.';

if ($errors) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'VALIDATION', 'message' => implode(' ', $errors)]);
    exit;
}

try {
    $year = date('Y');
    beginTransaction();
    query("SELECT GET_LOCK(?, 10)", ['bsa_dtc_ref_seq']);
    $rows = query("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(booking_reference, '-', -1) AS UNSIGNED)), 0) + 1 AS n FROM bsa_facility_bookings WHERE booking_reference LIKE ?", ["DTC-{$year}-%"]);
    $reference = sprintf("DTC-%s-%05d", $year, (int) ($rows[0]['n'] ?? 1));

    try {
        $id = insert('bsa_facility_bookings', [
            'booking_reference'           => $reference,
            'date_of_request'             => $dateOfRequest,
            'requesting_office'           => $requestingOffice,
            'contact_person'              => $contactPerson,
            'contact_email'               => $contactEmail,
            'contact_phone'               => $contactNumber,
            'activity_title'              => $activityTitle,
            'activity_type'               => $activityType,
            'activity_level'              => $activityType === 'Official' ? $activityLevel : null,
            'venue_option'                => $venueOption,
            'start_date'                  => $startDate,
            'end_date'                    => $endDate,
            'start_time'                  => $startTime,
            'end_time'                    => $endTime !== '' ? $endTime : null,
            'participant_count'           => $participantCount,
            'equipment_needed'            => $equipmentNeeded !== '' ? $equipmentNeeded : null,
            'no_of_microphones'           => $noOfMicrophones,
            'external_catering'           => $externalCatering,
            'external_equipment'          => $externalEquipment,
            'external_equipment_details'  => $externalEquipment === 'Yes' ? $externalEquipDetails : null,
            'special_requests'            => $specialRequests !== '' ? $specialRequests : null,
            'requested_by_name'           => $requestedByName,
            'requested_by_position'       => $requestedByPosition,
            'requested_by_signature'      => $requestedBySignature,
            'concurred_by_name'           => $concurredByName,
            'concurred_by_position'       => $concurredByPosition,
            'concurred_by_signature'      => $concurredBySignature,
            'status'                      => 'pending',
            'created_by'                  => (string) $GLOBALS['userId'],
        ]);

        if ($id === false) {
            throw new Exception('SAVE_FAILED');
        }

        query("SELECT RELEASE_LOCK(?)", ['bsa_dtc_ref_seq']);
        commit();
        echo json_encode(['success' => true, 'id' => (int) $id, 'booking_reference' => $reference, 'message' => 'Facility booking submitted successfully.']);
    } catch (Throwable $e) {
        query("SELECT RELEASE_LOCK(?)", ['bsa_dtc_ref_seq']);
        throw $e;
    }
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'SAVE_FAILED', 'message' => 'An error occurred while saving the facility booking.']);
}