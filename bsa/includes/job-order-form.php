<?php
/**
 * Job Order/Request Form partial (multi-step).
 * Expects: $csrfToken, $prefill.
 */
?>
<div class="form-head">
    <h2>JOB ORDER/REQUEST FORM</h2>
    <p>Republic of the Philippines &middot; Department of Education &middot; Region IX &middot; Schools Division of Dipolog City</p>
    <div class="control-no"><span class="icon-file-text me-1"></span>Order No.: <span id="jobOrderNoDisplay">assigned upon submission</span></div>
</div>

<div class="booking-form-container">
    <!-- Progress Steps -->
    <div class="booking-progress mb-4">
        <div class="progress">
            <div class="progress-bar" role="progressbar" style="width: 50%" id="formProgress"></div>
        </div>
        <div class="step-indicators d-flex justify-content-between mt-2">
            <div class="step active" data-step="1">
                <span class="step-number">1</span>
                <span class="step-label">Request Details</span>
            </div>
            <div class="step" data-step="2">
                <span class="step-number">2</span>
                <span class="step-label">Review &amp; Submit</span>
            </div>
        </div>
    </div>

    <form id="jobOrderForm" method="POST" action="<?php echo uri(); ?>/bsa/api/job-order-save.php">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

        <!-- Step 1: Request Details -->
        <div class="form-step active" data-step="1">
            <h3 class="mb-4">Step 1: Request Details</h3>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="date_request" class="form-label">Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="date_request" name="date_request" value="<?php echo htmlspecialchars($prefill['date_request']); ?>" required>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="requesting_office" class="form-label">Requesting Office <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="requesting_office" name="requesting_office" maxlength="255" value="<?php echo htmlspecialchars($prefill['requesting_office']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="requesting_personnel" class="form-label">Requesting Personnel <span class="text-danger">*</span></label>
                    <div class="job-order-autocomplete">
                        <input type="text" class="form-control" id="requesting_personnel" name="requesting_personnel" autocomplete="off" required>
                        <div id="personnelSuggestions" class="job-order-suggestions"></div>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="location_of_work" class="form-label">Location of Work <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="location_of_work" name="location_of_work" maxlength="255" required>
                </div>
            </div>

            <fieldset class="mb-3">
                <legend class="form-label mb-2">Description of Work <span class="text-danger">*</span></legend>
                <div class="row">
                    <?php foreach (['Electrical', 'Carpentry', 'Airconditioning', 'Janitorial', 'Plumbing', 'ICT-related'] as $workType): ?>
                        <div class="col-md-4 col-sm-6 mb-2">
                            <div class="form-check">
                                <input class="form-check-input work-type" type="checkbox" name="work_types[]" id="work_<?php echo strtolower(str_replace('-', '_', $workType)); ?>" value="<?php echo htmlspecialchars($workType); ?>">
                                <label class="form-check-label" for="work_<?php echo strtolower(str_replace('-', '_', $workType)); ?>"><?php echo htmlspecialchars($workType); ?></label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="col-md-4 col-sm-6 mb-2">
                        <div class="form-check">
                            <input class="form-check-input work-type" type="checkbox" name="work_types[]" id="work_others" value="Others">
                            <label class="form-check-label" for="work_others">Others (describe scope of work)</label>
                        </div>
                    </div>
                </div>
                <div id="otherScopeGroup" class="mt-2" style="display:none;">
                    <label for="other_scope" class="form-label">Other scope of work <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="other_scope" name="other_scope" rows="3" maxlength="65535"></textarea>
                </div>
                <div id="workTypeError" class="text-danger small mt-2" style="display:none;">Select at least one type of work.</div>
            </fieldset>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="requestor_name" class="form-label">Requestor's Name <span class="text-danger">*</span></label>
                    <div class="job-order-autocomplete">
                        <input type="text" class="form-control" id="requestor_name" name="requestor_name" maxlength="255" autocomplete="off" required>
                        <div id="requestorSuggestions" class="job-order-suggestions"></div>
                    </div>
                </div>
            </div>

            <div class="form-navigation">
                <button type="button" class="btn btn-primary next-step">Next <i class="icon-arrow-right ms-2"></i></button>
            </div>
        </div>

        <!-- Step 2: Review & Submit -->
        <div class="form-step" data-step="2">
            <h3 class="mb-4">Step 2: Review &amp; Submit</h3>

            <div class="alert alert-info">
                <i class="icon-info-circle me-2"></i>
                Please review all information before submitting your job order request.
            </div>

            <div id="jobOrderSummary" class="booking-summary">
                <!-- Summary will be populated by JavaScript -->
            </div>

            <div class="form-check mt-4 mb-3">
                <input class="form-check-input" type="checkbox" id="certify_correct" required>
                <label class="form-check-label" for="certify_correct">
                    I certify that the information provided is correct.
                </label>
            </div>

            <div id="jobOrderMessage" class="alert" style="display:none;"></div>

            <div class="form-navigation" style="justify-content: flex-start;">
                <button type="button" class="btn btn-secondary prev-step"><i class="icon-arrow-left me-2"></i>Previous</button>
                <button type="submit" class="btn btn-success"><i class="icon-check-circle me-2"></i>Submit Job Order Request</button>
            </div>
        </div>
    </form>

    <div id="jobOrderSuccess" class="success-message" style="display:none;">
        <div class="success-icon"><i class="icon-check-circle"></i></div>
        <h1>Request Submitted</h1>
        <p class="text-muted">Your job order request has been submitted successfully.</p>
        <p class="mb-0"><strong>Order No.:</strong> <span id="jobOrderSuccessNo"></span></p>
    </div>
