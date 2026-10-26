<link rel="stylesheet" href="{{ asset('vendor/css/bootstrap-colorpicker.css') }}" />

<div class="modal-header">
    <h5 class="modal-title">@lang('app.addNewLeaveType')</h5>
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
</div>
<div class="modal-body">
    <div class="portlet-body">
        <x-form id="createLeave" method="POST" class="ajax-form">
            <div class="tabs border-bottom-grey">
                <div class="nav" id="nav-tab">
                    <a class="nav-item nav-link f-15 type active" data-toggle="tab" href="#personal" role="tab" aria-controls="nav-type" aria-selected="true">@lang('app.general')</a>
                    <a class="nav-item nav-link f-15 type" data-toggle="tab" href="#promotion" role="tab" aria-controls="nav-type" aria-selected="true">@lang('modules.leaves.entitlement')</a>
                    <a class="nav-item nav-link f-15 type" data-toggle="tab" href="#vacation" role="tab" aria-controls="nav-type" aria-selected="true">@lang('modules.leaves.applicability')</a>
                    <a class="nav-item nav-link f-15 type" data-toggle="tab" href="#leave-rules" role="tab" aria-controls="nav-type" aria-selected="true">Rules</a>
                </div>
            </div>

            <div class="tab-content" id="tab-content">
                <div class="tab-pane active" id="personal">
                    <input type="hidden" value="true" name="page_reload" id="page_reload">
                    <h3 class="heading-h3 mt-4">@lang('app.general')</h3>

                    <div class="row">
                        <div class="col-lg-4 col-md-6">
                            <x-forms.text :fieldLabel="__('modules.leaves.leaveType')"
                                :fieldPlaceholder="__('placeholders.leaveType')" fieldName="type_name" fieldId="type_name"
                                fieldValue="" :fieldRequired="true" />
                        </div>
                            <div class="col-lg-2 col-md-4">
                                {{-- Payroll recognises a leave by this code rather than by its name,
                                     so renaming a leave type never silently changes how it is paid. --}}
                                <x-forms.text fieldLabel="Leave code" fieldName="code" fieldId="code"
                                    fieldPlaceholder="EL, CL, SL…" :fieldValue="''"
                                    popover="Short code used by payroll: EL, CL, SL, ML or LOP. CL and SL count as paid leave, LOP as loss of pay, any other code as paid leave." />
                            </div>


                        <div class="col-lg-4 col-md-6">
                            <x-forms.select fieldId="leavetype" :fieldLabel="__('modules.leaves.leaveAllotmentType')" fieldName="leavetype" search="true">
                                <option value="monthly">@lang('app.monthlyLeaveType')</option>
                                <option value="yearly">@lang('app.yearlyLeaveType')</option>
                                <option value="unlimited">@lang('app.unlimitedLeaveType')</option>
                            </x-forms.select>
                        </div>

                        <div class="col-lg-4 col-md-6 d-none" id="yearly-leave-field">
                            <x-forms.number :fieldLabel="__('modules.leaves.noOfYearlyLeaves')" fieldRequired="true"
                                fieldName="yearly_leave_number" fieldId="yearly_leave_number" fieldValue="0" minValue="0"
                                :popover="__('messages.leave.noOfYearlyLeaves')" />
                        </div>

                        <div class="col-lg-4 col-md-6" id="monthly-leave-field">
                            <x-forms.number :fieldLabel="__('modules.leaves.noOfMonthlyLeaves')" fieldRequired="true"
                                fieldName="monthly_leave_number" fieldId="monthly_leave_number" fieldValue="0" minValue="0"
                                :popover="__('messages.leave.noOfMonthlyLeaves')" />
                        </div>

                        <div class="col-lg-4 col-md-6 d-none" id="monthly-leave-limit">
                            <x-forms.number :fieldLabel="__('modules.leaves.monthLimit')" fieldRequired="true"
                                fieldName="monthly_limit" fieldId="monthly_limit" fieldValue="0"
                                :fieldHelp="__('modules.leaves.monthLimitInfo')" minValue="0"
                                :popover="__('messages.leave.monthlyLimit')" />
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <x-forms.select fieldId="paid" :fieldLabel="__('modules.leaves.leavePaidStatus')" fieldName="paid" search="true"
                                :popover="__('messages.leave.paidStatus')">
                                <option value="1">@lang('app.paid')</option>
                                <option value="0">@lang('app.unpaid')</option>
                            </x-forms.select>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <div class="form-group my-3">
                                <x-forms.label fieldId="colorselector" fieldRequired="true"
                                    :fieldLabel="__('modules.sticky.colors')">
                                </x-forms.label>
                                <x-forms.input-group id="colorpicker">
                                    <input type="text" class="form-control height-35 f-14"
                                        placeholder="{{ __('placeholders.colorPicker') }}" name="color" id="colorselector">
                                    <x-slot name="append">
                                        <span class="input-group-text height-35 colorpicker-input-addon"><i></i></span>
                                    </x-slot>
                                </x-forms.input-group>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane" id="promotion">
                    <h3 class="heading-h3 mt-4">@lang('modules.leaves.entitlement')</h3>

                    <div class="row">
                        <div class="col-lg-6 col-md-12">
                            <div class="form-group my-3">
                                <x-forms.label fieldId="effective_after" :fieldLabel="__('modules.leaves.effectiveAfter')"
                                    :popover="__('messages.leave.effectiveAfter')" />
                                <div class="d-flex align-items-center flex-wrap">
                                    <div class="mr-2 mb-2" style="width: 150px;">
                                        <x-forms.input-group>
                                            <input type="number" class="form-control height-35 f-14" name="effective_after"
                                                id="effective_after" min="0">
                                            <x-slot name="append">
                                                <select name="effective_type" class="select-picker form-control">
                                                    <option value="day">@lang('app.day')</option>
                                                    <option value="month">@lang('app.month')</option>
                                                </select>
                                            </x-slot>
                                        </x-forms.input-group>
                                    </div>
                                    <span class="f-14 text-dark-grey mb-2">@lang('modules.leaves.ofJoining')</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <div class="form-group my-3">
                                <x-forms.checkbox :fieldLabel="__('modules.leaves.allowedProbation')"
                                    fieldName="allowed_probation" fieldId="allowed_probation" checked="true" fieldValue="1"
                                    fieldRequired="true" :popover="__('messages.leave.allowedProbation')" />
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <div class="form-group my-3">
                                <x-forms.checkbox :fieldLabel="__('modules.leaves.allowedNotice')"
                                    fieldName="allowed_notice" fieldId="allowed_notice" checked="true" fieldValue="1"
                                    fieldRequired="true" :popover="__('messages.leave.allowedNotice')" />
                            </div>
                        </div>
                    </div>

                    <div id="entitlement-quota-rules" class="row">
                        <div class="col-lg-4 col-md-6">
                            <x-forms.select fieldId="unused_leave" :fieldLabel="__('modules.leaves.unusedLeaves')"
                                fieldName="unused_leave" search="true" :popover="__('messages.leave.unusedLeave')">
                                <option value="carry forward">@lang('modules.leaves.carryForward')</option>
                                <option value="lapse">@lang('modules.leaves.lapse')</option>
                                <option value="paid">@lang('app.paid')</option>
                            </x-forms.select>
                        </div>


                        {{-- Year-end ceiling on carried-forward leave, and the fate of anything above it. --}}
                        <div class="col-lg-4 col-md-6 " id="carry-forward-cap-fields">
                            <x-forms.number fieldLabel="Max carry forward (days)" fieldName="carry_forward_max"
                                fieldId="carry_forward_max" fieldPlaceholder="No limit" :fieldValue="''"
                                popover="Most days that may be carried into the next year at year end. Leave blank for no limit." />
                        </div>
                        <div class="col-lg-4 col-md-6 " id="carry-forward-excess-field">
                            <x-forms.select fieldId="carry_forward_excess" fieldLabel="Days above the maximum"
                                fieldName="carry_forward_excess"
                                popover="At year end, days above the maximum are either discarded or paid out on the next payslip.">
                                <option value="discard" selected>Discard</option>
                                <option value="encash" >Encash (pay on payslip)</option>
                            </x-forms.select>
                        </div>
                        <div class="col-lg-4 col-md-6 d-none" id="carry-forward-expiry-field">
                            <x-forms.datepicker fieldId="carry_forward_expiry_date"
                                :fieldLabel="__('modules.leaves.carryForwardExpiryDate')"
                                fieldName="carry_forward_expiry_date"
                                :popover="__('messages.leave.carryForwardExpiryDateInfo')"
                                :fieldPlaceholder="__('placeholders.date')" />
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <x-forms.select fieldId="over_utilization" :fieldLabel="__('modules.leaves.overutilization')"
                                fieldName="over_utilization" search="true" :popover="__('messages.leave.overutilization')">
                                <option value="not_allowed">@lang('modules.leaves.doNotAllow')</option>
                                <option value="allow_paid">@lang('modules.leaves.allowPaid')</option>
                                <option value="allow_unpaid">@lang('modules.leaves.allowUnpaid')</option>
                            </x-forms.select>
                        </div>
                    </div>
                </div>

                <div class="tab-pane" id="vacation">
                    <h3 class="heading-h3 mt-4">@lang('modules.leaves.applicability')</h3>

                    <div class="row">
                        <div class="col-lg-4 col-md-6">
                            <div class="form-group my-3">
                                <x-forms.label fieldId="gender" :fieldLabel="__('modules.employees.gender')" fieldRequired="true"
                                    :popover="__('messages.leave.gender')" />
                                <select class="form-control multiple-option" multiple name="gender[]"
                                    id="gender" data-live-search="true" data-size="8">
                                    <option value="male" selected>@lang('app.male')</option>
                                    <option value="female" selected>@lang('app.female')</option>
                                    <option value="others" selected>@lang('app.others')</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <div class="form-group my-3">
                                <x-forms.label fieldId="marital_status" :fieldLabel="__('modules.employees.maritalStatus')"
                                    fieldRequired="true" :popover="__('messages.leave.maritalStatus')" />
                                <select class="form-control multiple-option" multiple name="marital_status[]"
                                    id="marital_status" data-live-search="true" data-size="8">
                                    @foreach (\App\Enums\MaritalStatus::cases() as $status)
                                        <option selected value="{{ $status->value }}">{{ $status->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <div class="form-group my-3">
                                <x-forms.label fieldId="department" :fieldLabel="__('app.department')" fieldRequired="true"
                                    :popover="__('messages.leave.department')" />
                                <select class="form-control multiple-option" multiple name="department[]"
                                    id="department" data-live-search="true" data-size="8">
                                    @foreach ($teams as $team)
                                        <option value="{{ $team->id }}" selected>{{ $team->team_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <div class="form-group my-3">
                                <x-forms.label fieldId="designation" :fieldLabel="__('app.designation')" fieldRequired="true"
                                    :popover="__('messages.leave.designation')" />
                                <select class="form-control multiple-option" multiple name="designation[]"
                                    id="designation" data-live-search="true" data-size="8">
                                    @foreach ($designations as $designation)
                                        <option value="{{ $designation->id }}" selected>{{ $designation->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <div class="form-group my-3">
                                <x-forms.label fieldId="role" :fieldLabel="__('app.role')" fieldRequired="true"
                                    :popover="__('messages.leave.role')" />
                                <select class="form-control multiple-option" multiple name="role[]"
                                    id="role" data-live-search="true" data-size="8">
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}" selected>{{ $role->display_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                @include('leave-settings.partials.rules-tab', ['type' => null])
            </div>
        </x-form>

    </div>
</div>
<div class="modal-footer">
    <x-forms.button-cancel data-dismiss="modal" class="border-0 mr-3">@lang('app.cancel')</x-forms.button-cancel>
    <x-forms.button-primary id="save-leave-setting" icon="check">@lang('app.save')</x-forms.button-primary>
</div>

<script>
    $(document).ready(function () {

        $('#monthly-leave-limit').addClass('d-none');
        setTimeout(function () {
            $('[data-toggle="popover"]').popover();
        }, 500);

        toggleCarryForwardExpiryField();
        datepicker('#carry_forward_expiry_date', {
            position: 'bl',
            ...datepickerConfig
        });

        $('#unused_leave').on('changed.bs.select', function () {
            toggleCarryForwardExpiryField();
            toggleCarryForwardCap();
        });

        toggleCarryForwardCap();
    });

    // The ceiling only means something when unused leave is carried forward.
    function toggleCarryForwardCap() {
        var carry = $('#unused_leave').val() === 'carry forward';
        $('#carry-forward-cap-fields, #carry-forward-excess-field').toggleClass('d-none', !carry);
    }

    function toggleCarryForwardExpiryField() {
        if ($('#unused_leave').val() === 'carry forward') {
            $('#carry-forward-expiry-field').removeClass('d-none');
        } else {
            $('#carry-forward-expiry-field').addClass('d-none');
            $('#carry_forward_expiry_date').val('');
        }
    }

    $(".select-picker").selectpicker();

    $('#colorpicker').colorpicker({
        "color": "#16813D"
    });

    $('#leavetype').change(function() {
        var leaveType = $(this).val();

        if (leaveType == 'monthly') {
            $('#yearly-leave-field').addClass('d-none');
            $('#yearly_leave_number').attr('required', false);
            $('#monthly-leave-field').removeClass('d-none');
            $('#monthly_leave_number').attr('required', true);
            $('#monthly-leave-limit').addClass('d-none');
            $('#monthly_limit').attr('required', false);
            $('#entitlement-quota-rules').removeClass('d-none');
        } else if (leaveType == 'yearly') {
            $('#yearly-leave-field').removeClass('d-none');
            $('#yearly_leave_number').attr('required', true);
            $('#monthly-leave-field').addClass('d-none');
            $('#monthly_leave_number').attr('required', false);
            $('#monthly-leave-limit').removeClass('d-none');
            $('#monthly_limit').attr('required', true);
            $('#entitlement-quota-rules').removeClass('d-none');
        } else {
            $('#yearly-leave-field').addClass('d-none');
            $('#yearly_leave_number').attr('required', false).val(0);
            $('#monthly-leave-field').addClass('d-none');
            $('#monthly_leave_number').attr('required', false).val(0);
            $('#monthly-leave-limit').addClass('d-none');
            $('#monthly_limit').attr('required', false).val(0);
            $('#entitlement-quota-rules').addClass('d-none');
            $('#unused_leave').val('lapse').selectpicker('refresh');
            $('#over_utilization').val('not_allowed').selectpicker('refresh');
            $('#carry_forward_expiry_date').val('');
            $('#carry-forward-expiry-field').addClass('d-none');
        }
    });


    $(".multiple-option").selectpicker({
        actionsBox: true,
        selectAllText: "{{ __('modules.permission.selectAll') }}",
        deselectAllText: "{{ __('modules.permission.deselectAll') }}",
        multipleSeparator: ", ",
        selectedTextFormat: "count > 8",
        countSelectedText: function(selected, total) {
            return selected + " {{ __('app.membersSelected') }} ";
        }
    });

    $('#save-leave-setting').click(function() {
        $.easyAjax({
            container: '#createLeave',
            type: "POST",
            disableButton: true,
            blockUI: true,
            buttonSelector: "#save-leave-setting",
            errorPosition: 'inline',
            url: "{{ route('leaveType.store') }}",
            data: $('#createLeave').serialize(),
            success: function(response) {
                if (response.status == 'success') {
                    if (response.page_reload == 'true') {
                        window.location.reload();
                    } else {
                        $('#leave_type_id').html(response.data);
                        $('#leave_type_id').selectpicker('refresh');
                        $(MODAL_XL).modal('hide');
                    }
                }
            }
        })
    });
</script>
