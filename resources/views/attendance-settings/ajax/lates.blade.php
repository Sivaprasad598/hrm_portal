@php
    $s = $attendanceSetting;
    $general = $shifts->firstWhere('shift_name', 'General Shift');
    $deductsFullDay = in_array((string) $s->half_day_deduction_type, ['1', '1.0', '1.00'], true);
@endphp
@include('attendance-settings.ajax.rules-style')

<div class="w-100 p-4 rules-tab">
    <x-form id="lates-form">
        <div class="rules-head">
            <h3>Lates &amp; Half Day</h3>
            <p>When somebody is late, and what lates cost.</p>
        </div>

        <div class="rules-group">
            <h4>When is somebody late</h4>
            <div class="rule-row">
                <div class="rule-label"><strong>Late after</strong>
                    <span>The shift's start plus its late minutes{{ $general ? ' (General Shift: ' . substr($general->office_start_time, 0, 5) . ' + ' . (int) $general->late_mark_duration . ' min)' : '' }}, or one time for everybody.</span></div>
                <div class="rule-control">
                    <select class="form-control" id="wh_late_basis" name="wh_late_basis">
                        <option value="shift" @selected($s->wh_late_basis !== 'global')>Each shift's own late time</option>
                        <option value="global" @selected($s->wh_late_basis === 'global')>One time for everybody</option>
                    </select>
                </div>
            </div>
            <div class="rule-row late-time">
                <div class="rule-label"><strong>Late time</strong><span>Arriving after this is late, whatever the shift.</span></div>
                <div class="rule-control">
                    <input type="time" class="form-control" name="wh_late_time" value="{{ $s->wh_late_time ? substr($s->wh_late_time, 0, 5) : '' }}">
                </div>
            </div>
        </div>

        <div class="rules-group">
            <h4>What lates cost</h4>
            <div class="rule-row">
                <div class="rule-label"><strong>Lates before a deduction</strong><span>Counted in each salary period (21st to 20th); fewer than this cost nothing.</span></div>
                <div class="rule-control"><div class="input-group">
                    <input type="number" min="1" max="31" class="form-control" id="half_day_late_threshold" name="half_day_late_threshold" value="{{ $s->half_day_late_threshold ?? 3 }}">
                    <div class="input-group-append"><span class="input-group-text">lates</span></div>
                </div></div>
            </div>
            <div class="rule-row">
                <div class="rule-label"><strong>Deduction</strong><span>Taken off the paid days each time the count is reached.</span></div>
                <div class="rule-control">
                    <select class="form-control" id="half_day_deduction_type" name="half_day_deduction_type">
                        <option value="0.5" @selected(!$deductsFullDay)>Half day (0.5)</option>
                        <option value="1" @selected($deductsFullDay)>Full day (1)</option>
                    </select>
                </div>
            </div>
            <div class="rule-row">
                <div class="rule-summary w-100">Every <b id="lt-every">3</b> lates in a salary period take off <b id="lt-cost">½ day</b>.</div>
            </div>
        </div>

        <div class="rules-group">
            <h4>Half-day mark</h4>
            <div class="rule-row">
                <div class="rule-label"><strong>Arriving after the shift's half-day mark</strong>
                    <span>Is a half day straight away, whatever the hours. The mark is set on each shift{{ $general && $general->halfday_mark_time ? ' (General Shift: ' . substr($general->halfday_mark_time, 0, 5) . ')' : '' }}
                        under <a href="{{ route('attendance-settings.index') }}?tab=shift">Shifts</a>.</span></div>
            </div>
        </div>
    </x-form>
</div>

<div class="w-100 border-top-grey set-btns">
    <x-setting-form-actions>
        <x-forms.button-primary id="save-lates" icon="check">@lang('app.save')</x-forms.button-primary>
    </x-setting-form-actions>
</div>

<script>
    $(function () {
        function refresh() {
            $('.late-time').toggle($('#wh_late_basis').val() === 'global');
            $('#lt-every').text(parseInt($('#half_day_late_threshold').val(), 10) || 1);
            $('#lt-cost').text($('#half_day_deduction_type').val() === '1' ? '1 day' : '½ day');
        }
        $('#wh_late_basis, #half_day_late_threshold, #half_day_deduction_type').on('input change', refresh);
        refresh();

        $('#save-lates').on('click', function () {
            $.easyAjax({
                url: "{{ route('attendance-settings.update_lates') }}",
                container: '#lates-form',
                type: 'POST',
                blockUI: true,
                disableButton: true,
                buttonSelector: '#save-lates',
                data: $('#lates-form').serialize()
            });
        });
    });
</script>
