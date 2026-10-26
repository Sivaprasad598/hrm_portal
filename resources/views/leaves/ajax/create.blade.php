{{-- this plugin is used only in leaves create form --}}
@php
    $addLeadAgentPermission = user()->permission('manage_leave_setting');
    $approveRejectPermission = user()->permission('approve_or_reject_leaves');
@endphp

<link rel="stylesheet" href="{{ asset('vendor/css/bootstrap-datepicker3.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/css/dropzone.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/css/daterangepicker.css') }}">

<style>
    /* Calendar symbol on date fields — the date-picker popup logic already exists on
       these inputs (see the datepicker(...)/daterangepicker(...) calls below); this
       just makes it visible. */
    .date-field-icon {
        position: absolute;
        right: 12px;
        bottom: 10px;
        font-size: 14px;
        color: #8a8a8a;
        cursor: pointer;
        pointer-events: auto;
    }
</style>

<div class="row">
    <div class="col-sm-12">
        <x-form id="save-lead-data-form">
            <div class="add-client bg-white rounded">
                <h4 class="mb-0 p-20 f-21 font-weight-normal  border-bottom-grey">
                    @lang('modules.leaves.assignLeave')</h4>
                <div class="row p-20">

                    <div class="col-lg-4 col-md-6">
                        @if (isset($defaultAssign))
                            <x-forms.label class="mt-3" fieldId="" :fieldLabel="__('app.name')" fieldRequired="true">
                            </x-forms.label>
                            <input type="hidden" name="user_id" id="user_id" value="{{ $defaultAssign->id }}">
                            <input type="text" value="{{ $defaultAssign->name }}"
                                class="form-control height-35 f-15 readonly-background" readonly>
                        @else
                            <x-forms.select fieldId="user_id" :fieldLabel="__('modules.messages.chooseMember')" fieldName="user_id" search="true"
                                fieldRequired="true">
                                <option value="">--</option>
                                @foreach ($employees as $employee)
                                    <x-user-option :user="$employee" :selected="request()->has('default_assign') &&
                                        request('default_assign') == $employee->id" />
                                @endforeach
                            </x-forms.select>
                        @endif
                    </div>

                    <div class="col-lg-4 col-md-6">
                        <x-forms.label class="mt-3" fieldId="" :fieldLabel="__('modules.leaves.leaveType')" fieldRequired="true">
                        </x-forms.label>
                        <x-forms.input-group>
                            <select class="form-control select-picker" name="leave_type_id" id="leave_type_id"
                                data-live-search="true">
                                <option value="">--</option>
                                @if (isset($leaveTypes))
                                    @foreach ($leaveTypes as $leaveType)
                                        @if($leaveType->deleted_at == null)
                                            <option value="{{ $leaveType->id }}" @selected(isset($prefillLeaveTypeId) && $prefillLeaveTypeId == $leaveType->id)>
                                                {{ $leaveType->type_name }}
                                                ({{ $leaveType->isUnlimited() ? __('app.unlimitedLeaveType') : $leaveType->no_of_leaves }})
                                            </option>
                                        @endif
                                    @endforeach
                                @endif

                                @if (isset($leaveQuotas))
                                    @foreach ($leaveQuotas as $leaveQuota)
                                        @if($leaveQuota->leaveType && $leaveQuota->leaveType->leaveTypeCondition($leaveQuota->leaveType, $defaultAssign)
                                            && $leaveQuota->leaveType->deleted_at == null
                                        )
                                            <option value="{{ $leaveQuota->leaveType->id }}" @selected(isset($prefillLeaveTypeId) && $prefillLeaveTypeId == $leaveQuota->leaveType->id)>
                                                {{ $leaveQuota->leaveType->type_name }}
                                                ({{ $leaveQuota->leaveType->isUnlimited() ? __('app.unlimitedLeaveType') : $leaveQuota->leaves_remaining }})
                                            </option>
                                        @endif
                                    @endforeach
                                @endif
                            </select>

                            @if (($canApplyForOthers ?? false) && ($addLeadAgentPermission == 'all' || $addLeadAgentPermission == 'added'))
                                <x-slot name="append">
                                    <button type="button"
                                        class="btn btn-outline-secondary border-grey add-lead-type2"
                                        data-toggle="tooltip" data-original-title="{{ __('modules.leaves.addLeaveType') }}">@lang('app.add')</button>
                                </x-slot>
                            @endif
                        </x-forms.input-group>
                    </div>

                    {{-- One's own leave goes in as Pending; the status is set when it is approved. --}}
                    @if ($approveRejectPermission == 'all' && ($canApplyForOthers ?? false))
                        <div class="col-lg-4 col-md-6">
                            <x-forms.select fieldId="status" :fieldLabel="__('app.status')" fieldName="status" search="true">
                                <option value="pending" @selected(($prefillStatus ?? 'pending') === 'pending')>@lang('app.pending')</option>
                                <option value="approved" @selected(($prefillStatus ?? 'pending') === 'approved')>@lang('app.approved')</option>
                            </x-forms.select>
                        </div>
                    @endif

                    <div class="col-md-6 col-lg-4">
                        <div class="form-group my-3">
                            <label class="f-14 text-dark-grey mb-12 w-100" for="usr">@lang('modules.leaves.selectDuration')</label>
                            <div class="d-block d-lg-flex d-md-flex">
                                <x-forms.radio fieldId="duration_single" :fieldLabel="__('modules.leaves.single')" fieldName="duration"
                                    fieldValue="single" checked="true">
                                </x-forms.radio>
                                <x-forms.radio fieldId="duration_multiple" :fieldLabel="__('modules.leaves.multiple')" fieldValue="multiple"
                                    fieldName="duration"></x-forms.radio>

                                <x-forms.radio fieldId="half_day_first" :fieldLabel="__('modules.leaves.firstHalf')" fieldName="duration"
                                    fieldValue="first_half">
                                </x-forms.radio>
                                <x-forms.radio fieldId="half_day_second" :fieldLabel="__('modules.leaves.secondHalf')" fieldValue="second_half"
                                    fieldName="duration"></x-forms.radio>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 col-md-6 single_date_div">
                        <div class="position-relative">
                            <x-forms.text :fieldLabel="__('app.date')" fieldName="leave_date" fieldId="single_date" :fieldPlaceholder="__('app.date')"
                                :fieldValue="now(company()->timezone)->translatedFormat(company()->date_format)" />
                            <i class="fa fa-calendar-alt date-field-icon" data-target="single_date"></i>
                        </div>
                    </div>

                    <div class="col-lg-4 col-md-6 d-none multi_date_div">
                        <div class="position-relative">
                            <x-forms.text :fieldLabel="__('messages.selectMultipleDates')" fieldName="multi_date" fieldId="multi_date" :fieldPlaceholder="__('messages.selectMultipleDates')"
                                :fieldValue="now(company()->timezone)->translatedFormat(company()->date_format)" />
                            <i class="fa fa-calendar-alt date-field-icon" data-target="multi_date"></i>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 date-range-days mt-5">
                        <p id="users" class="mt-2 badge badge-secondary"></p>

                    </div>
                    <div class="col-md-12">
                        <div class="form-group my-3">
                            <x-forms.textarea class="mr-0 mr-lg-2 mr-md-2" :fieldLabel="__('modules.leaves.reason')" fieldName="reason"
                                fieldId="reason" fieldRequired="true" :fieldPlaceholder="__('placeholders.leave.reason')">
                            </x-forms.textarea>
                        </div>
                    </div>

                    <div class="col-lg-12">
                        <x-forms.file-multiple class="mr-0 mr-lg-2 mr-md-2" :fieldLabel="__('app.menu.addFile')" fieldName="file" :popover="__('messages.leaveFileMessage')" fieldId="leave-file-upload-dropzone" />
                        <input type="hidden" name="leaveIds[]" id="leaveID">
                    </div>

                </div>

                <x-form-actions>
                    <x-forms.button-primary id="save-leave-form" class="mr-3" icon="check">@lang('app.save')
                    </x-forms.button-primary>
                    <x-forms.button-cancel :link="route('leaves.index')" class="border-0">@lang('app.cancel')
                    </x-forms.button-cancel>
                </x-form-actions>

            </div>
        </x-form>

    </div>
</div>

{{-- this plugin is used only in leaves create form --}}
<script src="{{ asset('vendor/jquery/bootstrap-datepicker.min.js') }}"></script>
<script src="{{ asset('vendor/jquery/daterangepicker.min.js')}}" defer=""></script>


<script>
    $(document).ready(function() {

        // Calendar symbol click opens the same date-picker the field already has.
        // #single_date uses the qs-datepicker library, which exposes a direct show()
        // method on the instance (see setMin()/setMax() used elsewhere in this file) —
        // calling it directly is more reliable than simulating a focus event. #multi_date
        // uses daterangepicker, which only binds to real click/focus events on the input
        // itself (no direct show() API), so it still needs the focus-trigger fallback.
        $(document).on('click', '.date-field-icon', function() {
            var targetId = $(this).data('target');

            if (targetId === 'single_date' && dp1 && typeof dp1.show === 'function') {
                dp1.show();
            } else {
                $('#' + targetId).trigger('focus');
            }
        });

        Dropzone.autoDiscover = false;
        //Dropzone class
        myDropzone = new Dropzone("div#leave-file-upload-dropzone", {
            dictDefaultMessage: "{{ __('app.dragDrop') }}",
            url: "{{ route('leave-files.store') }}",
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            paramName: "file",
            maxFilesize: DROPZONE_MAX_FILESIZE,
            maxFiles: DROPZONE_MAX_FILES,
            autoProcessQueue: false,
            uploadMultiple: true,
            addRemoveLinks: true,
            parallelUploads: DROPZONE_MAX_FILES,
            acceptedFiles: DROPZONE_FILE_ALLOW,
            init: function() {
                myDropzone = this;
            }
        });
        myDropzone.on('sending', function(file, xhr, formData) {
            var leaveIds = $('#leaveID').val();

            if (leaveIds) {
                leaveIds.split(',').forEach(function(leaveId) {
                    formData.append('leave_ids[]', leaveId);
                });
            }
        });
        myDropzone.on('uploadprogress', function() {
            $.easyBlockUI();
        });
        myDropzone.on('queuecomplete', function() {
            var redirect_url = $('#redirect_url').val();
            if (redirect_url != '') {
                window.location.href = decodeURIComponent(redirect_url);
            }
            window.location.href = "{{ route('leaves.index') }}"
        });
        myDropzone.on('removedfile', function () {
            var grp = $('div#file-upload-dropzone').closest(".form-group");
            var label = $('div#file-upload-box').siblings("label");
            $(grp).removeClass("has-error");
            $(label).removeClass("is-invalid");
        });
        myDropzone.on('error', function (file, message) {
            myDropzone.removeFile(file);
            var grp = $('div#file-upload-dropzone').closest(".form-group");
            var label = $('div#file-upload-box').siblings("label");
            $(grp).find(".help-block").remove();
            var helpBlockContainer = $(grp);

            if (helpBlockContainer.length == 0) {
                helpBlockContainer = $(grp);
            }

            helpBlockContainer.append('<div class="help-block invalid-feedback">' + message + '</div>');
            $(grp).addClass("has-error");
            $(label).addClass("is-invalid");

        });


        getDate();

        var dateFormat = "{{ $dateformat }}";
        var minDate = null;
        var dp1, dp2;
        const currentDate = new Date(); // current date

        // Function to get joining date for selected employee
        function getJoiningDate(employeeID) {
            if (!employeeID) {
                return null;
            }

            var employees = @json($employees);
            var employee = employees.filter(function(item) {
                return item.id == employeeID;
            });

            if (employee.length > 0 && employee[0] !== undefined && employee[0].employee_detail && employee[0].employee_detail.joining_date) {
                // Parse the joining date - it comes as a string from JSON
                var joiningDateStr = employee[0].employee_detail.joining_date;
                // Handle different date formats (ISO string, timestamp, etc.)
                var joiningDate = new Date(joiningDateStr);

                // Ensure it's a valid date
                if (!isNaN(joiningDate.getTime())) {
                    return joiningDate;
                }
            }

            return null;
        }

        // Function to set minimum date based on employee joining date
        function setMinDate(employeeID) {
            var joiningDate = getJoiningDate(employeeID);
            minDate = joiningDate;

            // Update single date picker
            if (dp1) {
                if (minDate) {
                    dp1.setMin(minDate);
                } else {
                    dp1.setMin(null);
                }
            }

            // Update multiple date picker
            if (dp2) {
                $('#multi_date').data('daterangepicker').remove();
            }

            var dp2Options = {
                linkedCalendars: false,
                multidate: true,
                todayHighlight: true,
                locale: {
                    format: dateFormat,
                    separator: ' To ',
                },
                startDate: currentDate,
            };

            if (minDate) {
                dp2Options.minDate = minDate;
            }

            dp2 = $('#multi_date').daterangepicker(dp2Options);
        }

        // Initialize date pickers
        var initialEmployeeId = $('#user_id').val();
        var initialMinDate = getJoiningDate(initialEmployeeId);

        var dp1Config = {
            onSelect: function () {
                getDate();
            },
            position: 'bl',
            ...datepickerConfig
        };

        if (initialMinDate) {
            dp1Config.minDate = initialMinDate;
            minDate = initialMinDate;
        }

        dp1 = datepicker('#single_date', dp1Config);

        var dp2Options = {
            linkedCalendars: false,
            multidate: true,
            todayHighlight: true,
            locale: {
                format: dateFormat,
                separator: ' To ',
            },
            startDate: currentDate,
        };

        if (initialMinDate) {
            dp2Options.minDate = initialMinDate;
        }

        dp2 = $('#multi_date').daterangepicker(dp2Options);

        $('#multi_date').change(function() {
            var dates = $(this).val();
            if (dates && dates.includes(' To ')) {
                var startDate = moment(dates.split(' To ')[0], dateFormat);
                var endDate = moment(dates.split(' To ')[1], dateFormat);

                if (startDate.isValid() && endDate.isValid()) {
                    var totalDays = endDate.diff(startDate, 'days') + 1;
                    $('.date-range-days').html(totalDays + ' Days Selected');
                }
            }
        });

        $('input[type=radio][name=duration]').change(function() {
            if (this.value == 'multiple') {
                // Reinitialize with current minDate
                $('#multi_date').data('daterangepicker').remove();

                var dp2Options = {
                    linkedCalendars: false,
                    multidate: true,
                    todayHighlight: true,
                    locale: {
                        format: dateFormat,
                        separator: ' To ',
                    },
                    startDate: currentDate,
                };

                if (minDate) {
                    dp2Options.minDate = minDate;
                }

                dp2 = $('#multi_date').daterangepicker(dp2Options);
            }
            else {
                $('.date-range-days').html('');
            }
        });

        // Set min date on page load if employee is pre-selected
        if (initialEmployeeId) {
            setMinDate(initialEmployeeId);
        }

        // Update min date when employee changes
        $('#user_id').on('change', function(e) {
            setMinDate($(this).val());
        });

        $('#save-leave-form').click(function() {
            let markleave = 'no';
            var dateRange = $('#multi_date').data('daterangepicker');
            startDate = dateRange.startDate.format('YYYY-MM-DD');
            endDate = dateRange.endDate.format('YYYY-MM-DD');

            var multiDate = [];
            multiDate = [startDate, endDate];
            $('#startDate').val(startDate);
            $('#endDate').val(endDate);
            $('#multi_date').val(multiDate);

            const url = "{{ route('leaves.store') }}";
            function sendAjaxRequest(){
                $.easyAjax({
                    url: url,
                    container: '#save-lead-data-form',
                    type: "POST",
                    disableButton: true,
                    blockUI: true,
                    buttonSelector: "#save-leave-form",
                    data: $('#save-lead-data-form').serialize()+'&multiStartDate='+startDate + '&multiEndDate='+endDate + '&markLeave='+markleave
                        + '&has_file=' + (myDropzone.getQueuedFiles().length ? 1 : 0),
                    success: function(response) {
                        if (response.status == 'success') {
                            $('#leaveID').val(response.leaveIds);
                            myDropzone.processQueue();
                            window.location.href = response.redirectUrl;
                        }

                        if (response.status == 'attendanceMarked') {

                            Swal.fire({
                                title: "@lang('messages.sweetAlertTitle')",
                                text: "@lang('messages.attendanceIsMarked')",
                                icon: 'warning',
                                showCancelButton: true,
                                focusConfirm: false,
                                confirmButtonText: "@lang('app.apply')",
                                cancelButtonText: "@lang('app.cancel')",
                                customClass: {
                                    confirmButton: 'btn btn-primary mr-3',
                                    cancelButton: 'btn btn-secondary'
                                },
                                showClass: {
                                    popup: 'swal2-noanimation',
                                    backdrop: 'swal2-noanimation'
                                },
                                buttonsStyling: false
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    markleave = 'yes';
                                    sendAjaxRequest();
                                }
                            });
                        }

                    }
                });
            }

            sendAjaxRequest();
        });

        $('body').on('click', '.add-lead-type2', function() {
            var url = "{{ route('leaveType.create') }}";
            $(MODAL_XL + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_XL, url);
        });

        $("input[name=duration]").click(function() {
            ($('input[name=duration]:checked').val() == "multiple") ? getDate('multiple') : getDate();

            $(this).val() == 'multiple' ? $('.multi_date_div').removeClass('d-none') : $(
                '.multi_date_div').addClass('d-none');
            $(this).val() == 'multiple' ? $('.single_date_div').addClass('d-none') : $(
                '.single_date_div').removeClass('d-none');
        });

        $('#user_id').change(function() {
            var id = $(this).val();
            if (id == '') {
                id = 0;
            }
            var url = "{{ route('employee-leaves.employee_leave_types', ':id') }}";
            url = url.replace(':id', id);
            $.easyAjax({
                url: url,
                type: "GET",
                container: '#save-lead-data-form',
                blockUI: true,
                success: function(data) {
                    $('#leave_type_id').html(data.data);
                    $('#leave_type_id').selectpicker('refresh');
                }
            })
        });

        function getDate(value) {
            let url = "{{ route('leaves.date') }}";
            date = $('#single_date').val();

            if (value == 'multiple') {
                date = '';
            }

            $.easyAjax({
                type: 'GET',
                url: url,
                container: '#save-lead-data-form',
                data: {
                    'date': date
                },
                success: function(response) {
                    if(response.status == 'success'){
                        if(response.users > 0 && response.users < 2){
                            $('#users').text(response.users+` @lang('modules.leaves.employeeOnLeave')`);
                        }else if(response.users > 0){
                            $('#users').text(response.users+` @lang('modules.leaves.employeesOnLeave')`);
                        }else{
                            $('#users').text('');
                        }
                    }
                }
            });
        };

        // Pre-fill from the attendance calendar's "Assign Status" action —
        // selects the exact clicked date (single-day duration is already
        // the default). Leave type is pre-selected server-side above.
        @if (isset($prefillDate))
            (function () {
                var parts = '{{ $prefillDate }}'.split('-');
                var prefillJsDate = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
                dp1.setDate(prefillJsDate, true);
            })();
        @endif

        init(RIGHT_MODAL);
    });
</script>
