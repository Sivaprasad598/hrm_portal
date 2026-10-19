{{-- The HR master sheet, read and not yet saved: what would be created, every
     employee row with what was cleaned or could not be used, and the device
     links suggested by name. --}}
@php
    $m = $preview['master'];
    $s = $preview['summary'];
    $records = $preview['records'];
    $newDepartments = count(array_filter($m['departments'], fn ($d) => !$d['id'])) + count(array_filter($m['divisions'], fn ($d) => !$d['id']));
    $newDesignations = count(array_filter($m['designations'], fn ($d) => !$d['id']));
    $newBranches = array_filter($m['branches'], fn ($b) => !$b['id']);
    $money = fn ($v) => $v === null ? '—' : number_format((float) $v);
    $date = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('d-m-Y') : '—';
@endphp

<style>
    .imp-tile { border: 1px solid #e8eef3; border-radius: 6px; padding: 10px 14px; }
    .imp-tile .n { font-size: 20px; font-weight: 600; }
    .imp-tree li { list-style: none; }
    .imp-table td, .imp-table th { font-size: 12px; vertical-align: top; padding: 6px 8px; }
    .imp-issue { color: #d92d20; }
    .imp-note { color: #667085; }
    .imp-scroll { max-height: 520px; overflow: auto; }
    .imp-scroll thead th { position: sticky; top: 0; background: #f9fafb; z-index: 1; }
</style>

<div class="row w-100 mx-0" id="import-review">
    <div class="col-sm-12">
        <div class="add-client bg-white rounded">
            <h4 class="mb-0 p-20 f-21 font-weight-normal border-bottom-grey">
                Review import — {{ $preview['file'] }} <span class="f-13 text-lightest">(sheet "{{ $preview['sheet'] }}")</span>
            </h4>

            <div class="p-20">
                <div class="row">
                    @foreach ([
                        ['Rows read', $s['rows'], ''],
                        ['New employees', $s['new'], 'text-primary'],
                        ['Existing, updated', $s['update'], 'text-dark-grey'],
                        ['Clean rows', $s['clean'], 'text-success'],
                        ['Rows with something to fix', $s['with_issues'], 'text-warning'],
                        ['No email (cannot sign in yet)', $s['no_email'], 'text-red'],
                    ] as [$label, $value, $class])
                        <div class="col-lg-2 col-md-4 col-6 mb-2">
                            <div class="imp-tile"><div class="f-12 text-lightest">{{ $label }}</div><div class="n {{ $class }}">{{ $value }}</div></div>
                        </div>
                    @endforeach
                </div>

                <p class="f-13 text-dark-grey mt-2 mb-3">
                    Will create <strong>{{ $newDepartments }}</strong> department(s), <strong>{{ $newDesignations }}</strong> designation(s)
                    and up to <strong>{{ count($newBranches) }}</strong> branch(es). Nothing has been saved yet.
                    A value that could not be used is left empty and listed under that employee; the rest of the row is imported.
                    <a href="{{ route('employees.import.report') }}" class="ml-1"><i class="fa fa-download"></i> Download the list of problems (Excel)</a>
                </p>

                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item"><a class="nav-link active f-14" data-toggle="tab" href="#imp-employees">Employees ({{ count($records) }})</a></li>
                    <li class="nav-item"><a class="nav-link f-14" data-toggle="tab" href="#imp-departments">Departments ({{ count($m['departments']) + count($m['divisions']) }})</a></li>
                    <li class="nav-item"><a class="nav-link f-14" data-toggle="tab" href="#imp-designations">Designations ({{ count($m['designations']) }})</a></li>
                    <li class="nav-item"><a class="nav-link f-14" data-toggle="tab" href="#imp-branches">Branches ({{ count($m['branches']) }})</a></li>
                </ul>

                <form id="import-apply-form">
                    @csrf
                    <div class="tab-content">

                        {{-- Employees --}}
                        <div class="tab-pane fade show active" id="imp-employees">
                            <div class="d-flex flex-wrap align-items-center mb-2">
                                @foreach (['all' => 'All', 'new' => 'New', 'update' => 'Updates', 'issues' => 'Something to fix', 'clean' => 'Clean'] as $key => $label)
                                    <button type="button" class="btn btn-sm {{ $key === 'all' ? 'btn-primary' : 'btn-outline-secondary' }} mr-2 mb-1 imp-filter" data-filter="{{ $key }}">{{ $label }}</button>
                                @endforeach
                                <input type="text" class="form-control form-control-sm ml-auto mb-1" id="imp-search" placeholder="Search name or EMP ID…" style="max-width:240px">
                            </div>
                            <div class="imp-scroll border rounded">
                                <table class="table table-hover imp-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Line</th><th>EMP ID</th><th>Employee</th><th>Department</th><th>Designation</th><th>Branch</th>
                                            <th>DOJ</th><th class="text-right">CTC / year</th><th>Email (sign-in)</th><th>Checks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($records as $r)
                                            @php
                                                $state = $r['skip'] ? 'skip' : ($r['match'] ? 'update' : 'new');
                                                $branchName = isset($r['branch_key']) ? ($m['branches'][$r['branch_key']]['name'] ?? $r['branch']) : $r['branch'];
                                            @endphp
                                            <tr class="imp-row" data-state="{{ $state }}" data-issues="{{ $r['issues'] ? 1 : 0 }}"
                                                data-search="{{ strtolower($r['code'] . ' ' . $r['name']) }}">
                                                <td>{{ $r['line'] }}</td>
                                                <td class="text-nowrap">{{ $r['code'] }}</td>
                                                <td>
                                                    <span class="f-w-500">{{ $r['name'] }}</span>
                                                    @if ($r['match'])
                                                        <div class="badge badge-light border f-10">updates {{ $r['match']['name'] }} (by {{ $r['match']['by'] }})</div>
                                                    @elseif ($r['skip'])
                                                        <div class="badge badge-danger f-10">not imported</div>
                                                    @endif
                                                    @if ($r['rejoined'])
                                                        <div class="badge badge-info f-10">rejoined{{ $r['previous_joining_date'] ? ' · first joined ' . $date($r['previous_joining_date']) : '' }}</div>
                                                    @endif
                                                </td>
                                                <td>{{ $r['division'] ?: '—' }}<div class="text-lightest">{{ $r['department'] }}</div></td>
                                                <td>{{ isset($r['designation_key']) ? $m['designations'][$r['designation_key']]['name'] : '—' }}</td>
                                                <td>{{ $branchName ?: '—' }}</td>
                                                <td class="text-nowrap">{{ $date($r['joining_date']) }}</td>
                                                <td class="text-right">{{ $money($r['ctc']) }}</td>
                                                <td>{!! $r['email'] ? e($r['email']) : '<span class="text-red">none</span>' !!}</td>
                                                <td style="min-width:260px">
                                                    @foreach ($r['issues'] as $i)
                                                        <div class="imp-issue"><i class="fa fa-exclamation-circle"></i> <strong>{{ $i['field'] }}</strong>: {{ $i['message'] }}</div>
                                                    @endforeach
                                                    @foreach ($r['notes'] as $n)
                                                        <div class="imp-note"><i class="fa fa-check"></i> <strong>{{ $n['field'] }}</strong>: {{ $n['message'] }}</div>
                                                    @endforeach
                                                    @if (!$r['issues'] && !$r['notes'])
                                                        <span class="text-success"><i class="fa fa-check-circle"></i> OK</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Departments: parent department, and the departments under it --}}
                        <div class="tab-pane fade" id="imp-departments">
                            <p class="f-12 text-lightest">The second Department column is the parent; the first is the employee's department under it. Spellings that differ only in case are one department.</p>
                            <ul class="imp-tree pl-0 f-13">
                                @foreach ($m['departments'] as $key => $d)
                                    <li class="mb-2">
                                        <span class="badge {{ $d['id'] ? 'badge-light border' : 'badge-primary' }} f-10 mr-1">{{ $d['id'] ? 'exists' : 'new' }}</span>
                                        <strong>{{ $d['name'] }}</strong> <span class="text-lightest">({{ $d['count'] }})</span>
                                        <ul class="pl-4 mt-1">
                                            @foreach ($m['divisions'] as $v)
                                                @if ($v['department'] === $key)
                                                    <li>
                                                        └ <span class="badge {{ $v['id'] ? 'badge-light border' : 'badge-primary' }} f-10 mr-1">{{ $v['id'] ? 'exists' : 'new' }}</span>
                                                        {{ $v['name'] }} <span class="text-lightest">({{ $v['count'] }}){{ count($v['variants']) > 1 ? ' — from ' . implode(' / ', array_keys($v['variants'])) : '' }}</span>
                                                    </li>
                                                @endif
                                            @endforeach
                                        </ul>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        {{-- Designations --}}
                        <div class="tab-pane fade" id="imp-designations">
                            <p class="f-12 text-lightest">Spellings that differ only in case, dots, spaces or Jr/Junior, Sr/Senior, Modeller/Modeler are one designation. Rename any of them afterwards on HR → Designation.</p>
                            <div class="imp-scroll border rounded">
                                <table class="table imp-table mb-0">
                                    <thead><tr><th></th><th>Designation</th><th class="text-right">Employees</th><th>Spellings in the sheet</th></tr></thead>
                                    <tbody>
                                        @foreach ($m['designations'] as $d)
                                            <tr>
                                                <td><span class="badge {{ $d['id'] ? 'badge-light border' : 'badge-primary' }} f-10">{{ $d['id'] ? 'exists' : 'new' }}</span></td>
                                                <td class="f-w-500">{{ $d['name'] }}</td>
                                                <td class="text-right">{{ $d['count'] }}</td>
                                                <td class="text-lightest">{{ count($d['variants']) > 1 ? implode(' / ', array_keys($d['variants'])) : '' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Branches --}}
                        <div class="tab-pane fade" id="imp-branches">
                            <p class="f-12 text-lightest">"Murali Nagar" is the Visakhapatnam branch. Untick a new branch to leave it for later: its employees are imported without a branch.</p>
                            <table class="table imp-table">
                                <thead><tr><th>Create</th><th>Branch</th><th class="text-right">Employees</th><th>Written in the sheet as</th></tr></thead>
                                <tbody>
                                    @foreach ($m['branches'] as $key => $b)
                                        <tr>
                                            <td>
                                                @if ($b['id'])
                                                    <span class="badge badge-light border f-10">exists</span>
                                                @else
                                                    <input type="checkbox" name="branches[]" value="{{ $key }}" checked> <span class="badge badge-primary f-10">new</span>
                                                @endif
                                            </td>
                                            <td class="f-w-500">{{ $b['name'] }}</td>
                                            <td class="text-right">{{ $b['count'] }}</td>
                                            <td class="text-lightest">{{ implode(', ', array_keys($b['sheet_names'])) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                    </div>

                    <div class="row mt-4">
                        <div class="col-md-4">
                            <label class="f-14 text-dark-grey mb-1" for="import-password">Password for new accounts</label>
                            <input type="text" class="form-control height-35 f-14" id="import-password" name="password" value="{{ $defaultPassword }}" autocomplete="off">
                            <p class="f-11 text-lightest mt-1">Every new employee with an email signs in with this. No welcome email is sent. Existing employees keep their password.</p>
                        </div>
                    </div>
                </form>
            </div>

            <x-form-actions>
                <x-forms.button-primary id="import-apply" class="mr-3" icon="check">
                    Import {{ $s['new'] + $s['update'] }} employee(s)
                </x-forms.button-primary>
                <x-forms.button-cancel :link="route('employees.import')" class="border-0 openRightModal">Upload a different file</x-forms.button-cancel>
            </x-form-actions>
        </div>
    </div>
</div>

<script>
    $(function () {
        var filter = 'all';

        function apply() {
            var term = ($('#imp-search').val() || '').toLowerCase().trim();

            $('.imp-row').each(function () {
                var row = $(this), state = row.data('state'), issues = row.data('issues') == 1;
                var show = filter === 'all'
                    || (filter === 'new' && state === 'new')
                    || (filter === 'update' && state === 'update')
                    || (filter === 'issues' && issues)
                    || (filter === 'clean' && !issues && state !== 'skip');

                row.toggle(show && (!term || String(row.data('search')).indexOf(term) !== -1));
            });
        }

        $('.imp-filter').on('click', function () {
            filter = $(this).data('filter');
            $('.imp-filter').removeClass('btn-primary').addClass('btn-outline-secondary');
            $(this).removeClass('btn-outline-secondary').addClass('btn-primary');
            apply();
        });
        $('#imp-search').on('input', apply);

        $('#import-apply').on('click', function () {
            var button = $(this);

            Swal.fire({
                title: 'Import {{ $s['new'] }} new and update {{ $s['update'] }} existing employee(s)?',
                text: 'Departments, designations and the ticked branches are created. It takes a minute or two.',
                showCancelButton: true,
                confirmButtonText: 'Import',
                customClass: { confirmButton: 'btn btn-primary mr-2', cancelButton: 'btn btn-secondary' },
                buttonsStyling: false
            }).then(function (r) {
                if (!r.isConfirmed) { return; }

                $.easyAjax({
                    url: "{{ route('employees.import.apply') }}",
                    container: '#import-apply-form',
                    type: 'POST',
                    disableButton: true,
                    blockUI: true,
                    buttonSelector: '#import-apply',
                    data: $('#import-apply-form').serialize(),
                    success: function (response) {
                        if (response.status === 'success') {
                            $('#import-review').replaceWith(response.view);
                        }
                    }
                });
            });
        });
    });
</script>
