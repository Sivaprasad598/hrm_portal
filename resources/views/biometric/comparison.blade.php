@extends('layouts.app')

@section('content')
    {{-- Standard page gutter, matching the rest of the app --}}
    <div class="content-wrapper arw-dash">

        @include('biometric.nav', ['active' => $activeTab])

        {{-- Headline counts --}}
        <div class="row mb-3">
            @php
                $tiles = [
                    ['ERP Employees', $counts['erp'], 'text-dark-grey', 'with the employee role'],
                    ['Biometric Codes', $counts['devices'], 'text-dark-grey', 'enrolled on the devices'],
                    ['Matched', $counts['matched'], 'text-primary', 'attendance can be written'],
                    ['On Device Only', $counts['device_only'], $counts['device_only'] ? 'text-red' : 'text-dark-grey', 'not in the ERP'],
                ];
            @endphp

            @foreach ($tiles as [$label, $value, $colour, $sub])
                <div class="col-md-3 col-6 mb-2">
                    <div class="card bg-white border-0 b-shadow-4 h-100">
                        <div class="card-body p-3">
                            <p class="mb-1 f-12 text-lightest text-uppercase">{{ $label }}</p>
                            <h4 class="mb-0 f-21 {{ $colour }}">{{ number_format($value) }}</h4>
                            <p class="mb-0 f-11 text-lightest">{{ $sub }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="alert alert-info f-13">
            <strong>People are enrolled on the devices before they exist in the ERP.</strong>
            The devices know only a name and an enrolment code — no email, department, PAN or bank
            details, because the feed does not carry them. Creating someone here writes those two
            facts and leaves the rest blank for HR to complete; nothing is invented.
        </div>

        {{-- Section switcher --}}
        <ul class="nav nav-pills mb-3 f-13" id="section-tabs">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#tab-device">
                    On device only <span class="badge badge-light ml-1">{{ $counts['device_only'] }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#tab-matched">
                    Matched <span class="badge badge-light ml-1">{{ $counts['matched'] }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#tab-erp">
                    In ERP only <span class="badge badge-light ml-1">{{ $counts['erp_only'] }}</span>
                </a>
            </li>
        </ul>

        <div class="tab-content">

            {{-- ---------------- On device only ---------------- --}}
            <div class="tab-pane fade show active" id="tab-device">
                <div class="card bg-white border-0 b-shadow-4">
                    <div class="card-header bg-white border-bottom d-flex align-items-center flex-wrap p-3">
                        <div class="mr-auto">
                            <h6 class="mb-0 f-14">Enrolled on a device, not in the ERP</h6>
                            <p class="mb-0 f-12 text-lightest">
                                Their punches are stored and reported, but no attendance can be written
                                until they exist as ERP employees.
                            </p>
                        </div>

                        <input type="text" class="form-control form-control-sm w-auto mr-2" id="device-filter"
                               placeholder="Filter by name or code…">

                        @if ($canCreate)
                            <button type="button" class="btn btn-sm btn-primary" id="create-selected" disabled>
                                <i class="fa fa-user-plus mr-1"></i> Create selected
                            </button>
                        @endif
                    </div>

                    @if (!$canCreate)
                        <div class="alert alert-warning mb-0 f-12 rounded-0">
                            You do not have permission to add employees, so this list is read-only.
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-hover mb-0 f-13" id="device-table">
                            <thead>
                                <tr class="bg-light">
                                    @if ($canCreate)
                                        <th style="width:34px" class="text-center">
                                            <input type="checkbox" id="check-all">
                                        </th>
                                    @endif
                                    <th>Biometric code</th>
                                    <th>Name on device</th>
                                    <th class="text-right">Punches</th>
                                    <th class="text-right">Days</th>
                                    <th>First seen</th>
                                    <th>Last seen</th>
                                    <th style="width:130px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($deviceOnly as $row)
                                    <tr class="device-row" data-search="{{ strtolower($row['emp_code'] . ' ' . $row['employee_name']) }}">
                                        @if ($canCreate)
                                            <td class="text-center">
                                                @if ($row['creatable'])
                                                    <input type="checkbox" class="device-check"
                                                           value="{{ $row['device_id'] }}"
                                                           data-shared="{{ $row['looks_shared'] ? 1 : 0 }}">
                                                @endif
                                            </td>
                                        @endif
                                        <td><span class="font-weight-bold">{{ $row['emp_code'] }}</span></td>
                                        <td>
                                            {{ $row['employee_name'] ?? '—' }}
                                            @if ($row['looks_shared'])
                                                <span class="badge badge-warning ml-1"
                                                      title="Looks like a shared door or test account rather than a person">shared?</span>
                                            @endif
                                            @if ($row['ignored'])
                                                <span class="badge badge-secondary ml-1">ignored</span>
                                            @endif
                                        </td>
                                        <td class="text-right">{{ number_format($row['punches']) }}</td>
                                        <td class="text-right">{{ $row['attendance_days'] }}</td>
                                        <td class="f-12 text-lightest">{{ $row['first_seen'] ?? '—' }}</td>
                                        <td class="f-12 text-lightest">{{ $row['last_seen'] ?? '—' }}</td>
                                        <td>
                                            @if ($canCreate && $row['creatable'])
                                                <button type="button" class="btn btn-xs btn-outline-primary create-one"
                                                        data-id="{{ $row['device_id'] }}"
                                                        data-name="{{ $row['employee_name'] }}"
                                                        data-code="{{ $row['emp_code'] }}"
                                                        data-shared="{{ $row['looks_shared'] ? 1 : 0 }}">
                                                    <i class="fa fa-user-plus mr-1"></i>Create
                                                </button>
                                            @elseif ($row['ignored'])
                                                <span class="f-11 text-lightest">ignored</span>
                                            @else
                                                <span class="f-11 text-lightest">no name</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-lightest p-4">
                                            Every biometric code is linked to an ERP employee.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- ---------------- Matched ---------------- --}}
            <div class="tab-pane fade" id="tab-matched">
                <div class="card bg-white border-0 b-shadow-4">
                    <div class="card-header bg-white border-bottom p-3">
                        <h6 class="mb-0 f-14">Linked on both sides</h6>
                        <p class="mb-0 f-12 text-lightest">
                            These are the only people attendance can currently be written for.
                        </p>
                    </div>
                    <div class="table-responsive">
                        <table class="table mb-0 f-13">
                            <thead>
                                <tr class="bg-light">
                                    <th>ERP employee</th><th>Employee ID</th><th>Biometric code</th>
                                    <th>Name on device</th>
                                    <th class="text-right">Punches</th>
                                    <th class="text-right">Derived days</th>
                                    <th class="text-right">Written to attendance</th>
                                    <th>Last seen</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($matched as $row)
                                    <tr>
                                        <td class="font-weight-bold">{{ $row['name'] }}</td>
                                        <td>{{ $row['employee_id'] ?? '—' }}</td>
                                        <td><span class="badge badge-success">{{ $row['emp_code'] }}</span></td>
                                        <td class="f-12 text-lightest">{{ $row['device_name'] ?? '—' }}</td>
                                        <td class="text-right">{{ number_format($row['punches']) }}</td>
                                        <td class="text-right">{{ $row['attendance_days'] }}</td>
                                        <td class="text-right {{ $row['written'] ? '' : 'text-lightest' }}">{{ $row['written'] }}</td>
                                        <td class="f-12 text-lightest">{{ $row['last_seen'] ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="text-center text-lightest p-4">Nobody is linked yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- ---------------- In ERP only ---------------- --}}
            <div class="tab-pane fade" id="tab-erp">
                <div class="card bg-white border-0 b-shadow-4">
                    <div class="card-header bg-white border-bottom p-3">
                        <h6 class="mb-0 f-14">In the ERP, no biometric code</h6>
                        <p class="mb-0 f-12 text-lightest">
                            Either not enrolled on the devices, or enrolled under a name that does not
                            resemble their ERP name. Link them on
                            <a href="{{ route('biometric.employees') }}">Employee Mapping</a>.
                        </p>
                    </div>
                    <div class="table-responsive">
                        <table class="table mb-0 f-13">
                            <thead>
                                <tr class="bg-light">
                                    <th>Employee</th><th>Employee ID</th><th>Card ID</th><th>Email</th><th>Status</th><th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($erpOnly as $row)
                                    <tr>
                                        <td class="font-weight-bold">{{ $row['name'] }}</td>
                                        <td>{{ $row['employee_id'] ?? '—' }}</td>
                                        <td>{{ $row['employee_card_id'] ?? '—' }}</td>
                                        <td class="f-12 text-lightest">{{ $row['email'] ?? '—' }}</td>
                                        <td>
                                            <span class="badge badge-{{ $row['status'] === 'active' ? 'success' : 'secondary' }}">
                                                {{ ucfirst($row['status']) }}
                                            </span>
                                        </td>
                                        <td>
                                            <a href="{{ route('biometric.employees') }}"
                                               class="btn btn-xs btn-outline-secondary">
                                                <i class="fa fa-link mr-1"></i>Link
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-lightest p-4">
                                        Every ERP employee has a biometric code.
                                    </td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            var csrf = '{{ csrf_token() }}';
            var createRoute = "{{ route('biometric.comparison.create') }}";

            // ---- filter -----------------------------------------------------
            $('#device-filter').on('keyup', function () {
                var term = $(this).val().toLowerCase().trim();

                $('#device-table .device-row').each(function () {
                    $(this).toggle(term === '' || $(this).data('search').indexOf(term) !== -1);
                });
            });

            // ---- selection --------------------------------------------------
            function refreshButton() {
                var n = $('.device-check:checked').length;
                $('#create-selected')
                    .prop('disabled', n === 0)
                    .html('<i class="fa fa-user-plus mr-1"></i> Create selected' + (n ? ' (' + n + ')' : ''));
            }

            // Only rows still visible after filtering, so "select all" never
            // quietly picks up people who are filtered out of sight.
            $('#check-all').on('change', function () {
                var on = $(this).is(':checked');
                $('#device-table .device-row:visible').find('.device-check').prop('checked', on);
                refreshButton();
            });

            $(document).on('change', '.device-check', refreshButton);

            // ---- create -----------------------------------------------------
            function create(ids, description, sharedCount) {
                var warning = sharedCount > 0
                    ? '<p class="text-left mb-0" style="font-size:13px"><strong>' + sharedCount +
                      '</strong> of these look like shared door or test accounts rather than people. ' +
                      'Consider marking those as Ignored instead.</p>'
                    : '';

                Swal.fire({
                    title: 'Create ' + description + '?',
                    html: '<p class="mb-2">They will be created with a name and a biometric code only. ' +
                          'Department, designation, email, PAN and bank details stay blank until HR fills them in, ' +
                          'and they cannot sign in until then.</p>' + warning,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Create',
                    cancelButtonText: '@lang('app.cancel')'
                }).then(function (result) {
                    if (!result.isConfirmed) { return; }

                    runInBatches(ids);
                });
            }

            // People are created a handful at a time. One request for hundreds
            // of them looks like nothing is happening and can outlast the
            // server's time limit; in batches the screen can say how far it has
            // got, and a batch that fails does not lose the ones already done.
            var BATCH_SIZE = 10;

            function runInBatches(ids) {
                var total = ids.length, done = 0, created = 0, skipped = 0, reasons = [], stopped = null;

                Swal.fire({
                    title: 'Creating ' + total + ' employee' + (total === 1 ? '' : 's'),
                    html:
                        '<div class="progress mb-2" style="height:18px">' +
                        '  <div id="create-bar" class="progress-bar progress-bar-striped progress-bar-animated"' +
                        '       role="progressbar" style="width:0%">0%</div>' +
                        '</div>' +
                        '<p id="create-count" class="mb-0" style="font-size:13px">Starting…</p>',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: function () { nextBatch(); }
                });

                function paint() {
                    var pct = total ? Math.round((done / total) * 100) : 100;
                    $('#create-bar').css('width', pct + '%').text(pct + '%');
                    $('#create-count').html(
                        '<strong>' + created + '</strong> created' +
                        (skipped ? ' · <strong>' + skipped + '</strong> skipped' : '') +
                        ' · ' + done + ' of ' + total + ' processed'
                    );
                }

                function finish() {
                    var detail = reasons.slice(0, 5).map(function (r) {
                        return '<div class="text-left" style="font-size:12px">' + $('<div>').text(r).html() + '</div>';
                    }).join('');

                    if (reasons.length > 5) {
                        detail += '<div class="text-left" style="font-size:12px">and ' + (reasons.length - 5) + ' more</div>';
                    }

                    Swal.fire({
                        icon: stopped ? 'error' : (created ? 'success' : 'warning'),
                        title: stopped ? 'Stopped' : (created ? 'Created' : 'Nothing created'),
                        html: '<p class="mb-2" style="font-size:13px">' +
                              (stopped ? $('<div>').text(stopped).html() + '<br>' : '') +
                              '<strong>' + created + '</strong> created' +
                              (skipped ? ', <strong>' + skipped + '</strong> skipped' : '') +
                              ' out of ' + total + '.</p>' +
                              (created ? '<p class="mb-2" style="font-size:12px;color:#888">They have a name and a ' +
                                         'biometric code only — the rest still needs filling in by HR.</p>' : '') +
                              (detail ? '<p class="mb-1" style="font-size:12px"><strong>Skipped:</strong></p>' + detail : '')
                    }).then(function () { window.location.reload(); });
                }

                function nextBatch() {
                    if (done >= total) { return finish(); }

                    var batch = ids.slice(done, done + BATCH_SIZE);

                    $.post(createRoute, { ids: batch, _token: csrf })
                        .done(function (response) {
                            created += response.created || 0;
                            skipped += response.skipped || 0;
                            reasons = reasons.concat(response.reasons || []);
                            done += batch.length;
                            paint();
                            nextBatch();
                        })
                        .fail(function (xhr) {
                            // A refusal applies to every batch, so stop rather
                            // than repeating it; anything already created stands.
                            stopped = xhr.status === 403
                                ? 'You do not have permission to add employees.'
                                : ((xhr.responseJSON && xhr.responseJSON.message) || 'The server did not answer.');
                            finish();
                        });
                }
            }

            $(document).on('click', '.create-one', function () {
                var button = $(this);
                create([button.data('id')],
                    button.data('name') + ' (' + button.data('code') + ')',
                    button.data('shared') ? 1 : 0);
            });

            $('#create-selected').on('click', function () {
                var ids = [], shared = 0;

                $('.device-check:checked').each(function () {
                    ids.push($(this).val());
                    if ($(this).data('shared')) { shared++; }
                });

                if (!ids.length) { return; }

                create(ids, ids.length + ' employee' + (ids.length === 1 ? '' : 's'), shared);
            });

            refreshButton();
        });
    </script>
@endpush
