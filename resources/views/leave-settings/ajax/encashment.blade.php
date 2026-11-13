<!-- LEAVE ENCASHMENT SETTINGS -->
<div class="col-lg-12 col-md-12 w-100 p-4">

    <x-alert type="info">
        Leave that is <strong>paid</strong> at year end — or carried forward beyond its maximum and set to
        <strong>encash</strong> — is valued with the settings below and paid on the employee's next payslip.
        Which leave types encash is set on each leave type, under <em>Unused leave</em>. On leaving, the final settlement pays only
        the leave types marked <strong>encashed</strong> &mdash; Earned Leave, within the limits below.
    </x-alert>

    {{-- A <div>, not a <form>: the settings page already wraps this tab in its own form, and a form inside a form is dropped by the browser --}}
    <div id="encashment-settings-form" class="enc-form">
        <h4 class="enc-head">Encashment value</h4>
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <x-forms.select fieldId="encashment_basis" fieldLabel="Day value from"
                    fieldName="encashment_basis"
                    popover="The monthly salary figure a day of leave is worth a fraction of.">
                    <option value="basic" @selected(($settings->encashment_basis ?? 'basic') === 'basic')>Basic salary</option>
                    <option value="gross" @selected(($settings->encashment_basis ?? '') === 'gross')>Gross salary</option>
                </x-forms.select>
            </div>

            <div class="col-lg-3 col-md-6">
                @php $div = (float) ($settings->encashment_divisor ?? 0); @endphp
                <x-forms.select fieldId="encashment_divisor" fieldLabel="Divided by (days)" fieldName="encashment_divisor"
                    popover="Per-day value = monthly figure ÷ this number of days.">
                    <option value="0" @selected($div < 1)>Days in the salary period (30 / 31)</option>
                    <option value="30" @selected($div == 30)>30 days</option>
                    <option value="26" @selected($div == 26)>26 working days</option>
                    @if ($div >= 1 && !in_array($div, [26.0, 30.0], true))
                        <option value="{{ $div }}" selected>{{ rtrim(rtrim(number_format($div, 2), '0'), '.') }} days</option>
                    @endif
                </x-forms.select>
            </div>

            <div class="col-lg-3 col-md-6">
                <x-forms.select fieldId="encashment_salary_source" fieldLabel="Take the salary from"
                    fieldName="encashment_salary_source"
                    popover="CTC is available for everyone with a salary on file. The last payslip reflects what was actually paid but is missing for anyone not yet paid.">
                    <option value="ctc" @selected(($settings->encashment_salary_source ?? 'ctc') === 'ctc')>Employee CTC (÷ 12)</option>
                    <option value="last_payslip" @selected(($settings->encashment_salary_source ?? '') === 'last_payslip')>Latest payslip</option>
                </x-forms.select>
            </div>

            <div class="col-lg-3 col-md-6" id="basic-pct-field">
                <x-forms.number fieldId="encashment_basic_pct" fieldLabel="Basic as % of gross"
                    fieldName="encashment_basic_pct" :fieldValue="(float) ($settings->encashment_basic_pct ?? 50)"
                    popover="Used with CTC when valuing from basic. An employee's own basic % takes precedence where one is set." />
            </div>
        </div>

        @php
            $leaveTypesForEl = \App\Models\LeaveType::where('paid', 1)->orderBy('type_name')->get(['id', 'type_name']);
        @endphp
        <h4 class="enc-head enc-head--split">Earned Leave</h4>
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <x-forms.select fieldId="earned_leave_type_id" fieldLabel="Earned Leave type" fieldName="earned_leave_type_id"
                    popover="The leave type the rules below apply to. It accrues from the joining date like any monthly leave.">
                    <option value="">--</option>
                    @foreach ($leaveTypesForEl as $type)
                        <option value="{{ $type->id }}" @selected((int) ($settings->earned_leave_type_id ?? 0) === $type->id)>{{ $type->type_name }}</option>
                    @endforeach
                </x-forms.select>
            </div>
            <div class="col-lg-3 col-md-6">
                <x-forms.number fieldId="earned_leave_min_service_years" fieldLabel="Usable after (years)"
                    fieldName="earned_leave_min_service_years" :fieldValue="(float) ($settings->earned_leave_min_service_years ?? 2)"
                    popover="Counted from the joining date. Before this the balance builds up but cannot be used, and is not paid out on leaving. 0 = from joining." />
            </div>
            <div class="col-lg-3 col-md-6">
                <x-forms.number fieldId="earned_leave_encash_max_days" fieldLabel="Paid on exit, up to (days)"
                    fieldName="earned_leave_encash_max_days" :fieldValue="$settings->earned_leave_encash_max_days ?? 30"
                    popover="The most Earned Leave the final settlement pays. Leave it empty for no limit." />
            </div>
            <div class="col-lg-3 col-md-6">
                @php
                    $elFrom = !empty($settings->earned_leave_starts_from) ? \Carbon\Carbon::parse($settings->earned_leave_starts_from) : null;
                @endphp
                <x-forms.label class="my-3" fieldId="el_from_month" fieldLabel="Starts from"
                    popover="Earned Leave is earned, 1 a month, only from this month on - nothing before it. Leave both empty: from the start of the leave year (or the joining month)." />
                <input type="hidden" name="earned_leave_starts_from" id="earned_leave_starts_from" value="{{ $elFrom?->format('Y-m') }}">
                <div class="enc-pair">
                    <select class="form-control select-picker" id="el_from_month" data-size="8">
                        <option value="">Month</option>
                        @foreach (range(1, 12) as $m)
                            <option value="{{ sprintf('%02d', $m) }}" @selected($elFrom && $elFrom->month === $m)>{{ \Carbon\Carbon::create(2000, $m, 1)->format('F') }}</option>
                        @endforeach
                    </select>
                    <select class="form-control select-picker" id="el_from_year" data-size="8">
                        <option value="">Year</option>
                        @foreach (range(now()->year - 3, now()->year + 1) as $y)
                            <option value="{{ $y }}" @selected($elFrom && $elFrom->year === $y)>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="enc-example">
            <span class="enc-example__title"><i class="fa fa-calculator mr-1"></i>Example</span>
            <span id="encashment-example"></span>
        </div>

        <x-forms.button-primary id="save-encashment-settings" icon="check">@lang('app.save')</x-forms.button-primary>
    </div>
