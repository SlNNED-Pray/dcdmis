<?php
// includes/layout/content.php
if (!isset($url) || $url === 'dashboard') {
    require_once('dashboard.php');
} else {
    $file = '';

    switch ($url) {
        case 'Incoming Documents':
            $file = 'documents/incoming-documents';
            break;
        case 'Pending Documents':
            $file = 'documents/pending-documents';
            break;
        case 'Outgoing Documents':
            $file = 'documents/outgoing-documents';
            break;
        case 'Ongoing Documents':
            $file = 'documents/ongoing-documents';
            break;
        case 'Completed Documents':
            $file = 'documents/completed-documents';
            break;
        case 'Received Documents':
            $file = 'documents/received-documents';
            break;
        case 'Canceled Documents':
            $file = 'documents/canceled-documents';
            break;
        case 'Document Information':
            $file = 'documents/document-information';
            break;
        case 'Document Search':
            $file = 'documents/document-search';
            break;
        case 'Edit Document':
            $file = 'documents/edit-document';
            break;
        case 'Receive Document':
            $file = 'documents/receive-document';
            break;
        case 'Forward Document':
            $file = 'documents/forward-document';
            break;
        case 'Approve Document':
            $file = 'documents/approve-document';
            break;
        case 'Transactions':
            $file = 'documents/transactions';
            break;
        case 'Active Employees':
        case 'Employees':
        case 'School Employees':
            $file = 'employees/active-employees';
            break;
        case 'Retirable Employees':
            $file = 'employees/retirable-employees';
            break;
        case 'Archived Employees':
            $file = 'employees/archived-employees';
            break;
        case 'Non-Regular Employees':
            $file = 'employees/non-regular-employees';
            break;
        case 'Employee Information':
        case 'Edit Employee Information':
        case 'School Employee Information':
            $file = 'employees/employee-information';
            break;
        case 'Service Record':
        case 'School Employee Service Record':
            $file = 'service-record/page';
            break;
        case '201 Files':
        case 'School Employee 201 Files':
            $file = '201-file/page';
            break;
        case 'Payslips':
            $file = 'payslip/page';
            break;
        case 'Stock and Inventory':
            $file = root() . '/ims/inventory.php';
            break;
        case 'Create Stock Item':
            $file = root() . '/ims/item-create.php';
            break;
        case 'Edit Stock Item':
            $file = root() . '/ims/item-edit.php';
            break;
        case 'Requisition Slips':
            $file = root() . '/ims/ris.php';
            break;
        case 'Disapproved RIS':
            $file = root() . '/ims/ris-disapproved.php';
            break;
        case 'Create RIS':
            $file = root() . '/ims/ris-create.php';
            break;
        case 'IAR':
            $file = root() . '/ims/iar.php';
            break;
        case 'Create IAR':
            $file = root() . '/ims/iar-create.php';
            break;
        case 'Purchase Requests':
            $file = root() . '/ims/pr.php';
            break;
        case 'Create Purchase Request':
            $file = root() . '/ims/pr-create.php';
            break;
        case 'Stock Card':
            $file = root() . '/ims/stock-card.php';
            break;
        case 'Inventory Custodian Slip':
            $file = root() . '/ims/ics-report.php';
            break;
        case 'Issued ICS':
            $file = root() . '/ims/ics-issued.php';
            break;
        case 'Physical Count':
            $file = root() . '/ims/physical-count.php';
            break;
        case 'Price History':
            $file = root() . '/ims/price-history.php';
            break;
        case 'Purchase Order':
            $file = root() . '/ims/po.php';
            break;
        case 'Booking Approvals':
            $file = root() . '/bsa/booking-approvals.php';
            break;
        case 'Request Transfer':
            $file = 'transfer-request/page';
            break;
        case 'Transfer Requests':
            $file = 'transfer-request/requests';
            break;
        case 'Step Increment':
            $file = 'step-increment/page';
            break;
        case 'Loyalty Award':
            $file = 'loyalty-award/page';
            break;
        case 'Employee Search':
            $file = 'employees/employee-search';
            break;
        case 'Celebrant Employees':
            $file = 'employees/celebrant-employees';
            break;
        case 'Employees by Position':
            $file = 'employees/employees-position';
            break;
        case 'Users':
            $file = 'users/page';
            break;
        case 'Event Schedules':
        case 'Awards List':
        case 'Nominees List':
        case 'Winners Lookup':
        case 'Ranking':
        case 'Awards and Recognitions':
            $file = 'race/page';
            break;
        case 'Trainings':
        case 'School Employee Trainings':
            $file = 'trainings/attended-trainings';
            break;
        case 'Scheduled Trainings':
            $file = 'trainings/scheduled-trainings';
            break;
        case 'Conducted Trainings':
            $file = 'trainings/conducted-trainings';
            break;
        case 'Training Details':
            $file = 'trainings/training-details';
            break;
        case 'Attendance':
            $file = 'trainings/modules/add-attendance';
            break;
        case 'Attendance Summary':
            $file = 'trainings/modules/attendance_summary';
            break;
        case 'Programs':
            $file = 'trainings/modules/program-dashboard';
            break;
        case 'Program Detail':
            $file = 'trainings/modules/program-detail';
            break;
        case 'Project Detail':
            $file = 'trainings/modules/project-detail';
            break;
        case 'Add Training Participants':
            $file = 'trainings/add-training-participants';
            break;
        case 'Districts':
            $file = 'districts/page';
            break;
        case 'District Information':
            $file = 'districts/district-information';
            break;
        case 'Schools':
            $file = 'schools/page';
            break;
        case 'Positions':
            $file = 'positions/page';
            break;
        case 'Plantilla Items':
            $file = 'positions/plantilla-items';
            break;
        case 'School Information':
        case 'Division Office Information':
            $file = 'schools/school-information';
            break;
        case 'Sections':
            $file = 'sections/page';
            break;
        case 'Section Information':
            $file = 'sections/section-information';
            break;
        case 'Activity Log':
            $file = 'activity/page';
            break;
        case 'Edit History':
            $file = 'activity/edit-history';
            break;
        case 'System Logs':
            $file = 'activity/system-log';
            break;
        case 'Transactions Summary':
        case 'Section Summary':
            $file = 'documents/transactions-summary';
            break;
        case 'Settings':
            $file = 'settings/page';
            break;
        case 'Vacancies':
            $file = 'vacancies/page';
            break;
        case 'Create Call for Application':
        case 'Edit Call for Application':
            $file = 'vacancies/publish-vacancies';
            break;
        case 'Call for Application Details':
            $file = 'vacancies/publication-details';
            break;
        case 'Call for Application Applicants':
            $file = 'vacancies/applicants';
            break;
        case 'Applicants':
        case 'External Applicants':
            $file = 'applicants/page';
            break;
        case 'Applicant Information':
        case 'External Applicant Information':
            $file = 'applicants/applicant-information';
            break;
        case 'Edit External Applicant':
            $file = 'applicants/edit-applicant';
            break;
        case 'Qualified Applicants':
            $file = 'vacancies/qualified-applicants';
            break;
        case 'Comparative Assessment Results':
            $file = 'vacancies/comparative-assessment-results';
            break;
        case 'Assess Applicant':
            $file = 'vacancies/assess-applicant';
            break;
        case 'Disqualified Applicants':
            $file = 'vacancies/disqualified-applicants';
            break;
        case 'Call for Applications':
            $file = 'vacancies/publications';
            break;
        case 'Monitoring Tools':
            $file = 'tools/index';
            break;
        case 'Workforce Diversity - Gender':
        case 'Workforce Diversity - Category':
        case 'Workforce Diversity - Category by Gender':
        case 'Workforce Diversity - Position':
        case 'Workforce Diversity - Generation':
        case 'Workforce Diversity - Education':
        case 'Workforce Diversity - Religion':
        case 'Workforce Diversity - Indigenous Group':
        case 'Workforce Diversity - PWD':
        case 'Workforce Diversity - Solo Parent':
        case 'Workforce Diversity - District':
        case 'Workforce Diversity - Assignment':
            $file = 'employees/diversity/page';
            break;
        case 'Talent Pool Diversity - Gender':
        case 'Talent Pool Diversity - Generation':
        case 'Talent Pool Diversity - Religion':
        case 'Talent Pool Diversity - Ethnic Group':
        case 'Talent Pool Diversity - PWD':
        case 'Talent Pool Diversity - Undergraduate':
        case 'Talent Pool Diversity - Post Graduate':
        case 'Talent Pool Diversity - Registration':
            $file = 'applicants/diversity/page';
            break;
        case 'IPCRF':
            $file = 'pm/page';
            break;
        case 'Create IPCRF':
            $file = 'pm/create-ipcrf';
            break;
        case 'IPCRF Details':
            $file = 'pm/ipcrf-details';
            break;
        case 'Add Objective':
            $file = 'pm/add-objective';
            break;
        case 'Edit Objective':
            $file = 'pm/edit-objective';
            break;
        case 'Review IPCRF':
            $file = 'pm/review-ipcrf';
            break;
        case 'Phase 2':
            $file = 'pm/phase2';
            break;
        case 'Phase 4':
            $file = 'pm/phase4';
            break;
        case 'Coaching Form':
            $file = 'pm/coaching-form';
            break;
        case 'Recalibration Form':
            $file = 'pm/recalibration-form';
            break;
        case 'Print IPCRF':
            $file = 'pm/print-ipcrf-nt';
            break;
        case 'Upload MOV':
            $file = 'pm/upload-mov';
            break;
        case 'Rating Periods':
        case 'RPMS Cycles':
            $file = 'pm/admin-cycles';
            break;
        case 'Key Result Areas':
            $file = 'pm/admin-kra';
            break;
        case 'Raters Management':
            $file = 'pm/admin-raters';
            break;
        case 'Daily Time Record':
            $file = 'dtr/page';
            break;
        case 'System Overview':
            $file = 'overview/page';
            break;
        case 'Recruitment, Selection and Placement':
            $file = 'overview/rsp';
            break;
        case 'Learning and Development':
            $file = 'overview/lnd';
            break;
        case 'Performance Management':
            $file = 'overview/pm';
            break;
        case 'Rewards and Recognition':
            $file = 'overview/rnr';
            break;
        case '404':
        default:
            $file = 'error/404';
            break;
    }

    require_once(
        str_starts_with($file, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $file)
            ? $file
            : root() . "/modules/{$file}.php"
    );
}