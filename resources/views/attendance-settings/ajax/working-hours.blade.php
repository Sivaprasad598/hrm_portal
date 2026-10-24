@php
    $s = $attendanceSetting;
    $hm = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    $breakRows = $breaks->map(fn ($b) => ['key' => (string) $b->id, 'name' => $b->name, 'start' => substr($b->start_time, 0, 5),
        'end' => substr($b->end_time, 0, 5), 'default' => (bool) $b->is_default])->values();
    $shiftRows = $shifts->map(fn ($sh) => [
        'id' => $sh->id, 'name' => $sh->shift_name,
        'timings' => $sh->office_start_time ? substr($sh->office_start_time, 0, 5) . ' – ' . substr($sh->office_end_time, 0, 5) : null,
        'length' => $sh->office_start_time && $sh->office_end_time ? \App\Services\Attendance\ShiftResolver::lengthSeconds($sh) / 60 : null,
        'selected' => $sh->break_ids !== null ? array_map('strval', (array) json_decode($sh->break_ids, true)) : null,
    ])->values();
@endphp
@include('attendance-settings.ajax.rules-style')

<div class="w-100 p-4 rules-tab">
    <x-form id="working-hours-form">
        <div class="rules-head">
            <h3>Working Hours</h3>
            <p>What a full day and a half day are, and the breaks taken off. Applies from
                {{ $s->wh_effective_from ? \Carbon\Carbon::parse($s->wh_effective_from)->format(company()->date_format) : '--' }};
                earlier salary periods are not affected.</p>
        </div>

        {{-- ---------------- Breaks ---------------- --}}
        <div class="rules-group">
            <h4>Breaks</h4>
            <div class="table-responsive">
                <table class="table rules-table mb-0">
                    <thead><tr><th class="pl-4">Break</th><th style="width:150px">Starts</th><th style="width:150px">Ends</th><th>Length</th><th class="text-center">Default</th><th></th></tr></thead>
                    <tbody id="break-rows"></tbody>
                </table>
            </div>
            <div class="rule-row">
                <button type="button" class="btn btn-sm btn-outline-primary" id="add-break"><i class="fa fa-plus mr-1"></i>Add break</button>
                <span class="f-12 text-lightest ml-3">Only the part of a break inside someone's time in office is taken off. Default breaks apply to every shift that does not pick its own.</span>
            </div>
        </div>

        {{-- ---------------- Breaks per shift ---------------- --}}
        @if ($shifts->isNotEmpty())
            <div class="rules-group">
                <h4>Breaks per shift</h4>
                <div class="table-responsive">
                    <table class="table rules-table mb-0">
                        <thead><tr><th class="pl-4">Shift</th><th>Timings</th><th style="width:320px">Breaks</th><th>Total break</th><th>Work hours</th></tr></thead>
                        <tbody id="shift-rows"></tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- ---------------- The working day ---------------- --}}
        <div class="rules-group">
            <h4>The working day</h4>
            <div class="rule-row">
                <div class="rule-label"><strong>Work hours</strong><span>For a shift with no start and end time; otherwise the shift's length less its breaks.</span></div>
                <div class="rule-control"><div class="input-group">
                    <input type="number" step="0.25" min="1" max="16" class="form-control" id="wh_work_hours" name="wh_work_hours" value="{{ $hm($s->wh_work_hours) }}">
                    <div class="input-group-append"><span class="input-group-text">hours</span></div>
                </div></div>
            </div>
            <div class="rule-row">
                <div class="rule-label"><strong>Count hours</strong><span>Time in office is the first punch to the last punch of the day.</span></div>
                <div class="rule-control">
                    <select class="form-control" name="wh_count_mode">
                        <option value="anywhere" @selected($s->wh_count_mode !== 'within_shift')>Anywhere between the punches</option>
                        <option value="within_shift" @selected($s->wh_count_mode === 'within_shift')>Only within the shift timings</option>
                    </select>
                </div>
            </div>
            <div class="rule-row">
                <div class="rule-label"><strong>Half day</strong><span>Work hours for a half day; scaled to each shift's length.</span></div>
                <div class="rule-control"><div class="input-group">
                    <input type="number" step="0.25" min="0.5" max="12" class="form-control" name="wh_half_day_hours" value="{{ $hm($s->wh_half_day_hours) }}">
                    <div class="input-group-append"><span class="input-group-text">hours</span></div>
                </div></div>
            </div>
            <div class="rule-row">
                <div class="rule-label"><strong>Grace</strong><span>Short of a full or half day by up to this much still counts.</span></div>
                <div class="rule-control"><div class="input-group">
                    <input type="number" min="0" max="120" class="form-control" name="wh_grace_minutes" value="{{ $s->wh_grace_minutes }}">
                    <div class="input-group-append"><span class="input-group-text">minutes</span></div>
                </div></div>
            </div>
        </div>
    </x-form>
