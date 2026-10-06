<?php
/**
 * DTC facility booking form partial (multi-step "Division Training Center (DTC) Booking Form").
 * Copies the layout and signatory style of the Vehicle Request / Booking Form.
 * Expects: $csrfToken, $prefill.
 */
?>
<div class="form-head">
    <h2>Division Training Center (DTC) Booking Form</h2>
    <p>Republic of the Philippines &middot; Department of Education &middot; Region IX &middot; Schools Division of Dipolog City</p>
    <div class="control-no"><span class="bi bi-ticket-perforated me-1"></span>Booking Ref.: <span id="bookingRefDisplay">assigned upon submission</span></div>
</div>

<div class="booking-form-container">
    <!-- Progress Steps -->
    <div class="booking-progress mb-4">
        <div class="progress">
            <div class="progress-bar" role="progressbar" style="width: 20%" id="formProgress"></div>
        </div>
        <div class="step-indicators d-flex justify-content-between mt-2">
            <div class="step active" data-step="1">
                <span class="step-number">1</span>
                <span class="step-label">Request Details</span>
            </div>
            <div class="step" data-step="2">
                <span class="step-number">2</span>
                <span class="step-label">Activity Details</span>
            </div>
            <div class="step" data-step="3">
                <span class="step-number">3</span>
                <span class="step-label">Requirements</span>
            </div>
            <div class="step" data-step="4">
                <span class="step-number">4</span>
                <span class="step-label">Signatories</span>
            </div>
            <div class="step" data-step="5">
                <span class="step-number">5</span>
                <span class="step-label">Review &amp; Submit</span>
            </div>
        </div>
    </div>

    <form id="dtcBookingForm" method="POST" action="<?php echo uri(); ?>/bsa/api/dtc-booking-save.php">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

        <!-- Step 1: Request Details -->
        <div class="form-step active" data-step="1">
            <h3 class="mb-4">Step 1: Request Details</h3>

            <div class="row mb-3">
                <div class="col-md-8">
                    <label for="requesting_office" class="form-label">Requesting Office <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="requesting_office" name="requesting_office" value="<?php echo htmlspecialchars($prefill['requesting_office']); ?>" required>
                     
                </div>
                <div class="col-md-4">
                    <label for="date_request" class="form-label">Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="date_request" name="date_request" value="<?php echo htmlspecialchars($prefill['date_request']); ?>" required>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="contact_person" class="form-label">Contact Person <span class="text-danger">*</span></label>
                    <div class="dtc-autocomplete" data-suggestion-for="contact">
                        <input type="text" class="form-control" id="contact_person" name="contact_person" value="<?php echo htmlspecialchars($prefill['contact_person']); ?>" autocomplete="off" required>
                        <div class="dtc-autocomplete-suggestions"></div>
                    </div>
                    
                </div>
                <div class="col-md-6">
                    <label for="email_address" class="form-label">Email Address <span class="text-danger">*</span></label>
                    <div class="dtc-autocomplete" data-suggestion-for="email">
                        <input type="text" class="form-control" id="email_address" name="email_address" value="<?php echo htmlspecialchars($prefill['email_address']); ?>" autocomplete="off" required>
                        <div class="dtc-autocomplete-suggestions"></div>
                    </div>
 
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="contact_number" class="form-label">Contact Number <span class="text-danger">*</span></label>
                    <input type="tel" class="form-control" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($prefill['contact_number']); ?>" required>
                </div>
            </div>

            <div class="form-navigation">
                <button type="button" class="btn btn-primary next-step">Next <i class="icon-arrow-right ms-2"></i></button>
            </div>
        </div>

        <!-- Step 2: Activity Details -->
        <div class="form-step" data-step="2">
            <h3 class="mb-4">Step 2: Activity Details</h3>

            <div class="mb-3">
                <label for="activity_title" class="form-label">Name of Activity <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="activity_title" name="activity_title" maxlength="255" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Type of Activity <span class="text-danger">*</span></label>
                <div class="row">
                    <?php foreach (['Official', 'External'] as $t): ?>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input activity-type" type="radio" name="activity_type" id="activity_type_<?php echo strtolower($t); ?>" value="<?php echo $t; ?>" required>
                                <label class="form-check-label" for="activity_type_<?php echo strtolower($t); ?>"><?php echo $t; ?></label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="row mb-3" id="activityLevelRow" style="display:none;">
                <div class="col-md-6">
                    <label for="activity_level" class="form-label">If Official, specify level <span class="text-danger">*</span></label>
                    <select class="form-select" id="activity_level" name="activity_level">
                        <option value="">Select level...</option>
                        <?php foreach (['School', 'Division', 'Region', 'Central Office'] as $lvl): ?>
                            <option value="<?php echo $lvl; ?>"><?php echo $lvl; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="start_date" class="form-label">Inclusive Dates of Use <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($prefill['start_date']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="end_date" class="form-label">to <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($prefill['end_date'] ?: $prefill['start_date']); ?>" required>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="start_time" class="form-label">Inclusive Time of Use <span class="text-danger">*</span></label>
                    <input type="time" class="form-control" id="start_time" name="start_time" value="<?php echo htmlspecialchars($prefill['start_time']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="end_time" class="form-label">to</label>
                    <input type="time" class="form-control" id="end_time" name="end_time" value="<?php echo htmlspecialchars($prefill['end_time']); ?>">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="venue_option" class="form-label">Venue Option <span class="text-danger">*</span></label>
                    <select class="form-select" id="venue_option" name="venue_option" required>
                        <option value="">Select venue...</option>
                        <option value="Main Hall">Main Hall (30 to 80 pax or 100 w/o tables)</option>
                        <option value="Inner Room">Inner Room (5 to 15 pax or 20 w/o tables)</option>
                        <option value="TV Room">TV Room (5 to 15 pax or 30 w/o tables)</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="participant_count" class="form-label">No. of Participants <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="participant_count" name="participant_count" min="1" required>
                </div>
            </div>

            <div class="form-navigation">
                <button type="button" class="btn btn-secondary prev-step"><i class="icon-arrow-left me-2"></i>Previous</button>
                <button type="button" class="btn btn-primary next-step">Next <i class="icon-arrow-right ms-2"></i></button>
            </div>
        </div>

        <!-- Step 3: Requirements -->
        <div class="form-step" data-step="3">
            <h3 class="mb-4">Step 3: Requirements</h3>

            <div class="mb-4">
                <label class="form-label">Equipment Needed</label>
                <div class="equipment-options">
                    <?php foreach (['Tables', 'Chairs', 'LCD Projector', 'Projector Screen', 'Audio/Sound System'] as $equip): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="equipment_needed[]" id="equip_<?php echo strtolower(str_replace([' ', '/'], '_', $equip)); ?>" value="<?php echo $equip; ?>">
                            <label class="form-check-label" for="equip_<?php echo strtolower(str_replace([' ', '/'], '_', $equip)); ?>">
                                <?php echo $equip; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="no_of_microphones" class="form-label">No. of Microphone <span class="text-danger">*</span> <span class="text-muted small">(maximum of 4 only)</span></label>
                    <input type="number" class="form-control" id="no_of_microphones" name="no_of_microphones" min="0" max="4" value="0" required>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">With external catering services? <span class="text-danger">*</span></label>
                    <div class="mt-1">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="external_catering" id="catering_yes" value="Yes" required>
                            <label class="form-check-label" for="catering_yes">Yes</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="external_catering" id="catering_no" value="No" required>
                            <label class="form-check-label" for="catering_no">No</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">With external equipment? <span class="text-danger">*</span></label>
                    <div class="mt-1">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="external_equipment" id="ext_equip_yes" value="Yes" required>
                            <label class="form-check-label" for="ext_equip_yes">Yes</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="external_equipment" id="ext_equip_no" value="No" required>
                            <label class="form-check-label" for="ext_equip_no">No</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="external_equipment_details" class="form-label">If yes, please specify</label>
                    <textarea class="form-control" id="external_equipment_details" name="external_equipment_details" rows="2"></textarea>
                </div>
            </div>

            <div class="mb-3">
                <label for="special_requests" class="form-label">Special Requests</label>
                <textarea class="form-control" id="special_requests" name="special_requests" rows="3" placeholder="Any special requirements or requests..."></textarea>
            </div>

            <div class="form-navigation">
                <button type="button" class="btn btn-secondary prev-step"><i class="icon-arrow-left me-2"></i>Previous</button>
                <button type="button" class="btn btn-primary next-step">Next <i class="icon-arrow-right ms-2"></i></button>
            </div>
        </div>

        <!-- Step 4: Signatories -->
        <div class="form-step" data-step="4">
            <h3 class="mb-4">Step 4: Signatories</h3>

            <h5 class="mb-3">Requested by</h5>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="requested_by_name" class="form-label">Name <span class="text-danger">*</span></label>
                    <div class="dtc-autocomplete" data-suggestion-for="requested">
                        <input type="text" class="form-control" id="requested_by_name" name="requested_by_name" value="<?php echo htmlspecialchars($prefill['requested_by_name']); ?>" autocomplete="off" required>
                        <div class="dtc-autocomplete-suggestions"></div>
                    </div>
                    <div class="auto-note"><i class="bi bi-person-check me-1"></i>Type to search; position &amp; signature auto-fill.</div>
                </div>
                <div class="col-md-6">
                    <label for="requested_by_position" class="form-label">Position <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="requested_by_position" name="requested_by_position" value="<?php echo htmlspecialchars($prefill['requested_by_position']); ?>" required>
                     
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="requested_by_signature" class="form-label">Signature (type full name to sign) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="requested_by_signature" name="requested_by_signature" value="<?php echo htmlspecialchars($prefill['requested_by_signature']); ?>" maxlength="255" required>
                </div>
            </div>

            <h5 class="mb-3">Concurred by (Functional Division Chief)</h5>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="concurred_by_name" class="form-label">Name <span class="text-danger">*</span></label>
                    <div class="dtc-autocomplete" data-suggestion-for="concurred">
                        <input type="text" class="form-control" id="concurred_by_name" name="concurred_by_name" value="<?php echo htmlspecialchars($prefill['concurred_by_name']); ?>" autocomplete="off" required>
                        <div class="dtc-autocomplete-suggestions"></div>
                    </div>
                    <div class="auto-note"><i class="bi bi-person-check me-1"></i>Suggestions resolve to the office head of the selected employee.</div>
                </div>
                <div class="col-md-6">
                    <label for="concurred_by_position" class="form-label">Position <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="concurred_by_position" name="concurred_by_position" value="<?php echo htmlspecialchars($prefill['concurred_by_position']); ?>" required>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="concurred_by_signature" class="form-label">Signature (type full name to sign) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="concurred_by_signature" name="concurred_by_signature" value="<?php echo htmlspecialchars($prefill['concurred_by_signature']); ?>" maxlength="255" required>
                </div>
            </div>

            <div class="form-navigation">
                <button type="button" class="btn btn-secondary prev-step"><i class="icon-arrow-left me-2"></i>Previous</button>
                <button type="button" class="btn btn-primary next-step">Next <i class="icon-arrow-right ms-2"></i></button>
            </div>
        </div>

        <!-- Step 5: Review & Submit -->
        <div class="form-step" data-step="5">
            <h3 class="mb-4">Step 5: Review &amp; Submit</h3>

            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                Please review all information before submitting your facility booking.
            </div>

            <div id="bookingSummary" class="booking-summary">
                <!-- Summary will be populated by JavaScript -->
            </div>

            <div class="form-check mt-4 mb-3">
                <input class="form-check-input" type="checkbox" id="certify_correct" required>
                <label class="form-check-label" for="certify_correct">
                    I certify that the information provided is correct.
                </label>
            </div>

            <div class="form-navigation">
                <button type="button" class="btn btn-secondary prev-step"><i class="icon-arrow-left me-2"></i>Previous</button>
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="bi bi-check-circle me-2"></i>Submit Booking
                </button>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    function initDtcBookingForm() {
        if (window.jQuery === undefined) {
            setTimeout(initDtcBookingForm, 50);
            return;
        }
        jQuery(function ($) {
            var currentStep = 1;
            var totalSteps = 5;

            function showStep(step) {
                $('.form-step').removeClass('active').hide();
                $('.form-step[data-step="' + step + '"]').addClass('active').show();
                $('.step').removeClass('active');
                $('.step[data-step="' + step + '"]').addClass('active');
                $('#formProgress').css('width', ((step / totalSteps) * 100) + '%');
                currentStep = step;
            }

            $('.activity-type').on('change', function () {
                if ($(this).val() === 'Official') {
                    $('#activityLevelRow').show();
                    $('#activity_level').prop('required', true);
                } else {
                    $('#activityLevelRow').hide();
                    $('#activity_level').val('').prop('required', false);
                }
            });

            $('.next-step').on('click', function () {
                if (validateStep(currentStep)) {
                    if (currentStep < totalSteps) {
                        showStep(currentStep + 1);
                        if (currentStep + 1 === 5) {
                            generateSummary();
                        }
                    }
                }
            });

            $('.prev-step').on('click', function () {
                if (currentStep > 1) {
                    showStep(currentStep - 1);
                }
            });

            function validateStep(step) {
                var stepEl = $('.form-step[data-step="' + step + '"]');
                var requiredFields = stepEl.find('input[required], select[required], textarea[required]');
                var isValid = true;

                requiredFields.each(function () {
                    var val;
                    if ($(this).attr('type') === 'radio') {
                        val = $('input[name="' + $(this).attr('name') + '"]:checked').val();
                    } else if ($(this).attr('type') === 'checkbox') {
                        val = $(this).prop('checked') ? '1' : '';
                    } else {
                        val = $(this).val();
                    }
                    if (!val) {
                        $(this).addClass('is-invalid');
                        isValid = false;
                    } else {
                        $(this).removeClass('is-invalid');
                    }
                });

                if (step === 2) {
                    var startDate = $('#start_date').val();
                    var endDate = $('#end_date').val();
                    var startTime = $('#start_time').val();
                    var endTime = $('#end_time').val();

                    if (startDate && endDate && endDate < startDate) {
                        alert('End Date (Inclusive Dates of Use) must be on or after the Start Date');
                        isValid = false;
                    }
                    if (startDate && startDate === endDate && startTime && endTime && endTime <= startTime) {
                        alert('End Time (Inclusive Time of Use) must be after the Start Time');
                        isValid = false;
                    }
                    var mics = parseInt($('#no_of_microphones').val(), 10) || 0;
                    if (mics > 4) {
                        alert('No. of Microphone is limited to a maximum of 4 only');
                        isValid = false;
                    }
                    if (!startTime) {
                        alert('Inclusive Time of Use (Start) is required');
                        isValid = false;
                    }
                }

                if (step === 3) {
                    var extEquip = $('input[name="external_equipment"]:checked').val();
                    if (extEquip === 'Yes' && !$('#external_equipment_details').val().trim()) {
                        alert('Please specify the external equipment.');
                        $('#external_equipment_details').addClass('is-invalid');
                        isValid = false;
                    } else {
                        $('#external_equipment_details').removeClass('is-invalid');
                    }
                }

                if (step === 4) {
                    if ($('#requested_by_signature').val() !== $('#requested_by_name').val()) {
                        if (!confirm('The requested-by signature does not match the requested-by name. Continue?')) {
                            isValid = false;
                        }
                    }
                }

                return isValid;
            }

            function generateSummary() {
                var activityType = $('input[name="activity_type"]:checked').val() || 'N/A';
                var level = $('#activity_level').val() ? (' (' + $('#activity_level').val() + ')') : '';
                var equipment = $('input[name="equipment_needed[]"]:checked').map(function () { return $(this).val(); }).get().join(', ') || 'None';
                var summaryHTML =
                    '<div class="row">' +
                        '<div class="col-md-6">' +
                            '<h6>Request Details</h6>' +
                            '<p><strong>Office:</strong> ' + $('#requesting_office').val() + '</p>' +
                            '<p><strong>Date:</strong> ' + $('#date_request').val() + '</p>' +
                            '<p><strong>Person:</strong> ' + $('#contact_person').val() + '</p>' +
                            '<p><strong>Email:</strong> ' + ($('#email_address').val() || 'N/A') + '</p>' +
                            '<p><strong>Contact:</strong> ' + $('#contact_number').val() + '</p>' +
                        '</div>' +
                        '<div class="col-md-6">' +
                            '<h6>Activity Details</h6>' +
                            '<p><strong>Activity:</strong> ' + $('#activity_title').val() + '</p>' +
                            '<p><strong>Type:</strong> ' + activityType + level + '</p>' +
                            '<p><strong>Venue:</strong> ' + $('#venue_option').val() + '</p>' +
                            '<p><strong>Schedule:</strong> ' + $('#start_date').val() + ' ' + $('#start_time').val() + ' - ' + $('#end_date').val() + ' ' + ($('#end_time').val() || '') + '</p>' +
                            '<p><strong>Participants:</strong> ' + $('#participant_count').val() + '</p>' +
                        '</div>' +
                    '</div>' +
                    '<hr>' +
                    '<div class="row">' +
                        '<div class="col-md-6">' +
                            '<h6>Requirements</h6>' +
                            '<p><strong>Equipment:</strong> ' + equipment + '</p>' +
                            '<p><strong>Microphones:</strong> ' + $('#no_of_microphones').val() + '</p>' +
                            '<p><strong>Catering:</strong> ' + ($('input[name="external_catering"]:checked').val() || 'N/A') + '</p>' +
                            '<p><strong>External Equipment:</strong> ' + ($('input[name="external_equipment"]:checked').val() || 'N/A') + ($('#external_equipment_details').val() ? ' - ' + $('#external_equipment_details').val() : '') + '</p>' +
                            '<p><strong>Special Requests:</strong> ' + ($('#special_requests').val() || 'None') + '</p>' +
                        '</div>' +
                        '<div class="col-md-6">' +
                            '<h6>Signatories</h6>' +
                            '<p><strong>Requested By:</strong> ' + $('#requested_by_name').val() + ' (' + $('#requested_by_position').val() + ')</p>' +
                            '<p><strong>Concurred By:</strong> ' + $('#concurred_by_name').val() + ' (' + $('#concurred_by_position').val() + ')</p>' +
                        '</div>' +
                    '</div>';
                $('#bookingSummary').html(summaryHTML);
            }

            // --- Employee autocomplete suggestions ---
            var suggestUrl = '<?php echo uri(); ?>/bsa/api/employees-suggest.php';
            var suggestTimers = {};

            $(document).on('click', function (e) {
                if (!$(e.target).closest('.dtc-autocomplete').length) {
                    $('.dtc-autocomplete-suggestions').hide().empty();
                }
            });

            $('.dtc-autocomplete').each(function () {
                var $wrap = $(this);
                var $input = $wrap.find('input');
                var $list = $wrap.find('.dtc-autocomplete-suggestions');
                var mode = $wrap.attr('data-suggestion-for');
                var active = -1;
                var items = [];

                function closeList() {
                    $list.hide().empty();
                    active = -1;
                    items = [];
                }

                function select(item) {
                    if (mode === 'contact') {
                        $input.val(item.name);
                        $('#email_address').val(item.email_address || '');
                        if (!$('#contact_number').val()) { $('#contact_number').val(item.mobile_number); }
                    } else if (mode === 'email') {
                        $('#email_address').val(item.email_address || item.name);
                        if (!$('#contact_person').val()) { $('#contact_person').val(item.name); }
                        if (!$('#contact_number').val()) { $('#contact_number').val(item.mobile_number); }
                    } else if (mode === 'requested') {
                        $('#requested_by_name').val(item.name);
                        $('#requested_by_position').val(item.position || '');
                        $('#requested_by_signature').val(item.name);
                    } else if (mode === 'concurred') {
                        var chief = item.head || item;
                        $('#concurred_by_name').val(chief.name);
                        $('#concurred_by_position').val(chief.position || '');
                        $('#concurred_by_signature').val(chief.name);
                    }
                    closeList();
                }

                function render() {
                    $list.empty();
                    active = -1;
                    if (!items.length) {
                        $list.hide();
                        return;
                    }
                    items.forEach(function (item, idx) {
                        var $item = $('<div class="item"></div>');
                        var subparts = [];
                        if (item.position) { subparts.push(item.position); }
                        if (item.office) { subparts.push(item.office); }
                        $('<div class="main"></div>').text(item.name).appendTo($item);
                        if (subparts.length) {
                            $('<div class="sub"></div>').text(subparts.join(' - ')).appendTo($item);
                        }
                        if (item.email_address) {
                            $('<div class="sub"></div>').text(item.email_address).appendTo($item);
                        }
                        $item.on('click', function () { select(item); });
                        $item.on('mouseenter', function () {
                            $list.find('.item').removeClass('active');
                            $(this).addClass('active');
                            active = idx;
                        });
                        $list.append($item);
                    });
                    $list.show();
                }

                $input.on('input', function () {
                    var q = $(this).val().trim();
                    clearTimeout(suggestTimers[mode]);
                    if (q.length < 2) {
                        closeList();
                        return;
                    }
                    suggestTimers[mode] = setTimeout(function () {
                        $.getJSON(suggestUrl, { q: q }, function (data) {
                            items = data || [];
                            render();
                        }).fail(function () { closeList(); });
                    }, 250);
                });

                $input.on('keydown', function (e) {
                    if (!$list.is(':visible') || !items.length) { return; }
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        active = (active + 1) % items.length;
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        active = (active - 1 + items.length) % items.length;
                    } else if (e.key === 'Enter') {
                        if (active >= 0) {
                            e.preventDefault();
                            select(items[active]);
                        }
                        return;
                    } else if (e.key === 'Escape') {
                        closeList();
                        return;
                    } else {
                        return;
                    }
                    $list.find('.item').removeClass('active');
                    $list.find('.item').eq(active).addClass('active');
                });

                $input.on('blur', function () {
                    setTimeout(closeList, 150);
                });
            });

            $('#dtcBookingForm').on('submit', function (e) {
                e.preventDefault();
                $('#certify_correct').removeClass('is-invalid');
                if (!$('#certify_correct').prop('checked')) {
                    $('#certify_correct').addClass('is-invalid');
                    alert('Please certify that the information provided is correct.');
                    return;
                }
                if (!validateStep(currentStep)) {
                    return;
                }

                $.ajax({
                    url: $(this).attr('action'),
                    method: 'POST',
                    data: $(this).serialize(),
                    success: function (response) {
                        if (response.success) {
                            $('#bookingRefDisplay').text(response.booking_reference);
                            $('.form-step').removeClass('active').hide();
                            $('#bookingSummary').html(
                                '<div class="success-message">' +
                                    '<div class="success-icon"><i class="bi bi-check-circle"></i></div>' +
                                    '<h1>Booking Submitted</h1>' +
                                    '<p class="text-muted">Your facility booking request has been submitted successfully and is now pending approval.</p>' +
                                    '<p class="mb-0"><strong>Booking Ref.:</strong> ' + response.booking_reference + '</p>' +
                                '</div>'
                            );
                            $('.step-indicators').hide();
                            $('#formProgress').css('width', '100%');
                        } else {
                            alert('Error: ' + (response.message || 'Unable to submit the booking.'));
                        }
                    },
                    error: function () {
                        alert('An error occurred while submitting your booking. Please try again.');
                    }
                });
            });
        });
    }

    initDtcBookingForm();
}());
</script>