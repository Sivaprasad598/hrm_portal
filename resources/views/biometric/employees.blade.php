@extends('layouts.app')

{{-- Employee Mapping, simplified: one list of ERP employees, one way to act per row.
     Linked: the code, with Unlink. A suggestion: the code and why, with Link.
     Otherwise: pick a free code (links on pick). --}}

@section('content')
    @php
        $total = count($rollCall);
        $isSuggested = fn ($r) => $r['mapped'] === [] && $r['candidates'] !== [];
        $linkedCount = count(array_filter($rollCall, fn ($r) => $r['mapped'] !== []));
        $suggestedCount = count(array_filter($rollCall, $isSuggested));
        $notLinkedCount = $total - $linkedCount;
        $confident = array_filter($rollCall, fn ($r) => $r['status'] === 'confident');
        // the name the device has under this person's Employee ID, when it is somebody else
        $mismatchOf = fn ($r) => $r['mapped'] === [] && ($k = \App\Services\Iapp\Sync\KnownCodes::key($r['employee_id'] ?? '')) !== ''
            ? ($mismatches[$k] ?? null) : null;
        $mismatchCount = count(array_filter($rollCall, fn ($r) => $mismatchOf($r) !== null));
    @endphp

    <div class="content-wrapper arw-dash">

        @include('biometric.nav', ['active' => $activeTab])

        {{-- Counts (click to filter) · search · Link all suggested --}}
        <div class="em-bar">
            <div class="em-chips" role="group" aria-label="Show">
                <button type="button" class="em-chip is-active" data-filter="all"><b>{{ $total }}</b> All</button>
                <button type="button" class="em-chip em-chip--ok" data-filter="linked"><i></i><b>{{ $linkedCount }}</b> Linked</button>
                <button type="button" class="em-chip em-chip--warn" data-filter="unlinked"><i></i><b>{{ $notLinkedCount }}</b> Not linked</button>
                <button type="button" class="em-chip em-chip--sug" data-filter="suggested"><i></i><b>{{ $suggestedCount }}</b> Suggested</button>
                @if ($mismatchCount > 0)
                    <button type="button" class="em-chip em-chip--bad" data-filter="mismatch"><i></i><b>{{ $mismatchCount }}</b> Name mismatch</button>
                @endif
            </div>

            <div class="em-search">
                <i class="fa fa-search"></i>
                <input type="search" id="em-search" placeholder="Search name, ID or code" autocomplete="off">
            </div>

            @if (count($confident) > 0)
                <button type="button" class="btn-primary rounded f-14 em-btn" id="accept-confident"
                        data-pairs='@json(collect($confident)->mapWithKeys(fn ($r) => [$r['candidates'][0]['id'] => $r['user_id']]))'>
                    <i class="fa fa-check mr-1"></i> Link all suggested ({{ count($confident) }})
                </button>
            @endif

            {{-- the device's own employee list: cross-checked in a side panel before anything is linked --}}
            <button type="button" class="btn-primary rounded f-14 em-btn" id="upload-device-list"
                    title="The Employee report exported from the biometric software (Employee.xlsx)">
                <i class="fa fa-upload mr-1"></i> Upload device list
            </button>
            <input type="file" id="device-list-file" accept=".xlsx,.xls,.csv" class="d-none">
            <button type="button" class="btn-secondary rounded f-14 em-btn" id="sync-all-employees"
                    title="Link every known device code to the employee whose Employee ID it is">
                <i class="fa fa-sync-alt mr-1"></i> Sync all
            </button>

        </div>

        @if ($unmappedCodes === [] && $linkedCount < $total)
            <div class="em-note">
                <i class="fa fa-info-circle mr-1"></i>
                No free device codes left: only codes that have punched are known here. To check everyone enrolled on the
                device (punched or not), export the Employee report from the biometric software and click
                <a href="javascript:;" class="font-weight-bold" onclick="$('#upload-device-list').trigger('click')">Upload device list</a>.
                It is cross-checked by code and name before anything is linked.
            </div>
        @endif

        <div class="em-card">
            <table class="table mb-0 em-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Device code</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rollCall as $row)
                        @php
                            $linked = $row['mapped'] !== [];
                            $top = $row['candidates'][0] ?? null;
                            $mismatch = $mismatchOf($row);
                            $state = $linked ? 'linked' : ($top ? 'suggested' : ($mismatch !== null ? 'mismatch' : 'unlinked'));
                            $haystack = \Illuminate\Support\Str::lower(trim(implode(' ', array_filter([
                                $row['name'], $row['employee_id'] ?? null, $row['employee_card_id'] ?? null,
                                implode(' ', array_map(fn ($m) => $m['emp_code'] . ' ' . $m['employee_name'], $row['mapped'])),
                                implode(' ', array_map(fn ($c) => $c['emp_code'] . ' ' . $c['employee_name'], $row['candidates'])),
                            ]))));
                        @endphp
                        <tr class="em-row" data-state="{{ $state }}" data-search="{{ $haystack }}">
                            <td>
                                <div class="em-name">{{ $row['name'] }}</div>
                                <div class="em-sub">{{ $row['employee_id'] ?? '—' }}</div>
                            </td>

                            <td>
                                @if ($linked)
                                    @foreach ($row['mapped'] as $mapped)
                                        <span class="em-code em-code--ok"><i class="fa fa-check"></i> {{ $mapped['emp_code'] }}</span>
                                        <span class="em-sub">{{ $mapped['employee_name'] }}</span>
                                    @endforeach
                                @elseif ($top)
                                    <span class="em-code em-code--sug">{{ $top['emp_code'] }}</span>
                                    <span class="em-sub">{{ $top['employee_name'] }} · {{ $top['reasons'][0] ?? 'close match' }}</span>
                                @elseif ($mismatch !== null)
                                    <span class="em-code em-code--bad">{{ $row['employee_id'] }}</span>
                                    <span class="em-sub">On the device this code is “{{ $mismatch }}”: correct the Employee ID or the device</span>
                                @else
                                    <span class="em-sub">Not on the device yet (or never punched)</span>
                                @endif
                            </td>

                            <td class="text-right em-act">
                                @if ($linked)
                                    @foreach ($row['mapped'] as $mapped)
                                        <button type="button" class="btn-secondary rounded f-13 em-btn em-btn--sm unlink-btn" data-id="{{ $mapped['id'] }}">Unlink</button>
                                    @endforeach
                                @else
                                    @if ($top)
                                        <button type="button" class="btn-primary rounded f-13 em-btn em-btn--sm link-btn"
                                                data-id="{{ $top['id'] }}" data-user="{{ $row['user_id'] }}"
                                                data-code="{{ $top['emp_code'] }}" data-name="{{ $row['name'] }}">
                                            <i class="fa fa-link mr-1"></i>Link
                                        </button>
                                    @endif
                                    @if ($unmappedCodes !== [])
                                        <div class="em-pick">
                                            <select class="form-control manual-pick" data-user="{{ $row['user_id'] }}"></select>
                                        </div>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    <tr class="em-empty" style="display:none"><td colspan="3" class="text-center py-4 em-sub"></td></tr>
                </tbody>
            </table>

            {{-- Show N entries · Showing x to y of z · pages --}}
            <div class="pl-pager">
                <div class="pl-pager__size">
                    <span>Show</span>
                    <select id="em-size" class="form-control">
                        @foreach ([10, 25, 50, 100] as $n)
                            <option value="{{ $n }}" @selected($n === 25)>{{ $n }}</option>
                        @endforeach
                    </select>
                    <span>entries</span>
                </div>
                <div class="pl-pager__info" id="em-info"></div>
                <ul class="pagination mb-0" id="em-pages"></ul>
            </div>
        </div>
    </div>

    <style>
        .em-bar { display: flex; align-items: center; flex-wrap: wrap; gap: 10px 12px; margin-bottom: 14px; }
        .em-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-right: auto; }
        .em-chip { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; border: 1px solid #eaecf0;
            background: #fff; font-size: 13px; color: #475467; cursor: pointer; }
        .em-chip b { color: #101828; font-size: 14px; }
        .em-chip i { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
        .em-chip--ok i { background: #12b76a; } .em-chip--warn i { background: #f04438; } .em-chip--sug i { background: #f79009; }
        .em-chip.is-active { border-color: var(--epic-blue, #cb6b4f); background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 10%, #fff); }
        .em-search { position: relative; width: 280px; }
        .em-search i, .em-search svg { position: absolute; z-index: 1; left: 12px; top: 50%; transform: translateY(-50%); color: #98a2b3; font-size: 13px; }
        .em-search input { width: 100%; height: 38px; padding: 0 12px 0 34px; border: 1px solid #e8e9e9; border-radius: 8px; font-size: 13.5px; }
        .em-search input:focus { outline: 0; border-color: var(--epic-blue, #cb6b4f); }
        body.epic .em-btn { height: 38px; padding: 0 14px !important; border-radius: 8px !important; display: inline-flex; align-items: center; white-space: nowrap; }
        body.epic .em-btn--sm { height: 32px; padding: 0 12px !important; }
        .em-note { background: #fffaeb; border: 1px solid #fedf89; color: #93370d; border-radius: 10px; padding: 10px 14px; font-size: 13px; margin-bottom: 14px; }
        .em-card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(16, 24, 40, .06); overflow: hidden; }
        .em-table th { font-size: 12px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: #667085; padding: 14px 18px !important; border-bottom: 1px solid #eaecf0 !important; border-top: 0 !important; background: #fff; }
        .em-table td { padding: 12px 18px !important; vertical-align: middle !important; border-top: 1px solid #f2f4f7 !important; font-size: 13.5px; }
        .em-name { font-weight: 600; color: #101828; }
        .em-sub { font-size: 12px; color: #667085; }
        .em-code { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 999px; font-size: 12.5px; font-weight: 600; margin-right: 6px; }
        .em-code--ok { background: #ecfdf3; color: #067647; }
        .em-code--sug { background: #fffaeb; color: #b54708; }
        .em-code--bad { background: #fef3f2; color: #b42318; }
        .em-chip--bad i { background: #b42318; }
        .em-chip--bad.is-active { border-color: #b42318; }
        .em-act { white-space: nowrap; }
        .em-act > * { vertical-align: middle; }
        .em-pick { display: inline-block; width: 210px; margin-left: 8px; text-align: left; }
        .em-pick .bootstrap-select > .dropdown-toggle { height: 32px !important; border-radius: 8px !important; font-size: 13px; }
        .pl-pager { display: flex; align-items: center; flex-wrap: wrap; gap: 12px 20px; padding: 14px 18px; border-top: 1px solid #f2f4f7; }
        .pl-pager__size { display: flex; align-items: center; gap: 8px; font-size: 14px; color: #344054; }
        .pl-pager__size select { width: 72px; height: 36px; border-radius: 8px; }
        .pl-pager__info { margin-left: auto; font-size: 14px; color: #475467; }
        .bootstrap-select .dropdown-menu { z-index: 1060; }
    </style>
@endsection

@push('scripts')
<script>
    var csrf = '{{ csrf_token() }}';
    var mapRoute = "{{ url('account/biometric/employees') }}";
    var bulkRoute = "{{ route('biometric.employees.bulk-map') }}";
    var freeCodes = @json($unmappedCodes);

    // ---- filter (chips) · search · pages (all rows are on the page) ----
    (function () {
        var $rows = $('.em-row'), $empty = $('.em-empty'), filter = 'all', page = 1;

        function size() { return parseInt($('#em-size').val(), 10) || 25; }

        function draw() {
            var term = ($('#em-search').val() || '').toLowerCase().trim();
            var hits = $rows.filter(function () {
                var st = this.getAttribute('data-state');
                var okF = filter === 'all' || st === filter || (filter === 'unlinked' && (st === 'suggested' || st === 'mismatch'));
                return okF && (!term || this.getAttribute('data-search').indexOf(term) !== -1);
            });
            var n = hits.length, per = size(), pages = Math.max(1, Math.ceil(n / per));
            page = Math.min(page, pages);
            var from = (page - 1) * per;

            $rows.hide();
            hits.slice(from, from + per).show();
            $empty.toggle(n === 0).find('td').text(term ? 'No employee matches “' + term + '”.' : 'Nobody in this view.');
            $('#em-info').text(n ? 'Showing ' + (from + 1) + ' to ' + Math.min(from + per, n) + ' of ' + n + ' entries' : '');

            var $p = $('#em-pages').empty();
            if (pages < 2) { return; }
            function item(label, target, disabled, active) {
                $('<li class="page-item"></li>').toggleClass('disabled', disabled).toggleClass('active', active)
                    .append($('<a class="page-link" href="javascript:;"></a>').text(label).on('click', function () {
                        if (!disabled && !active) { page = target; draw(); }
                    })).appendTo($p);
            }
            item('‹', page - 1, page === 1, false);
            var a = Math.max(1, page - 1), b = Math.min(pages, page + 1);
            if (a > 1) { item('1', 1, false, page === 1); if (a > 2) { $p.append('<li class="page-item disabled"><span class="page-link">…</span></li>'); } }
            for (var i = a; i <= b; i++) { item(String(i), i, false, i === page); }
            if (b < pages) { if (b < pages - 1) { $p.append('<li class="page-item disabled"><span class="page-link">…</span></li>'); } item(String(pages), pages, false, page === pages); }
            item('›', page + 1, page === pages, false);
        }

        $('.em-chip').on('click', function () {
            $('.em-chip').removeClass('is-active'); $(this).addClass('is-active');
            filter = $(this).data('filter'); page = 1; draw();
        });
        $('#em-search').on('input', function () { page = 1; draw(); });
        $('#em-size').on('change', function () { page = 1; draw(); });
        draw();
    })();

    // ---- the "choose a code" pickers: one shared option list ----
    (function () {
        var options = ['<option value="">Choose a code&hellip;</option>'];
        freeCodes.forEach(function (code) {
            var label = code.emp_code + (code.employee_name ? ' — ' + code.employee_name : '');
            options.push('<option value="' + code.id + '">' + $('<div>').text(label).html() + '</option>');
        });
        $('.manual-pick').html(options.join('')).selectpicker({
            liveSearch: true, liveSearchPlaceholder: 'Search code or name…', noneResultsText: 'No code matches {0}',
            size: 8, width: '100%', container: 'body'
        });
    })();

    function linkCode(deviceId, userId, description) {
        $.ajax({
            url: mapRoute + '/' + deviceId + '/map', type: 'POST',
            data: { action: 'map', user_id: userId, _token: csrf },
            success: function (response) {
                if (response.status === 'success') { PageSwap.reload(); return; }
                Swal.fire({ icon: 'error', title: response.message || 'Could not link ' + description });
            },
            error: function (xhr) {
                Swal.fire({ icon: 'error', title: 'Could not link ' + description, text: (xhr.responseJSON && xhr.responseJSON.message) || '' });
            }
        });
    }

    $(document).on('click', '.link-btn', function () {
        var b = $(this);
        Swal.fire({
            title: 'Link ' + b.data('code') + ' to ' + b.data('name') + '?',
            text: 'Their punches and attendance days are linked to this employee.',
            icon: 'question', showCancelButton: true, confirmButtonText: 'Link', cancelButtonText: '@lang('app.cancel')'
        }).then(function (r) { if (r.isConfirmed) { linkCode(b.data('id'), b.data('user'), b.data('code')); } });
    });

    $(document).on('click', '.unlink-btn', function () {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Unlink this code?', text: 'Its punches stop counting for this employee. Nothing is deleted.',
            icon: 'warning', showCancelButton: true, confirmButtonText: 'Unlink', confirmButtonColor: '#d33', cancelButtonText: '@lang('app.cancel')'
        }).then(function (r) {
            if (!r.isConfirmed) { return; }
            $.ajax({ url: mapRoute + '/' + id + '/map', type: 'POST', data: { action: 'unmap', _token: csrf },
                success: function () { PageSwap.reload(); } });
        });
    });

    // picking a code links straight away
    $(document).on('changed.bs.select', '.manual-pick', function () {
        var deviceId = $(this).val();
        if (deviceId) { linkCode(deviceId, $(this).data('user'), 'that code'); }
    });

    $('#accept-confident').on('click', function () {
        var pairs = $(this).data('pairs'), ids = Object.keys(pairs);
        Swal.fire({
            title: 'Link ' + ids.length + ' suggested match(es)?',
            text: 'Each is the clear top match for that employee. You can unlink any of them afterwards.',
            icon: 'question', showCancelButton: true, confirmButtonText: 'Link them', cancelButtonText: '@lang('app.cancel')'
        }).then(function (r) {
            if (!r.isConfirmed) { return; }
            $.ajax({
                url: bulkRoute, type: 'POST', data: { action: 'map', ids: ids, pairs: pairs, _token: csrf },
                success: function (response) {
                    Swal.fire({ icon: response.status === 'success' ? 'success' : 'error', title: response.message })
                        .then(function () { PageSwap.reload(); });
                },
                error: function () { Swal.fire({ icon: 'error', title: 'Could not link the suggested matches.' }); }
            });
        });
    });

    // ---- Upload device list: the file is checked, then the cross-check opens in the side panel ----
    $('#upload-device-list').on('click', function () { $('#device-list-file').val('').trigger('click'); });

    $('#device-list-file').on('change', function () {
        if (!this.files || !this.files.length) { return; }
        var data = new FormData();
        data.append('file', this.files[0]);
        data.append('_token', csrf);
        var $btn = $('#upload-device-list').prop('disabled', true);

        $.ajax({
            url: "{{ route('biometric.employees.machine-list.preview') }}", type: 'POST', data: data, processData: false, contentType: false,
            headers: { 'Accept': 'application/json' },
            success: function (r) {
                if (r.status !== 'success') { Swal.fire({ icon: 'error', text: r.message }); return; }
                // opened as any side-panel link is, so Back / refresh behave the same
                var a = $('<a class="openRightModal d-none"></a>').attr('href', r.url).appendTo('.content-wrapper');
                a.trigger('click'); a.remove();
            },
            error: function (x) {
                var j = x.responseJSON || {};
                Swal.fire({ icon: 'error', text: (j.errors && j.errors.file ? j.errors.file[0] : j.message) || 'Could not read the file.' });
            },
            complete: function () { $btn.prop('disabled', false); }
        });
    });

    // ---- Sync all: every known code to the employee whose Employee ID it is ----
    $('#sync-all-employees').on('click', function () {
        Swal.fire({
            title: 'Sync all employees?',
            text: 'Each device code is linked to the employee whose Employee ID it is. Codes where the names disagree become exceptions, not links.',
            icon: 'question', showCancelButton: true, confirmButtonText: 'Sync', cancelButtonText: '@lang('app.cancel')'
        }).then(function (r) {
            if (!r.isConfirmed) { return; }
            $.easyAjax({
                url: "{{ route('biometric.employees.sync') }}", type: 'POST', data: { _token: csrf }, blockUI: true,
                disableButton: true, buttonSelector: '#sync-all-employees',
                success: function (res) {
                    if (res.status === 'success') {
                        Swal.fire({ icon: 'success', title: 'Synced', text: res.message }).then(function () { PageSwap.reload(); });
                    }
                }
            });
        });
    });

</script>
@endpush