</div>

<div class="w-100 border-top-grey set-btns">
    <x-setting-form-actions>
        <x-forms.button-primary id="save-working-hours" icon="check">@lang('app.save')</x-forms.button-primary>
    </x-setting-form-actions>
</div>

<script>
    $(function () {
        var breaks = @json($breakRows);
        var shifts = @json($shiftRows);
        var newKey = 0;

        function minutesOf(b) {
            if (!b.start || !b.end) { return 0; }
            var s = b.start.split(':'), e = b.end.split(':');
            var m = (parseInt(e[0], 10) * 60 + parseInt(e[1], 10)) - (parseInt(s[0], 10) * 60 + parseInt(s[1], 10));
            return m > 0 ? m : m + 1440;
        }
        function hours(min) {
            var h = Math.floor(min / 60), m = Math.round(min % 60);
            return h + 'h' + (m ? ' ' + m + 'm' : '');
        }
        function esc(v) { return $('<div>').text(v == null ? '' : v).html(); }

        function drawBreaks() {
            $('#break-rows').html(breaks.map(function (b) {
                return '<tr data-key="' + esc(b.key) + '">' +
                    '<td class="pl-4"><input type="text" class="form-control form-control-sm b-name" maxlength="60" name="breaks[' + esc(b.key) + '][name]" value="' + esc(b.name) + '" placeholder="Lunch"></td>' +
                    '<td><input type="time" class="form-control form-control-sm b-start" name="breaks[' + esc(b.key) + '][start]" value="' + esc(b.start) + '"></td>' +
                    '<td><input type="time" class="form-control form-control-sm b-end" name="breaks[' + esc(b.key) + '][end]" value="' + esc(b.end) + '"></td>' +
                    '<td class="b-len font-weight-500">' + hours(minutesOf(b)) + '</td>' +
                    '<td class="text-center"><input type="checkbox" class="b-default" name="breaks[' + esc(b.key) + '][default]" value="1"' + (b.default ? ' checked' : '') + '></td>' +
                    '<td class="text-right pr-4"><a href="javascript:;" class="text-danger b-remove" title="Remove"><i class="fa fa-times"></i></a></td>' +
                    '</tr>';
            }).join('') || '<tr><td colspan="6" class="pl-4 text-dark-grey">No breaks: the whole time in office counts as work.</td></tr>');
        }

        function drawShifts() {
            $('#shift-rows').html(shifts.map(function (sh) {
                var picked = sh.selected === null ? breaks.filter(function (b) { return b.default; }).map(function (b) { return b.key; }) : sh.selected;
                var options = breaks.map(function (b) {
                    return '<option value="' + esc(b.key) + '"' + (picked.indexOf(b.key) !== -1 ? ' selected' : '') + '>' +
                        esc(b.name || 'Break') + (b.start ? ' (' + b.start + '–' + b.end + ')' : '') + '</option>';
                }).join('');
                return '<tr data-shift="' + sh.id + '">' +
                    '<td class="pl-4 font-weight-500">' + esc(sh.name) + '</td>' +
                    '<td>' + (sh.timings ? esc(sh.timings) + ' (' + hours(sh.length) + ')' : '--') + '</td>' +
                    '<td><select multiple class="form-control select-picker s-breaks" name="shift_breaks[' + sh.id + '][]" title="Default breaks" data-selected-text-format="count > 2">' + options + '</select>' +
                    '<input type="hidden" name="shift_custom[' + sh.id + ']" class="s-custom" value="' + (sh.selected === null ? 0 : 1) + '"></td>' +
                    '<td class="s-total"></td><td class="s-work font-weight-500"></td></tr>';
            }).join(''));
            $('#shift-rows .s-breaks').selectpicker();
            totals();
        }

        function totals() {
            var work = parseFloat($('#wh_work_hours').val()) || 0;
            $('#shift-rows tr').each(function () {
                var keys = $(this).find('.s-breaks').val() || [];
                var total = breaks.filter(function (b) { return keys.indexOf(b.key) !== -1; })
                    .reduce(function (sum, b) { return sum + minutesOf(b); }, 0);
                var sh = shifts.filter(function (x) { return String(x.id) === String($(this).data('shift')); }, this)[0];
                $(this).find('.s-total').text(hours(total));
                $(this).find('.s-work').text(sh && sh.length ? hours(sh.length - total) : hours(work * 60));
            });
        }

        // Keep the break list in step with what is typed, then redraw the shift choices.
        $('#break-rows').on('input change', 'input', function (e) {
            var row = $(this).closest('tr'), b = breaks.filter(function (x) { return x.key === String(row.data('key')); })[0];
            if (!b) { return; }
            b.name = row.find('.b-name').val();
            b.start = row.find('.b-start').val();
            b.end = row.find('.b-end').val();
            b.default = row.find('.b-default').is(':checked');
            row.find('.b-len').text(hours(minutesOf(b)));
            if (e.type === 'change') { rememberShifts(); drawShifts(); }   // a field finished: refresh the shift choices
        });

        $('#break-rows').on('click', '.b-remove', function () {
            var key = String($(this).closest('tr').data('key'));
            rememberShifts();
            breaks = breaks.filter(function (b) { return b.key !== key; });
            shifts.forEach(function (sh) { if (sh.selected) { sh.selected = sh.selected.filter(function (k) { return k !== key; }); } });
            drawBreaks(); drawShifts();
        });

        $('#add-break').on('click', function () {
            rememberShifts();
            breaks.push({ key: 'new' + (++newKey), name: '', start: '', end: '', default: false });
            drawBreaks(); drawShifts();
            $('#break-rows tr:last .b-name').focus();
        });

        // A shift's own choice survives a redraw; untouched shifts keep "default".
        function rememberShifts() {
            $('#shift-rows tr').each(function () {
                var sh = shifts.filter(function (x) { return String(x.id) === String($(this).data('shift')); }, this)[0];
                if (sh && $(this).find('.s-custom').val() === '1') { sh.selected = $(this).find('.s-breaks').val() || []; }
            });
        }
        $('#shift-rows').on('changed.bs.select', '.s-breaks', function () {
            $(this).closest('td').find('.s-custom').val(1);
            totals();
        });
        $('#wh_work_hours').on('input change', totals);

        drawBreaks();
        drawShifts();

        $('#save-working-hours').on('click', function () {
            $.easyAjax({
                url: "{{ route('attendance-settings.update_working_hours') }}",
                container: '#working-hours-form',
                type: 'POST',
                blockUI: true,
                disableButton: true,
                buttonSelector: '#save-working-hours',
                data: $('#working-hours-form').serialize(),
                success: function (r) {
                    if (r.status === 'success') {
                        window.location.href = "{{ route('attendance-settings.index') }}?tab=working-hours";
                    }
                }
            });
        });
    });
</script>
