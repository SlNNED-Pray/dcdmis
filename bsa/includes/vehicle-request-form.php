<?php
/**
 * Vehicle request form partial (multi-step "Vehicle Request" form).
 * Mirrors the DTC-SC "Book a Facility" form layout and style.
 * Expects: $csrfToken, $prefill.
 */
?>
<div class="form-head">
    <h2>Vehicle Request / Booking Form</h2>
    <p>Republic of the Philippines · Department of Education · Region IX · Schools Division of Dipolog City</p>
    <div class="control-no"><span class="bi bi-ticket-perforated me-1"></span>Control No.: <span id="controlNoDisplay">assigned upon submission</span></div>
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
                <span class="step-label">Travel Details</span>
            </div>
            <div class="step" data-step="3">
                <span class="step-number">3</span>
                <span class="step-label">Vehicle & Special Request</span>
            </div>
            <div class="step" data-step="4">
                <span class="step-number">4</span>
                <span class="step-label">Signatories</span>
            </div>
            <div class="step" data-step="5">
                <span class="step-number">5</span>
                <span class="step-label">Review & Submit</span>
            </div>
        </div>
    </div>

    <form id="vehicleRequestForm" method="POST" action="<?php echo uri(); ?>/bsa/api/vehicle-request-save.php">
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
                <div class="col-md-8">
                    <label for="requesting_personnel" class="form-label">Requesting Personnel <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="requesting_personnel" name="requesting_personnel" value="<?php echo htmlspecialchars($prefill['requesting_personnel']); ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="contact_number" class="form-label">Contact Number <span class="text-danger">*</span></label>
                    <input type="tel" class="form-control" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($prefill['contact_number']); ?>" required>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="no_of_passengers" class="form-label">No. of Passengers <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="no_of_passengers" name="no_of_passengers" min="1" value="<?php echo htmlspecialchars($prefill['no_of_passengers']); ?>" required>
                </div>
            </div>

            <div class="form-navigation">
                <button type="button" class="btn btn-primary next-step">Next <i class="icon-arrow-right ms-2"></i></button>
            </div>
        </div>

        <!-- Step 2: Travel Details -->
        <div class="form-step" data-step="2">
            <h3 class="mb-4">Step 2: Travel Details</h3>

            <div class="mb-3">
                <label for="purpose_of_travel" class="form-label">Purpose of Travel <span class="text-danger">*</span></label>
                <textarea class="form-control" id="purpose_of_travel" name="purpose_of_travel" rows="2" required><?php echo htmlspecialchars($prefill['purpose_of_travel']); ?></textarea>
            </div>

            <div class="mb-3">
                <label for="destination" class="form-label">Destination <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="destination" name="destination" value="<?php echo htmlspecialchars($prefill['destination']); ?>" required>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="depart_date" class="form-label">Date of Departure <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="depart_date" name="depart_date" value="<?php echo htmlspecialchars($prefill['depart_date']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="depart_time" class="form-label">Time of Departure <span class="text-danger">*</span></label>
                    <input type="time" class="form-control" id="depart_time" name="depart_time" value="<?php echo htmlspecialchars($prefill['depart_time']); ?>" required>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="return_date" class="form-label">Estimated Date of Return</label>
                    <input type="date" class="form-control" id="return_date" name="return_date" value="<?php echo htmlspecialchars($prefill['return_date']); ?>">
                </div>
                <div class="col-md-6">
                    <label for="return_time" class="form-label">Estimated Time of Return</label>
                    <input type="time" class="form-control" id="return_time" name="return_time" value="<?php echo htmlspecialchars($prefill['return_time']); ?>">
                </div>
            </div>

            <div class="form-navigation">
                <button type="button" class="btn btn-secondary prev-step"><i class="icon-arrow-left me-2"></i>Previous</button>
                <button type="button" class="btn btn-primary next-step">Next <i class="icon-arrow-right ms-2"></i></button>
            </div>
        </div>

        <!-- Step 3: Vehicle & Special Request -->
        <div class="form-step" data-step="3">
            <h3 class="mb-4">Step 3: Vehicle & Special Request</h3>

            <div class="mb-4">
                <label class="form-label">Vehicle Type <span class="text-danger">*</span></label>
                <div class="vehicle-type-options">
                    <?php foreach (['Pickup Truck', 'Utility Van', 'Commuter Van'] as $type): ?>
                        <div class="form-check">
                            <input class="form-check-input vehicle-type" type="radio" name="vehicle_type" id="vehicle_<?php echo strtolower(str_replace(' ', '_', $type)); ?>" value="<?php echo $type; ?>" required>
                            <label class="form-check-label" for="vehicle_<?php echo strtolower(str_replace(' ', '_', $type)); ?>">
                                <?php echo $type; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="mb-3">
                <label for="special_request" class="form-label">Special Request</label>
                <textarea class="form-control" id="special_request" name="special_request" rows="4" placeholder="Any special requirements or requests..."><?php echo htmlspecialchars($prefill['special_request']); ?></textarea>
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
                    <input type="text" class="form-control" id="requested_by_name" name="requested_by_name" value="<?php echo htmlspecialchars($prefill['requested_by_name']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="requested_by_position" class="form-label">Position <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="requested_by_position" name="requested_by_position" value="<?php echo htmlspecialchars($prefill['requested_by_position']); ?>" required>
                </div>
            </div>

            <h5 class="mb-3">Concurred by</h5>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="concurred_by_name" class="form-label">Name</label>
                    <input type="text" class="form-control" id="concurred_by_name" name="concurred_by_name" value="<?php echo htmlspecialchars($prefill['concurred_by_name']); ?>">
                </div>
                <div class="col-md-6">
                    <label for="concurred_by_position" class="form-label">Position</label>
                    <input type="text" class="form-control" id="concurred_by_position" name="concurred_by_position" value="<?php echo htmlspecialchars($prefill['concurred_by_position']); ?>">
                </div>
            </div>

            <div class="mb-3">
                <label for="division_chief" class="form-label">Functional Division Chief</label>
                <input type="text" class="form-control" id="division_chief" name="division_chief" value="<?php echo htmlspecialchars($prefill['division_chief']); ?>">
            </div>

            <div class="form-navigation">
                <button type="button" class="btn btn-secondary prev-step"><i class="icon-arrow-left me-2"></i>Previous</button>
                <button type="button" class="btn btn-primary next-step">Next <i class="bi bi-arrow-right ms-2"></i></button>
            </div>
        </div>

        <!-- Step 5: Review & Submit -->
        <div class="form-step" data-step="5">
            <h3 class="mb-4">Step 5: Review & Submit</h3>

            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                Please review all information before submitting your vehicle request.
            </div>

            <div id="vehicleSummary" class="booking-summary">
                <!-- Summary will be populated by JavaScript -->
            </div>

            <div class="form-check mt-4 mb-3">
                <input class="form-check-input" type="checkbox" id="certify_correct" required>
                <label class="form-check-label" for="certify_correct">
                    I certify that the information provided is correct.
                </label>
            </div>

            <div class="form-navigation">
                <button type="button" class="btn btn-secondary prev-step"><i class="bi bi-arrow-left me-2"></i>Previous</button>
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="bi bi-check-circle me-2"></i>Submit Vehicle Request
                </button>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    function initVehicleRequestForm() {
        if (window.jQuery === undefined) {
            setTimeout(initVehicleRequestForm, 50);
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
                    var departDate = $('#depart_date').val();
                    var returnDate = $('#return_date').val();
                    var departTime = $('#depart_time').val();
                    var returnTime = $('#return_time').val();

                    if (departDate && returnDate && returnDate < departDate) {
                        alert('Estimated Date of Return must be after the Date of Departure');
                        isValid = false;
                    }
                    if (departDate && departDate === returnDate && departTime && returnTime && returnTime <= departTime) {
                        alert('Estimated Time of Return must be after the Time of Departure');
                        isValid = false;
                    }
                }

                return isValid;
            }

            function generateSummary() {
                var vehicleType = $('input[name="vehicle_type"]:checked').val();
                var summaryHTML =
                    '<div class="row">' +
                        '<div class="col-md-6">' +
                            '<h6>Request Details</h6>' +
                            '<p><strong>Office:</strong> ' + $('#requesting_office').val() + '</p>' +
                            '<p><strong>Date:</strong> ' + $('#date_request').val() + '</p>' +
                            '<p><strong>Personnel:</strong> ' + $('#requesting_personnel').val() + '</p>' +
                            '<p><strong>Contact:</strong> ' + $('#contact_number').val() + '</p>' +
                            '<p><strong>Passengers:</strong> ' + $('#no_of_passengers').val() + '</p>' +
                        '</div>' +
                        '<div class="col-md-6">' +
                            '<h6>Travel Details</h6>' +
                            '<p><strong>Purpose:</strong> ' + $('#purpose_of_travel').val() + '</p>' +
                            '<p><strong>Destination:</strong> ' + $('#destination').val() + '</p>' +
                            '<p><strong>Departure:</strong> ' + $('#depart_date').val() + ' ' + $('#depart_time').val() + '</p>' +
                            '<p><strong>Return:</strong> ' + ($('#return_date').val() || 'N/A') + ' ' + ($('#return_time').val() || '') + '</p>' +
                        '</div>' +
                    '</div>' +
                    '<hr>' +
                    '<div class="row">' +
                        '<div class="col-md-6">' +
                            '<h6>Vehicle</h6>' +
                            '<p><strong>Type:</strong> ' + (vehicleType || 'N/A') + '</p>' +
                            '<p><strong>Special Request:</strong> ' + ($('#special_request').val() || 'None') + '</p>' +
                        '</div>' +
                        '<div class="col-md-6">' +
                            '<h6>Signatories</h6>' +
                            '<p><strong>Requested By:</strong> ' + $('#requested_by_name').val() + ' (' + $('#requested_by_position').val() + ')</p>' +
                            '<p><strong>Concurred By:</strong> ' + ($('#concurred_by_name').val() || 'N/A') + ' (' + ($('#concurred_by_position').val() || 'N/A') + ')</p>' +
                            '<p><strong>Division Chief:</strong> ' + ($('#division_chief').val() || 'N/A') + '</p>' +
                        '</div>' +
                    '</div>';
                $('#vehicleSummary').html(summaryHTML);
            }

            $('#vehicleRequestForm').on('submit', function (e) {
                e.preventDefault();
                $('#certify_correct').removeClass('is-invalid');
                if (!$('#certify_correct').prop('checked')) {
                    $('#certify_correct').addClass('is-invalid');
                    alert('Please certify that the information provided is correct.');
                    return;
                }

                $.ajax({
                    url: $(this).attr('action'),
                    method: 'POST',
                    data: $(this).serialize(),
                    success: function (response) {
                        if (response.success) {
                            $('#controlNoDisplay').text(response.control_no);
                            $('.form-step').removeClass('active').hide();
                            $('#vehicleSummary').html(
                                '<div class="success-message">' +
                                    '<div class="success-icon"><i class="bi bi-check-circle"></i></div>' +
                                    '<h1>Request Submitted</h1>' +
                                    '<p class="text-muted">Your vehicle request has been submitted successfully.</p>' +
                                    '<p class="mb-0"><strong>Control No.:</strong> ' + response.control_no + '</p>' +
                                '</div>'
                            );
                            $('.step-indicators').hide();
                            $('#formProgress').css('width', '100%');
                        } else {
                            alert('Error: ' + (response.message || 'Unable to submit the vehicle request.'));
                        }
                    },
                    error: function () {
                        alert('An error occurred while submitting your request. Please try again.');
                    }
                });
            });
        });
    }

    initVehicleRequestForm();
}());
</script>