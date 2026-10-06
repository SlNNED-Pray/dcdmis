<?php
// bsa/dashboard.php
require_once(__DIR__ . '/includes/dtcsc-optional.php');

$dashboardDefaultDate = date('Y-m-d');
try {
    $dtcDefaultStmt = Database::getInstance()->getConnection()->query("
        SELECT DATE_FORMAT(MIN(start_date), '%Y-%m-01')
        FROM bookings
        WHERE status IN ('approved', 'pending')
        GROUP BY DATE_FORMAT(start_date, '%Y-%m')
        ORDER BY COUNT(*) DESC, MIN(start_date) ASC
        LIMIT 1
    ");
    $dashboardDefaultDate = $dtcDefaultStmt->fetchColumn() ?: $dashboardDefaultDate;
} catch (Throwable $e) {
    // Ignore; fall back to the current date.
}
messageAlert($showAlert, $message, $success);
?>

<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Facility Booking Calendar</h6>
            </div>
            <div class="card-body">
                <div id="calendar"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="bookingModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Select Event Option</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="bookingForm">
                    <div class="form-group">
                        <label for="bookingType">Event Option *</label>
                        <select class="form-control" id="bookingType" required>
                            <option value="">-- Select Option --</option>
                            <option value="DTC Booking">DTC Booking</option>
                            <option value="Vehicle Book">Vehicle Book</option>
                            <option value="JOB Order Request">JOB Order Request</option>
                        </select>
                    </div>
                    <div id="bookingError" class="text-danger small mb-2" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="bookingSaveBtn">Ok</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="dtcBookingModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Facility Booking</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <div id="dtcLoading" class="text-center py-5">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Loading booking form...
                </div>
                <iframe id="dtcBookingFrame" title="DTC Booking Form" style="display:none;width:100%;min-height:70vh;border:0;" src="about:blank"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="vehicleRequestModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Vehicle Request</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <div id="vehicleLoading" class="text-center py-5">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Loading vehicle request form...
                </div>
                <iframe id="vehicleRequestFrame" title="Vehicle Request Form" style="display:none;width:100%;min-height:70vh;border:0;" src="about:blank"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="eventDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="eventDetailTitle">Booking Details</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center mb-3">
                    <span class="badge badge-pill" id="eventDetailKind">&nbsp;</span>
                    <span class="badge ml-2" id="eventDetailStatus">&nbsp;</span>
                </div>
                <dl class="row mb-0" id="eventDetailList"></dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <a class="btn btn-primary" id="eventDetailLink" href="#">Open Full Record</a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="jobOrderModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Job Order/Request Form</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <div id="jobOrderLoading" class="text-center py-5">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Loading job order form...
                </div>
                <iframe id="jobOrderFrame" title="Job Order/Request Form" style="display:none;width:100%;min-height:70vh;border:0;" src="about:blank"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
.fc-button.fc-button-primary {
    background-color: #4e73df;
    border-color: #4e73df;
}
.fc-button.fc-button-primary:hover {
    background-color: #4e97df !important;
    border-color: #4e97df !important;
}
.fc-button.fc-button-primary:focus,
.fc-button.fc-button-primary:active,
.fc-button.fc-button-primary.fc-button-active {
    background-color: #4e73df !important;
    border-color: #4e73df !important;
}
.fc-button.fc-button-primary:not(:disabled):active,
.fc-button.fc-button-primary:not(:disabled).fc-button-active {
    background-color: #4e73df !important;
    border-color: #4e73df !important;
}
/* Scheduled events open a details popup, so show a pointer on hover. */
.fc-event.fc-clickable { cursor: pointer; }
</style>

<link rel="stylesheet" href="<?= uri() ?>/bsa/fullcalendar/packages/core/main.css?v=<?= VERSION ?>">
<link rel="stylesheet" href="<?= uri() ?>/bsa/fullcalendar/packages/daygrid/main.css?v=<?= VERSION ?>">
<link rel="stylesheet" href="<?= uri() ?>/bsa/fullcalendar/packages/timegrid/main.css?v=<?= VERSION ?>">
<script src="<?= uri() ?>/bsa/fullcalendar/packages/core/main.js?v=<?= VERSION ?>"></script>
<script src="<?= uri() ?>/bsa/fullcalendar/packages/interaction/main.js?v=<?= VERSION ?>"></script>
<script src="<?= uri() ?>/bsa/fullcalendar/packages/daygrid/main.js?v=<?= VERSION ?>"></script>
<script src="<?= uri() ?>/bsa/fullcalendar/packages/timegrid/main.js?v=<?= VERSION ?>"></script>
<script>
(function () {
    var calendarEl = document.getElementById('calendar');
    if (!calendarEl) {
        return;
    }

    calendarEl.innerHTML = '<p class="text-secondary mb-0"><i class="fas fa-spinner fa-spin mr-2"></i>Loading calendar...</p>';

    if (typeof FullCalendar === 'undefined') {
        calendarEl.innerHTML = '<p class="text-danger mb-0">Calendar failed to initialize: FullCalendar core script did not load.</p>';
        return;
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof FullCalendarDayGrid === 'undefined' || typeof FullCalendarTimeGrid === 'undefined') {
            calendarEl.innerHTML = '<p class="text-danger mb-0">Calendar failed to initialize: FullCalendar day grid / time grid plugin did not load.</p>';
            return;
        }

        function bookingError(msg) {
            var el = document.getElementById('bookingError');
            el.textContent = msg;
            el.style.display = msg ? 'block' : 'none';
            return msg;
        }

        var pendingSelInfo = null;

        function parseDatePart(str) {
            if (!str) {
                return '';
            }
            var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(str);
            return m ? m[1] + '-' + m[2] + '-' + m[3] : '';
        }

        function buildDtcBookingUrl() {
            var src = '/bsa/dtc-booking-embed.php';
            if (!pendingSelInfo) {
                return src;
            }

            var startRaw = pendingSelInfo.startStr || '';
            var endRaw = pendingSelInfo.endStr || '';
            var allDaySel = /T00:00:00/.test(startRaw) && (!endRaw || /T00:00:00/.test(endRaw));
            var sDate = parseDatePart(startRaw);
            var eDate = parseDatePart(endRaw);
            var sTime = /T(\d{2}:\d{2})/.exec(startRaw);
            var eTime = /T(\d{2}:\d{2})/.exec(endRaw);

            var params = [];
            if (sDate) {
                params.push('start_date=' + encodeURIComponent(sDate));
            }
            if (eDate) {
                params.push('end_date=' + encodeURIComponent(eDate));
            }
            if (!allDaySel) {
                if (sTime) {
                    params.push('start_time=' + encodeURIComponent(sTime[1]));
                }
                if (eTime) {
                    params.push('end_time=' + encodeURIComponent(eTime[1]));
                }
            } else {
                params.push('start_time=08:00');
                params.push('end_time=17:00');
            }
            return src + (params.length ? '?' + params.join('&') : '');
        }

        function openDtcBookingModal() {
            var iframe = document.getElementById('dtcBookingFrame');
            var loading = document.getElementById('dtcLoading');

            loading.style.display = 'block';
            iframe.style.display = 'none';

            iframe.onload = function () {
                loading.style.display = 'none';
                iframe.style.display = 'block';
            };

            iframe.src = buildDtcBookingUrl();
            $('#dtcBookingModal').modal('show');
        }

        function buildVehicleRequestUrl() {
            var src = '/bsa/vehicle-request-embed.php';
            if (!pendingSelInfo) {
                return src;
            }

            var startRaw = pendingSelInfo.startStr || '';
            var endRaw = pendingSelInfo.endStr || '';
            var allDaySel = /T00:00:00/.test(startRaw) && (!endRaw || /T00:00:00/.test(endRaw));
            var sDate = parseDatePart(startRaw);
            var eDate = parseDatePart(endRaw);
            var sTime = /T(\d{2}:\d{2})/.exec(startRaw);
            var eTime = /T(\d{2}:\d{2})/.exec(endRaw);

            var params = [];
            if (sDate) {
                params.push('start_date=' + encodeURIComponent(sDate));
            }
            if (eDate) {
                params.push('end_date=' + encodeURIComponent(eDate));
            }
            if (!allDaySel) {
                if (sTime) {
                    params.push('start_time=' + encodeURIComponent(sTime[1]));
                }
                if (eTime) {
                    params.push('end_time=' + encodeURIComponent(eTime[1]));
                }
            } else {
                params.push('start_time=08:00');
                params.push('end_time=17:00');
            }
            return src + (params.length ? '?' + params.join('&') : '');
        }

        function openVehicleRequestModal() {
            var iframe = document.getElementById('vehicleRequestFrame');
            var loading = document.getElementById('vehicleLoading');

            loading.style.display = 'block';
            iframe.style.display = 'none';

            iframe.onload = function () {
                loading.style.display = 'none';
                iframe.style.display = 'block';
            };

            iframe.src = buildVehicleRequestUrl();
            $('#vehicleRequestModal').modal('show');
        }

        function buildJobOrderUrl() {
            var src = '/bsa/job-order-embed.php';
            var date = pendingSelInfo ? parseDatePart(pendingSelInfo.startStr || '') : '';
            return date ? src + '?date_request=' + encodeURIComponent(date) : src;
        }

        function openJobOrderModal() {
            var iframe = document.getElementById('jobOrderFrame');
            var loading = document.getElementById('jobOrderLoading');

            loading.style.display = 'block';
            iframe.style.display = 'none';
            iframe.onload = function () {
                loading.style.display = 'none';
                iframe.style.display = 'block';
            };
            iframe.src = buildJobOrderUrl();
            $('#jobOrderModal').modal('show');
        }

        function openBookingModal(info) {
            pendingSelInfo = info;
            document.getElementById('bookingType').value = '';
            bookingError('');
            document.getElementById('bookingSaveBtn').disabled = false;
            $('#bookingModal').modal('show');
        }

        /* Approved schedules already carry their detail rows in extendedProps, so the
           popup renders straight from the event rather than re-querying per click. */
        var EVENT_KIND_LABELS = {
            booking: 'Facility Booking',
            facility: 'Facility Booking',
            dtc: 'DTC-SC Booking',
            vehicle: 'Vehicle Request',
            joborder: 'Job Order / Request'
        };

        function statusBadgeClass(status) {
            var s = String(status || '').toLowerCase();
            if (s === 'approved' || s === 'available') {
                return 'badge-success';
            }
            if (s === 'pending') {
                return 'badge-warning';
            }
            if (s === 'rejected' || s === 'cancelled') {
                return 'badge-danger';
            }
            return 'badge-secondary';
        }

        /* Drives the popup from a synthetic event so each kind can be checked without
           depending on which month the feed happens to have rows for. */
        function renderEventDetail(props, title, url) {
            document.getElementById('eventDetailTitle').textContent = (props && props.heading) || title || 'Booking Details';

            var kind = document.getElementById('eventDetailKind');
            kind.textContent = EVENT_KIND_LABELS[(props && props.kind) || 'booking'] || 'Booking';
            kind.className = 'badge badge-pill badge-info';

            var statusEl = document.getElementById('eventDetailStatus');
            var status = (props && props.status) || '';
            statusEl.textContent = status
                ? status.charAt(0).toUpperCase() + status.slice(1)
                : 'Scheduled';
            statusEl.className = 'badge ' + (status ? statusBadgeClass(status) : 'badge-secondary');

            var details = (props && props.details) || {};
            var list = document.getElementById('eventDetailList');
            list.innerHTML = '';
            var keys = Object.keys(details);
            if (!keys.length) {
                var empty = document.createElement('div');
                empty.className = 'text-muted';
                empty.textContent = 'No further details available for this schedule.';
                list.appendChild(empty);
            }
            keys.forEach(function (key) {
                var dt = document.createElement('dt');
                dt.className = 'col-sm-4 text-sm-right font-weight-bold';
                dt.textContent = key;
                var dd = document.createElement('dd');
                dd.className = 'col-sm-8';
                dd.textContent = details[key];
                list.appendChild(dt);
                list.appendChild(dd);
            });

            var link = document.getElementById('eventDetailLink');
            if (url) {
                link.href = url;
                link.style.display = '';
            } else {
                link.style.display = 'none';
            }
        }

        function openEventDetailModal(info) {
            var ev = info.event;
            renderEventDetail(ev.extendedProps || {}, ev.title, ev.url);
            $('#eventDetailModal').modal('show');
        }

        document.getElementById('bookingType').addEventListener('change', function () {
            if (this.value === 'DTC Booking') {
                openDtcBookingModal();
            } else if (this.value === 'Vehicle Book') {
                openVehicleRequestModal();
            } else if (this.value === 'JOB Order Request') {
                openJobOrderModal();
            }
        });

        function wireDtcBooking() {
            $('#dtcBookingModal').on('hidden.bs.modal', function () {
                document.getElementById('bookingType').value = '';
                document.getElementById('bookingSaveBtn').disabled = false;
            });
            $('#vehicleRequestModal').on('hidden.bs.modal', function () {
                document.getElementById('bookingType').value = '';
                document.getElementById('bookingSaveBtn').disabled = false;
            });
            $('#jobOrderModal').on('hidden.bs.modal', function () {
                document.getElementById('bookingType').value = '';
                document.getElementById('bookingSaveBtn').disabled = false;
            });
        }

        function wireBookingSave(calendar) {
            document.getElementById('bookingSaveBtn').addEventListener('click', function () {
                var btn = this;
                var type = document.getElementById('bookingType').value;

                if (!type) {
                    bookingError('Please select an event option.');
                    return;
                }
                if (!pendingSelInfo) {
                    bookingError('No date range selected.');
                    return;
                }

                if (type === 'DTC Booking') {
                    openDtcBookingModal();
                    return;
                }

                if (type === 'Vehicle Book') {
                    openVehicleRequestModal();
                    return;
                }

                if (type === 'JOB Order Request') {
                    openJobOrderModal();
                    return;
                }

                btn.disabled = true;
                bookingError('');

                var allDay = /T00:00:00/.test(pendingSelInfo.startStr || '') &&
                    (!pendingSelInfo.endStr || /T00:00:00/.test(pendingSelInfo.endStr || ''));

                var fd = new FormData();
                fd.append('type', type);
                fd.append('start', pendingSelInfo.startStr);
                fd.append('end', pendingSelInfo.endStr || '');
                fd.append('all_day', allDay ? 1 : 0);

                fetch('<?= uri() ?>/bsa/bookings-save.php', { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        btn.disabled = false;
                        if (d && d.success) {
                            $('#bookingModal').modal('hide');
                            calendar.refetchEvents();
                        } else {
                            bookingError((d && d.message) || 'Failed to save the booking.');
                        }
                    })
                    .catch(function () {
                        btn.disabled = false;
                        bookingError('Network error while saving.');
                    });
            });
        }

        try {
            var calendar = new FullCalendar.Calendar(calendarEl, {
plugins: ['dayGrid', 'timeGrid', 'interaction'],
                defaultView: 'dayGridMonth',
                defaultDate: '<?= $dashboardDefaultDate ?>',
                height: 640,
                header: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                buttonText: {
                    prev: '<',
                    next: '>',
                    today: 'Today',
                    dayGridMonth: 'Month',
                    timeGridWeek: 'Week',
                    timeGridDay: 'Day'
                },
                editable: false,
                selectable: true,
                eventColor: '#4e73df',
                events: {
                    url: '<?= uri() ?>/bsa/bookings.php',
                    method: 'GET'
                },
                eventDidMount: function (info) {
                    // Job order events carry a multi-line tooltip for the hover card.
                    if (info.event.extendedProps && info.event.extendedProps.tooltip) {
                        info.el.setAttribute('title', info.event.extendedProps.tooltip);
                    }
                    // Signals that an approved schedule opens a details popup.
                    info.el.classList.add('fc-clickable');
                },
                eventClick: function (info) {
                    // Show the popup instead of letting FullCalendar follow event.url
                    // directly; the popup keeps that link as "Open Full Record".
                    info.jsEvent.preventDefault();
                    openEventDetailModal(info);
                },
                select: function (info) {
                    openBookingModal(info);
                }
            });

            calendar.render();
            wireBookingSave(calendar);
            wireDtcBooking();
        } catch (err) {
            calendarEl.innerHTML = '<p class="text-danger mb-0">Calendar failed to initialize: ' +
                (err && err.message ? err.message : String(err)) + '</p>';
        }
    });
})();
</script>