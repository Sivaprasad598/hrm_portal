{{-- The checked rows of a past-OT spreadsheet (OtPastController::importPreview). Nothing is saved yet. --}}
@php
    $counts = $preview['counts'];
    $h = fn ($v) => $v === null || (float) $v == 0 ? '' : \App\Services\Payroll\OtBank::fmt((float) $v) . 'h';
@endphp

<div class="d-flex flex-wrap mb-3">
    <div class="border rounded px-3 py-2 mr-2 mb-2"><span class="f-12 text-dark-grey d-block">Rows</span><strong class="f-18">{{ $counts['total'] }}</strong></div>
    <div class="border rounded px-3 py-2 mr-2 mb-2 border-success"><span class="f-12 text-dark-grey d-block">Ready to import</span><strong class="f-18 text-success">{{ $counts['valid'] }}</strong></div>
    <div class="border rounded px-3 py-2 mr-2 mb-2 {{ $counts['invalid'] ? 'border-danger' : '' }}"><span class="f-12 text-dark-grey d-block">With errors (skipped)</span><strong class="f-18 {{ $counts['invalid'] ? 'text-danger' : '' }}">{{ $counts['invalid'] }}</strong></div>
    <div class="border rounded px-3 py-2 mr-2 mb-2"><span class="f-12 text-dark-grey d-block">Employees</span><strong class="f-18">{{ $counts['employees'] }}</strong></div>
    <div class="border rounded px-3 py-2 mb-2"><span class="f-12 text-dark-grey d-block">Net hours added</span><strong class="f-18 text-primary">{{ \App\Services\Payroll\OtBank::fmt($counts['hours']) }}h</strong></div>
</div>

@if ($preview['unknown'])
    <p class="f-13 text-warning mb-2"><i class="fa fa-info-circle mr-1"></i>Columns not recognised, ignored: {{ implode(', ', $preview['unknown']) }}</p>
@endif

<div class="table-responsive" style="max-height:55vh;overflow:auto">
    <table class="table table-sm table-bordered f-12 mb-0">
        <thead class="bg-light" style="position:sticky;top:0;z-index:1">
            <tr>
                <th>Row</th><th>Employee</th><th>Month / day</th><th class="text-right">Banked</th><th class="text-right">Worked</th>
                <th class="text-right">Used for LOP</th><th class="text-right">Encashed</th><th class="text-right">Net</th><th>Note</th><th>Check</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($preview['rows'] as $row)
            @php $v = $row['values']; @endphp
            <tr class="{{ $row['errors'] ? 'table-danger' : '' }}">
                <td>{{ $row['line'] }}</td>
                <td>{{ $row['name'] ?: '--' }}<br><span class="text-dark-grey">{{ $row['code'] }}</span></td>
                <td class="text-nowrap">
                    @if ($row['date'])
                        {{ \Carbon\Carbon::parse($row['date'])->format(company()->date_format) }}<br><span class="text-dark-grey">day of {{ $row['label'] ?? '' }}</span>
                    @else
                        {{ $row['label'] ?? '--' }}
                    @endif
                </td>
                <td class="text-right">{{ $h($v['ot_hours'] ?? null) }}</td>
                <td class="text-right">{{ $h($v['raw_hours'] ?? null) }}</td>
                <td class="text-right">{{ $h($v['used_hours'] ?? null) }}@if (!empty($v['used_days']))<br><span class="text-dark-grey">{{ \App\Services\Payroll\OtBank::fmt($v['used_days']) }} day(s)</span>@endif</td>
                <td class="text-right">{{ $h($v['encashed_hours'] ?? null) }}@if (!empty($v['encashed_amount']))<br><span class="text-dark-grey">₹{{ number_format($v['encashed_amount'], 2) }}</span>@endif</td>
                <td class="text-right font-weight-600 {{ $row['net'] < 0 ? 'text-red' : '' }}">{{ $v ? \App\Services\Payroll\OtBank::fmt($row['net']) . 'h' : '' }}</td>
                <td>{{ $v['note'] ?? '' }}</td>
                <td>
                    @foreach ($row['errors'] as $error)
                        <div class="text-danger"><i class="fa fa-times-circle mr-1"></i>{{ $error }}</div>
                    @endforeach
                    @foreach ($row['hints'] as $note)
                        <div class="text-dark-grey"><i class="fa fa-info-circle mr-1"></i>{{ $note }}</div>
                    @endforeach
                    @if (!$row['errors'] && !$row['hints'])
                        <span class="text-success"><i class="fa fa-check-circle"></i> OK</span>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<form id="ot-import-run-form" class="mt-3">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
</form>

<div class="d-flex align-items-center">
    @if ($counts['valid'])
        <button type="button" class="btn btn-primary rounded f-14 mr-3" id="ot-import-run"><i class="fa fa-check mr-1"></i>Import {{ $counts['valid'] }} row(s)</button>
    @endif
    <button type="button" class="btn btn-secondary rounded f-14 mr-3" id="ot-import-again">Upload another file</button>
    @if ($counts['invalid'])
        <span class="f-13 text-danger">Rows with errors are skipped. Fix them in the sheet and upload again to include them.</span>
    @endif
</div>
