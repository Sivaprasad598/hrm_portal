@php
    $s = $attendanceSetting;
    $hm = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
@endphp
@include('attendance-settings.ajax.rules-style')

<div class="w-100 p-4 rules-tab">
    <x-form id="overtime-form">
        <div class="rules-head">
            <h3>Overtime</h3>
            <p>Hours worked beyond the full day, paid at an hourly rate. Not for attendance-exempt employees or departments that do not consider hours.</p>
        </div>

        <div class="rules-group">
            <h4>Overtime</h4>
            <div class="rule-row">
                <div class="rule-label"><strong>Calculate overtime</strong><span>Off: nobody is paid overtime from attendance.</span></div>
                <div class="rule-control">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="ot_enabled" name="ot_enabled" value="1" @checked($s->ot_enabled)>
                        <label class="custom-control-label f-14" for="ot_enabled">{{ $s->ot_enabled ? 'On' : 'Off' }}</label>
                    </div>
                </div>
            </div>
        </div>

        <div id="ot-fields">
            <div class="rules-group">
                <h4>What happens to overtime</h4>
                <div class="rule-row">
                    <div class="rule-label"><strong>Overtime is</strong><span>Banked hours carry forward with no expiry, to cover LOP or be encashed in payroll.</span></div>
                    <div class="rule-control">
                        <select class="form-control" id="ot_mode" name="ot_mode">
                            <option value="bank" @selected(($s->ot_mode ?? 'bank') === 'bank')>Banked (carried forward)</option>
                            <option value="pay" @selected(($s->ot_mode ?? 'bank') === 'pay')>Paid every month</option>
                        </select>
                    </div>
                </div>
                <div class="rule-row bank-only">
                    <div class="rule-label"><strong>Cover LOP with OT</strong><span>Absent days and unpaid leave only; casual and sick leave are already paid. A day uses a day's work hours.</span></div>
                    <div class="rule-control">
                        <select class="form-control" id="ot_compensate_choice" name="ot_compensate_choice">
                            <option value="manual" @selected(($s->ot_compensate ?? 1) && ($s->ot_compensate_mode ?? 'manual') === 'manual')>Yes, HR decides in payroll</option>
                            <option value="auto" @selected(($s->ot_compensate ?? 1) && ($s->ot_compensate_mode ?? 'manual') === 'auto')>Yes, automatically</option>
                            <option value="off" @selected(!($s->ot_compensate ?? 1))>No</option>
                        </select>
                    </div>
                </div>
                <div class="rule-row bank-only">
                    <div class="rule-label"><strong>Encash in payroll</strong><span>HR can pay out banked hours when generating payroll.</span></div>
                    <div class="rule-control">
                        <select class="form-control" name="ot_encash">
                            <option value="1" @selected($s->ot_encash ?? 1)>Allowed</option>
                            <option value="0" @selected(!($s->ot_encash ?? 1))>Not allowed</option>
                        </select>
                    </div>
                </div>
                <div class="rule-row bank-only">
                    <div class="rule-label"><strong>Bank limit</strong><span>Most hours one person can hold. Empty = no limit.</span></div>
                    <div class="rule-control"><div class="input-group">
                        <input type="number" step="0.5" min="0" max="9999" class="form-control" name="ot_bank_cap" value="{{ $s->ot_bank_cap !== null ? $hm($s->ot_bank_cap) : '' }}" placeholder="No limit">
                        <div class="input-group-append"><span class="input-group-text">hours</span></div>
                    </div></div>
                </div>
            </div>

            <div class="rules-group">
                <h4>Hourly rate</h4>
                <div class="rule-row">
                    <div class="rule-label"><strong>Paid on</strong><span>Hourly rate = monthly amount ÷ (days in the salary period × work hours).</span></div>
                    <div class="rule-control">
                        <select class="form-control" name="ot_basis">
                            <option value="gross" @selected($s->ot_basis !== 'basic')>Gross salary</option>
                            <option value="basic" @selected($s->ot_basis === 'basic')>Basic salary</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="rules-group">
                <h4>Rates by day</h4>
                <div class="table-responsive">
                    <table class="table rules-table mb-0">
                        <thead><tr><th class="pl-4">Day</th><th>Overtime is</th><th style="width:200px">Rate</th></tr></thead>
                        <tbody>
                            <tr>
                                <td class="pl-4 font-weight-500">Weekday</td>
                                <td>Time beyond the full day in office</td>
                                <td><div class="input-group input-group-sm">
                                    <input type="number" step="0.05" min="0" max="10" name="ot_rate_weekday" value="{{ $hm($s->ot_rate_weekday) }}" class="form-control">
                                    <div class="input-group-append"><span class="input-group-text">× hourly</span></div>
                                </div></td>
                            </tr>
                            <tr>
                                <td class="pl-4 font-weight-500">Week-off</td>
                                <td>Every hour worked (Sunday, or the shift's off days)</td>
                                <td><div class="input-group input-group-sm">
                                    <input type="number" step="0.05" min="0" max="10" name="ot_rate_weekend" value="{{ $hm($s->ot_rate_weekend) }}" class="form-control">
                                    <div class="input-group-append"><span class="input-group-text">× hourly</span></div>
                                </div></td>
                            </tr>
                            <tr>
                                <td class="pl-4 font-weight-500">Holiday</td>
                                <td>Every hour worked</td>
                                <td><div class="input-group input-group-sm">
                                    <input type="number" step="0.05" min="0" max="10" name="ot_rate_holiday" value="{{ $hm($s->ot_rate_holiday) }}" class="form-control">
                                    <div class="input-group-append"><span class="input-group-text">× hourly</span></div>
                                </div></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="rule-row"><span class="f-12 text-lightest">A rate of 0 means that kind of day is not paid as overtime.</span></div>
            </div>

            <div class="rules-group">
                <h4>Limits</h4>
                <div class="rule-row">
                    <div class="rule-label"><strong>Count from</strong><span>Less than this in a day is not overtime.</span></div>
                    <div class="rule-control"><div class="input-group">
                        <input type="number" min="0" max="600" class="form-control" name="ot_min_minutes" value="{{ $s->ot_min_minutes }}">
                        <div class="input-group-append"><span class="input-group-text">minutes</span></div>
                    </div></div>
                </div>
                <div class="rule-row">
                    <div class="rule-label"><strong>Weekday cap</strong><span>Most overtime in one weekday. 0 = no cap.</span></div>
                    <div class="rule-control"><div class="input-group">
                        <input type="number" step="0.5" min="0" max="16" class="form-control" name="ot_cap_weekday_hours" value="{{ $hm($s->ot_cap_weekday_hours) }}">
                        <div class="input-group-append"><span class="input-group-text">hours</span></div>
                    </div></div>
                </div>
                <div class="rule-row">
                    <div class="rule-label"><strong>Week-off / holiday cap</strong><span>Most overtime in one week-off or holiday. 0 = no cap.</span></div>
                    <div class="rule-control"><div class="input-group">
                        <input type="number" step="0.5" min="0" max="24" class="form-control" name="ot_cap_off_hours" value="{{ $hm($s->ot_cap_off_hours) }}">
                        <div class="input-group-append"><span class="input-group-text">hours</span></div>
                    </div></div>
                </div>
            </div>

            <p class="f-12 text-lightest mb-0 bank-only">
                Banked hours are already weighted by their rate (4h on a week-off at 1.5× = 6 bank hours), so one bank hour is worth one hourly rate.
                In <a href="{{ route('payroll-run.index') }}">Payroll</a>, each employee's <strong>OT bank</strong> shows the balance, this period's
                overtime and LOP days, to cover LOP or encash; the rest carries forward.
            </p>
            <p class="f-12 text-lightest mb-0 pay-only">
                In <a href="{{ route('payroll-run.index') }}">Payroll</a>, <strong>Calculate overtime</strong> adds it to each employee as an
                "Overtime (calculated)" line that can be changed or removed before the payslip is issued.
            </p>
        </div>
    </x-form>
</div>

<div class="w-100 border-top-grey set-btns">
    <x-setting-form-actions>
        <x-forms.button-primary id="save-overtime" icon="check">@lang('app.save')</x-forms.button-primary>
    </x-setting-form-actions>
</div>

<script>
    $(function () {
        function toggle() {
            var on = $('#ot_enabled').is(':checked');
            var banked = $('#ot_mode').val() === 'bank';
            $('#ot-fields').toggle(on);
            $('.bank-only').toggle(banked);
            $('.pay-only').toggle(!banked);
            $('label[for=ot_enabled]').text(on ? 'On' : 'Off');
        }
        $('#ot_enabled, #ot_mode').on('change', toggle);
        toggle();

        $('#save-overtime').on('click', function () {
            $.easyAjax({
                url: "{{ route('attendance-settings.update_overtime') }}",
                container: '#overtime-form',
                type: 'POST',
                blockUI: true,
                disableButton: true,
                buttonSelector: '#save-overtime',
                data: $('#overtime-form').serialize()
            });
        });
    });
</script>
