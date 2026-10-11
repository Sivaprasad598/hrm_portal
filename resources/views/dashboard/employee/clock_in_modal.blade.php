<div class="modal-header">
    <h5 class="modal-title" id="modelHeading">@lang('modules.attendance.clock_in')</h5>
    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
            aria-hidden="true">×</span></button>
</div>

@if ($cannotLogin == false)
<x-form id="clockInForm">
    <div class="modal-body">
            <div class="row justify-content-between">
                <div class="col" id="task_div">
                    <h4 class="mb-4 d-flex justify-content-between"><span><i class="fa fa-clock"></i> {{ now()->timezone(company()->timezone)->translatedFormat(company()->date_format . ' ' . company()->time_format) }}</span>
                        <span class="badge badge-info f-14"
                              style="background-color: {{ $shiftAssigned->color }}">{{ $shiftAssigned->shift_name }}</span>
                    </h4>
                    <div class="row">
                        <div class="col-md-6">
                            <x-forms.select fieldId="location" :fieldLabel="__('app.location')" fieldName="location"
                                            search="true">
                                @foreach ($location as $locations)
                                    <option @if ($locations->id == $user->employeeDetail->company_address_id) selected
                                            @endif value="{{ $locations->id }}">
                                        {{ $locations->location }}</option>
                                @endforeach
                            </x-forms.select>
                        </div>
                        <div class="col-md-6">
                            <x-forms.select fieldId="work_from_type" :fieldLabel="__('modules.attendance.working_from')"
                                            fieldName="work_from_type" fieldRequired="true"
                                            search="true">
                                <option value="office">@lang('modules.attendance.office')</option>
                                <option value="home">@lang('modules.attendance.home')</option>
                                <option value="other">@lang('modules.attendance.other')</option>
                            </x-forms.select>
                        </div>
                        <div class="col-md-12" id="other_place" style="display:none">
                            <x-forms.text fieldId="working_from" :fieldLabel="__('modules.attendance.otherPlace')"
                                          fieldName="working_from" fieldRequired="true">
                            </x-forms.text>
                        </div>
                    </div>

                    @include('dashboard.employee.capture_panel')
                </div>
            </div>
    </div>
    <div class="modal-footer">
        <x-forms.button-cancel data-dismiss="modal" class="border-0 mr-3">@lang('app.cancel')</x-forms.button-cancel>
        <x-forms.button-primary id="save-clock-in">@lang('modules.attendance.clock_in')</x-forms.button-primary>
    </div>
</x-form>
@else
    <div class="modal-body">
        <x-alert type="danger">@lang('messages.clockInNotAllowed')</x-alert>
    </div>
@endif

@if ($attendanceSettings->radius_check == 'yes' || $attendanceSettings->save_current_location)
    <script>
       setCurrentLocation();
    </script>
@endif

<script>
    $('.select-picker').selectpicker();

    $(function () {
        $('#work_from_type').change(function () {

            ($(this).val() == 'other') ? $('#other_place').show() : $('#other_place').hide();

        });
    });

    $('body').off('click.clockInModal', '#save-clock-in').on('click.clockInModal', '#save-clock-in', function (e) {
        e.preventDefault();
        const $btn = $(this);
        if ($btn.data('clock-in-submitting')) {
            return;
        }
        $btn.data('clock-in-submitting', true);

        const workingFrom = $('#working_from').val();
        const location = $('#location').val();
        const work_from_type = $('#work_from_type').val();

        // Prefer what this modal captured; fall back to anything the page behind
        // it had already found.
        const capturedLat = document.getElementById('capture-latitude');
        const capturedLng = document.getElementById('capture-longitude');
        const pageLat = document.getElementById('current-latitude');
        const pageLng = document.getElementById('current-longitude');

        const currentLatitude = (capturedLat && capturedLat.value) ? capturedLat.value : (pageLat ? pageLat.value : '');
        const currentLongitude = (capturedLng && capturedLng.value) ? capturedLng.value : (pageLng ? pageLng.value : '');

        const photoField = document.getElementById('capture-photo-data');
        const clockInPhoto = photoField ? photoField.value : '';

        // Say so here rather than letting the server refuse it, so the camera
        // is still open and the person can simply take the picture.
        if (@json($captureRules['photo']) === 'mandatory' && !clockInPhoto) {
            $btn.data('clock-in-submitting', false);
            Swal.fire({ icon: 'info', title: 'A photograph is needed', text: 'Take a photo before clocking in.' });
            return;
        }

        if (@json($captureRules['location']) === 'mandatory' && !currentLatitude) {
            $btn.data('clock-in-submitting', false);
            Swal.fire({ icon: 'info', title: 'Your location is needed',
                        text: 'Allow location access in the browser, then try again.' });
            return;
        }

        const token = "{{ csrf_token() }}";

        $.easyAjax({
            url: "{{ route('attendances.store_clock_in') }}",
            type: "POST",
            buttonSelector: "#save-clock-in",
            disableButton: true,
            blockUI: true,
            container: '#clockInForm',
            data: {
                working_from: workingFrom,
                location: location,
                work_from_type: work_from_type,
                currentLatitude: currentLatitude,
                currentLongitude: currentLongitude,
                clock_in_photo: clockInPhoto,
                _token: token
            },
            success: function (response) {
                if (response.status === 'success') {
                    window.location.reload();
                }
            },
            error: function () {
                $btn.data('clock-in-submitting', false);
            }
        })
    })


</script>
