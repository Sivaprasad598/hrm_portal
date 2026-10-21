@php
    $counts = $preview['counts'];
    $fmt = company()->date_format;
    $d = fn ($ymd) => $ymd ? \Carbon\Carbon::parse($ymd)->format($fmt) : '';
@endphp

<div class="d-flex flex-wrap mb-3 mt-3">
    <div class="border rounded px-3 py-2 mr-2 mb-2"><span class="f-12 text-dark-grey d-block">Rows</span><strong class="f-18">{{ $counts['total'] }}</strong></div>
    <div class="border rounded px-3 py-2 mr-2 mb-2 border-success"><span class="f-12 text-dark-grey d-block">Ready to import</span><strong class="f-18 text-success">{{ $counts['valid'] }}</strong></div>
    <div class="border rounded px-3 py-2 mr-2 mb-2 {{ $counts['invalid'] ? 'border-danger' : '' }}"><span class="f-12 text-dark-grey d-block">With errors (skipped)</span><strong class="f-18 {{ $counts['invalid'] ? 'text-danger' : '' }}">{{ $counts['invalid'] }}</strong></div>
    <div class="border rounded px-3 py-2 mb-2"><span class="f-12 text-dark-grey d-block">Already with employees</span><strong class="f-18 text-primary">{{ $counts['assigned'] }}</strong></div>
</div>

@if ($preview['unknown'])
    <p class="f-13 text-warning mb-2"><i class="fa fa-info-circle mr-1"></i>Columns not recognised, ignored: {{ implode(', ', $preview['unknown']) }}</p>
@endif

<div class="table-responsive" style="max-height:55vh;overflow:auto">
    <table class="table table-sm table-bordered f-12 mb-0">
        <thead class="bg-light" style="position:sticky;top:0;z-index:1">
            <tr>
                <th>Row</th><th>Asset</th><th>Code</th><th>Type</th><th>Serial</th><th>Branch</th><th>Condition</th>
                <th>Purchased</th><th>Cost</th><th>Warranty</th><th>With</th><th>Check</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($preview['rows'] as $row)
            <tr class="{{ $row['errors'] ? 'table-danger' : '' }}">
                <td>{{ $row['line'] }}</td>
                <td>{{ $row['name'] }}<br><span class="text-dark-grey">{{ trim(($row['brand'] ?? '') . ' ' . ($row['model'] ?? '')) }}</span></td>
                <td>{{ $row['asset_code'] ?: 'auto' }}</td>
                <td>{{ $row['type'] ?: '--' }}</td>
                <td>{{ $row['serial_number'] ?: '--' }}</td>
                <td>{{ $row['branch'] ?: '--' }}</td>
                <td>{{ \App\Models\Asset::CONDITIONS[$row['condition']] ?? '--' }}</td>
                <td>{{ $d($row['purchase_date']) }}</td>
                <td>{{ $row['purchase_cost'] !== null ? number_format($row['purchase_cost'], 2) : '' }}</td>
                <td>{{ $d($row['warranty_until']) }}</td>
                <td>{{ $row['employee_name'] ? $row['employee_name'] . ' (' . $row['employee_code'] . '), ' . $d($row['assigned_on']) : ($row['employee_code'] ?: '--') }}</td>
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

<form id="asset-import-run" class="mt-3">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    @if ($counts['assigned'])
        <div class="custom-control custom-checkbox mb-3">
            <input type="checkbox" class="custom-control-input" id="import_notify" name="notify" value="1">
            <label class="custom-control-label f-14" for="import_notify">Email the {{ $counts['assigned'] }} employee(s) about the asset(s) they hold</label>
        </div>
    @endif
</form>

<div class="d-flex align-items-center">
    @if ($counts['valid'])
        <x-forms.button-primary id="run-import" class="mr-3" icon="check">Import {{ $counts['valid'] }} Asset(s)</x-forms.button-primary>
    @endif
    <x-forms.button-secondary id="import-again" class="mr-3">Upload Another File</x-forms.button-secondary>
    @if ($counts['invalid'])
        <span class="f-13 text-danger">Rows with errors are skipped. Fix them in the sheet and upload again to include them.</span>
    @endif
</div>

<script>
    $('#run-import').on('click', function () {
        assetPanelSubmit('asset-import-run', '{{ route('assets.import.run') }}', 'run-import');
    });

    $('#import-again').on('click', function () {
        $('#import-review').html('');
        $('#import-upload').removeClass('d-none');
    });
</script>
