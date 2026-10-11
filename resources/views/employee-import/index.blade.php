@extends('layouts.app')

@push('styles')
    <style>
        .step-card { position: relative; }
        .step-num { width: 26px; height: 26px; border-radius: 50%; background: #6c5ffc; color: #fff;
                    display: inline-flex; align-items: center; justify-content: center; font-size: 13px; }
        .change-from { color: #8a94a6; text-decoration: line-through; }
        .change-to { color: #1d9e6f; font-weight: 600; }
        .import-table th, .import-table td { white-space: nowrap; }
        .import-table thead th { position: sticky; top: 0; background: #f8f9fa; z-index: 2; }
    </style>
@endpush

@section('content')
    <div class="content-wrapper">

        @if (session('employee-import.error'))
            <div class="alert alert-danger f-13">{{ session('employee-import.error') }}</div>
        @endif

        {{-- Why this screen exists, in numbers --}}
        <div class="card bg-white border-0 b-shadow-4 mb-3">
            <div class="card-body p-3">
                <h6 class="f-15 mb-1">Fill in salary and statutory details for everyone at once</h6>
                <p class="f-13 text-lightest mb-2">
                    Most employees came from the biometric devices, which know a name and a fingerprint and
                    nothing else. Payroll cannot value somebody without a salary, and the PF and ESI returns
                    cannot name somebody without their numbers.
                </p>
                <div class="row f-13">
                    @foreach ([
                        ['no salary on record', $missing['ctc']],
                        ['no employee code', $missing['code']],
                        ['no PAN', $missing['pan']],
                        ['no bank account', $missing['bank']],
                        ['no PF number', $missing['pf']],
                        ['no ESI number', $missing['esi']],
                    ] as [$label, $count])
                        <div class="col-md-2 col-4 mb-1">
                            <span class="{{ $count > 0 ? 'text-red font-weight-bold' : 'text-success' }}">{{ $count }}</span>
                            <span class="text-lightest d-block f-11">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="f-11 text-lightest mb-0 mt-2">of {{ $missing['employees'] }} active employees</p>
            </div>
        </div>

        @if (!$preview)
            {{-- ---------------------------------------------- steps 1 and 2 --}}
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="card bg-white border-0 b-shadow-4 h-100 step-card">
                        <div class="card-body p-3">
                            <p class="mb-2"><span class="step-num mr-2">1</span> <strong class="f-14">Take the sheet away</strong></p>
                            <p class="f-13 text-lightest">
                                Everyone on record, with what is already known filled in. The blanks are the work.
                                Open it in Excel, fill in what you have, and save it.
                            </p>
                            <a href="{{ route('employee-import.template') }}" class="btn btn-primary f-14">
                                <i class="fa fa-download mr-1"></i> Download the sheet
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-3">
                    <div class="card bg-white border-0 b-shadow-4 h-100 step-card">
                        <div class="card-body p-3">
                            <p class="mb-2"><span class="step-num mr-2">2</span> <strong class="f-14">Bring it back</strong></p>
                            <p class="f-13 text-lightest">
                                Nothing is written yet — the next screen shows exactly what would change,
                                field by field, and you decide.
                            </p>
                            <form method="POST" action="{{ route('employee-import.preview') }}" enctype="multipart/form-data">
                                @csrf
                                <div class="d-flex">
                                    <input type="file" name="sheet" class="form-control f-14 mr-2"
                                           accept=".xlsx,.xls,.csv" required>
                                    <button type="submit" class="btn btn-outline-primary f-14 flex-shrink-0">
                                        <i class="fa fa-upload mr-1"></i> Check it
                                    </button>
                                </div>
                                @error('sheet')
                                    <span class="text-red f-12 d-block mt-1">{{ $message }}</span>
                                @enderror
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card bg-white border-0 b-shadow-4">
                <div class="card-header bg-white border-bottom p-3">
                    <h6 class="mb-0 f-14">What the sheet can carry</h6>
                    <p class="mb-0 f-12 text-lightest">
                        A blank cell always means <strong>leave it as it is</strong> — the import never erases
                        what is on record. <strong>Ref</strong> is what each line is matched on, so leave it
                        alone. An employee code can be filled in where somebody has none, but not changed once
                        it is set — their payslips are filed under it.
                    </p>
                </div>
                <div class="card-body p-3">
                    @foreach (array_chunk(array_keys($columns), 6) as $chunk)
                        <div class="row f-13 mb-1">
                            @foreach ($chunk as $heading)
                                <div class="col-md-2 col-6">
                                    {{ $heading }}
                                    @if (in_array($heading, ['Ref', 'Name']))
                                        <span class="f-11 text-lightest">(read only)</span>
                                    @elseif ($heading === 'Employee Code')
                                        <span class="f-11 text-lightest">(fill in, cannot change)</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            {{-- ----------------------------------------------- step 3 --}}
            @php $s = $preview['summary']; @endphp

            <div class="card bg-white border-0 b-shadow-4 mb-3">
                <div class="card-header bg-white border-bottom d-flex align-items-center flex-wrap p-3">
                    <div class="mr-auto">
                        <p class="mb-1"><span class="step-num mr-2">3</span>
                            <strong class="f-14">What {{ $preview['file'] ?? 'that sheet' }} would change</strong></p>
                        <p class="mb-0 f-12 text-lightest">
                            Nothing has been written. Read it, then apply or discard.
                        </p>
                    </div>
                    <a href="{{ route('employee-import.discard') }}" class="btn btn-sm btn-outline-secondary mr-2">Discard</a>
                    <button type="button" class="btn btn-sm btn-primary" id="apply-import"
                            @disabled($s['changes'] < 1)>
                        <i class="fa fa-check mr-1"></i> Apply to {{ $s['changes'] }} employee(s)
                    </button>
                </div>
                <div class="card-body p-3">
                    <div class="row f-13">
                        @foreach ([
                            ['will change', $s['changes'], 'text-success'],
                            ['fields filled in', $s['fields'], 'text-dark-grey'],
                            ['already correct', $s['unchanged'], 'text-lightest'],
                            ['no such employee', $s['unmatched'], $s['unmatched'] ? 'text-red' : 'text-lightest'],
                            ['lines with a problem', $s['problems'], $s['problems'] ? 'text-red' : 'text-lightest'],
                        ] as [$label, $count, $colour])
                            <div class="col-md-2 col-4">
                                <span class="f-19 {{ $colour }}">{{ $count }}</span>
                                <span class="text-lightest d-block f-11">{{ $label }}</span>
                            </div>
                        @endforeach
                    </div>

                    @foreach ($preview['errors'] ?? [] as $error)
                        <p class="f-12 text-red mb-0 mt-2">
                            Line {{ $error['row'] }}{{ isset($error['column']) ? ', “' . $error['column'] . '”' : '' }}:
                            {{ $error['message'] }}
                        </p>
                    @endforeach
                </div>
            </div>

            <div class="card bg-white border-0 b-shadow-4">
                <div class="table-responsive" style="max-height: 62vh">
                    <table class="table table-hover mb-0 f-13 import-table">
                        <thead>
                            <tr>
                                <th>Line</th>
                                <th>@lang('app.employee')</th>
                                <th>What changes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($preview['rows'] as $row)
                                <tr class="{{ in_array($row['state'], ['unmatched', 'problem']) ? 'bg-light-red' : '' }}">
                                    <td class="text-lightest">{{ $row['line'] }}</td>
                                    <td>
                                        {{ $row['name'] ?: '—' }}
                                        <span class="f-11 text-lightest d-block">{{ $row['code'] ?: 'no code' }}</span>
                                    </td>
                                    <td>
                                        @if ($row['state'] === 'unchanged')
                                            <span class="text-lightest f-12">already matches what is on record</span>
                                        @elseif ($row['state'] === 'unmatched' || $row['state'] === 'problem')
                                            @foreach ($row['problems'] as $problem)
                                                <div class="text-red f-12">{{ $problem }}</div>
                                            @endforeach
                                        @else
                                            @foreach ($row['changes'] as $change)
                                                <div class="f-12">
                                                    <span class="text-lightest">{{ $change['column'] }}:</span>
                                                    <span class="change-from">{{ $change['from'] }}</span>
                                                    <i class="fa fa-arrow-right f-10 mx-1 text-lightest"></i>
                                                    <span class="change-to">{{ $change['to_shown'] }}</span>
                                                </div>
                                            @endforeach
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-lightest p-4 f-13">
                                        No rows could be read from that sheet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            $('#apply-import').on('click', function () {
                var button = $(this);

                Swal.fire({
                    title: 'Apply these changes?',
                    text: 'Only the lines shown as changing are written. Nothing else is touched.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Apply',
                    cancelButtonText: '@lang('app.cancel')'
                }).then(function (result) {
                    if (!result.isConfirmed) { return; }

                    button.prop('disabled', true);

                    $.post('{{ route('employee-import.apply') }}', { _token: '{{ csrf_token() }}' })
                        .done(function (response) {
                            Swal.fire({
                                icon: response.status === 'success' ? 'success' : 'error',
                                title: response.message
                            }).then(function () { window.location.href = '{{ route('employee-import.index') }}'; });
                        })
                        .fail(function (xhr) {
                            button.prop('disabled', false);
                            Swal.fire({
                                icon: 'error',
                                title: (xhr.responseJSON && xhr.responseJSON.message) || 'That did not work'
                            });
                        });
                });
            });
        });
    </script>
@endpush
