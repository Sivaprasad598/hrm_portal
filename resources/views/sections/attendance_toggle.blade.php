{{--
    Clock in / clock out from the topbar, on every page in the account area.

    Until now both clock modals only worked on the dashboard: the clock-in
    modal calls setCurrentLocation() and the clock-out modal calls clockOut(),
    and both of those — along with the #current-latitude / #current-longitude
    inputs they read — were defined inside dashboard/employee/index.blade.php.
    Opening either modal from anywhere else threw a ReferenceError before it
    did anything. Both now live here instead, which is loaded on every page, so
    the dashboard's own copies have been removed rather than duplicated.

    Whether to show a button at all, and which one, comes from ClockState.
--}}

@php
    $clockState = (new \App\Services\Attendance\ClockState())->current();
@endphp

@if ($clockState['show'])
    <li data-toggle="tooltip" data-placement="top"
        title="{{ $clockState['clockedIn'] ? __('modules.attendance.clock_out') : __('modules.attendance.clock_in') }}">
        <div class="d-flex align-items-center">
            <a href="javascript:;" class="d-block header-icon-box" id="topbar-clock-toggle"
               data-clocked-in="{{ $clockState['clockedIn'] ? 'yes' : 'no' }}"
               data-attendance-id="{{ $clockState['attendanceId'] }}">
                @if ($clockState['clockedIn'])
                    {{-- Red, because it ends the session: the same colour the
                         dashboard gives its own Clock Out button. --}}
                    <i class="fa fa-sign-out-alt f-16 text-red"></i>
                @else
                    <i class="fa fa-sign-in-alt f-16 text-dark-grey"></i>
                @endif
            </a>
        </div>
    </li>
@endif

{{-- Needed by both modals whether or not a button is drawn: the clock-in modal
     on the dashboard reads them too. --}}
<input type="hidden" id="current-latitude" name="current_latitude">
<input type="hidden" id="current-longitude" name="current_longitude">

<script>
    // Asked for by clock_in_modal.blade.php. Kept as a no-argument global with
    // the same name and behaviour it had on the dashboard.
    function setCurrentLocation() {
        const currentLatitude = document.getElementById("current-latitude");
        const currentLongitude = document.getElementById("current-longitude");

        if (!currentLatitude || !currentLongitude || !navigator.geolocation) {
            return;
        }

        navigator.geolocation.getCurrentPosition(function (position) {
            currentLatitude.value = position.coords.latitude;
            currentLongitude.value = position.coords.longitude;
        });
    }

    // Called by the onclick on the clock-out button in show_clocked_hours.
    // That button carries the attendance id and the capture rules, so this does
    // not need anything the dashboard page used to supply.
    function clockOut() {
        const button = document.getElementById('clock-out-submit');

        if (!button) {
            return;
        }

        const photoRule = button.getAttribute('data-photo-rule') || 'off';
        const locationRule = button.getAttribute('data-location-rule') || 'off';

        // Prefer what the modal captured; fall back to whatever the page had
        // already found.
        const capturedLat = document.getElementById('capture-latitude');
        const capturedLng = document.getElementById('capture-longitude');
        const pageLat = document.getElementById('current-latitude');
        const pageLng = document.getElementById('current-longitude');

        const currentLatitude = (capturedLat && capturedLat.value) ? capturedLat.value : (pageLat ? pageLat.value : '');
        const currentLongitude = (capturedLng && capturedLng.value) ? capturedLng.value : (pageLng ? pageLng.value : '');

        const photoField = document.getElementById('capture-photo-data');
        const clockOutPhoto = photoField ? photoField.value : '';

        // Said here rather than letting the server refuse it, so the camera is
        // still open and the person can simply take the picture.
        if (photoRule === 'mandatory' && !clockOutPhoto) {
            Swal.fire({ icon: 'info', title: 'A photograph is needed', text: 'Take a photo before clocking out.' });
            return;
        }

        if (locationRule === 'mandatory' && !currentLatitude) {
            Swal.fire({ icon: 'info', title: 'Your location is needed',
                        text: 'Allow location access in the browser, then try again.' });
            return;
        }

        const locationField = document.getElementById('clock_out_location');
        const workFromTypeField = document.getElementById('clock_out_work_from_type');
        const workFromField = document.getElementById('clock_out_working_from');

        $.easyAjax({
            // POST, not the older GET route: a photograph is far too large to
            // travel in a query string.
            url: "{{ route('attendances.store_clock_out') }}",
            type: "POST",
            buttonSelector: "#clock-out-submit",
            disableButton: true,
            blockUI: true,
            data: {
                currentLatitude: currentLatitude,
                currentLongitude: currentLongitude,
                clockOutLocation: locationField ? locationField.value : '',
                clockOutWorkFromType: workFromTypeField ? workFromTypeField.value : '',
                clockOutWorkFrom: workFromField ? workFromField.value : '',
                clock_out_photo: clockOutPhoto,
                _token: "{{ csrf_token() }}",
                id: button.getAttribute('data-attendance-id')
            },
            success: function (response) {
                if (response.status === 'success') {
                    window.location.reload();
                }
            }
        });
    }

    $(function () {
        // Namespaced and rebound defensively: the topbar is a single include,
        // but a page that reloads part of itself could evaluate this twice.
        $('body').off('click.topbarClock', '#topbar-clock-toggle')
            .on('click.topbarClock', '#topbar-clock-toggle', function () {
                const clockedIn = $(this).data('clocked-in') === 'yes';
                const attendanceId = $(this).data('attendance-id');

                const url = clockedIn
                    ? "{{ route('attendances.show_clocked_hours') }}?aid=" + attendanceId
                    // Cache-buster: a plain GET of a changing fragment is
                    // cacheable, and a stale modal is a camera that never opens.
                    : "{{ route('attendances.clock_in_modal') }}?v=" + Date.now();

                $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
                $.ajaxModal(MODAL_LG, url);
            });
    });
</script>
