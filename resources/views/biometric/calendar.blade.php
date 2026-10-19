@extends('layouts.app')

@php
    use App\Models\IappDaySync;
@endphp

@section('content')
    {{-- Standard page gutter, matching the rest of the app --}}
    <div class="content-wrapper arw-dash">

        @include('biometric.nav', ['active' => $activeTab])

        {{-- Month summary --}}
        <div class="row mb-3">
            @php
                $tiles = [
                    ['Days in Month', $summary['days'], 'text-dark-grey', 'up to today'],
                    ['Synced', $summary['synced'], 'text-primary', number_format($summary['punches']) . ' punches'],
                    ['Not Synced', $summary['never'], $summary['never'] > 0 ? 'text-red' : 'text-dark-grey', 'never pulled'],
                    ['No Punches', $summary['empty'], 'text-yellow', 'pulled, day was empty'],
                ];
            @endphp

            @foreach ($tiles as [$label, $value, $colour, $sub])
                <div class="col-md-3 col-6 mb-2">
                    <div class="card bg-white border-0 b-shadow-4 h-100">
                        <div class="card-body p-3">
                            <p class="mb-1 f-12 text-lightest text-uppercase">{{ $label }}</p>
                            <h4 class="mb-0 f-21 {{ $colour }}">{{ $value }}</h4>
                            <p class="mb-0 f-11 text-lightest">{{ $sub }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Progress panel. Hidden until a run exists, then driven by polling. --}}
        <div class="card bg-white border-0 b-shadow-4 mb-3" id="run-panel" @if (!$activeRun) style="display:none" @endif>
            <div class="card-body p-3">
                <div class="d-flex align-items-center flex-wrap mb-2">
                    <span class="badge badge-info mr-2" id="run-status">Running</span>
                    <span class="f-13 text-dark-grey mr-auto" id="run-message"></span>

                    <button type="button" class="btn btn-sm btn-outline-secondary ml-2" id="run-pause">
                        <i class="fa fa-pause mr-1"></i> Pause
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary ml-2" id="run-resume" style="display:none">
                        <i class="fa fa-play mr-1"></i> Resume
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger ml-2" id="run-cancel">
                        <i class="fa fa-times mr-1"></i> Cancel
                    </button>
                </div>

                <div class="progress" style="height:8px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" id="run-bar"
                         role="progressbar" style="width:0%"></div>
                </div>

                <p class="mb-0 mt-2 f-11 text-lightest" id="run-detail"></p>
            </div>
        </div>

        {{-- Re-sync: which devices the next pull from this page reads. --}}
        <div class="card bg-white border-0 b-shadow-4 mb-3">
            <div class="card-body p-3 d-flex flex-wrap align-items-center">
                <span class="f-13 text-dark-grey mr-3 mb-1"><strong>Re-sync from</strong></span>
                @foreach ($deviceOptions as $code => $label)
                    <label class="f-13 mr-3 mb-1 d-inline-flex align-items-center">
                        <input type="checkbox" class="resync-device mr-1" value="{{ $code }}" @checked(in_array((string) $code, $selectedDevices, true))>
                        {{ $label }} <span class="text-lightest ml-1">{{ $code }}</span>
                    </label>
                @endforeach
                <span class="f-11 text-lightest w-100">
                    Ticked as on <a href="{{ route('biometric-settings.index') }}">Biometric Settings</a>. Tick another device and pick dates below
                    to pull its history; the nightly pull keeps following Settings.
                </span>
            </div>
        </div>

        {{-- Calendar --}}
        <div class="card bg-white border-0 b-shadow-4 mb-3">
            <div class="card-header bg-white border-bottom d-flex align-items-center flex-wrap p-3">
                <a href="{{ route('biometric.calendar', ['month' => $prevMonth]) }}"
                   class="btn btn-sm btn-outline-secondary mr-2" title="Previous month">
                    <i class="fa fa-chevron-left"></i>
                </a>

                <select class="form-control form-control-sm w-auto mr-2" id="month-picker">
                    @foreach ($monthOptions as $option)
                        <option value="{{ $option['value'] }}"
                            @if ($option['value'] === $month->format('Y-m')) selected @endif>
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>

                @if ($canGoForward)
                    <a href="{{ route('biometric.calendar', ['month' => $nextMonth]) }}"
                       class="btn btn-sm btn-outline-secondary mr-2" title="Next month">
                        <i class="fa fa-chevron-right"></i>
                    </a>
                @else
                    <button class="btn btn-sm btn-outline-secondary mr-2" disabled title="Nothing to pull past today">
                        <i class="fa fa-chevron-right"></i>
                    </button>
                @endif

                <div class="ml-auto d-flex align-items-center flex-wrap">
                    <span class="f-12 text-dark-grey mr-2" id="selection-count"></span>

                    <button type="button" class="btn btn-sm btn-primary mr-2" id="sync-selected" style="display:none">
                        <i class="fa fa-download mr-1"></i> Sync selected
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary mr-2" id="clear-selection" style="display:none">
                        Clear
                    </button>

                    <button type="button" class="btn btn-sm btn-outline-primary mr-2" id="sync-month">
                        <i class="fa fa-calendar mr-1"></i> Sync whole month
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="collapse"
                            data-target="#range-panel">
                        <i class="fa fa-sliders mr-1"></i> Custom range
                    </button>
                </div>
            </div>

            {{-- Arbitrary period, for the long backfill --}}
            <div class="collapse" id="range-panel">
                <div class="card-body border-bottom p-3 bg-light">
                    <div class="form-row align-items-end">
                        <div class="col-md-3 mb-2">
                            <label class="f-12 text-lightest mb-1">From</label>
                            <input type="date" class="form-control form-control-sm" id="range-from"
                                   min="{{ $earliest->toDateString() }}" max="{{ now()->toDateString() }}"
                                   value="{{ $month->copy()->startOfMonth()->toDateString() }}">
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="f-12 text-lightest mb-1">To</label>
                            <input type="date" class="form-control form-control-sm" id="range-to"
                                   min="{{ $earliest->toDateString() }}" max="{{ now()->toDateString() }}"
                                   value="{{ $month->copy()->endOfMonth()->min(now())->toDateString() }}">
                        </div>
                        <div class="col-md-6 mb-2">
                            <button type="button" class="btn btn-sm btn-primary" id="sync-range">
                                <i class="fa fa-download mr-1"></i> Sync this period
                            </button>
                            <span class="f-11 text-lightest ml-2">
                                Device history starts {{ $earliest->format('d M Y') }}.
                                Roughly two minutes per day pulled.
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body p-3">
                <p class="f-11 text-lightest mb-2">
                    Click a day to select it. Drag across days, or shift-click, to select a span.
                    Double-click syncs a single day straight away.
                </p>

                <div class="table-responsive">
                    <table class="table table-bordered mb-0 calendar-grid">
                        <thead>
                            <tr>
                                @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayName)
                                    <th class="text-center f-12 text-lightest bg-light">{{ $dayName }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($weeks as $week)
                                <tr>
                                    @foreach ($week as $cell)
                                        @php
                                            $selectable = $cell['in_month'] && !$cell['is_future'];
                                        @endphp
                                        <td class="day-cell p-0 align-top
                                                   {{ $cell['in_month'] ? '' : 'outside-month' }}
                                                   {{ $cell['is_future'] ? 'future-day' : '' }}
                                                   {{ $cell['is_today'] ? 'today' : '' }}"
                                            data-date="{{ $cell['date'] }}"
                                            @if ($selectable) data-selectable="1" @endif>

                                            <div class="p-2 h-100 cell-inner" data-status="{{ $cell['status'] }}">
                                                <div class="d-flex align-items-start">
                                                    <span class="f-14 font-weight-bold
                                                                 {{ $cell['in_month'] ? 'text-dark-grey' : 'text-lightest' }}">
                                                        {{ $cell['day'] }}
                                                    </span>

                                                    @if ($selectable)
                                                        <span class="ml-auto status-dot bg-{{ $cell['status_color'] }}"
                                                              title="{{ $cell['status_label'] }}{{ $cell['synced_at'] ? ' — last pulled ' . $cell['synced_at'] : '' }}"></span>
                                                    @endif
                                                </div>

                                                @if ($selectable)
                                                    <div class="cell-body mt-1">
                                                        @if ($cell['status'] === IappDaySync::STATUS_NEVER)
                                                            <p class="mb-0 f-11 text-lightest">Not synced</p>
                                                        @elseif ($cell['status'] === IappDaySync::STATUS_EMPTY)
                                                            <p class="mb-0 f-11 text-yellow">No punches</p>
                                                        @elseif ($cell['status'] === IappDaySync::STATUS_FAILED)
                                                            <p class="mb-0 f-11 text-red" title="{{ $cell['error'] }}">Failed</p>
                                                        @else
                                                            <p class="mb-0 f-11 text-dark-grey">
                                                                {{ number_format($cell['punches']) }} punches
                                                            </p>
                                                            <p class="mb-0 f-11 text-lightest">
                                                                {{ $cell['employees'] }} staff
                                                            </p>
                                                        @endif
                                                    </div>

                                                    <button type="button"
                                                            class="btn btn-xs btn-link p-0 mt-1 f-11 sync-day"
                                                            data-date="{{ $cell['date'] }}">
                                                        <i class="fa fa-refresh mr-1"></i>{{ $cell['status'] === IappDaySync::STATUS_NEVER ? 'Sync' : 'Re-sync' }}
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex flex-wrap mt-3 f-11 text-lightest">
                    <span class="mr-3"><span class="status-dot bg-success mr-1"></span> Synced, has punches</span>
                    <span class="mr-3"><span class="status-dot bg-warning mr-1"></span> Pulled, day was empty</span>
                    <span class="mr-3"><span class="status-dot bg-secondary mr-1"></span> Never synced</span>
                    <span class="mr-3"><span class="status-dot bg-danger mr-1"></span> Failed</span>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .calendar-grid { table-layout: fixed; }
        .calendar-grid td.day-cell { height: 104px; cursor: default; transition: background-color .12s; }
        .calendar-grid td.day-cell[data-selectable] { cursor: pointer; }
        .calendar-grid td.day-cell[data-selectable]:hover { background-color: #f4f6f9; }
        .calendar-grid td.outside-month,
        .calendar-grid td.future-day { background-color: #fafbfc; }
        .calendar-grid td.today { box-shadow: inset 0 0 0 2px #1d82f5; }
        .calendar-grid td.selected { background-color: #e3f0ff !important; box-shadow: inset 0 0 0 1px #1d82f5; }
        .calendar-grid td.syncing { background-color: #fff8e1 !important; }
        .cell-inner { display: flex; flex-direction: column; }
        .cell-body { flex: 1 1 auto; }
        .status-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; }
        /* Dragging across cells selects them; suppress the browser's own text drag. */
        .calendar-grid.dragging { user-select: none; }
        .btn-xs { padding: 0 !important; line-height: 1.2; }
    </style>
@endpush

@push('scripts')
    <script>
        $(function () {
            var token = '{{ csrf_token() }}';
            var routes = {
                start: "{{ route('biometric.backfill.start') }}",
                step: "{{ route('biometric.backfill.step') }}",
                status: "{{ route('biometric.backfill.status') }}",
                control: "{{ route('biometric.backfill.control') }}"
            };

            var month = '{{ $month->format('Y-m') }}';
            var selected = [];
            var lastClicked = null;
            var currentRun = null;
            var stepping = false;

            // ---- selection -------------------------------------------------

            function cells() {
                return $('.day-cell[data-selectable]');
            }

            function paintSelection() {
                cells().each(function () {
                    $(this).toggleClass('selected', selected.indexOf($(this).data('date')) !== -1);
                });

                var n = selected.length;
                $('#selection-count').text(n ? n + (n === 1 ? ' day selected' : ' days selected') : '');
                $('#sync-selected, #clear-selection').toggle(n > 0);
            }

            function setSelection(dates) {
                selected = dates;
                paintSelection();
            }

            function toggle(date) {
                var i = selected.indexOf(date);
                if (i === -1) { selected.push(date); } else { selected.splice(i, 1); }
                paintSelection();
            }

            // Every selectable date between two cells, so shift-click and drag
            // both fill in the span rather than only the endpoints.
            function spanBetween(a, b) {
                var all = cells().map(function () { return $(this).data('date'); }).get();
                var i = all.indexOf(a), j = all.indexOf(b);
                if (i === -1 || j === -1) { return []; }
                return all.slice(Math.min(i, j), Math.max(i, j) + 1);
            }

            var dragging = false;
            var dragStart = null;
            var dragMoved = false;
            var dragShift = false;

            cells().on('mousedown', function (e) {
                // Leave the per-day button to its own handler.
                if ($(e.target).closest('.sync-day').length) { return; }

                e.preventDefault();
                dragging = true;
                dragMoved = false;
                dragShift = e.shiftKey;
                dragStart = $(this).data('date');
                $('.calendar-grid').addClass('dragging');

                if (e.shiftKey && lastClicked) {
                    setSelection(spanBetween(lastClicked, dragStart));
                }
            });

            cells().on('mouseenter', function () {
                if (!dragging) { return; }
                dragMoved = true;
                setSelection(spanBetween(dragStart, $(this).data('date')));
            });

            $(document).on('mouseup', function () {
                if (!dragging) { return; }
                dragging = false;
                $('.calendar-grid').removeClass('dragging');

                // A press that never moved is an ordinary click: toggle it.
                // A shift-click already painted its span on mousedown.
                if (!dragMoved && !dragShift) {
                    toggle(dragStart);
                }

                lastClicked = dragStart;
            });

            cells().on('dblclick', function () {
                startRun({ scope: 'days', dates: [$(this).data('date')] });
            });

            $('#clear-selection').on('click', function () { setSelection([]); });

            $('#month-picker').on('change', function () {
                window.location = "{{ route('biometric.calendar') }}?month=" + $(this).val();
            });

            // ---- starting a run --------------------------------------------

            $(document).on('click', '.sync-day', function (e) {
                e.stopPropagation();
                startRun({ scope: 'days', dates: [$(this).data('date')] });
            });

            $('#sync-selected').on('click', function () {
                if (!selected.length) { return; }
                startRun({ scope: 'days', dates: selected });
            });

            $('#sync-month').on('click', function () {
                confirmLong('Sync the whole of {{ $month->format('F Y') }}?',
                    'Roughly two minutes per day. Keep this tab open — it pauses if you close it, and resumes where it stopped.',
                    function () { startRun({ scope: 'month', month: month }); });
            });

            $('#sync-range').on('click', function () {
                var from = $('#range-from').val(), to = $('#range-to').val();
                if (!from || !to) { return; }

                confirmLong('Sync ' + from + ' to ' + to + '?',
                    'Roughly two minutes per day pulled. Keep this tab open — it pauses if you close it, and resumes where it stopped.',
                    function () { startRun({ scope: 'range', start_date: from, end_date: to }); });
            });

            function confirmLong(title, text, onConfirm) {
                Swal.fire({
                    title: title, text: text, icon: 'question',
                    showCancelButton: true, confirmButtonText: 'Start sync',
                    cancelButtonText: '@lang('app.cancel')'
                }).then(function (r) { if (r.isConfirmed) { onConfirm(); } });
            }

            function startRun(payload) {
                payload._token = token;
                payload.devices = $('.resync-device:checked').map(function () { return this.value; }).get();

                if (!payload.devices.length) {
                    Swal.fire({ icon: 'warning', title: 'Tick at least one device to pull from.' });
                    return;
                }

                $.post(routes.start, payload, function (response) {
                    if (response.status !== 'success') {
                        Swal.fire({ icon: 'error', title: response.message || 'Could not start the sync.' });
                        return;
                    }

                    setSelection([]);
                    adopt(response.run);
                    pump();
                }).fail(function () {
                    Swal.fire({ icon: 'error', title: 'Could not start the sync.' });
                });
            }

            // ---- driving a run ---------------------------------------------

            // The pull happens a step at a time: each request does about twenty
            // seconds of work and returns, so nothing hits a request timeout and
            // progress stays visible.
            function pump() {
                if (!currentRun || !currentRun.running || stepping) { return; }

                stepping = true;

                $.post(routes.step, { run_id: currentRun.id, _token: token }, function (response) {
                    stepping = false;

                    if (response.status !== 'success') { return; }

                    adopt(response.run);
                    repaint(response.days);

                    if (currentRun.running) {
                        // Back off after a failed chunk rather than hammering a
                        // server that has just refused us.
                        setTimeout(pump, currentRun.error_count > 0 ? 3000 : 0);
                    } else if (currentRun.status === 'done') {
                        Swal.fire({ icon: 'success', title: 'Sync complete', text: currentRun.message, timer: 2600 });
                    }
                }).fail(function () {
                    stepping = false;
                    // A dropped step is not fatal: the cursor is committed, so
                    // retry shortly rather than abandoning the run.
                    setTimeout(pump, 4000);
                });
            }

            function adopt(run) {
                currentRun = run;

                if (!run) { $('#run-panel').hide(); return; }

                $('#run-panel').show();
                $('#run-status').attr('class', 'badge badge-' + run.status_color + ' mr-2').text(run.status_label);
                $('#run-message').text(run.message || '');
                $('#run-bar').css('width', run.progress + '%')
                    .toggleClass('progress-bar-animated', !!run.running);

                $('#run-detail').text(
                    run.days_done + ' of ' + run.day_count + ' days · ' +
                    run.punches_created.toLocaleString() + ' punches stored · ' +
                    run.days_built + ' attendance days built' +
                    (run.error ? ' · ' + run.error : '')
                );

                $('#run-pause').toggle(run.status === 'running');
                $('#run-resume').toggle(run.status === 'paused');
                $('#run-cancel').toggle(!!run.active);

                cells().removeClass('syncing');
                if (run.current_date) {
                    $('.day-cell[data-date="' + run.current_date + '"]').addClass('syncing');
                }
            }

            // Repaint finished cells in place, so the grid keeps up without a
            // page reload.
            function repaint(days) {
                $.each(days || {}, function (date, state) {
                    var cell = $('.day-cell[data-date="' + date + '"]');
                    if (!cell.length || !cell.data('selectable')) { return; }

                    cell.find('.status-dot')
                        .attr('class', 'ml-auto status-dot bg-' + state.status_color)
                        .attr('title', state.status_label + (state.synced_at ? ' — last pulled ' + state.synced_at : ''));

                    var body = cell.find('.cell-body');

                    if (state.status === 'ok') {
                        body.html('<p class="mb-0 f-11 text-dark-grey">' + state.punches.toLocaleString() +
                            ' punches</p><p class="mb-0 f-11 text-lightest">' + state.employees + ' staff</p>');
                    } else if (state.status === 'empty') {
                        body.html('<p class="mb-0 f-11 text-yellow">No punches</p>');
                    } else if (state.status === 'failed') {
                        body.html('<p class="mb-0 f-11 text-red">Failed</p>');
                    } else {
                        body.html('<p class="mb-0 f-11 text-lightest">Not synced</p>');
                    }

                    cell.find('.sync-day').html('<i class="fa fa-refresh mr-1"></i>Re-sync');
                });
            }

            // ---- controls ---------------------------------------------------

            function control(action) {
                $.post(routes.control, { run_id: currentRun ? currentRun.id : null, action: action, _token: token },
                    function (response) {
                        if (response.status !== 'success') { return; }
                        adopt(response.run);
                        if (action === 'resume') { pump(); }
                    });
            }

            $('#run-pause').on('click', function () { control('pause'); });
            $('#run-resume').on('click', function () { control('resume'); });
            $('#run-cancel').on('click', function () {
                Swal.fire({
                    title: 'Cancel this sync?',
                    text: 'Days already pulled are kept. You can start again later from where it stopped.',
                    icon: 'warning', showCancelButton: true, confirmButtonText: 'Cancel sync',
                    cancelButtonText: 'Keep going'
                }).then(function (r) { if (r.isConfirmed) { control('cancel'); } });
            });

            // A run may already be going when this page opens — from another
            // tab, or from before a reload. Pick it up and carry on.
            $.get(routes.status, function (response) {
                if (response.status !== 'success' || !response.run) { return; }
                adopt(response.run);
                repaint(response.days);
                pump();
            });

            paintSelection();
        });
    </script>
@endpush
