@extends('layouts.app')

@section('content')
    {{-- Standard page gutter, matching the rest of the app --}}
    <div class="content-wrapper arw-dash">

        @include('biometric.nav', ['active' => $activeTab])

        <div class="alert alert-info f-13">
            <strong>This writes to the ERP's own attendance.</strong>
            Device punches are replayed through the same importer your Excel upload uses, so the
            sessions, late marks and half-day marks come out identical — you just stop doing the
            export by hand. Nothing is written until you review the preview and confirm.
        </div>

        {{-- Mapping roll call. Rendered server-side so the state is visible before
             anything is clicked, then kept current by the preview. --}}
        @php
            $unmappedCount = count($mapping['unmapped']);
            $mappedCount = count($mapping['mapped_users']);
            $employeeCount = count($mapping['employees']);
        @endphp

        <div class="alert alert-{{ $unmappedCount > 0 ? 'warning' : 'success' }} f-13" id="mapping-alert">
            <div class="d-flex align-items-start flex-wrap">
                <div class="mr-auto">
                    <strong id="mapping-headline">
                        @if ($unmappedCount > 0)
                            {{ $unmappedCount }} of {{ $employeeCount }} employees are not linked to a biometric code.
                        @else
                            All {{ $employeeCount }} employees are linked to a biometric code.
                        @endif
                    </strong>
                    <p class="mb-0 mt-1" id="mapping-detail">
                        @if ($unmappedCount > 0)
                            Their punches cannot be matched, so no attendance will be written for them.
                            Map them first for a complete sync.
                        @else
                            Every employee can receive attendance from the devices.
                        @endif
                    </p>
                </div>

                @if ($unmappedCount > 0)
                    <a href="{{ route('biometric.employees') }}" class="btn btn-sm btn-warning ml-2">
                        <i class="fa fa-link mr-1"></i> Map employees
                    </a>
                @endif
            </div>

            @if ($unmappedCount > 0)
                <div class="mt-2 d-flex flex-wrap" id="unmapped-list">
                    @foreach ($mapping['unmapped'] as $employee)
                        <span class="badge badge-light border mr-1 mb-1 f-11">
                            {{ $employee->name }}
                            @if ($employee->employee_card_id)
                                <span class="text-lightest">· {{ $employee->employee_card_id }}</span>
                            @endif
                        </span>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Range and options --}}
        <div class="card bg-white border-0 b-shadow-4 mb-3">
            <div class="card-body p-3">
                {{-- Every column is label / control / hint, in that order and at
                     the same heights, so the controls sit on one line whatever
                     length the hints run to. The checkbox and the button carry
                     an empty label for the same reason. --}}
                <div class="form-row sync-filters">
                    <div class="col-md-2">
                        <label class="f-12 text-lightest">Start date</label>
                        <input type="date" class="form-control form-control-sm" id="start-date" value="{{ $startDate }}">
                        <span class="filter-hint"></span>
                    </div>

                    <div class="col-md-2">
                        <label class="f-12 text-lightest">End date</label>
                        <input type="date" class="form-control form-control-sm" id="end-date" value="{{ $endDate }}">
                        <span class="filter-hint"></span>
                    </div>

                    <div class="col-md-3">
                        <label class="f-12 text-lightest" for="employees">Employees</label>
                        <select class="form-control form-control-sm select-picker" id="employees" multiple
                                data-live-search="true" data-actions-box="true" data-selected-text-format="count > 1">
                            @foreach ($mapping['employees'] as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                            @endforeach
                        </select>
                        <span class="filter-hint">Leave empty for everyone mapped.</span>
                    </div>

                    <div class="col-md-3">
                        <label class="f-12 text-lightest filter-spacer">&nbsp;</label>
                        <div class="custom-control custom-checkbox filter-control">
                            <input type="checkbox" class="custom-control-input" id="collapse" checked>
                            <label class="custom-control-label f-12" for="collapse">
                                Collapse duplicate gate swipes
                            </label>
                        </div>
                        <span class="filter-hint">
                            Controllers report one swipe once per gate. Leave this on unless you
                            want the raw feed.
                        </span>
                    </div>

                    <div class="col-md-2">
                        <label class="f-12 text-lightest filter-spacer">&nbsp;</label>
                        <button type="button" class="btn btn-sm btn-primary btn-block filter-control" id="preview-btn">
                            <i class="fa fa-search mr-1"></i> Preview
                        </button>
                        <span class="filter-hint"></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Preview --}}
        <div class="card bg-white border-0 b-shadow-4 mb-3" id="preview-card" style="display:none">
            <div class="card-header bg-white border-bottom d-flex align-items-center flex-wrap p-3">
                <div class="mr-auto f-13" id="preview-summary"></div>
                <button type="button" class="btn btn-sm btn-success" id="sync-btn">
                    <i class="fa fa-download mr-1"></i> Sync to attendance
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0 f-13">
                    <thead>
                        <tr class="bg-light">
                            <th style="width:34px" class="text-center">
                                <input type="checkbox" id="check-all" checked>
                            </th>
                            <th>Employee</th>
                            <th>Date</th>
                            <th class="text-center">Punches</th>
                            <th>First</th>
                            <th>Last</th>
                            <th>Existing attendance</th>
                            <th style="width:150px"></th>
                        </tr>
                    </thead>
                    <tbody id="preview-body"></tbody>
                </table>
            </div>
        </div>

        <div class="alert alert-secondary f-13" id="empty-state" style="display:none">
            No device punches for mapped employees in this range.
        </div>

        {{-- Recent runs --}}
        @if ($recentRuns->isNotEmpty())
            <div class="card bg-white border-0 b-shadow-4">
                <div class="card-header bg-white border-bottom p-3">
                    <h6 class="mb-0 f-14">Recent syncs</h6>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0 f-13">
                        <thead>
                            <tr class="bg-light">
                                <th>When</th><th>Range</th><th>Mode</th>
                                <th class="text-center">Created</th>
                                <th class="text-center">Replaced</th>
                                <th class="text-center">Skipped</th>
                                <th>By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentRuns as $run)
                                <tr>
                                    <td>{{ $run->created_at->format('d M Y H:i') }}</td>
                                    <td>{{ $run->from_date->format('d M') }} – {{ $run->to_date->format('d M Y') }}</td>
                                    <td>
                                        <span class="badge badge-{{ $run->conflict_mode === 'override' ? 'danger' : 'secondary' }}">
                                            {{ ucfirst($run->conflict_mode) }}
                                        </span>
                                    </td>
                                    <td class="text-center">{{ $run->rows_created }}</td>
                                    <td class="text-center {{ $run->rows_replaced > 0 ? 'text-red' : '' }}">{{ $run->rows_replaced }}</td>
                                    <td class="text-center">{{ $run->days_skipped }}</td>
                                    <td>{{ $run->startedBy?->name ?? 'System' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    {{-- Review popup --}}
    <div class="modal fade" id="review-modal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title f-16" id="review-title">Existing attendance</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body p-0">
                    <table class="table mb-0 f-13">
                        <thead>
                            <tr class="bg-light">
                                <th>Clock in</th><th>Clock out</th><th>Hours</th>
                                <th>Late</th><th>Half day</th><th>Source</th>
                            </tr>
                        </thead>
                        <tbody id="review-body"></tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <p class="mr-auto mb-0 f-11 text-lightest" id="review-note"></p>
                    <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    {{-- The employees picker sits inside a card, and its list was rendering
         inside that card's stacking context — so it slid behind the preview
         card below instead of over it. Attaching the list to the page body and
         lifting it above the cards is the same treatment the Employee Mapping
         and Employee 360 pickers already carry. --}}
    <style>
        .bootstrap-select .dropdown-menu { z-index: 1060; }

        /* One grid for the filter row: every column is label / control / hint,
           each at a fixed height, so the controls line up however long the
           hints are. */
        .sync-filters > [class^="col-"] {
            display: flex;
            flex-direction: column;
            margin-bottom: .5rem;
        }

        .sync-filters label:not(.custom-control-label) {
            margin-bottom: .25rem;
            line-height: 1.2;
            min-height: 18px;
        }

        /* The control sits in the same band in every column, whether it is an
           input, a picker, a checkbox or the button. */
        .sync-filters .form-control-sm,
        .sync-filters .filter-control,
        .sync-filters .bootstrap-select > .dropdown-toggle {
            height: calc(1.5em + .5rem + 2px);
            display: flex;
            align-items: center;
        }

        .sync-filters .bootstrap-select > .dropdown-toggle {
            padding: .25rem .5rem;
            font-size: .875rem;
            line-height: 1.5;
        }

        /* The picker is a block element in the flex column, so it needs to stop
           stretching to the row height. */
        .sync-filters .bootstrap-select {
            width: 100% !important;
            flex: 0 0 auto;
        }

        /* Hints share one band, so a long one cannot push its neighbour's
           control out of line. An empty hint still reserves the space. */
        .sync-filters .filter-hint {
            display: block;
            margin-top: .25rem;
            min-height: 30px;
            font-size: 11px;
            line-height: 1.3;
            color: #99a5b5;
        }

        @media (max-width: 767.98px) {
            /* Stacked on a phone, the reserved bands only add dead space. */
            .sync-filters .filter-hint:empty { display: none; }
            .sync-filters .filter-hint { min-height: 0; }
            .sync-filters .filter-spacer { display: none; }
        }
    </style>

    <script>
        $(function () {
            // Re-initialised deliberately: the global handler builds this one
            // inline, which is what put the list behind the card.
            $('#employees').selectpicker('destroy').selectpicker({
                liveSearch: true,
                actionsBox: true,
                selectedTextFormat: 'count > 1',
                liveSearchPlaceholder: 'Search employee…',
                noneSelectedText: 'Everyone mapped',
                noneResultsText: 'No employee matches {0}',
                size: 8,
                width: '100%',
                container: 'body'
            });

            var token = '{{ csrf_token() }}';
            var routes = {
                preview: "{{ route('biometric.attendance-sync.preview') }}",
                run: "{{ route('biometric.attendance-sync.run') }}",
                review: "{{ route('biometric.attendance-sync.review') }}"
            };

            var rows = [];

            function params() {
                return {
                    start_date: $('#start-date').val(),
                    end_date: $('#end-date').val(),
                    employees: $('#employees').val() || [],
                    collapse: $('#collapse').is(':checked') ? 1 : 0,
                    _token: token
                };
            }

            function esc(text) {
                return $('<div>').text(text === null || text === undefined ? '' : text).html();
            }

            // ---- preview ---------------------------------------------------

            $('#preview-btn').on('click', function () {
                var button = $(this).prop('disabled', true)
                    .html('<i class="fa fa-spinner fa-spin mr-1"></i> Checking…');

                $.post(routes.preview, params(), function (response) {
                    button.prop('disabled', false).html('<i class="fa fa-search mr-1"></i> Preview');

                    if (response.status !== 'success') { return; }

                    updateMapping(response);
                    rows = response.rows || [];
                    render(response.totals);
                }).fail(function () {
                    button.prop('disabled', false).html('<i class="fa fa-search mr-1"></i> Preview');
                    Swal.fire({ icon: 'error', title: 'Could not build the preview.' });
                });
            });

            function updateMapping(data) {
                var unmapped = data.unmapped || [];
                var alertBox = $('#mapping-alert');

                alertBox.removeClass('alert-warning alert-success')
                    .addClass(unmapped.length ? 'alert-warning' : 'alert-success');

                if (unmapped.length) {
                    $('#mapping-headline').text(unmapped.length + ' of ' + data.employee_count +
                        ' employees are not linked to a biometric code.');
                    $('#mapping-detail').text('Their punches cannot be matched, so no attendance will be ' +
                        'written for them. Map them first for a complete sync.');
                } else {
                    $('#mapping-headline').text('All ' + data.employee_count +
                        ' employees are linked to a biometric code.');
                    $('#mapping-detail').text('Every employee can receive attendance from the devices.');
                }

                var list = $('#unmapped-list');
                if (list.length) {
                    list.html(unmapped.map(function (e) {
                        return '<span class="badge badge-light border mr-1 mb-1 f-11">' + esc(e.name) +
                            (e.card ? ' <span class="text-lightest">· ' + esc(e.card) + '</span>' : '') + '</span>';
                    }).join(''));
                }
            }

            function render(totals) {
                var body = $('#preview-body').empty();

                if (!rows.length) {
                    $('#preview-card').hide();
                    $('#empty-state').show();
                    return;
                }

                $('#empty-state').hide();
                $('#preview-card').show();

                rows.forEach(function (row, i) {
                    var existing = row.existing_rows === 0
                        ? '<span class="text-lightest">none</span>'
                        : (row.conflict
                            ? '<span class="badge badge-warning">' + row.conflict_rows + ' from elsewhere</span>'
                            : '<span class="badge badge-info">' + row.our_rows + ' from a previous sync</span>');

                    body.append(
                        '<tr' + (row.conflict ? ' class="table-warning"' : '') + '>' +
                        '<td class="text-center"><input type="checkbox" class="row-check" data-i="' + i + '" checked></td>' +
                        '<td>' + esc(row.name) + '<br><span class="f-11 text-lightest">' + esc(row.codes.join(', ')) + '</span></td>' +
                        '<td>' + esc(row.date) + '</td>' +
                        '<td class="text-center">' + row.punches + '</td>' +
                        '<td>' + esc(row.first || '-') + '</td>' +
                        '<td>' + esc(row.last || '-') + '</td>' +
                        '<td>' + existing + '</td>' +
                        '<td>' + (row.existing_rows > 0
                            ? '<button class="btn btn-xs btn-outline-secondary review-btn" data-i="' + i + '">' +
                              '<i class="fa fa-eye mr-1"></i>Review</button>'
                            : '') + '</td>' +
                        '</tr>'
                    );
                });

                $('#preview-summary').html(
                    '<strong>' + totals.days + '</strong> day(s) for <strong>' + totals.employees +
                    '</strong> employee(s), ' + totals.punches + ' punches. ' +
                    '<span class="text-lightest">' + totals.fresh + ' new, ' + totals.resync +
                    ' re-sync, </span>' +
                    (totals.conflicts > 0
                        ? '<span class="text-red">' + totals.conflicts + ' with existing attendance</span>'
                        : '<span class="text-lightest">no conflicts</span>')
                );

                $('#check-all').prop('checked', true);
            }

            $('#check-all').on('change', function () {
                $('.row-check').prop('checked', $(this).is(':checked'));
            });

            // ---- review popup ----------------------------------------------

            $(document).on('click', '.review-btn', function () {
                var row = rows[$(this).data('i')];

                $.post(routes.review, { user_id: row.user_id, date: row.date, _token: token }, function (response) {
                    if (response.status !== 'success') { return; }

                    $('#review-title').text('Attendance for ' + response.employee + ' — ' + response.date);

                    var body = $('#review-body').empty();

                    (response.rows || []).forEach(function (r) {
                        body.append(
                            '<tr>' +
                            '<td>' + esc(r.in) + '</td>' +
                            '<td>' + esc(r.out || 'still open') + '</td>' +
                            '<td>' + (r.minutes === null ? '-' : (Math.floor(r.minutes / 60) + 'h ' + Math.round(r.minutes % 60) + 'm')) + '</td>' +
                            '<td>' + esc(r.late) + '</td>' +
                            '<td>' + esc(r.half_day) + '</td>' +
                            '<td><span class="badge badge-' + (r.ours ? 'info' : 'secondary') + '">' +
                                esc(r.source) + '</span></td>' +
                            '</tr>'
                        );
                    });

                    $('#review-note').text(row.conflict
                        ? 'Rows marked "Manual / import" were not written by the biometric sync. Overriding replaces them.'
                        : 'These rows were written by a previous biometric sync and will be rebuilt.');

                    $('#review-modal').modal('show');
                });
            });

            // ---- run --------------------------------------------------------

            $('#sync-btn').on('click', function () {
                var chosen = $('.row-check:checked').map(function () {
                    return rows[$(this).data('i')];
                }).get();

                if (!chosen.length) {
                    Swal.fire({ icon: 'info', title: 'Select at least one day.' });
                    return;
                }

                var conflicts = chosen.filter(function (r) { return r.conflict; });

                if (!conflicts.length) {
                    confirmPlain(chosen);
                    return;
                }

                // A conflicting day already holds attendance somebody else put
                // there, so the choice is put to the user rather than assumed.
                Swal.fire({
                    title: conflicts.length + ' day(s) already have attendance',
                    html: '<p class="mb-2">These days hold attendance that did not come from the ' +
                          'biometric sync — an Excel import, or entered by hand.</p>' +
                          '<p class="mb-0 text-left" style="font-size:13px">' +
                          conflicts.slice(0, 8).map(function (r) {
                              return '&bull; ' + esc(r.name) + ' — ' + esc(r.date) +
                                     ' (' + r.conflict_rows + ' row(s))';
                          }).join('<br>') +
                          (conflicts.length > 8 ? '<br>&bull; …and ' + (conflicts.length - 8) + ' more' : '') +
                          '</p>',
                    icon: 'warning',
                    showDenyButton: true,
                    showCancelButton: true,
                    confirmButtonText: 'Keep existing',
                    denyButtonText: 'Override with device data',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#3085d6',
                    denyButtonColor: '#d33'
                }).then(function (result) {
                    if (result.isConfirmed) {
                        run(chosen, 'skip');
                    } else if (result.isDenied) {
                        Swal.fire({
                            title: 'Replace existing attendance?',
                            text: 'The existing rows for those days are removed and rebuilt from device ' +
                                  'punches. A copy of every replaced row is kept, so this can be undone.',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, override',
                            confirmButtonColor: '#d33',
                            cancelButtonText: 'Back'
                        }).then(function (confirmation) {
                            if (confirmation.isConfirmed) { run(chosen, 'override'); }
                        });
                    }
                });
            });

            function confirmPlain(chosen) {
                Swal.fire({
                    title: 'Write ' + chosen.length + ' day(s) to attendance?',
                    text: 'Device punches are replayed through the same importer your Excel upload uses.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sync now',
                    cancelButtonText: '@lang('app.cancel')'
                }).then(function (result) {
                    if (result.isConfirmed) { run(chosen, 'skip'); }
                });
            }

            function run(chosen, mode) {
                var payload = params();
                payload.conflict_mode = mode;
                payload.days = chosen.map(function (r) { return r.user_id + '|' + r.date; });

                var button = $('#sync-btn').prop('disabled', true)
                    .html('<i class="fa fa-spinner fa-spin mr-1"></i> Syncing…');

                $.post(routes.run, payload, function (response) {
                    button.prop('disabled', false).html('<i class="fa fa-download mr-1"></i> Sync to attendance');

                    if (response.status !== 'success') {
                        Swal.fire({ icon: 'error', title: response.message || 'Sync failed.' });
                        return;
                    }

                    Swal.fire({
                        icon: 'success',
                        title: 'Attendance updated',
                        text: response.run.message
                    }).then(function () { window.location.reload(); });
                }).fail(function () {
                    button.prop('disabled', false).html('<i class="fa fa-download mr-1"></i> Sync to attendance');
                    Swal.fire({ icon: 'error', title: 'Sync failed.' });
                });
            }

            $('#preview-btn').trigger('click');
        });
    </script>
@endpush
