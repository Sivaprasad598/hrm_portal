{{-- Past OT for one employee and year: a line per salary month (OtPastController::year). --}}
@php
    $num = fn ($v) => $v === null || (float) $v == 0 ? '' : \App\Services\Payroll\OtBank::fmt((float) $v);
@endphp

<form id="ot-past-year-form">
    @csrf
    <input type="hidden" name="user_id" value="{{ $employee->id }}">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
        <div>
            <h5 class="f-16 mb-0">{{ $employee->name }} <span class="f-13 text-lightest">{{ $employee->employee_id }}</span></h5>
            <span class="f-12 text-dark-grey">Bank hours each month, as kept before the system: already times the rate. 12.5 or 12:30.</span>
        </div>
        <div class="d-flex align-items-center">
            <label class="f-13 text-dark-grey mb-0 mr-2">Year</label>
            <select name="year" id="ot-past-year" class="form-control form-control-sm" style="width:100px">
                @foreach ($years as $y)<option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>@endforeach
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-sm table-bordered f-13 mb-0 ot-past-grid">
            <thead class="bg-light">
                <tr>
                    <th>Salary month</th>
                    <th class="text-right" style="width:105px">OT banked <span class="text-red">*</span></th>
                    <th class="text-right" style="width:95px">OT worked</th>
                    <th class="text-right" style="width:95px">Used for LOP (h)</th>
                    <th class="text-right" style="width:80px">LOP days</th>
                    <th class="text-right" style="width:95px">Encashed (h)</th>
                    <th class="text-right" style="width:105px">Amount paid</th>
                    <th>Note</th>
                    <th class="text-right" style="width:70px">Net</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($grid as $m => $g)
                @php
                    $p = \App\Services\Payroll\PayPeriod::forMonth($m, $year);
                    $r = $g['row'];
                    $closed = $problems[$m] ?? null;
                @endphp
                <tr class="{{ $closed || $g['day_count'] ? 'bg-light' : '' }}">
                    <td class="text-nowrap">
                        <strong>{{ \Carbon\Carbon::create($year, $m, 1)->format('M Y') }}</strong>
                        <br><span class="f-11 text-lightest">{{ $p->start->format('d M') }} – {{ $p->end->format('d M') }}</span>
                    </td>
                    @if ($g['day_count'])
                        <td colspan="8" class="text-dark-grey">Entered day by day: {{ $g['day_count'] }} day(s), {{ \App\Services\Payroll\OtBank::fmt($g['day_hours']) }}h. Change it in the list of days.</td>
                    @elseif ($closed && !$r)
                        <td colspan="8" class="text-lightest">{{ $closed }}</td>
                    @else
                        @foreach (['ot_hours', 'raw_hours', 'used_hours', 'used_days', 'encashed_hours'] as $f)
                            <td><input type="text" inputmode="decimal" class="form-control form-control-sm text-right op-{{ $f }}" name="months[{{ $m }}][{{ $f }}]" value="{{ $num($r->$f ?? null) }}" @disabled($closed)></td>
                        @endforeach
                        <td><input type="text" inputmode="decimal" class="form-control form-control-sm text-right" name="months[{{ $m }}][encashed_amount]" value="{{ $r && $r->encashed_amount !== null ? $r->encashed_amount : '' }}" @disabled($closed)></td>
                        <td><input type="text" class="form-control form-control-sm" maxlength="255" name="months[{{ $m }}][note]" value="{{ $r->note ?? '' }}" @disabled($closed)></td>
                        <td class="text-right font-weight-600 op-net"></td>
                    @endif
                </tr>
            @endforeach
            </tbody>
            <tfoot>
                <tr class="font-weight-bold">
                    <td>{{ $year }}</td>
                    <td class="text-right" id="op-sum-ot_hours"></td><td></td>
                    <td class="text-right" id="op-sum-used_hours"></td><td></td>
                    <td class="text-right" id="op-sum-encashed_hours"></td><td></td><td></td>
                    <td class="text-right" id="op-sum-net"></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <p class="f-12 text-lightest mt-2 mb-0">
        Net = banked − used − encashed; it adds to the balance. Empty a month to remove it. The system counts overtime from
        {{ $goLive->format('d M Y') }}, so months from then on are closed here.
    </p>
</form>

<script>
    $(function () {
        function n(v) {
            v = String(v || '').replace(/,/g, '').trim();
            var t = v.match(/^(\d+):([0-5]\d)$/);
            return t ? parseInt(t[1], 10) + parseInt(t[2], 10) / 60 : (parseFloat(v) || 0);
        }
        function f(v) { return v ? (Math.round(v * 100) / 100) + 'h' : ''; }

        function sums() {
            var tot = { ot_hours: 0, used_hours: 0, encashed_hours: 0, net: 0 };
            $('#ot-past-year-form tbody tr').each(function () {
                if (!$(this).find('.op-ot_hours').length) { return; }
                var ot = n($(this).find('.op-ot_hours').val()), used = n($(this).find('.op-used_hours').val()), enc = n($(this).find('.op-encashed_hours').val());
                tot.ot_hours += ot; tot.used_hours += used; tot.encashed_hours += enc; tot.net += ot - used - enc;
                $(this).find('.op-net').text(f(ot - used - enc)).toggleClass('text-red', ot - used - enc < 0);
            });
            $.each(tot, function (k, v) { $('#op-sum-' + k).text(f(v) || '0h'); });
        }

        $('#ot-past-year-form').on('input', 'input', sums);
        sums();
    });
</script>
