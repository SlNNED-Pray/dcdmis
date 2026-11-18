<?php
/**
 * bsa/includes/booking-edit-fields.php
 * Shared field definitions for the Booking Approvals "Edit" form and its
 * save endpoint. Only fields listed here can be changed.
 */

if (!function_exists('bsaBookingEditConfig')) {
    function bsaBookingEditConfig(string $type): ?array
    {
        $configs = [
            'vehicle' => [
                'title' => 'Vehicle Request',
                'db' => 'dcdmis',
                'table' => 'vehicle_requests',
                'key' => 'id',
                'fields' => [
                    'control_no'            => ['label' => 'Control No.', 'type' => 'text', 'readonly' => true],
                    'requesting_office'     => ['label' => 'Requesting Office', 'type' => 'text', 'max' => 255],
                    'date_request'          => ['label' => 'Date of Request', 'type' => 'date'],
                    'requesting_personnel'  => ['label' => 'Requesting Personnel', 'type' => 'text', 'max' => 255],
                    'contact_number'        => ['label' => 'Contact Number', 'type' => 'text', 'max' => 50],
                    'purpose_of_travel'     => ['label' => 'Purpose of Travel', 'type' => 'textarea'],
                    'destination'           => ['label' => 'Destination', 'type' => 'text', 'max' => 255],
                    'depart_date'           => ['label' => 'Date of Departure', 'type' => 'date'],
                    'depart_time'           => ['label' => 'Time of Departure', 'type' => 'time'],
                    'return_date'           => ['label' => 'Estimated Date of Return', 'type' => 'date'],
                    'return_time'           => ['label' => 'Estimated Time of Return', 'type' => 'time'],
                    'no_of_passengers'      => ['label' => 'No. of Passengers', 'type' => 'number', 'min' => 1],
                    'vehicle_type'          => ['label' => 'Vehicle Type', 'type' => 'select', 'options' => ['Pickup Truck', 'Utility Van', 'Commuter Van']],
                    'vehicle_subtype'       => ['label' => 'Pickup Truck Unit', 'type' => 'select', 'options' => ['FOTON', 'STRADA'], 'null_option' => '— Select unit —', 'conditional' => 'Pickup Truck'],
                    'special_request'       => ['label' => 'Special Request', 'type' => 'textarea'],
                    'requested_by_name'     => ['label' => 'Requested By (Name)', 'type' => 'text', 'max' => 255],
                    'requested_by_position' => ['label' => 'Requested By (Position)', 'type' => 'text', 'max' => 255],
                    'concurred_by_name'     => ['label' => 'Concurred By (Name)', 'type' => 'text', 'max' => 255],
                    'concurred_by_position' => ['label' => 'Concurred By (Position)', 'type' => 'text', 'max' => 255],
                    'division_chief'        => ['label' => 'Functional Division Chief', 'type' => 'text', 'max' => 255],
                    'status'                => ['label' => 'Status', 'type' => 'select', 'options' => ['pending', 'available', 'not_available', 'cancelled']],
                    'remarks'               => ['label' => 'Remarks', 'type' => 'textarea'],
                ],
            ],
            'local' => [
                'title' => 'Local Facility Booking',
                'db' => 'dcdmis',
                'table' => 'bsa_facility_bookings',
                'key' => 'id',
                'fields' => [
                    'booking_reference'  => ['label' => 'Booking Reference', 'type' => 'text', 'readonly' => true],
                    'activity_title'     => ['label' => 'Activity Title', 'type' => 'text', 'max' => 255],
                    'activity_description' => ['label' => 'Description', 'type' => 'textarea'],
                    'activity_type'      => ['label' => 'Activity Type', 'type' => 'text', 'max' => 50],
                    'requesting_office'  => ['label' => 'Requesting Office', 'type' => 'text', 'max' => 255],
                    'facility_name'      => ['label' => 'Facility', 'type' => 'text', 'max' => 150],
                    'venue_option'       => ['label' => 'Venue Option', 'type' => 'text', 'max' => 80],
                    'start_date'         => ['label' => 'Start Date', 'type' => 'date'],
                    'end_date'           => ['label' => 'End Date', 'type' => 'date'],
                    'start_time'         => ['label' => 'Start Time', 'type' => 'time'],
                    'end_time'           => ['label' => 'End Time', 'type' => 'time'],
                    'participant_count'  => ['label' => 'Participants', 'type' => 'number', 'min' => 0],
                    'status'             => ['label' => 'Status', 'type' => 'select', 'options' => ['pending', 'approved', 'rejected', 'cancelled']],
                    'remarks'            => ['label' => 'Remarks', 'type' => 'textarea'],
                ],
            ],
            'joborder' => [
                'title' => 'Job Order Request',
                'db' => 'dcdmis',
                'table' => 'bsa_job_order_requests',
                'key' => 'id',
                'fields' => [
                    'order_no'             => ['label' => 'Order No.', 'type' => 'text', 'readonly' => true],
                    'date_request'         => ['label' => 'Date of Request', 'type' => 'date'],
                    'requesting_personnel' => ['label' => 'Requesting Personnel', 'type' => 'text', 'max' => 255],
                    'requesting_office'    => ['label' => 'Requesting Office', 'type' => 'text', 'max' => 255],
                    'location_of_work'     => ['label' => 'Location of Work', 'type' => 'text', 'max' => 255],
                    'description_of_work'  => ['label' => 'Description of Work', 'type' => 'text', 'max' => 255],
                    'other_scope'          => ['label' => 'Other Scope', 'type' => 'textarea'],
                    'requestor_name'       => ['label' => 'Requestor Name', 'type' => 'text', 'max' => 255],
                    'status'               => ['label' => 'Status', 'type' => 'select', 'options' => ['pending', 'approved', 'rejected', 'cancelled']],
                    'tracking_remarks'     => ['label' => 'Tracking Remark', 'type' => 'select', 'options' => ['work_in_progress', 'backlog', 'on_hold', 'completed'], 'null_option' => '— Select —'],
                    'actions_taken'        => ['label' => 'Actions Taken', 'type' => 'textarea'],
                    'recommendation'       => ['label' => 'Recommendation', 'type' => 'textarea'],
                    'assigned_personnel'   => ['label' => 'Assigned Personnel', 'type' => 'text', 'max' => 255],
                    'prepared_by'          => ['label' => 'Prepared By', 'type' => 'text', 'max' => 255],
                    'date_time_started'    => ['label' => 'Date/Time Started', 'type' => 'text', 'max' => 30],
                    'date_time_completed'  => ['label' => 'Date/Time Completed', 'type' => 'text', 'max' => 30],
                    'remarks'              => ['label' => 'Remarks', 'type' => 'textarea'],
                ],
            ],
            'facility' => [
                'title' => 'Facility Booking',
                'db' => 'dtcsc_booking',
                'table' => 'bookings',
                'key' => 'booking_id',
                'fields' => [
                    'booking_reference'   => ['label' => 'Booking Reference', 'type' => 'text', 'readonly' => true],
                    'activity_title'      => ['label' => 'Activity Title', 'type' => 'text', 'max' => 200],
                    'activity_description' => ['label' => 'Description', 'type' => 'textarea'],
                    'facility_id'         => ['label' => 'Facility ID', 'type' => 'number', 'min' => 0],
                    'requesting_office'   => ['label' => 'Requesting Office', 'type' => 'text', 'max' => 150],
                    'contact_person'      => ['label' => 'Contact Person', 'type' => 'text', 'max' => 100],
                    'contact_email'       => ['label' => 'Contact Email', 'type' => 'text', 'max' => 150],
                    'contact_phone'       => ['label' => 'Contact Phone', 'type' => 'text', 'max' => 20],
                    'start_datetime'      => ['label' => 'Start (YYYY-MM-DD HH:MM)', 'type' => 'text', 'max' => 30],
                    'end_datetime'        => ['label' => 'End (YYYY-MM-DD HH:MM)', 'type' => 'text', 'max' => 30],
                    'participant_count'   => ['label' => 'Participants', 'type' => 'number', 'min' => 0],
                    'status'              => ['label' => 'Status', 'type' => 'select', 'options' => ['pending', 'approved', 'rejected', 'cancelled']],
                    'remarks'             => ['label' => 'Remarks', 'type' => 'textarea'],
                ],
            ],
        ];

        return $configs[$type] ?? null;
    }
}
