$(function () {
    $('.attendance-table').DataTable({
        pageLength: 25,
        lengthMenu: [5, 10, 25, 50, 100],
        ordering: true,
        searching: true,
        responsive: true,
        autoWidth: false
    });

    if (typeof toastr !== 'undefined') {
        toastr.options = {
            closeButton: true,
            progressBar: true,
            positionClass: "toast-bottom-right",
            preventDuplicates: true,
            timeOut: 3000
        };
    }

    $('a[data-toggle="tab"]').on('shown.bs.tab', function () {
        $($.fn.dataTable.tables(true))
            .DataTable()
            .columns.adjust()
            .responsive.recalc();
    });

    //TAB
    var savedTab = localStorage.getItem('activeAttendanceTab');

    if (savedTab) {
        $('#attendanceTabs a[href="' + savedTab + '"]').tab('show');

        // also update hidden date
        var date = $(savedTab).data('date');
        $('#selected_date').val(date);
    }

    // SET DEFAULT DATE (first active tab)
    var firstDate = $('#attendanceTabs .nav-link.active').data('date');
    $('#selected_date').val(firstDate);

    $('#attendanceTabs .nav-link').on('click', function () {
        var selectedDate = $(this).data('date');
        var tabId = $(this).attr('href');

        $('#selected_date').val(selectedDate);

        // SAVE TO LOCALSTORAGE
        localStorage.setItem('activeAttendanceTab', tabId);
    });
    //TAB CLOSE

    // GENERATE PDF BUTTON
    $('#generatePDF').click(function (e) {

        e.preventDefault();

        var training_id = btoa($('#training_id').val());
        var selected_date = btoa($('#selected_date').val());

        var url = "generateAttendancePDF.php?training_id=" + training_id + "&date=" + selected_date;

        window.open(url, "_blank");

    });
    // GENERATE PDF BUTTON
    $('#generatePDFDays').click(function (e) {

        e.preventDefault();

        var training_id = btoa($('#training_id').val());
        var selected_date = btoa($('#selected_date').val());

        var url = "generateAttendancePDFActivity.php?training_id=" + training_id + "&date=" + selected_date;

        window.open(url, "_blank");

    });

    // VIEW ATTENDANCE SUMMARY BUTTON
    $('#viewAttendanceSum').click(function (e) {

        e.preventDefault();

        var training_id = btoa($('#training_id').val());
        var selected_date = btoa($('#selected_date').val())
        var url = "viewActualAttendance.php?training_id=" + training_id + "&date=" + selected_date;

        window.open(url, "_blank");

    });
    // VIEW ATTENDANCE SUMMARY CLOSE BUTTON

    var titleText = $('#titleText').val();
    var venueText = $('#venueText').val();

    $('#attendanceSummaryTable').DataTable({
        pageLength: 50,
        lengthMenu: [5, 10, 25, 50, 100],
        ordering: true,
        searching: true,
        responsive: true,
        autoWidth: false,

        dom: 'Bfrtip',

        buttons: [
            {
                extend: 'excelHtml5',
                text: '<i class="fa fa-file-excel"></i> Excel',
                className: 'btn btn-sm mr-2',
                title: "TRAINING ATTENDANCE SUMMARY",

                messageTop: function () {
                    return `
                    Training Name: ${titleText}
                    Venue: ${venueText}
                `;
                }
            },
            {
                extend: 'pdfHtml5',
                text: '<i class="fa fa-file-pdf"></i> PDF',
                orientation: 'landscape',
                className: 'btn btn-sm  mr-2',
                pageSize: 'A4',
                exportOptions: {
                    format: {
                        body: function (data, row, column, node) {

                            // replace check / x symbols
                            if (data === '✔' || data === '✓') {
                                return 'PRESENT';
                            }
                            if (data === '✖' || data === '✗') {
                                return 'ABSENT';
                            }

                            return data;
                        }
                    }
                }
            },
            {
                extend: 'print',
                text: '<i class="fa fa-print"></i> Print',
                className: 'btn btn-primary btn-sm me-2'
            }
        ]
    });

    var urlView = $('#url_view').val();

    if (urlView === "Attendance") {
        var csrfToken = $('#csrf_token').val();
        $('#qrInput').autocomplete({
            source: function (request, response) {
                $.ajax({
                    url: SITE_URL + '/modules/trainings/modules/action.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        searchEmployeeName: true,
                        search: request.term,
                        csrf_token: csrfToken
                    },
                    success: function (data) {
                        response(data);
                    }
                });
            },
            minLength: 3,
            select: function (event, ui) {
                $('#qrInput').val(String(ui.item.employee_id));
                return false;
            }
        });
    }
});

