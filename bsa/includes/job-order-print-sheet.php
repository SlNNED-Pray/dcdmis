<?php
/**
 * bsa/includes/job-order-print-sheet.php
 * Read-only print rendering of a Job Order/Request, laid out to match the official
 * DepEd Region IX "Annex 8 ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â Job Order/Request Form" sheet.
 *
 * Expects:
 *   $jobOrder  (array)  a row from bsa_job_order_requests, already employee-joined.
 * Optional:
 *   $printSheetPrintButton (bool) render a floating "Print" button (screen preview only).
 */
if (empty($jobOrder) || !is_array($jobOrder)) {
    return;
}

// The sheet is included by pages with different bootstrap chains (e.g.
// booking-approvals.php loads includes/dtcsc-optional.php, job-order-tracking.php
// does not), so define the display format here to keep this file self-contained.
if (!defined('DISPLAY_DATETIME_FORMAT')) {
    define('DISPLAY_DATETIME_FORMAT', 'M d, Y h:i A');
}

$joWorkTypes = array_values(array_filter(array_map('trim', explode(',', (string) ($jobOrder['description_of_work'] ?? '')))));
$joScope = trim((string) ($jobOrder['other_scope'] ?? ''));

$joStatus = (string) ($jobOrder['status'] ?? 'pending');
$joTracking = (string) ($jobOrder['tracking_remarks'] ?? '');
if (!in_array($joTracking, jobOrderTrackingRemarks(), true)) {
    $joTracking = 'work_in_progress';
}
$joTrackingLabel = jobOrderTrackingRemarksLabel($joTracking);

$joStamp = static function ($value): string {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    $ts = strtotime($value);
    return $ts ? date(DISPLAY_DATETIME_FORMAT, $ts) : '';
};

$joDateRequested = $joStamp($jobOrder['date_request'] ?? null);

// "Prepared by" is the configured GSS Focal; the tracking save writes the same name,
// but resolve from settings so the signature block is never blank on older records.
$joGssFocal = trim((string) ($jobOrder['prepared_by'] ?? ''));
if ($joGssFocal === '' && function_exists('bsaGssFocal')) {
    $joGssFocal = (string) bsaGssFocal()['name'];
}
if ($joGssFocal === '') {
    $joGssFocal = 'GSS Focal';
}

// "Noted by" is the AO V (Admin) who decided the request; the tracking save records it.
$joNotedBy = trim((string) ($jobOrder['noted_by'] ?? ''));
if ($joNotedBy === '' && function_exists('approvedByName')) {
    $joNotedBy = trim((string) approvedByName($jobOrder));
}

/* Keyed stored-value => printed label. The database stores the bare token "Others"
   (see the checkbox lists in job-order-tracking.php and booking-approvals.php) while
   the form prints the longer "Others (describe scope of work)" wording, so the tick
   has to be tested against the key, not the label. */