</div>

<style>
    .job-order-autocomplete { position: relative; }
    .job-order-suggestions { position: absolute; z-index: 10; width: 100%; background: #fff; border: 1px solid #dee2e6; border-radius: 0 0 6px 6px; box-shadow: 0 4px 12px rgba(0,0,0,.12); }
    .job-order-suggestion { padding: .55rem .75rem; cursor: pointer; }
    .job-order-suggestion:hover { background: #f1f3f5; }
</style>

<script>
(function () {
    function initJobOrderForm() {
        if (window.jQuery === undefined) {
            setTimeout(initJobOrderForm, 50);
            return;
        }
        jQuery(function ($) {
            var $form = $('#jobOrderForm');
            var totalSteps = 2;
            var currentStep = 1;

            function toggleOtherScope() {
                var selected = $('#work_others').prop('checked');
                $('#otherScopeGroup').toggle(selected);
                $('#other_scope').prop('required', selected);
            }

            $('.work-type').on('change', function () {
                toggleOtherScope();
                if ($('.work-type:checked').length) {
                    $('#workTypeError').hide();
                }
            });
            toggleOtherScope();

            function bindEmployeeAutocomplete(inputSelector, suggestionsSelector, onSelect) {
                var $input = $(inputSelector);
                var $suggestions = $(suggestionsSelector);
                var suggestTimer;

                $input.on('input', function () {
                    var query = $.trim($input.val());
                    clearTimeout(suggestTimer);
                    $suggestions.empty().hide();
                    if (query.length < 2) {
                        return;
                    }
                    suggestTimer = setTimeout(function () {
                        $.getJSON('<?php echo uri(); ?>/bsa/api/employees-suggest.php', { q: query })
                            .done(function (items) {
                                (items || []).slice(0, 8).forEach(function (item) {
                                    var $option = $('<div class="job-order-suggestion"></div>').text(item.name);
                                    if (item.office) {
                                        $option.append($('<small class="d-block text-muted"></small>').text(item.office));
                                    }
                                    $option.on('mousedown', function () {
                                        $input.val(item.name);
                                        onSelect(item);
                                        $suggestions.empty().hide();
                                    });
                                    $suggestions.append($option);
                                });
                                if ($suggestions.children().length) {
                                    $suggestions.show();
                                }
                            });
                    }, 250);
                });
            }

            bindEmployeeAutocomplete('#requesting_personnel', '#personnelSuggestions', function (item) {
                $('#requestor_name').val(item.name);
            });
            bindEmployeeAutocomplete('#requestor_name', '#requestorSuggestions', function () {});

            $(document).on('click', function (event) {
                if (!$(event.target).closest('.job-order-autocomplete').length) {
                    $('.job-order-suggestions').empty().hide();
                }
            });

            function showStep(step) {
                $('.form-step').removeClass('active').hide();
                $('.form-step[data-step="' + step + '"]').addClass('active').show();
                $('.step').removeClass('active');
                $('.step[data-step="' + step + '"]').addClass('active');
                $('#formProgress').css('width', ((step / totalSteps) * 100) + '%');
                currentStep = step;
                if (step === 2) {
                    generateSummary();
                }
            }

            function validateStep(step) {
                var stepEl = $('.form-step[data-step="' + step + '"]');
                var isValid = true;

                stepEl.find('input[required], select[required], textarea[required]').each(function () {
                    if (!$(this).val()) {
                        $(this).addClass('is-invalid');
                        isValid = false;
                    } else {
                        $(this).removeClass('is-invalid');
                    }
                });

                if (step === 1 && !$('.work-type:checked').length) {
                    $('#workTypeError').show();
                    isValid = false;
                }

                return isValid;
            }

            $('.next-step').on('click', function () {
                if (validateStep(currentStep) && currentStep < totalSteps) {
                    showStep(currentStep + 1);
                }
            });

            $('.prev-step').on('click', function () {
                if (currentStep > 1) {
                    showStep(currentStep - 1);
                }
            });

            function selectedWorkTypes() {
                var types = [];
                $('.work-type:checked').each(function () {
                    types.push($(this).val());
                });
                return types.length ? types.join(', ') : 'N/A';
            }

            function summaryRow(label, value) {
                return '<p><strong>' + label + ':</strong> ' + (value || 'N/A') + '</p>';
            }

            function generateSummary() {
                var html =
                    '<div class="row">' +
                        '<div class="col-md-6">' +
                            '<h6>Request Details</h6>' +
                            summaryRow('Date', $('#date_request').val()) +
                            summaryRow('Requesting Office', $('#requesting_office').val()) +
                            summaryRow('Requesting Personnel', $('#requesting_personnel').val()) +
                        '</div>' +
                        '<div class="col-md-6">' +
                            '<h6>Work Details</h6>' +
                            summaryRow('Location of Work', $('#location_of_work').val()) +
                            summaryRow('Description of Work', selectedWorkTypes()) +
                            summaryRow('Other Scope', $('#other_scope').val()) +
                            summaryRow("Requestor's Name", $('#requestor_name').val()) +
                        '</div>' +
                    '</div>';
                $('#jobOrderSummary').html(html);
            }

            $form.on('submit', function (event) {
                event.preventDefault();
                var $message = $('#jobOrderMessage');
                $message.hide().removeClass('alert-danger alert-success');

                if (!$('.work-type:checked').length) {
                    $('#workTypeError').show();
                    showStep(1);
                    return;
                }
                $('#workTypeError').hide();

                var $submit = $form.find('button[type="submit"]');
                $submit.prop('disabled', true);
                $.ajax({
                    url: $form.attr('action'),
                    method: 'POST',
                    data: $form.serialize(),
                    dataType: 'json'
                }).done(function (response) {
                    if (!response.success) {
                        $message.addClass('alert-danger').text(response.message || 'Unable to save the job order request.').show();
                        return;
                    }
                    $('#jobOrderNoDisplay').text(response.order_no);
                    $('#jobOrderSuccessNo').text(response.order_no);
                    $form.hide();
                    $('.booking-progress').hide();
                    $('#jobOrderSuccess').show();
                }).fail(function (xhr) {
                    var response = xhr.responseJSON || {};
                    $message.addClass('alert-danger').text(response.message || 'An error occurred while submitting the request.').show();
                }).always(function () {
                    $submit.prop('disabled', false);
                });
            });
        });
    }

    initJobOrderForm();
}());
</script>
