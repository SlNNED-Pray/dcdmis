<?php
/**
 * bsa/includes/bsa-access.php
 * Central access policy for the Booking & Schedule (BSA) module.
 *
 * Policy (as of this revision):
 *   - Booking & Schedule (calendar + creating facility/vehicle/job-order
 *     requests) is open to every logged-in employee.
 *   - The Booking Approvals page and its decision endpoints are limited to:
 *       - Administrative Officer V (ADOF5) — DTC Manager / Admin Officer V (Admin), OR
 *       - an employee id listed in bsaBookingApproverIds() — the Facility
 *         Coordinator(s) / Administrative Aide VI (Personnel Section).
 */

if (!function_exists('bsaUserPositionId')) {
    /**
     * Current (latest) position id of an employee, or '' when unknown.
     */
    function bsaUserPositionId($employee_id): string
    {
        $employee_id = (int) $employee_id;
        if ($employee_id <= 0) {
            return '';
        }

        $row = find(
            "SELECT sa.`position_id` FROM `station_assignments` sa
             WHERE sa.`employee_id` = ?
             ORDER BY sa.`assignment_date` DESC, sa.`position_id` ASC
             LIMIT 1",
            [$employee_id]
        );

        return $row ? (string) ($row['position_id'] ?? '') : '';
    }
}

if (!function_exists('bsaBookingApproverIds')) {
    /**
     * Employee IDs allowed to open Booking Approvals as the Facility Coordinator
     * (Administrative Aide VI), in addition to any Administrative Officer V.
     *
     * EDIT THE DEFAULT LIST HERE, or add a comma-separated employee-id list to
     * the `bsa_settings` row with key `booking_approver_ids` (no code change).
     *
     * @return string[]
     */
    function bsaBookingApproverIds(): array
    {
        // Facility Coordinator(s) — Administrative Aide VI.
        $ids = [
            '20260105113222', // Leslie Erang Egason — Facility Coordinator (Personnel Section)
        ];

        try {
            if (function_exists('find')) {
                $row = find("SELECT `setting_value` FROM `bsa_settings` WHERE `setting_key` = ?", ['booking_approver_ids']);
                $value = $row ? trim((string) ($row['setting_value'] ?? '')) : '';
                if ($value !== '') {
                    foreach (preg_split('/[\s,;]+/', $value) as $id) {
                        $id = trim($id);
                        if ($id !== '') {
                            $ids[] = $id;
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            // bsa_settings may not exist yet — fall back to the default list.
        }

        return array_values(array_unique($ids));
    }
}

if (!function_exists('canAccessBookingApprovals')) {
    /**
     * Whether the given account may use Booking Approvals (view + decide).
     *
     * Allowed when the account is an Administrative Officer V (DTC Manager),
     * OR its employee id is in the Facility Coordinator allowlist.
     */
    function canAccessBookingApprovals($employee_id): bool
    {
        $id = trim((string) $employee_id);
        if ($id === '') {
            return false;
        }

        if (bsaUserPositionId($id) === 'ADOF5') {
            return true;
        }

        return in_array($id, bsaBookingApproverIds(), true);
    }
}

if (!function_exists('denyBsaApprovalAccess')) {
    /**
     * Emit the 403 for a denied Booking Approvals request.
     *
     * @param bool $asJson true for the decide endpoints, false for the HTML view.
     */
    function denyBsaApprovalAccess(bool $asJson = false): void
    {
        if ($asJson) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error'   => 'FORBIDDEN',
                'message' => 'You do not have permission to use Booking Approvals.',
            ]);
            exit;
        }

        http_response_code(403);
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Access denied</title></head><body style="margin:0;padding:48px;'
            . 'font-family:Segoe UI,Arial,sans-serif;background:#f8f9fc;color:#3a3b45">'
            . '<div style="max-width:640px;margin:0 auto;background:#fff;border-left:6px solid #e74a3b;'
            . 'border-radius:8px;box-shadow:0 2px 12px rgba(0,0,0,.08);padding:32px 36px">'
            . '<h1 style="margin:0 0 12px;font-size:22px;color:#e74a3b">Access denied</h1>'
            . '<p style="margin:0 0 8px;line-height:1.5">Booking Approvals is limited to the '
            . '<strong>DTC Manager (Administrative Officer V)</strong> and the '
            . '<strong>Facility Coordinator (Administrative Aide VI)</strong>.</p>'
            . '<p style="margin:0;color:#858796;font-size:13px">You can still use Booking &amp; Schedule to view and '
            . 'submit bookings. Contact your DMIS administrator if you believe you should have access.</p>'
            . '</div></body></html>';
        exit;
    }
}