$joWorkTypeList = [
    'Electrical' => 'Electrical',     'Airconditioning' => 'Airconditioning',
    'Plumbing' => 'Plumbing',    'Carpentry' => 'Carpentry',
    'Janitorial' => 'Janitorial',    'ICT-related' => 'ICT-related',
    'Others' => 'Others (describe scope of work)',
];
?>
<style>
    /* Sheet = 107.95 x 330.2 mm (4.25 x 13 in) paper with a 0.25in (6.35mm) page margin,
       so the printer's printable box - and the sheet - is 95.25 x 317.5 mm. The
       sheet fills that box as a flex column; the table grows (flex:1 1 auto) to
       absorb the leftover height so the footer stays pinned to the bottom and the
       document stays on one page.

       The margin is deliberate: at margin 0 the content sits on the paper edge,
       inside the unprintable area of most printers, so the outer border and edge
       text get clipped. 6.35mm is the near-universal minimum safe inset; raise it
       to 12.7mm if your hardware still clips.

       NOTE: the size is given explicitly rather than as `size: legal` because the
       CSS `legal` keyword means 8.5 x 14 in (355.6mm tall), not 13 in.

       NOTE: at 95.25 mm wide the label column is only ~20% of that (~19mm), so long
       labels wrap over several lines and rows grow taller. Verify the document still
       resolves to a single page after any width change.

       The ".jo-print-scope" selectors carry the layout so they still win when an
       including page (e.g. booking-approvals.php) declares its own print rules
       and @page for other print forms rendered in the same document. */
    .jo-print-scope .jo-sheet {
        width: 95.25mm;
        max-width: 95.25mm;
        min-height: 317.5mm;
        margin: 0 auto;
        padding: 0;
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
        background: #fff;
        color: #111;
        font-family: "Times New Roman", serif;
        font-size: 11px;
    }
    /* Header */
    .jo-head { font-family: "Old English Text MT", Arial, sans-serif; text-align: center; font-size: 13px; line-height: 1.1; }
    .jo-head img { height: 1.6cm; width: auto; }
    .jo-head2 { font-family: "Trajan Pro", Arial, sans-serif; text-align: center; font-size: 10.5px; margin-top: 1px; }
    .jo-head3 { font-family: "Times New Roman", serif; text-align: center; font-size: 10.5px; font-weight: 700; text-transform: uppercase; }
    .jo-title { text-align: center; font-size: 12px; font-weight: 700; text-transform: uppercase; margin: 3px 0 1px; }

    /* The main form table absorbs any leftover vertical space (flex-grow) so
       the sheet fills the printable area and the footer meta/footer image stay
       pinned to the bottom of the page. */
    .jo-print-scope .jo-table { width: 100%; border-collapse: collapse; table-layout: fixed; flex: 1 1 auto; }
    /* Arial 11pt for every cell in the form table. Set once on the cell rule so any
       new row inherits it; the variants below only override weight/style/alignment.
       Size is in pt because the label cells were already 11pt, so values, band and
       acknowledgement line were brought up to that same size rather than the
       reverse. Check the document still resolves to one page if this is raised. */
    .jo-table th, .jo-table td { border: 1px solid #111; padding: 3px 4px; vertical-align: top; font-family: Arial, Helvetica, sans-serif; font-size: 11pt; }
    .jo-table th {
        width: 20%;
        font-weight: 400;
        text-align: left;
        padding: 1px 2px;
    }
    /* Value cells are th as well, so they opt out of the label typography above. */
    .jo-table th.jo-v {
        width: auto;
        font-weight: 400;
        text-align: left;
        padding: 2px 3px;
    }
    .jo-table th.jo-v:not([colspan]) { width: 20%; }
    .jo-table .jo-h { height: 18px; }
    .jo-table .jo-h2 { height: 23px; }
    .jo-table .jo-h3 { height: 50px;   }
    .jo-table .jo-h4 { height: 46px; }

    /* "For GSS only:" band and the acknowledgement line span the full width. */
    .jo-table .jo-band th { width: auto; font-weight: 700; text-align: left; letter-spacing: .3px; }
    .jo-table .jo-ack th { width: auto; border: 0; padding: 6px 4px 2px; font-style: italic; font-weight: 400; text-align: left; }

    .jo-checks { display: grid; grid-template-columns: 1fr 1fr; column-gap: 6px; row-gap: 2px; }
    .jo-checks .jo-checks-row { display: block; }
    /* The "Others (describe scope of work)" label is much longer than the other
       entries, so it takes the full row instead of being squeezed into one column. */
    .jo-checks .jo-checks-row:last-child { grid-column: 1 / -1; }
    /* Fixed width keeps every box aligned down the list; the margin supplies the
       gap between the "[X]" and its label, which had none. */
    .jo-checks .jo-box, .jo-box { display: inline-block; width: 15px; margin-right: 4px; font-weight: 700; }
    /* Free-text scope belongs to the "Others" tick above it, so it is indented to
       line up with that label rather than starting at the cell edge. */
    .jo-scope { display: block; margin-top: 2px; margin-left: 19px; }
    .jo-mt { margin-top: 3px; }
    .jo-muted { color: #444; }

    /* Footer meta + image sit at the bottom of the page. The table grows to take
       up the slack, so no auto margin is needed here.
       The meta block is a bordered table of five columns by two rows:
         row 1:  Doc. Ref. Code |   | Rev |   |
         row 2:  Effectivity    |   | Page |   | 1 of 1
       Labels sit in columns 1 and 3, the blank write-in cells in columns 2 and 4,
       and the trailing "1 of 1" in column 5. table-layout:fixed honours the
       colgroup widths, so the cell edges line up between the two rows. */
    .jo-foot { font-size: 7.5pt; flex: 0 0 auto; width: 100%; border-collapse: collapse; table-layout: fixed; }
    .jo-foot td { border: 1px solid #000; padding: 1px 3px; vertical-align: bottom; height: 4.5mm; }
    .jo-foot .jo-fv { white-space: nowrap; }
    .jo-print-scope .jo-footer-img { text-align: center; flex: 0 0 auto; padding-top: 5px; }
    .jo-print-scope .jo-footer-img img { width: 100%; max-width: 95.25mm; height: auto; display: block; margin: 0 auto; }

    .jo-print-btn { position: fixed; top: 12px; right: 12px; z-index: 2000; }

    @page { size: 107.95mm 330.2mm; margin: 6.35mm; }
    @media print {
        html, body { background: #fff; margin: 0; padding: 0; }
        .no-print, .jo-print-btn { display: none !important; }
        /* Repeated under the scope class so the sheet keeps its geometry even if
           the including page sets its own print rules for other print forms.
           width/padding are reset here; position is deliberately left alone
           because booking-approvals.php hides the rest of the page with
           visibility:hidden (which still reserves layout space) and relies on
           the sheet being absolutely positioned so it does not add a page. */
        .jo-print-scope .jo-sheet {
            width: 95.25mm;
            max-width: 95.25mm;
            min-height: 317.5mm;
            margin: 0 auto;
            padding: 0;
        }
    }
</style>

<?php if (!empty($printSheetPrintButton)): ?>
    <div class="jo-print-btn no-print">
        <button type="button" class="btn btn-primary" onclick="window.print();">
            <i class="fa fa-print"></i> Print
        </button>
    </div>
<?php endif; ?>

<div class="jo-print-scope">
<div class="jo-sheet">
    <div class="jo-head">
        <img src="image/logo.png" alt=""><br>
        Republic of the Philippines<br>
        Department of Education
    </div>
    <div class="jo-head2">Region IX &ndash; Zamboanga Peninsula</div>
    <div class="jo-head3">Schools Division of Dipolog City</div>
    <div class="jo-title">Job Order/Request Form</div>

    <table class="jo-table">
        <tr>
            <th  >Order No. <?php echo htmlspecialchars((string) $jobOrder['order_no']); ?></th>
            <th   >Date <?php echo htmlspecialchars($joDateRequested); ?></th>
        </tr>
        <tr class="jo-v">
            <th class="jo-v" colspan="2" >Requesting Office : <?php echo htmlspecialchars((string) ($jobOrder['requesting_office'] ?? '')); ?></th>
        </tr>
        <tr class="jo-v">
            <th class="jo-v"colspan="2" >Requesting Personnel : <?php echo htmlspecialchars((string) $jobOrder['requesting_personnel']); ?></th>
        </tr>
        <tr class="jo-v">
            <th class="jo-v" colspan="2">Location of Work : <?php echo htmlspecialchars((string) $jobOrder['location_of_work']); ?></th>
        </tr>
        <tr>
            <th class="jo-v" colspan="2" >Description of Work :
             
                <span class="jo-checks">
                    <?php foreach ($joWorkTypeList as $joWorkTypeValue => $workType): ?>
                        <span class="jo-checks-row">
                            <?php /* Accept the bare token "Others" and the long label, so a
                                     legacy row saved from the printed wording still ticks. */ ?>
                            <?php $joTicked = in_array($joWorkTypeValue, $joWorkTypes, true) || in_array($workType, $joWorkTypes, true); ?>
                            <span class="jo-box">[<?php echo $joTicked ? 'X' : ' '; ?>]</span><?php echo htmlspecialchars($workType); ?>
                        </span>
                    <?php endforeach; ?>
                </span>
                <?php if ($joScope !== ''): ?>
                    <span class="jo-scope"><?php echo nl2br(htmlspecialchars($joScope)); ?></span>
                <?php else: ?>
                    
                <?php endif; ?>
            </th>
        </tr>
         
        <tr class="jo-h3" >
              
            <th style= "text-align: center; padding-top: 30px; " class="jo-v" colspan="2" ><?php echo htmlspecialchars((string) $jobOrder['requestor_name']); ?>
                <?php if (!empty($jobOrder['requestor_signature'])): ?>
                    <span class="jo-muted"><?php echo htmlspecialchars((string) $jobOrder['requestor_signature']); ?></span>
                <?php endif; ?> <br>Requestor's Name and Signature </th>
            </tr>

        <tr class="jo-band">
            <th colspan="2">For GSS only:</th>
        </tr>

        <tr class="jo-h2">
            <th class="jo-v" colspan="2" >Actions Taken <br> <?php echo nl2br(htmlspecialchars((string) ($jobOrder['actions_taken'] ?? ''))); ?></th>
             
        </tr>
        <tr class="jo-h4">
            <th class="jo-v" colspan="2">Recommendation : <br>   <?php echo nl2br(htmlspecialchars((string) ($jobOrder['recommendation'] ?? ''))); ?></th>
        </tr>
        <tr class="jo-h2">
            <th  class="jo-v" colspan = "1">Assigned Personnel <br> <?php echo htmlspecialchars((string) ($jobOrder['assigned_personnel'] ?? '')); ?></th>
            <th class="jo-v" colspan = "1" >Prepared by <br> <?php echo htmlspecialchars($joGssFocal); ?> <br> GSS Focal</th>
             
        </tr>
        <tr class="jo-h2">
            <th>Date &amp; Time Started</th>
            <th class="jo-v" colspan="1"><?php echo htmlspecialchars($joStamp($jobOrder['date_time_started'] ?? null)); ?></th>
        </tr>
        <tr class="jo-h2">
            <th>Date &amp; Time Completed</th>
            <th class="jo-v" colspan="1"><?php echo htmlspecialchars($joStamp($jobOrder['date_time_completed'] ?? null)); ?></th>
        </tr>
        <tr class="jo-h2">
            <th class="jo-v" colspan="2">Comments/Remarks <br> <?php echo htmlspecialchars($joTrackingLabel); ?>
                <?php if (!empty(trim((string) ($jobOrder['remarks'] ?? '')))): ?>
                    <br><span class="jo-muted"><?php echo nl2br(htmlspecialchars((string) $jobOrder['remarks'])); ?></span>
                <?php endif; ?></th>
        </tr>

        <tr class="jo-h2">
            <th colspan="2">I hereby acknowledge that the work above has been satisfactorily completed <br><?php echo htmlspecialchars((string) ($jobOrder['job_proponent_name'] ?? '')); ?>
                <?php if (!empty($jobOrder['job_proponent_signature'])): ?>
                    <br><span class="jo-muted"><?php echo htmlspecialchars((string) $jobOrder['job_proponent_signature']); ?></span>
                <?php endif; ?><br>Job Proponent (Name &amp; Signature)</th>
        </tr>
         
        <tr class="jo-h2">
            <th class="jo-v" colspan="2">Noted by: <br> <p stlye = textalignment"Center"> <?php echo htmlspecialchars($joNotedBy !== '' ? $joNotedBy : 'Administrative Officer V (Admin)'); ?>
                <br><span class="jo-muted">Administrative Officer V (Admin)</span></p>
            </th>
             
        </tr>
    </table>
    <br>
    <table class="jo-foot">
        <colgroup>
            <col style="width: 24%">
            <col style="width: 26%">
            <col style="width: 13%">
            <col style="width: 24%">
            <col style="width: 13%">
        </colgroup>
        <tr>
            <td>Doc. Ref. Code</td>
            <td></td>
            <td></td>
            <td>Rev</td>
            <td></td>
        </tr>
        <tr>
            <td>Effectivity</td>
            <td></td>
            <td></td>
            <td>Page</td>
            <td class="jo-fv">1 of 1</td>
        </tr>
    </table>

    <div class="jo-footer-img">
        <img src="image/footer%202.png" alt="">
    </div>
</div>
</div>