function generateAttendancePDF(event) {
    event.preventDefault(); // stops page reload
    const activityId = 14; // or dynamic value
    const date = new Date().toISOString().split('T')[0];
    const url = `samplePDF.php?activity_id=${activityId}&date=${date}`;
    window.open(url, '_blank');
}

function showDeleteAttendance(trainingID) {
    $('#AttendanceT_id_hidden').val(trainingID);
    $('#deleteAttendanceModal').modal('show');
}

function deleteAttendanceTraining() {
    var attendanceT = $('#AttendanceT_id_hidden').val();
    var csrfToken = $('#csrf_token').val();
    $.ajax({
        url: SITE_URL + '/modules/trainings/modules/action.php',
        type: 'POST',
        data: {
            deleteEmployeeAttendance: true,
            id: attendanceT,
            csrf_token: csrfToken
        },
        success: function (response) {
            // Remove row smoothly
            $('#employeeAttendanceID' + attendanceT).fadeOut(300, function () {
                $(this).remove();
            });
            $('#deleteAttendanceModal').modal('hide');
            $('#AttendanceT_id_hidden').val('');

            $('#modalName').text('Employee Removed Successfully');
            $('#modalQR').text('The employee has been removed from the attendance list. This action has been completed successfully.');

            $('#attendanceModal').modal('show');
        },
        error: function () {
            alert('Error: Could not connect to the server.');
        }
    });
}

//activity add function
function saveAttendance(employee_id, status) {
    var activeDate = $('#attendanceTabs .nav-link.active').data('date');
    var trainingId = $('#training_id').val();
    var csrfToken = $('#csrf_token').val();

    $.ajax({
        url: SITE_URL + '/modules/trainings/modules/action.php',
        type: 'POST',
        dataType: 'json',
        data: {
            saveTrainingAttendance: true,
            training_id: trainingId,
            employee_id: employee_id,
            date_in: activeDate,
            status: status,
            csrf_token: csrfToken,
        },
        success: function (res) {

            $('#modalName').text('');
            $('#modalQR').text('');

            if (res.status === "success") {

                $('#qrInput').val('').focus();

                $('#modalName').text(res.employee_name);
                $('#modalQR').text('Successfully Added to attendance');

                $('#attendanceModal').modal('show');

                $('#attendanceModal').on('hidden.bs.modal', function () {
                    location.reload();
                });
            } else {
                $('#modalName').text("Oops!");
                $('#modalQR').text(res.message || 'Unable to record attendance.');

                $('#attendanceModal').modal('show');
            }
        },

        error: function (xhr) {
            $('#modalName').text("Sorry! Unable to Record Attendance");
            $('#modalQR').text(xhr.responseText);
            $('#attendanceModal').modal('show');
        }
    });
}

//When Using Barcode Scanner
$('#qrInput').keypress(function (e) {
    var status = 1;// status is 1 as actual attendance
    if (e.which == 13) { // Enter key
        e.preventDefault();
        saveAttendance($(this).val().trim(), status);
    }
});

function showAttendanceModal(type, title, name, qr, message = "") {

    $('#modal-title').text(title);
    $('#modalName').text(name);
    $('#modalQR').text(qr);

    if (message !== "") {
        $('#modalMessage').text(message).show();
    } else {
        $('#modalMessage').hide();
    }

    $('#attendanceModal')
        .removeClass('modal-success modal-error modal-warning')
        .addClass('modal-' + type)
        .modal('show');
}

