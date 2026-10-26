<link rel="stylesheet" href="{{ asset('vendor/css/bootstrap-colorpicker.css') }}" />

<div class="modal-header">
    <h5 class="modal-title">@lang('modules.leaves.editLeaveType')</h5>
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
</div>
<div class="modal-body">
    <div class="portlet-body">
        <x-form id="editLeave" method="PUT" class="ajax-form">
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
                    <h3 class="heading-h3 mt-4">@lang('app.general')</h3>

                    <div class="row">
                        <div class="col-lg-4 col-md-6">
                            <x-forms.text :fieldLabel="__('modules.leaves.leaveType')"
                                :fieldPlaceholder="__('placeholders.leaveType')" fieldName="type_name" fieldId="type_name"
                                :fieldValue="$leaveType->type_name" fieldRequired="true" />
                        </div>
                            <div class="col-lg-2 col-md-4">
                                {{-- Payroll recognises a leave by this code rather than by its name,
                                     so renaming a leave type never silently changes how it is paid. --}}
                                <x-forms.text fieldLabel="Leave code" fieldName="code" fieldId="code"
                                    fieldPlaceholder="EL, CL, SL…" :fieldValue="$leaveType->code"
                                    popover="Short code used by payroll: EL, CL, SL, ML or LOP. CL and SL count as paid leave, LOP as loss of pay, any other code as paid leave." />
                            </div>


                        <div class="col-lg-4 col-md-6">
                            <div class="form-group my-3">
                                <x-forms.label fieldId="leavetype" :fieldLabel="__('modules.leaves.leaveAllotmentType')" />
                                <select name="leavetype" id="leavetype" class="form-control select-picker" disabled>
                                    <option value="monthly" {{ $leaveType->leavetype == 'monthly' ? 'selected' : '' }}>@lang('app.monthlyLeaveType')</option>
                                    <option value="yearly" {{ $leaveType->leavetype == 'yearly' ? 'selected' : '' }}>@lang('app.yearlyLeaveType')</option>
                                    <option value="unlimited" {{ $leaveType->leavetype == 'unlimited' ? 'selected' : '' }}>@lang('app.unlimitedLeaveType')</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6 d-none" id="yearly-leave-field">
                            <x-forms.number :fieldLabel="__('modules.leaves.noOfYearlyLeaves')"
                                fieldName="yearly_leave_number" fieldId="yearly_leave_number"
                                :fieldValue="$leaveType->no_of_leaves" minValue="0"
                                :popover="__('messages.leave.noOfYearlyLeaves')" />
                        </div>

                        <div class="col-lg-4 col-md-6" id="monthly-leave-field">
                            <x-forms.number :fieldLabel="__('modules.leaves.noOfMonthlyLeaves')"
                                fieldName="monthly_leave_number" fieldId="monthly_leave_number"
                                :fieldValue="$leaveType->no_of_leaves" minValue="0"
                                :popover="__('messages.leave.noOfMonthlyLeaves')" />
                        </div>

                        <div class="col-lg-4 col-md-6 d-none" id="monthly-leave-limit">
                            <x-forms.number :fieldLabel="__('modules.leaves.monthLimit')"
                                fieldName="monthly_limit" fieldId="monthly_limit"
                                :fieldValue="$leaveType->monthly_limit" fieldRequired="true"
                                :fieldHelp="__('modules.leaves.monthLimitInfo')" minValue="0"
                                :popover="__('messages.leave.monthlyLimit')" />
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <x-forms.select fieldId="paid" :fieldLabel="__('modules.leaves.leavePaidStatus')" fieldName="paid"
                                search="true" :popover="__('messages.leave.paidStatus')">
                                <option value="1" {{ $leaveType->paid == 1 ? 'selected' : '' }}>@lang('app.paid')</option>
                                <option value="0" {{ $leaveType->paid == 0 ? 'selected' : '' }}>@lang('app.unpaid')</option>
                            </x-forms.select>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <div class="form-group my-3">
                                <x-forms.label fieldId="colorselector" :fieldLabel="__('modules.sticky.colors')" />
                                <x-forms.input-group id="colorpicker">
                                    <input type="text" class="form-control height-35 f-14"
                                        placeholder="{{ __('placeholders.colorPicker') }}" name="color" id="colorselector"
                                        value="{{ $leaveType->color }}">
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
                                                id="effective_after" min="0" value="{{ $leaveType->effective_after }}">
                                            <x-slot name="append">
                                                <select name="effective_type" class="select-picker form-control">
                                                    <option value="days" @if ($leaveType->effective_type == 'days') selected @endif>@lang('app.day')</option>
                                                    <option value="months" @if ($leaveType->effective_type == 'months') selected @endif>@lang('app.month')</option>
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
                                    fieldName="allowed_probation" fieldId="allowed_probation" fieldValue="1"
                                    :checked="$leaveType->allowed_probation == 1"
                                    :popover="__('messages.leave.allowedProbation')" />
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <div class="form-group my-3">
                                <x-forms.checkbox :fieldLabel="__('modules.leaves.allowedNotice')"
                                    fieldName="allowed_notice" fieldId="allowed_notice" fieldValue="1"
                                    :checked="$leaveType->allowed_notice == 1"
                                    :popover="__('messages.leave.allowedNotice')" />
                            </div>
                        </div>
                    </div>

                    <div id="entitlement-quota-rules" class="row {{ $leaveType->isUnlimited() ? 'd-none' : '' }}">
                        <div class="col-lg-4 col-md-6">
                            <div class="form-group my-3">
                                <x-forms.label fieldId="unused_leave" :fieldLabel="__('modules.leaves.unusedLeaves')"
                                    :popover="__('messages.leave.unusedLeave')" />
                                <select name="unused_leave" id="unused_leave" class="form-control select-picker">
                                    <option value="carry forward" @if ($leaveType->unused_leave == 'carry forward') selected @endif>@lang('modules.leaves.carryForward')</option>
                                    <option value="lapse" @if ($leaveType->unused_leave == 'lapse') selected @endif>@lang('modules.leaves.lapse')</option>
                                    <option value="paid" @if ($leaveType->unused_leave == 'paid') selected @endif>@lang('app.paid')</option>
                                </select>
                                <small class="text-lightest f-11">Applied at the next year end.</small>
                            </div>
                        </div>


                        {{-- Year-end ceiling on carried-forward leave, and the fate of anything above it. --}}
                        <div class="col-lg-4 col-md-6 {{ $leaveType->unused_leave == 'carry forward' ? '' : 'd-none' }}" id="carry-forward-cap-fields">
                            <x-forms.number fieldLabel="Max carry forward (days)" fieldName="carry_forward_max"
                                fieldId="carry_forward_max" fieldPlaceholder="No limit" :fieldValue="$leaveType->carry_forward_max !== null ? rtrim(rtrim((string) $leaveType->carry_forward_max, '0'), '.') : ''"
                                popover="Most days that may be carried into the next year at year end. Leave blank for no limit." />
                        </div>
                        <div class="col-lg-4 col-md-6 {{ $leaveType->unused_leave == 'carry forward' ? '' : 'd-none' }}" id="carry-forward-excess-field">
                            <x-forms.select fieldId="carry_forward_excess" fieldLabel="Days above the maximum"
                                fieldName="carry_forward_excess"
                                popover="At year end, days above the maximum are either discarded or paid out on the next payslip.">
                                <option value="discard" @selected(($leaveType->carry_forward_excess ?? 'discard') === 'discard')>Discard</option>
                                <option value="encash" @selected($leaveType->carry_forward_excess === 'encash')>Encash (pay on payslip)</option>
                            </x-forms.select>
                        </div>
                        @if ($leaveType->unused_leave == 'carry forward' && !$leaveType->isUnlimited())
                            <div class="col-lg-4 col-md-6" id="carry-forward-expiry-field">
                                <x-forms.datepicker fieldId="carry_forward_expiry_date"
                                    :fieldLabel="__('modules.leaves.carryForwardExpiryDate')"
                                    fieldName="carry_forward_expiry_date"
                                    :popover="__('messages.leave.carryForwardExpiryDateInfo')"
                                    :fieldPlaceholder="__('placeholders.date')"
                                    :fieldValue="$leaveType->carry_forward_expiry_date ? $leaveType->carry_forward_expiry_date->timezone(company()->timezone)->format(company()->date_format) : ''" />
                            </div>
                        @endif

                        <div class="col-lg-4 col-md-6">
                            <x-forms.select fieldId="over_utilization" :fieldLabel="__('modules.leaves.overutilization')"
                                fieldName="over_utilization" search="true" :popover="__('messages.leave.overutilization')">
                                <option value="not_allowed" @if ($leaveType->over_utilization == 'not_allowed') selected @endif>@lang('modules.leaves.doNotAllow')</option>
                                <option value="allow_paid" @if ($leaveType->over_utilization == 'allow_paid') selected @endif>@lang('modules.leaves.allowPaid')</option>
                                <option value="allow_unpaid" @if ($leaveType->over_utilization == 'allow_unpaid') selected @endif>@lang('modules.leaves.allowUnpaid')</option>
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
                                    @foreach ($allGenders as $allGender)
                                        <option value="{{ $allGender }}"
                                            @if (is_array($gender) && in_array($allGender, $gender)) selected @endif>
                                            @lang('app.' . $allGender)
                                        </option>
                                    @endforeach
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
                                        <option @selected(is_array($maritalStatus) && in_array($status->value, $maritalStatus))
                                            value="{{ $status->value }}">{{ $status->label() }}</option>
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
                                    @foreach ($allTeams as $allTeam)
                                        <option value="{{ $allTeam->id }}"
                                            @if (is_array($department) && in_array($allTeam->id, $department)) selected @endif>
                                            {{ $allTeam->team_name }}
                                        </option>
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
                                    @foreach ($allDesignations as $allDesignation)
                                        <option value="{{ $allDesignation->id }}"
                                            @if (is_array($designation) && in_array($allDesignation->id, $designation)) selected @endif>
                                            {{ $allDesignation->name }}
                                        </option>
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
                                    @foreach ($allRoles as $allRole)
                                        <option value="{{ $allRole->id }}"
                                            @if (is_array($role) && in_array($allRole->id, $role)) selected @endif>
                                            {{ $allRole->display_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                @include('leave-settings.partials.rules-tab', ['type' => $leaveType])
            </div>
        </x-form>
    </div>
</div>
<div class="modal-footer">
    <x-forms.button-cancel data-dismiss="modal" class="border-0 mr-3">@lang('app.cancel')</x-forms.button-cancel>
    <x-forms.button-primary id="save-leave-setting" icon="check">@lang('app.save')</x-forms.button-primary>
</div>

<script>
    // The ceiling only means something when unused leave is carried forward.
    function toggleCarryForwardCap() {
        var carry = $('#unused_leave').val() === 'carry forward';
        $('#carry-forward-cap-fields, #carry-forward-excess-field').toggleClass('d-none', !carry);
    }

    $(document).on('changed.bs.select', '#unused_leave', toggleCarryForwardCap);

    $(document).ready(function () {
        setTimeout(function () {
            $('[data-toggle="popover"]').popover();
        }, 500);

        if ($('#carry_forward_expiry_date').length) {
            datepicker('#carry_forward_expiry_date', {
                position: 'bl',
                ...datepickerConfig
            });
        }
    });

    $(".select-picker").selectpicker();

    $('#colorpicker').colorpicker({
        "color": "{{ $leaveType->color }}"
    });

    function setLeaveFields() {
        var leaveType = "{{ $leaveType->leavetype }}";

        if (leaveType == 'monthly') {
            $('#monthly-leave-field').removeClass('d-none');
            $('#monthly_leave_number').attr('required', true).val('{{ $leaveType->no_of_leaves }}');
            $('#yearly-leave-field').addClass('d-none');
            $('#yearly_leave_number').attr('required', false).val(0);
            $('#monthly-leave-limit').addClass('d-none');
            $('#monthly_limit').attr('required', false).val(0);
            $('#entitlement-quota-rules').removeClass('d-none');
        } else if (leaveType == 'yearly') {
            $('#monthly-leave-field').addClass('d-none');
            $('#monthly_leave_number').attr('required', false).val(0);
            $('#monthly-leave-limit').removeClass('d-none');
            $('#monthly_limit').attr('required', true);
            $('#yearly-leave-field').removeClass('d-none');
            $('#yearly_leave_number').attr('required', true).val('{{ $leaveType->no_of_leaves }}');
            $('#entitlement-quota-rules').removeClass('d-none');
        } else if (leaveType == 'unlimited') {
            $('#monthly-leave-field').addClass('d-none');
            $('#monthly_leave_number').attr('required', false).val(0);
            $('#yearly-leave-field').addClass('d-none');
            $('#yearly_leave_number').attr('required', false).val(0);
            $('#monthly-leave-limit').addClass('d-none');
            $('#monthly_limit').attr('required', false).val(0);
            $('#entitlement-quota-rules').addClass('d-none');
        }
    }

    setLeaveFields();

    $('#leavetype').change(function () {
        setLeaveFields();
    });

    $(".multiple-option").selectpicker({
        actionsBox: true,
        selectAllText: "{{ __('modules.permission.selectAll') }}",
        deselectAllText: "{{ __('modules.permission.deselectAll') }}",
        multipleSeparator: ", ",
        selectedTextFormat: "count > 8",
        countSelectedText: function (selected, total) {
            return selected + " {{ __('app.membersSelected') }} ";
        }
    });

    $('#save-leave-setting').click(function () {
        $.easyAjax({
            container: '#editLeave',
            type: "POST",
            disableButton: true,
            blockUI: true,
            buttonSelector: "#save-leave-setting",
            errorPosition: 'inline',
            url: "{{ route('leaveType.update', $leaveType->id) }}",
            data: $('#editLeave').serialize(),
            success: function (response) {
                if (response.status == 'success') {
                    window.location.reload();
                }
            }
        });
    });
</script>
