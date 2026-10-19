@extends('layouts.app')

@php
    $fmt = company()->date_format;
    $d = $draft;                                   // an open draft wins over the record
    $ed = $employee->employeeDetail;
    $period = $check['period'];
    $settlement = $check['settlement'];
    $blocked = !empty($check['blockers']);
    $notEligible = $period && (string) $period->eligible_for_rehire === '0';
    $val = fn ($draftKey, $fallback) => $d && $d->{$draftKey} !== null && $d->{$draftKey} !== '' ? $d->{$draftKey} : $fallback;
    $date = fn ($v) => $v ? \Carbon\Carbon::parse($v)->translatedFormat($fmt) : '';
    $probationOn = $d ? (bool) $d->probation_applies : true;
@endphp

@push('styles')
<style>
    .rejoin-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 16px; }
    .rejoin-card h5 { font-size: 15px; font-weight: 600; margin-bottom: 4px; }
    .rejoin-facts { display: flex; flex-wrap: wrap; gap: 24px; }
    .rejoin-facts div span { display: block; font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: .03em; }
    .rejoin-facts div strong { font-size: 14px; color: #1e293b; font-weight: 500; }
    .timeline-period { border-left: 3px solid #c7d2fe; padding: 4px 0 12px 14px; position: relative; }
    .timeline-period.current { border-left-color: #4f46e5; }
    .timeline-period::before { content: ''; position: absolute; left: -7px; top: 6px; width: 11px; height: 11px; border-radius: 50%; background: #4f46e5; }
    /* One rhythm for every field, whichever component drew it (as on Add / Edit Employee) */
    .rejoin-card .form-group.my-3, .rejoin-card .form-group.mb-0 { margin-top: 0 !important; margin-bottom: 0 !important; }
    .rejoin-card .row > [class*="col-"] > label.f-14,
    .rejoin-card .row > [class*="col-"] .form-group > label.f-14,
    .rejoin-card .form-group > label.f-14 {
        display: block; margin-top: 16px !important; margin-bottom: 8px !important; line-height: 20px; min-height: 20px;
    }
</style>
@endpush

@section('content')
<div class="content-wrapper">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0 f-21 font-weight-normal">Rejoin — {{ $employee->name }}</h4>
            <p class="f-13 text-lightest mb-0">The same employee, a new employment: its own joining date, terms, probation, leave and documents.</p>
        </div>
        <a href="{{ route('employees.show', $employee->id) }}" class="btn btn-outline-secondary btn-sm"><i class="fa fa-arrow-left mr-1"></i> Back to profile</a>
    </div>

    {{-- The employment that ended --}}
    <div class="rejoin-card">
        <h5><i class="fa fa-history mr-1 text-primary"></i> Last employment</h5>
        <div class="rejoin-facts mt-3">
            <div><span>Joined</span><strong>{{ $date($period->joined_on ?? $ed->joining_date) ?: '—' }}</strong></div>
            <div><span>Last working day</span><strong>{{ $date($period->left_on ?? $ed->last_date) ?: '—' }}</strong></div>
            <div><span>Exit</span><strong>{{ ucfirst($period->exit_type ?? '—') }}</strong></div>
            <div><span>Reason</span><strong>{{ $period->exit_reason ?: '—' }}</strong></div>
            <div><span>Final settlement</span><strong>
                @if ($settlement)
                    {{ ucfirst($settlement->status) }}{{ $settlement->status === 'paid' && $settlement->paid_at ? ' on ' . $date($settlement->paid_at) : '' }}
                    · {{ company()->currency->currency_symbol }}{{ number_format((float) $settlement->net_payable, 2) }}
                @else
                    None made
                @endif
            </strong></div>
            <div><span>Eligible for rehire</span><strong>
                @if ($period && $period->eligible_for_rehire === null) Not decided
                @elseif ($notEligible) <span class="text-red">No</span>
                @else Yes @endif
            </strong></div>
        </div>
    </div>

    @foreach ($check['blockers'] as $message)
        <div class="alert alert-danger f-14"><i class="fa fa-ban mr-1"></i> {{ $message }}</div>
    @endforeach
    @foreach ($check['warnings'] as $message)
        <div class="alert alert-warning f-14"><i class="fa fa-exclamation-triangle mr-1"></i> {{ $message }}</div>
    @endforeach

    <x-form id="rejoin-form">
        <div class="rejoin-card">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fa fa-user-plus mr-1 text-primary"></i> New employment</h5>
                @if ($d)
                    <span class="badge badge-warning f-12">Draft saved {{ \Carbon\Carbon::parse($d->updated_at)->diffForHumans() }}</span>
                @endif
            </div>

            <div class="row">
                <div class="col-lg-3 col-md-6">
                    <x-forms.datepicker fieldId="rejoin_date" fieldLabel="Rejoining Date" fieldName="rejoin_date" fieldRequired="true"
                        :fieldValue="$date($val('rejoin_date', null))" :fieldPlaceholder="__('placeholders.date')" />
                </div>
                <div class="col-lg-3 col-md-6">
                    <x-forms.text fieldId="employee_code" :fieldLabel="__('modules.employees.employeeId')" fieldName="employee_code"
                        fieldRequired="true" :fieldValue="$val('employee_code', $ed->employee_id)" />
                </div>
                <div class="col-lg-3 col-md-6">
                    <x-forms.text fieldId="employee_card_id" :fieldLabel="__('modules.employees.employeeCardId')" fieldName="employee_card_id"
                        fieldRequired="true" :fieldValue="$val('employee_card_id', $ed->employee_card_id)" maxlength="50"
                        oninput="this.value = this.value.replace(/[^A-Za-z0-9_-]/g, '').slice(0, 50)" />
                </div>
                <div class="col-lg-3 col-md-6">
                    <x-forms.select fieldId="iapp_emp_code" fieldLabel="Biometric Code" fieldName="iapp_emp_code" search="true">
                        <option value="">— not on the device —</option>
                        @foreach ($deviceCodes as $code)
                            <option value="{{ $code->emp_code }}" @selected($val('iapp_emp_code', $ed->iapp_emp_code) == $code->emp_code)>
                                {{ $code->emp_code }}{{ $code->employee_name ? ' — ' . $code->employee_name : '' }}{{ $code->user_id && $code->user_id != $employee->id ? ' (linked to someone else)' : '' }}
                            </option>
                        @endforeach
                    </x-forms.select>
                </div>

                <div class="col-lg-3 col-md-6">
                    <x-forms.select fieldId="designation_id" :fieldLabel="__('app.designation')" fieldName="designation_id" fieldRequired="true" search="true">
                        <option value="">--</option>
                        @foreach ($designations as $item)
                            <option value="{{ $item->id }}" @selected($val('designation_id', $ed->designation_id) == $item->id)>{{ $item->name }}</option>
                        @endforeach
                    </x-forms.select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <x-forms.select fieldId="department_id" :fieldLabel="__('app.department')" fieldName="department_id" fieldRequired="true" search="true">
                        <option value="">--</option>
                        @foreach ($teams as $item)
                            <option value="{{ $item->id }}" @selected($val('department_id', $ed->department_id) == $item->id)>{{ $item->team_name }}</option>
                        @endforeach
                    </x-forms.select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <x-forms.select fieldId="branch_id" :fieldLabel="__('app.branch')" fieldName="branch_id">
                        <option value="">--</option>
                        @foreach ($branches as $item)
                            <option value="{{ $item->id }}" @selected($val('branch_id', $ed->branch_id) == $item->id)>{{ $item->branch_name }}</option>
                        @endforeach
                    </x-forms.select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <x-forms.select fieldId="reporting_to" :fieldLabel="__('modules.employees.reportingTo')" fieldName="reporting_to" search="true">
                        <option value="">--</option>
                        @foreach ($managers as $item)
                            <option value="{{ $item->id }}" @selected($val('reporting_to', $ed->reporting_to) == $item->id)>{{ $item->name }}</option>
                        @endforeach
                    </x-forms.select>
                </div>

                <div class="col-lg-3 col-md-6">
                    <x-forms.select fieldId="role_id" :fieldLabel="__('app.role')" fieldName="role_id">
                        @foreach ($roles as $item)
                            <option value="{{ $item->id }}" @selected($val('role_id', $currentRoleId) == $item->id)>{{ $item->display_name }}</option>
                        @endforeach
                    </x-forms.select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <x-forms.select fieldId="employment_type" :fieldLabel="__('modules.employees.employmentType')" fieldName="employment_type">
                        <option value="">--</option>
                        @foreach (['full_time' => __('app.fullTime'), 'part_time' => __('app.partTime'), 'on_contract' => __('app.onContract'),
                                   'internship' => __('app.internship'), 'trainee' => __('app.trainee')] as $value => $label)
                            <option value="{{ $value }}" @selected($val('employment_type', $ed->employment_type) == $value)>{{ $label }}</option>
                        @endforeach
                    </x-forms.select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <x-forms.label class="my-3" fieldId="employee_ctc" fieldLabel="CTC (per annum)"></x-forms.label>
                    <x-forms.input-group>
                        <x-slot name="prepend"><span class="input-group-text f-14 bg-white-shade">{{ company()->currency->currency_symbol }}</span></x-slot>
                        <input type="number" step="0.01" min="0" class="form-control height-35 f-14" id="employee_ctc" name="employee_ctc"
                            value="{{ $val('employee_ctc', $employee->employee_ctc) }}">
                    </x-forms.input-group>
                    <small class="form-text text-muted" id="ctc-monthly"></small>
                </div>
                <div class="col-lg-3 col-md-6">
                    <x-forms.label class="my-3" fieldId="basic_for_payroll" :fieldLabel="__('modules.employees.basicForPayroll')"></x-forms.label>
                    <x-forms.input-group>
                        <x-slot name="prepend"><span class="input-group-text f-14 bg-white-shade">%</span></x-slot>
                        <input type="number" step="1" min="0" max="100" class="form-control height-35 f-14" id="basic_for_payroll" name="basic_for_payroll"
                            value="{{ $val('basic_for_payroll', $employee->basic_for_payroll) }}" placeholder="Payroll default">
                    </x-forms.input-group>
                </div>

                <div class="col-lg-3 col-md-6">
                    <x-forms.select fieldId="employee_shift_id" fieldLabel="Work Shift" fieldName="employee_shift_id">
                        <option value="">Company default</option>
                        @foreach ($shifts as $item)
                            <option value="{{ $item->id }}" @selected($val('employee_shift_id', $currentShiftId) == $item->id)>
                                {{ $item->shift_name }} ({{ substr($item->office_start_time, 0, 5) }}–{{ substr($item->office_end_time, 0, 5) }})
                            </option>
                        @endforeach
                    </x-forms.select>
                </div>
                <div class="col-lg-9 col-md-6">
                    <x-forms.text fieldId="rejoin_reason" fieldLabel="Reason for rejoining" fieldName="rejoin_reason"
                        :fieldValue="$val('rejoin_reason', '')" fieldPlaceholder="e.g. Rehired for the Revit team" maxlength="500" />
                </div>
            </div>
        </div>

        {{-- Probation --}}
        <div class="rejoin-card">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fa fa-hourglass-half mr-1 text-primary"></i> Probation</h5>
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="probation_applies" name="probation_applies" value="1" @checked($probationOn)>
                    <label class="custom-control-label f-14" for="probation_applies">Applies</label>
                </div>
            </div>
            <div class="row" id="probation-on" @if (!$probationOn) style="display:none" @endif>
                <div class="col-lg-3 col-md-6">
                    <x-forms.number fieldId="probation_months" fieldLabel="Months" fieldName="probation_months"
                        :fieldValue="$val('probation_months', $probationMonths ?: 6)" minValue="1" />
                </div>
                <div class="col-lg-3 col-md-6">
                    <x-forms.datepicker fieldId="probation_end_date" fieldLabel="Probation ends" fieldName="probation_end_date"
                        :fieldValue="$date($val('probation_end_date', null))" :fieldPlaceholder="__('placeholders.date')" />
                </div>
                <div class="col-lg-6 col-md-12 d-flex align-items-end">
                    <p class="f-12 text-lightest mb-3">Default from Settings → Probation ({{ $probationMonths ?: 6 }} months), counted from the rejoining date.</p>
                </div>
            </div>
            <div class="row" id="probation-off" @if ($probationOn) style="display:none" @endif>
                <div class="col-12">
                    <x-forms.text fieldId="skip_probation_reason" fieldLabel="Why probation is skipped" fieldName="skip_probation_reason"
                        :fieldValue="$val('skip_probation_reason', '')" fieldPlaceholder="e.g. Rejoining in the same role after a short break" />
                </div>
            </div>
        </div>

        @if ($notEligible)
            <div class="rejoin-card border-warning">
                <x-forms.text fieldId="rehire_override_reason" fieldLabel="Reason for rehiring despite 'not eligible'" fieldName="rehire_override_reason"
                    fieldRequired="true" :fieldValue="$val('rehire_override_reason', '')" />
            </div>
        @endif

        <div class="rejoin-card">
            <x-forms.textarea fieldId="notes" fieldLabel="Notes" fieldName="notes" :fieldValue="$val('notes', '')"
                fieldPlaceholder="Anything HR should know about this rejoin" />

            <div class="d-flex flex-wrap align-items-center mt-3">
                <button type="button" class="btn btn-secondary mr-2 mb-2 rejoin-save" data-confirm="0" @disabled($blocked)>
                    <i class="fa fa-save mr-1"></i> Save draft
                </button>
                <button type="button" class="btn btn-primary mr-2 mb-2 rejoin-save" data-confirm="1" @disabled($blocked)>
                    <i class="fa fa-check mr-1"></i> Confirm rejoin
                </button>
                @if ($d)
                    <button type="button" class="btn btn-outline-danger mb-2" id="rejoin-cancel"><i class="fa fa-times mr-1"></i> Cancel draft</button>
                @endif
                <p class="f-12 text-lightest mb-2 ml-md-3">
                    Confirming brings them back from the rejoining date: account and sign-in on, leave started afresh, probation set,
                    shift from that date. Joining documents for the new employment are then collected on Edit Employee.
                </p>
            </div>
        </div>
    </x-form>

    {{-- Every employment --}}
    <div class="rejoin-card">
        <h5 class="mb-3"><i class="fa fa-stream mr-1 text-primary"></i> Employment history</h5>
        @include('employees.partials.employment-history', ['periods' => $periods, 'employeeId' => $employee->id])
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(function () {
        ['#rejoin_date', '#probation_end_date'].forEach(function (sel) {
            if ($(sel).length) { datepicker(sel, { position: 'bl', ...datepickerConfig }); }
        });

        // Probation end: the rejoining date plus the months
        function probationEnd() {
            var start = moment($('#rejoin_date').val(), company.moment_date_format, true);
            var months = parseInt($('#probation_months').val(), 10);
            if (start.isValid() && months > 0) {
                $('#probation_end_date').val(start.clone().add(months, 'months').subtract(1, 'day').format(company.moment_date_format));
            }
        }
        $('#rejoin_date, #probation_months').on('change input', probationEnd);
        if (!$('#probation_end_date').val()) { probationEnd(); }

        $('#probation_applies').on('change', function () {
            $('#probation-on').toggle(this.checked);
            $('#probation-off').toggle(!this.checked);
        });

        function monthly() {
            var ctc = parseFloat($('#employee_ctc').val());
            $('#ctc-monthly').text(ctc > 0 ? 'Monthly ' + (ctc / 12).toLocaleString('en-IN', { maximumFractionDigits: 2 }) : '');
        }
        $('#employee_ctc').on('input', monthly);
        monthly();

        $('.rejoin-save').on('click', function () {
            var confirming = $(this).data('confirm') === 1;
            var send = function () {
                $.easyAjax({
                    url: "{{ route('employees.rejoin.store', $employee->id) }}",
                    container: '#rejoin-form',
                    type: 'POST',
                    blockUI: true,
                    data: $('#rejoin-form').serialize() + '&confirm=' + (confirming ? 1 : 0),
                    success: function (response) {
                        if (response.status === 'success' && !response.url) { window.location.reload(); }
                    }
                });
            };

            if (!confirming) { send(); return; }

            Swal.fire({
                title: 'Confirm the rejoin?',
                text: 'They come back from ' + $('#rejoin_date').val() + ': account and sign-in on, leave started afresh, probation and shift set.',
                icon: 'question', showCancelButton: true, confirmButtonText: 'Confirm rejoin',
                customClass: { confirmButton: 'btn btn-primary mr-2', cancelButton: 'btn btn-secondary' }, buttonsStyling: false
            }).then(function (result) { if (result.isConfirmed) { send(); } });
        });

        $('#rejoin-cancel').on('click', function () {
            $.easyAjax({
                url: "{{ $d ? route('employees.rejoin.cancel', $d->id) : '#' }}",
                type: 'POST', data: { _token: '{{ csrf_token() }}' }
            });
        });
    });
</script>
@endpush