$('#addAttendanceBtn').click(function () {

    $('#processParticipantBtn').remove();
    var status = 1;// status is 1 as actual attendance
    var qrCode = $('#qrInput').val().trim();

    if (qrCode === '') {
        $('#qrInput').focus();

        $('#modalName').text("Oops! Please scan the barcode or search employee name.");

        $('#attendanceModal').modal('show');
        return; // stop execution
    }

    saveAttendance(qrCode, status);
});

$('#showAddModalInfo').click(function () {
    var qrCode = $('#qrInput').val().trim();

    if (qrCode === '') {

        $('#qrInput').focus();

        $('#modalName').text('No Employee Selected');

        $('#modalQR').html(`
            Please search and select an employee before proceeding.
        `);

        $('#attendanceModal').modal('show');
        return;
    }

    $('#modalName').text('Include Participant?');

    $('#modalQR').html(`
        This employee will be <strong>included in the official participant list</strong> and will appear in the
        <strong>printed attendance sheet</strong>.<br><br>

        <span class="text-danger">
            <i class="fas fa-exclamation-triangle"></i>
            This employee <strong>will not receive a Certificate of Appearance (COA)</strong> or
            <strong>Certificate of Participation (COP)</strong> unless marked as <strong>Attended</strong>.
        </span><br><br>

        Do you want to continue?
    `);

    // Remove existing button if already appended
    $('#processParticipantBtn').remove();

    // Append confirmation button
    $('.modal-footer').prepend(`
        <button id="processParticipantBtn" class="btn btn-warning" onclick="processParticipantBtn()">
            <i class="fas fa-user-plus"></i> Include Participant
        </button>
    `);

    $('#attendanceModal').modal('show');
});

function processParticipantBtn() {
    var qrCode = $('#qrInput').val().trim();
    var status = 0;// status is partial attendance

    saveAttendance(qrCode, status);
}

function sendBulkEmail() {
    let training_id = $("#training_id").val();

    // Validate Training ID
    if (!training_id) {
        alert("Training ID not found.");
        return;
    }

    // Prevent multiple clicks
    $(".btnSendEmail").prop("disabled", true);

    $.post(
        "queue_emails.php",
        {
            training_id: training_id
        },
        function (res) {
            try {
                let data = JSON.parse(res);
                if (data.status === "success") {
                    // Open progress window
                    window.open(
                        "progressemail.php?training_id=" + training_id,
                        "_blank"
                    );

                    // Start email worker (for XAMPP development)
                    startWorker(training_id);
                } else {
                    alert(data.message);
                    // Re-enable button if queueing failed
                    $(".btnSendEmail").prop("disabled", false);
                }
            } catch (e) {
                console.log("Invalid JSON Response:");
                console.log(res);
                alert("Server returned an invalid response.");
                // Re-enable button on error
                $(".btnSendEmail").prop("disabled", false);
            }
        }
    ).fail(function () {
        alert("Unable to connect to server.");
        // Re-enable button if AJAX fails
        $(".btnSendEmail").prop("disabled", false);
    });
}

function sendEmailSubmit(btn) {

    btn = $(btn);

    let attendanceId = btn.data("attendance-id");
    let employeeID = btn.data("employee-id");
    let email = btn.data("email");
    let trainingID = btn.data("training-id");

    // Disable ALL email buttons
    $('.btn-send-email').prop("disabled", true);

    btn.text("SENDING...");

    $.ajax({
        url: SITE_URL + '/modules/trainings/modules/sendEmail.php',
        type: "POST",
        dataType: "json",
        data: {
            attendance_id: attendanceId,
            email: email,
            trainingID: trainingID,
            employeeID: employeeID
        },

        success: function (response) {
            if (response.status === "success") {

                btn.text("DONE")
                    .removeClass("btn-success")
                    .addClass("btn-secondary");

                toastr.success("Email Successfully Sent.");

            } else {

                btn.prop("disabled", false)
                    .text("SEND");

                toastr.error(response.message);

            }

            // Enable all except completed buttons
            $('.btn-send-email').not('.btn-secondary').prop("disabled", false);

        },

        error: function () {

            btn.prop("disabled", false)
                .text("SEND");

            $('.btn-send-email').not('.btn-secondary').prop("disabled", false);

            toastr.error("Server Error.");

        }

    });

}