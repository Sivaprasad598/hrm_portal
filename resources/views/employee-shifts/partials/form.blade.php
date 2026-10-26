{{-- The shift form, for Add and Edit: fixed timing or flexible hours, then
     only what that type needs. EmployeeShiftController::fill() fills in the
     rest. $shift: the shift being edited, or null. --}}
@php
    $shift = $shift ?? null;
    $type = $shift->shift_type ?? 'strict';
    $fmt = fn ($t) => $t && $t !== '00:00:00' ? \Carbon\Carbon::parse($t)->format(company()->time_format) : '';
    $num = fn ($v) => $v === null || $v === '' ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    $openDays = $shift ? (json_decode((string) $shift->office_open_days, true) ?: []) : ['1', '2', '3', '4', '5', '6'];
@endphp

<style>
    .shift-type-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .shift-type-card { border: 1px solid #d0d5dd; border-radius: 8px; padding: 12px 14px; cursor: pointer; margin: 0; display: block; }
    .shift-type-card input { margin-right: 6px; }
    .shift-type-card.is-on { border-color: #1d82f5; box-shadow: 0 0 0 1px #1d82f5; background: #f5faff; }
    .shift-type-card .t { font-weight: 600; font-size: 14px; color: #101828; }
    .shift-type-card .d { font-size: 12px; color: #667085; margin-top: 2px; }
    @media (max-width: 575.98px) { .shift-type-cards { grid-template-columns: 1fr; } }
</style>

<div class="row">
    <div class="col-12 mt-3">
        <label class="f-14 text-dark-grey mb-12 w-100">How is this shift worked?</label>
        <div class="shift-type-cards">
            <label class="shift-type-card {{ $type !== 'flexible' ? 'is-on' : '' }}">
                <span class="t"><input type="radio" name="shift_type" value="strict" @checked($type !== 'flexible')>Fixed timing</span>
                <span class="d d-block">Set start and end times. Coming in late is marked; hours count inside the shift.</span>
            </label>
            <label class="shift-type-card {{ $type === 'flexible' ? 'is-on' : '' }}">
                <span class="t"><input type="radio" name="shift_type" value="flexible" @checked($type === 'flexible')>Flexible hours</span>
                <span class="d d-block">Come in any time. A full day is complete after the set hours, breaks included (e.g. 9). No late marks.</span>
            </label>
        </div>
    </div>

    <div class="col-md-6">
        <x-forms.text fieldLabel="Shift name" fieldPlaceholder="e.g. General Shift, Night Shift" fieldName="shift_name"
            fieldId="shift_name" fieldRequired="true" :fieldValue="$shift->shift_name ?? ''" />
    </div>
    <div class="col-md-3">
        <x-forms.text fieldLabel="Short code" fieldName="shift_short_code" fieldId="shift_short_code"
            fieldPlaceholder="e.g. GS" fieldRequired="true" :fieldValue="$shift->shift_short_code ?? ''" />
    </div>
    <div class="col-md-3">
        <div class="form-group my-3">
            <x-forms.label fieldId="colorselector" fieldRequired="true" fieldLabel="Colour"></x-forms.label>
            <x-forms.input-group id="colorpicker">
                <input type="text" class="form-control height-35 f-14" name="color" id="colorselector" value="{{ $shift->color ?? '#16813D' }}">
                <x-slot name="append">
                    <span class="input-group-text height-35 colorpicker-input-addon"><i></i></span>
                </x-slot>
            </x-forms.input-group>
        </div>
    </div>

    {{-- Fixed timing --}}
    <div class="col-md-3 fixed-field">
        <div class="bootstrap-timepicker">
            <x-forms.text fieldLabel="Start time" fieldName="office_start_time" fieldId="office_start_time" fieldRequired="true"
                :fieldValue="$type !== 'flexible' ? $fmt($shift->office_start_time ?? '09:30:00') : $fmt('09:30:00')" />
        </div>
    </div>
    <div class="col-md-3 fixed-field">
        <div class="bootstrap-timepicker">
            <x-forms.text fieldLabel="End time" fieldName="office_end_time" fieldId="office_end_time" fieldRequired="true"
                :fieldValue="$type !== 'flexible' ? $fmt($shift->office_end_time ?? '18:30:00') : $fmt('18:30:00')" />
        </div>
    </div>
    <div class="col-md-3 fixed-field">
        <x-forms.number fieldLabel="Late after (minutes)" fieldName="late_mark_duration" fieldId="late_mark_duration" minValue="0"
            :fieldValue="$shift->late_mark_duration ?? 15"
            popover="Coming in later than the start time plus these minutes is marked late." />
    </div>
    <div class="col-md-3 fixed-field">
        <div class="bootstrap-timepicker">
            <x-forms.text fieldLabel="Half day if in after" fieldName="halfday_mark_time" fieldId="halfday_mark_time"
                :fieldValue="$type !== 'flexible' ? $fmt($shift->halfday_mark_time ?? null) : ''" fieldPlaceholder="Halfway through the shift"
                popover="Coming in after this time counts as a half day at most. Leave blank for halfway through the shift." />
        </div>
    </div>

    {{-- Flexible hours --}}
    <div class="col-md-4 flexible-field">
        <x-forms.number fieldLabel="Hours for a full day (including breaks)" fieldName="total_shift_hours" fieldId="total_shift_hours" fieldRequired="true"
            minValue="1" maxValue="23" :fieldValue="$num($shift->flexible_total_hours ?? 9) ?: 9"
            popover="Time present from first punch to last punch; lunch and other breaks are inside it. 9 hours = e.g. 10:00 to 19:00." />
    </div>
    <div class="col-md-4 flexible-field">
        <x-forms.number fieldLabel="Hours for a half day (including breaks)" fieldName="halfday_shift_hours" fieldId="halfday_shift_hours"
            minValue="0.5" maxValue="23" :fieldValue="$num($shift->flexible_half_day_hours ?? null)" fieldPlaceholder="Half the full day" />
    </div>

    <div class="col-12">
        <div class="form-group my-3">
            <x-forms.label fieldId="office_open_days" fieldLabel="Working days" fieldRequired="true"></x-forms.label>
            <div class="d-flex flex-wrap">
                @foreach ([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $n => $day)
                    <label class="mr-3 mb-0 f-14 d-flex align-items-center">
                        <input type="checkbox" class="mr-1" name="office_open_days[]" value="{{ $n }}" @checked(in_array((string) $n, array_map('strval', $openDays), true))>{{ $day }}
                    </label>
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-12">
        <a href="javascript:;" class="f-13" data-toggle="collapse" data-target="#shift-more">More options</a>
        <div class="collapse" id="shift-more">
            <div class="row">
                <div class="col-md-4">
                    <x-forms.number fieldLabel="Clock-ins allowed in a day" fieldName="clockin_in_day" fieldId="clockin_in_day" minValue="0"
                        :fieldValue="$shift->clockin_in_day ?? 20" />
                </div>
                <div class="col-md-4 fixed-field">
                    <x-forms.number fieldLabel="Clock in early by (minutes)" fieldName="early_clock_in" fieldId="early_clock_in" minValue="0"
                        :fieldValue="$shift->early_clock_in ?? ''" fieldPlaceholder="Any time" />
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        function showType() {
            var flexible = $('input[name=shift_type]:checked').val() === 'flexible';
            $('.fixed-field').toggle(!flexible);
            $('.flexible-field').toggle(flexible);
            $('.shift-type-card').each(function () { $(this).toggleClass('is-on', $(this).find('input').is(':checked')); });
        }
        $('input[name=shift_type]').on('change', showType);
        showType();

        $('#colorpicker').colorpicker();
        $('#office_end_time, #office_start_time, #halfday_mark_time').timepicker({
            @if (company()->time_format == 'H:i') showMeridian: false, @endif
            defaultTime: false
        });
        setTimeout(function () { $('[data-toggle="popover"]').popover(); }, 300);
    })();
</script>