</div>

<style>
    body.epic .enc-form .enc-head { font-size: 15px !important; font-weight: 600 !important; color: var(--epic-text, #1d2939) !important; margin: 4px 0 0 !important; }
    body.epic .enc-form .enc-head--split { margin-top: 26px !important; padding-top: 20px !important; border-top: 1px solid var(--epic-line, #e8e9e9) !important; }
    /* labels share one height (a long one may wrap), so the fields in a row line up */
    body.epic .enc-form label { white-space: normal !important; min-height: 42px; display: flex !important; align-items: flex-end; margin-bottom: 8px !important; }
    body.epic .enc-form .enc-pair { display: flex; gap: 8px; }
    body.epic .enc-form .enc-pair .bootstrap-select:first-child { flex: 3 1 0 !important; }
    body.epic .enc-form .enc-pair .bootstrap-select { flex: 2 1 0; width: auto !important; min-width: 0 !important; margin: 0 !important; }
    body.epic .enc-form .enc-example { margin: 24px 0 20px; padding: 12px 16px; border-radius: 10px; background: #f9fafb;
        border: 1px solid var(--epic-line, #e8e9e9); font-size: 13px; color: #475467; }
    body.epic .enc-form .enc-example__title { display: block; font-weight: 600; color: var(--epic-text, #1d2939); margin-bottom: 4px; }
</style>

<script>
    (function () {
        // Starts from: month + year make the one value the form sends (empty unless both are chosen)
        // (the page turns .select-picker into dropdowns itself)
        $('body').off('change.elFrom').on('change.elFrom', '#el_from_month, #el_from_year', function () {
            var m = $('#el_from_month').val(), y = $('#el_from_year').val();
            $('#earned_leave_starts_from').val(m && y ? y + '-' + m : '');
        });

        function refresh() {
            var basis = $('#encashment_basis').val();
            var source = $('#encashment_salary_source').val();
            var divisor = parseFloat($('#encashment_divisor').val()) || {{ \App\Services\Payroll\PayPeriod::containing(now())->days() }};
            var pct = parseFloat($('#encashment_basic_pct').val()) || 50;

            // The basic % only matters when basic is derived from CTC.
            $('#basic-pct-field').toggle(basis === 'basic' && source === 'ctc');

            // Worked example on a round figure, so the setting is easy to sanity-check.
            var ctc = 360000, monthly = ctc / 12;
            var base = basis === 'gross' ? monthly : monthly * pct / 100;
            var text = source === 'ctc'
                ? 'CTC ₹3,60,000 → ₹30,000 a month' + (basis === 'basic' ? ' → basic at ' + pct + '% = ₹' + base.toLocaleString('en-IN') : ' gross')
                : (basis === 'basic' ? 'Basic' : 'Gross') + ' from the latest payslip, e.g. ₹' + base.toLocaleString('en-IN');
            text += ' → ÷ ' + divisor + ' = ₹' + (base / divisor).toFixed(2) + ' per day → 10 days = ₹' + (base / divisor * 10).toFixed(2);
            $('#encashment-example').text(text);
        }

        $('#encashment-settings-form').on('change keyup', 'select, input', refresh);
        refresh();

        $('#save-encashment-settings').on('click', function () {
            $.easyAjax({
                url: "{{ route('leaves-settings.encashment') }}",
                type: 'POST',
                container: '#encashment-settings-form',
                blockUI: true,
                data: $('#encashment-settings-form').find('input, select, textarea').serialize() + '&_token={{ csrf_token() }}'
            });
        });
    })();
</script>
