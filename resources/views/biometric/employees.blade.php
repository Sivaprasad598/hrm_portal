@extends('layouts.app')

@push('styles')
    <style>
        /* The pickers in these tables attach their list to the page body, so it
           must sit above the cards and the sticky table header. */
        .bootstrap-select .dropdown-menu { z-index: 1060; }
    </style>
@endpush

@section('content')
    {{-- Standard page gutter, matching the rest of the app --}}
    <div class="content-wrapper arw-dash">

        @include('biometric.nav', ['active' => $activeTab])

        <div class="row mb-3">
            @php
                $tiles = [
                    ['label' => 'Biometric Employees', 'value' => $counts['total'], 'color' => 'text-dark-grey', 'key' => 'all'],
                    ['label' => 'Linked to ERP', 'value' => $counts['mapped'], 'color' => 'text-primary', 'key' => 'mapped'],
                    ['label' => 'Needs Linking', 'value' => $counts['unmapped'], 'color' => $counts['unmapped'] ? 'text-red' : 'text-dark-grey', 'key' => 'unmapped'],
                    ['label' => 'Ignored', 'value' => $counts['ignored'], 'color' => 'text-lightest', 'key' => 'ignored'],
                    ['label' => 'Needs Attention', 'value' => $counts['attention'], 'color' => $counts['attention'] ? 'text-red' : 'text-dark-grey', 'key' => 'attention'],
                ];
            @endphp

            @foreach ($tiles as $tile)
                <div class="col mb-2" style="min-width:150px">
                    <a href="{{ route('biometric.employees', ['map_status' => $tile['key']]) }}" class="text-decoration-none">
                        <div class="card bg-white border-0 b-shadow-4 {{ $mapStatus === $tile['key'] ? 'border-primary' : '' }}">
                            <div class="card-body p-3">
                                <p class="mb-1 f-12 text-lightest text-uppercase">{{ $tile['label'] }}</p>
                                <h4 class="mb-0 f-21 {{ $tile['color'] }}">{{ $tile['value'] }}</h4>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        {{-- Employee roll call.
             The table further down lists 173 device codes, which is the right
             shape for "who is this code?". It is the wrong shape for "is
             everyone covered?" — most codes belong to people with no ERP record
             at all, so working from the employee side is the only way to see
             that the nine who matter are done. --}}
        @php
            $total = count($rollCall);
            $needsWork = array_filter($rollCall, fn ($r) => $r['status'] !== 'mapped');
            $confident = array_filter($rollCall, fn ($r) => $r['status'] === 'confident');
            $statusMeta = [
                'mapped' => ['success', 'Linked'],
                'confident' => ['primary', 'Suggested'],
                'ambiguous' => ['warning', 'Two possible people'],
                'weak' => ['warning', 'Needs review'],
                'none' => ['secondary', 'No match found'],
            ];
        @endphp

        <div class="card bg-white border-0 b-shadow-4 mb-3">
            <div class="card-header bg-white border-bottom d-flex align-items-center flex-wrap p-3">
                <div class="mr-auto">
                    <h6 class="mb-0 f-14">Employee roll call</h6>
                    <p class="mb-0 f-12 text-lightest">
                        {{ $rollCallDone }} of {{ $total }} ERP employees linked to a biometric code.
                        Attendance can only be written for those who are.
                        <span class="ml-1 text-lightest" id="roll-call-count"></span>
                    </p>
                </div>

                {{-- Every row is already on the page, so this filters what is
                     shown rather than going back to the server. With 176
                     employees, scrolling to find one is the slow part. --}}
                <div class="d-flex align-items-center mr-2 my-1" style="min-width:290px">
                    <div class="input-group input-group-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white border-right-0">
                                <i class="fa fa-search f-12 text-lightest"></i>
                            </span>
                        </div>
                        <input type="search" id="roll-call-search" class="form-control border-left-0 f-13"
                               placeholder="Search name, ID, card or code…" autocomplete="off">
                        <div class="input-group-append">
                            <button type="button" class="btn btn-outline-secondary" id="roll-call-clear"
                                    title="Clear">&times;</button>
                        </div>
                    </div>
                </div>

                <div class="btn-group btn-group-sm mr-2 my-1" role="group" aria-label="Filter by status">
                    <button type="button" class="btn btn-outline-secondary active roll-call-filter" data-filter="all">All</button>
                    <button type="button" class="btn btn-outline-secondary roll-call-filter" data-filter="unlinked">Not linked</button>
                    <button type="button" class="btn btn-outline-secondary roll-call-filter" data-filter="linked">Linked</button>
                </div>

                @if (count($confident) > 0)
                    <button type="button" class="btn btn-sm btn-primary" id="accept-confident"
                            data-pairs='@json(collect($confident)->mapWithKeys(fn ($r) => [$r['candidates'][0]['id'] => $r['user_id']]))'>
                        <i class="fa fa-check mr-1"></i> Link {{ count($confident) }} suggested
                    </button>
                @endif
            </div>

            <div class="progress" style="height:4px;">
                <div class="progress-bar bg-success" role="progressbar"
                     style="width: {{ $total ? round($rollCallDone / $total * 100) : 0 }}%"></div>
            </div>

            @if ($unmappedCodes === [] && $rollCallDone < $total)
                {{-- The manual picker offers free codes only. With none left it
                     renders as an empty dropdown, which reads as a fault rather
                     than as the true answer: there is nothing to assign. --}}
                <div class="alert alert-warning f-12 mb-0 rounded-0 border-left-0 border-right-0">
                    <strong>Nothing left to assign by hand.</strong>
                    All {{ $counts['mapped'] }} biometric codes on record are already linked to somebody{{ $counts['ignored'] ? ', and ' . $counts['ignored'] . ' more ' . ($counts['ignored'] === 1 ? 'is' : 'are') . ' marked ignored' : '' }}.
                    The {{ $total - $rollCallDone }} {{ \Illuminate\Support\Str::plural('employee', $total - $rollCallDone) }}
                    still unlinked have no code waiting for them — either they are not enrolled on the devices yet,
                    or their punches have not been pulled across.
                    <br>
                    <span class="f-12">
                        To bring a code back, pull punches first and rebuild second:
                        <strong>Punch Log &rarr; Sync</strong> fetches from the devices into the local punch store, then
                        <a href="javascript:;" class="alert-link" id="roll-call-sync-hint">Sync employees</a>
                        rebuilds this register from those punches. The employee sync alone never reaches the devices —
                        it only re-reads what has already been fetched. Or unlink a code from whoever holds it now.
                    </span>
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-hover mb-0 f-13">
                    <thead>
                        <tr class="bg-light">
                            <th>ERP employee</th>
                            <th style="width:150px">Status</th>
                            <th>Biometric code</th>
                            <th style="width:240px">Assign manually</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rollCall as $row)
                            @php
                                [$colour, $label] = $statusMeta[$row['status']] ?? ['secondary', ucfirst($row['status'])];

                                // Everything the search box should match, gathered
                                // server-side so the filter is a single string
                                // comparison per row rather than a DOM walk.
                                $haystack = trim(implode(' ', array_filter([
                                    $row['name'],
                                    $row['employee_id'] ?? null,
                                    $row['employee_card_id'] ?? null,
                                    $label,
                                    implode(' ', array_map(fn ($m) => $m['emp_code'] . ' ' . $m['employee_name'], $row['mapped'])),
                                    implode(' ', array_map(fn ($c) => $c['emp_code'] . ' ' . $c['employee_name'], $row['candidates'])),
                                ])));
                            @endphp
                            <tr class="roll-call-row"
                                data-status="{{ $row['mapped'] === [] ? 'unlinked' : 'linked' }}"
                                data-search="{{ \Illuminate\Support\Str::lower($haystack) }}">
                                <td>
                                    <span class="font-weight-bold">{{ $row['name'] }}</span>
                                    <br>
                                    <span class="f-11 text-lightest">
                                        ID {{ $row['employee_id'] ?? '—' }}
                                        @if ($row['employee_card_id'])
                                            &middot; card {{ $row['employee_card_id'] }}
                                        @endif
                                    </span>
                                </td>

                                <td><span class="badge badge-{{ $colour }}">{{ $label }}</span></td>

                                <td>
                                    @forelse ($row['mapped'] as $mapped)
                                        <span class="badge badge-success mr-1">{{ $mapped['emp_code'] }}</span>
                                        <span class="f-11 text-lightest">
                                            {{ $mapped['employee_name'] }} &middot;
                                            {{ number_format($mapped['punch_count']) }} punches
                                        </span>
                                        <button type="button" class="btn btn-xs btn-link text-red p-0 ml-2 unlink-btn"
                                                data-id="{{ $mapped['id'] }}">unlink</button>
                                        <br>
                                    @empty
                                        @forelse ($row['candidates'] as $candidate)
                                            <div class="mb-1 d-flex align-items-center flex-wrap">
                                                <button type="button"
                                                        class="btn btn-xs btn-outline-primary mr-2 link-btn"
                                                        data-id="{{ $candidate['id'] }}"
                                                        data-user="{{ $row['user_id'] }}"
                                                        data-code="{{ $candidate['emp_code'] }}"
                                                        data-name="{{ $row['name'] }}">
                                                    <i class="fa fa-link mr-1"></i>Link {{ $candidate['emp_code'] }}
                                                </button>
                                                <span class="f-11 text-lightest">
                                                    {{ $candidate['employee_name'] }} &middot;
                                                    {{ number_format($candidate['punch_count']) }} punches &middot;
                                                    <strong>{{ $candidate['score'] }}%</strong>
                                                    &middot; {{ implode('; ', $candidate['reasons']) }}
                                                </span>
                                            </div>
                                        @empty
                                            <span class="f-12 text-lightest">
                                                No biometric code resembles this employee — they may not be
                                                enrolled on the devices, or enrolled under a different name.
                                            </span>
                                        @endforelse
                                    @endforelse
                                </td>

                                <td>
                                    @if ($row['mapped'] === [])
                                        @if ($unmappedCodes === [])
                                            {{-- An empty picker looks broken. Say why instead. --}}
                                            <span class="f-11 text-lightest">
                                                No free codes
                                            </span>
                                        @else
                                            {{-- Options are injected once from a shared list; repeating
                                                 all 173 codes per row doubled the page weight. --}}
                                            <select class="form-control form-control-sm manual-pick"
                                                    data-user="{{ $row['user_id'] }}"></select>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pager for the roll call. Filled in by the script below, and
                 hidden while everything fits on one page. --}}
            <div class="card-footer bg-white border-top d-flex align-items-center flex-wrap py-2"
                 id="roll-call-pager" style="display:none !important">
                <span class="f-12 text-lightest mr-auto" id="roll-call-range"></span>
                <nav aria-label="Roll call pages">
                    <ul class="pagination pagination-sm mb-0" id="roll-call-pages"></ul>
                </nav>
            </div>
        </div>

        <div class="alert alert-info f-13">
            <strong>One-way sync.</strong>
            Employees are enrolled on the biometric devices first, so the device code is their original
            identity. Linking stores that code on the ERP employee record. Nothing is ever written back
            to the biometric system.
            <br>
            <span class="f-12">
                Suggestions combine identifier evidence (card ID, employee ID, leading-zero variants) with
                name evidence (exact, word-order, initials, close spelling). A link is only proposed
                automatically when an <strong>identifier</strong> agrees and no other employee scores close —
                a name on its own is never enough to link without review.
            </span>
        </div>

        {{-- The ERP picker makes rows taller than a plain table, so cell padding is
             tightened and the picker column given a fixed width; without that the
             column widths shift between pages as names change length. --}}
        <style>
            #employee-map-table td { padding: .5rem .75rem; vertical-align: middle; }
            #employee-map-table th { padding: .5rem .75rem; }
            #employee-map-table .erp-cell { width: 270px; }
            #employee-map-table .bootstrap-select .dropdown-toggle { padding: .25rem .5rem; font-size: 13px; }
            #employee-map-table .suggestion-list { margin-top: .25rem; line-height: 1.35; }
        </style>

        <x-cards.data padding="false">

            <form method="GET" action="{{ route('biometric.employees') }}" class="p-3 border-bottom-grey">
                <div class="row align-items-end">
                    <div class="col-md-3 mb-2">
                        <label class="f-12 text-dark-grey">@lang('app.search')</label>
                        <input type="text" name="search" value="{{ $search }}" class="form-control f-14"
                               placeholder="Code, name or card ID">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="f-12 text-dark-grey">@lang('app.status')</label>
                        <select name="map_status" class="form-control f-14">
                            @foreach (['unmapped' => 'Needs Linking', 'attention' => 'Needs Attention (ID conflicts)', 'mapped' => 'Linked', 'ignored' => 'Ignored', 'all' => 'All'] as $key => $label)
                                <option value="{{ $key }}" @selected($mapStatus === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-7 mb-2">
                        <button type="submit" class="btn btn-primary f-14 mr-2">@lang('app.apply')</button>
                        <a href="{{ route('biometric.employees') }}" class="btn btn-secondary f-14 mr-2">@lang('app.reset')</a>
                        <button type="button" class="btn btn-primary f-14" id="sync-employees"
                                title="Link every device code to the employee with the same Employee ID, where the names agree">
                            <i class="fa fa-refresh mr-1"></i> Sync all employees
                        </button>
                        <button type="button" class="btn btn-secondary f-14 ml-2" id="upload-machine-list"
                                title="The biometric software's Employee report: every enrolled person, whether they have punched or not">
                            <i class="fa fa-upload mr-1"></i> Upload device employee list
                        </button>
                        <input type="file" id="machine-list-file" accept=".xlsx,.xls,.csv" class="d-none">
                    </div>
                </div>
            </form>

            {{-- Bulk bar: hidden until something is selected. --}}
            <div class="p-3 border-bottom-grey bg-light d-none" id="bulk-bar">
                <span class="f-13 mr-3"><strong id="bulk-count">0</strong> selected</span>
                <button type="button" class="btn btn-sm btn-primary f-12 mr-2 bulk-action" data-action="map">
                    Link selected
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary f-12 mr-2 bulk-action" data-action="accept_suggestions">
                    Accept top suggestion
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary f-12 mr-2 bulk-action" data-action="ignore">
                    Ignore selected
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary f-12 bulk-action" data-action="unmap">
                    Unlink selected
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-hover border-0 mb-0" id="employee-map-table">
                    <thead>
                        <tr>
                            <th class="f-12" style="width:32px">
                                <input type="checkbox" id="select-all">
                            </th>
                            <th class="f-12">Biometric Code</th>
                            <th class="f-12">Device Name</th>
                            <th class="f-12">Punches</th>
                            <th class="f-12">Last Seen</th>
                            <th class="f-12 erp-cell">ERP Employee</th>
                            <th class="f-12">Link</th>
                            <th class="f-12 text-right">@lang('app.action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($employeeRecords as $record)
                            @php $rowSuggestions = $suggestions[$record->id] ?? []; @endphp

                            <tr data-id="{{ $record->id }}">
                                <td>
                                    <input type="checkbox" class="row-select" value="{{ $record->id }}">
                                </td>
                                <td class="f-13 f-w-500">{{ $record->emp_code }}</td>
                                <td class="f-13">
                                    {{ $record->employee_name ?: '--' }}
                                    @if (isset($conflicts[$record->emp_code]) && !$record->user_id)
                                        <div class="f-11 text-red mt-1"><i class="fa fa-exclamation-triangle"></i> {{ $conflicts[$record->emp_code] }}</div>
                                    @endif
                                    @if ($record->location_name)
                                        <div class="f-11 text-lightest">{{ $record->location_name }}</div>
                                    @endif
                                </td>
                                <td class="f-13">{{ number_format($record->punch_count) }}</td>
                                <td class="f-13">{{ $record->last_seen_at?->format(company()->date_format) ?: '--' }}</td>
                                <td class="f-13 erp-cell">
                                    @if ($record->user)
                                        {{ $record->user->name }}
                                    @else
                                        {{-- Type-ahead: the ERP employee list is long, so the
                                             picker needs searching rather than scrolling.

                                             No `title` attribute: Bootstrap Select treats it as a
                                             placeholder and clears the pre-selected option on init,
                                             which made a suggested employee look chosen while the
                                             underlying value was empty. The blank first option
                                             serves the same purpose without that side effect. --}}
                                        {{-- Options are injected once from a shared list rather than
                                             rendered per row. With 48 codes awaiting mapping this
                                             was emitting 8,454 <option> tags — 176 employees repeated
                                             48 times — and a 3.4 MB page. The roll call above already
                                             works this way; this table had been left behind. --}}
                                        <select class="form-control select-picker erp-employee-select"
                                                data-live-search="true"
                                                data-size="8"
                                                data-selected="{{ $rowSuggestions[0]['user_id'] ?? '' }}"></select>

                                        {{-- Ranked suggestions, with why each was proposed. --}}
                                        @foreach ($rowSuggestions as $index => $suggestion)
                                            <div class="f-11 suggestion-list {{ $index === 0 ? 'text-dark-grey' : 'text-lightest' }}">
                                                <a href="#" class="use-suggestion" data-user="{{ $suggestion['user_id'] }}">
                                                    {{ $suggestion['name'] }}
                                                </a>
                                                <span class="badge badge-{{ $suggestion['score'] >= 90 ? 'success' : ($suggestion['score'] >= 70 ? 'warning' : 'secondary') }} f-10">
                                                    {{ $suggestion['score'] }}
                                                </span>
                                                <span class="text-lightest">{{ implode('; ', $suggestion['reasons']) }}</span>
                                            </div>
                                        @endforeach
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-{{ $record->map_status_color }} f-11">
                                        {{ $record->map_status_label }}
                                    </span>
                                    @if ($record->map_method)
                                        <div class="f-11 text-lightest">{{ $record->map_method }}</div>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if ($record->isMapped())
                                        <button type="button" class="btn btn-sm btn-outline-secondary f-12 map-action"
                                                data-action="unmap">Unlink</button>
                                    @else
                                        <button type="button" class="btn btn-sm btn-primary f-12 map-action"
                                                data-action="map">Link</button>
                                        @if ($record->map_status !== 'ignored')
                                            <button type="button" class="btn btn-sm btn-outline-secondary f-12 map-action"
                                                    data-action="ignore">Ignore</button>
                                        @else
                                            <button type="button" class="btn btn-sm btn-outline-secondary f-12 map-action"
                                                    data-action="unmap">Restore</button>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    {{-- Three different reasons for an empty table, and they
                                         need different answers. Telling someone to sync when
                                         they have simply filtered their only code out of view
                                         sends them the wrong way. --}}
                                    @if ($counts['total'] === 0)
                                        <p class="mb-1 f-15 text-dark-grey">@lang('messages.noRecordFound')</p>
                                        <p class="mb-0 f-13 text-lightest">
                                            No device codes yet: click <strong>Upload device employee list</strong> (the biometric Employee report),
                                            or pull punches under Setup &rarr; Sync Calendar, then <strong>Sync all employees</strong>.
                                        </p>
                                    @elseif ($search !== '')
                                        <p class="mb-1 f-15 text-dark-grey">No code matches “{{ $search }}”.</p>
                                        <p class="mb-0 f-13 text-lightest">
                                            {{ $counts['total'] }} biometric
                                            {{ \Illuminate\Support\Str::plural('code', $counts['total']) }}
                                            on record.
                                            <a href="{{ route('biometric.employees', ['map_status' => 'all']) }}">Show them all</a>.
                                        </p>
                                    @else
                                        @php
                                            $filterLabel = [
                                                'unmapped' => 'awaiting linking',
                                                'mapped' => 'linked',
                                                'ignored' => 'ignored',
                                            ][$mapStatus] ?? $mapStatus;
                                        @endphp

                                        <p class="mb-1 f-15 text-dark-grey">No biometric code is {{ $filterLabel }}.</p>
                                        <p class="mb-0 f-13 text-lightest">
                                            {{ $counts['total'] }} on record —
                                            {{ $counts['mapped'] }} linked,
                                            {{ $counts['unmapped'] }} awaiting linking,
                                            {{ $counts['ignored'] }} ignored.
                                            <a href="{{ route('biometric.employees', ['map_status' => 'all']) }}">Show them all</a>.
                                        </p>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($employeeRecords->hasPages())
                <div class="p-3">
                    {{ $employeeRecords->links() }}
                </div>
            @endif

        </x-cards.data>
    </div>

@endsection

@push('scripts')
<script>
    var csrf = '{{ csrf_token() }}';

    // ---- Employee roll call -------------------------------------------------

    var mapRoute = "{{ url('account/biometric/employees') }}";
    var bulkRoute = "{{ route('biometric.employees.bulk-map') }}";

    // Built once from a shared list rather than rendered per row, then made
    // searchable — 173 codes is far too many to scroll.
    var freeCodes = @json($unmappedCodes);

    // ---- roll call search ---------------------------------------------------
    // Every row is already rendered, so filtering is done here rather than by
    // asking the server again. Each row carries a prepared lowercase string of
    // everything worth matching, so this is one comparison per row.
    (function rollCallSearch() {
        var $rows = $('.roll-call-row');

        if (!$rows.length) {
            return;
        }

        var $box = $('#roll-call-search');
        var $table = $rows.first().closest('table');
        var status = 'all';

        // One row appended after the table to report an empty result, rather
        // than leaving the person looking at nothing.
        var $empty = $('<tr class="roll-call-empty"><td colspan="4" class="text-center f-13 text-lightest py-4"></td></tr>')
            .hide()
            .appendTo($table.find('tbody'));

        var PER_PAGE = 50;
        var page = 1;

        // Which rows survive the search and the status filter. Paging then
        // works on this list, so a search runs across all 176 employees and
        // the pager describes the matches rather than the whole table.
        function matching() {
            var term = ($box.val() || '').toLowerCase().trim();

            return $rows.filter(function () {
                var matchesStatus = status === 'all' || this.getAttribute('data-status') === status;
                var matchesTerm = term === '' || (this.getAttribute('data-search') || '').indexOf(term) !== -1;

                return matchesStatus && matchesTerm;
            });
        }

        function drawPager(pages) {
            var $pager = $('#roll-call-pager');
            var $list = $('#roll-call-pages').empty();

            if (pages < 2) {
                $pager.hide();

                return;
            }

            // Inline style is used on the element, so removing it needs the
            // same priority back.
            $pager.css('display', 'flex');

            function item(label, target, disabled, active) {
                var $li = $('<li class="page-item"></li>')
                    .toggleClass('disabled', !!disabled)
                    .toggleClass('active', !!active);

                $('<a class="page-link" href="javascript:;"></a>')
                    .text(label)
                    .on('click', function () {
                        if (disabled || active) {
                            return;
                        }

                        page = target;
                        apply(true);
                    })
                    .appendTo($li);

                return $li;
            }

            $list.append(item('‹', page - 1, page === 1, false));

            // A window around the current page, so 20 pages does not produce
            // 20 buttons.
            var from = Math.max(1, page - 2);
            var to = Math.min(pages, page + 2);

            if (from > 1) {
                $list.append(item('1', 1, false, page === 1));

                if (from > 2) {
                    $list.append($('<li class="page-item disabled"><span class="page-link">…</span></li>'));
                }
            }

            for (var i = from; i <= to; i++) {
                $list.append(item(String(i), i, false, i === page));
            }

            if (to < pages) {
                if (to < pages - 1) {
                    $list.append($('<li class="page-item disabled"><span class="page-link">…</span></li>'));
                }

                $list.append(item(String(pages), pages, false, page === pages));
            }

            $list.append(item('›', page + 1, page === pages, false));
        }

        // keepPage is set when the pager itself moved; any other change starts
        // again at the first page, or a search could land on an empty page.
        function apply(keepPage) {
            if (!keepPage) {
                page = 1;
            }

            var $hits = matching();
            var total = $hits.length;
            var pages = Math.max(1, Math.ceil(total / PER_PAGE));

            if (page > pages) {
                page = pages;
            }

            var start = (page - 1) * PER_PAGE;
            var end = start + PER_PAGE;

            $rows.hide();
            $hits.slice(start, Math.min(end, total)).show();

            if (total === 0) {
                var term = ($box.val() || '').trim();
                $empty.find('td').text(
                    term ? 'No employee matches “' + term + '”.' : 'No employee in this view.'
                );
                $empty.show();
            } else {
                $empty.hide();
            }

            $('#roll-call-count').text(
                total === $rows.length ? '' : '· ' + total + ' matching'
            );

            $('#roll-call-range').text(
                total === 0
                    ? 'Nothing to show'
                    : 'Showing ' + (start + 1) + '–' + Math.min(end, total) + ' of ' + total
                        + (total === $rows.length ? '' : ' matching (' + $rows.length + ' total)')
            );

            drawPager(pages);
        }

        $box.on('input', function () { apply(false); });

        $('#roll-call-clear').on('click', function () {
            $box.val('');
            apply(false);
            $box.focus();
        });

        $('.roll-call-filter').on('click', function () {
            $('.roll-call-filter').removeClass('active');
            $(this).addClass('active');
            status = $(this).data('filter');
            apply(false);
        });

        // The warning banner points at the sync button further down the page.
        $('#roll-call-sync-hint').on('click', function () {
            $('#sync-employees').trigger('click');
        });

        apply(false);
    })();

    (function buildPickers() {
        var options = ['<option value="">Choose a code&hellip;</option>'];

        freeCodes.forEach(function (code) {
            var label = code.emp_code + (code.employee_name ? ' — ' + code.employee_name : '');
            options.push('<option value="' + code.id + '">' + $('<div>').text(label).html() + '</option>');
        });

        var markup = options.join('');

        $('.manual-pick').each(function () {
            $(this).html(markup);
        }).selectpicker({
            liveSearch: true,
            liveSearchPlaceholder: 'Search code or name…',
            noneResultsText: 'No code matches {0}',
            size: 8,
            width: '100%',
            // These sit in a scrolling table, which clips anything taller than
            // the cell, so the list is attached to the page instead.
            container: 'body'
        });
    })();

    function linkCode(deviceId, userId, description) {
        $.ajax({
            url: mapRoute + '/' + deviceId + '/map',
            type: 'POST',
            data: { action: 'map', user_id: userId, _token: csrf },
            success: function (response) {
                if (response.status === 'success') {
                    window.location.reload();
                    return;
                }
                Swal.fire({ icon: 'error', title: response.message || 'Could not link ' + description });
            },
            error: function (xhr) {
                Swal.fire({
                    icon: 'error',
                    title: 'Could not link ' + description,
                    text: (xhr.responseJSON && xhr.responseJSON.message) || ''
                });
            }
        });
    }

    $(document).on('click', '.link-btn', function () {
        var button = $(this);

        Swal.fire({
            title: 'Link ' + button.data('code') + ' to ' + button.data('name') + '?',
            text: 'Their punches and attendance days are re-linked to this employee.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Link',
            cancelButtonText: '@lang('app.cancel')'
        }).then(function (result) {
            if (result.isConfirmed) {
                linkCode(button.data('id'), button.data('user'), button.data('code'));
            }
        });
    });

    $(document).on('click', '.unlink-btn', function () {
        var id = $(this).data('id');

        Swal.fire({
            title: 'Unlink this code?',
            text: 'Its punches stop counting towards that employee. Nothing is deleted.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Unlink',
            confirmButtonColor: '#d33',
            cancelButtonText: '@lang('app.cancel')'
        }).then(function (result) {
            if (!result.isConfirmed) { return; }

            $.ajax({
                url: mapRoute + '/' + id + '/map',
                type: 'POST',
                data: { action: 'unmap', _token: csrf },
                success: function () { window.location.reload(); }
            });
        });
    });

    // Picking from the manual dropdown links immediately — an extra confirm
    // button next to it would just be a second click for the same decision.
    $(document).on('changed.bs.select', '.manual-pick', function () {
        var deviceId = $(this).val();

        if (!deviceId) { return; }

        linkCode(deviceId, $(this).data('user'), 'that code');
    });

    $('#accept-confident').on('click', function () {
        var pairs = $(this).data('pairs');
        var ids = Object.keys(pairs);

        Swal.fire({
            title: 'Link ' + ids.length + ' suggested match(es)?',
            text: 'Each one is the clear top candidate for that employee. You can unlink any of them afterwards.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Link them',
            cancelButtonText: '@lang('app.cancel')'
        }).then(function (result) {
            if (!result.isConfirmed) { return; }

            $.ajax({
                url: bulkRoute,
                type: 'POST',
                data: { action: 'map', ids: ids, pairs: pairs, _token: csrf },
                success: function (response) {
                    Swal.fire({
                        icon: response.status === 'success' ? 'success' : 'error',
                        title: response.message
                    }).then(function () { window.location.reload(); });
                },
                error: function () {
                    Swal.fire({ icon: 'error', title: 'Could not link the suggested matches.' });
                }
            });
        });
    });

    // Bootstrap Select turns each picker into a searchable dropdown. The
    // underlying <select> keeps its value, so .val() still works everywhere.
    // No `title` option here either — it would clear the pre-selected
    // suggestion, the same way the title attribute did.
    // Built once and reused, for the reason given beside the markup: rendering
    // the whole employee list per row produced a multi-megabyte page.
    (function buildEmployeePickers() {
        var erpEmployees = @json($erpEmployees);
        var parts = ['<option value="">-- choose employee --</option>'];

        erpEmployees.forEach(function (employee) {
            var subtext = employee.code ? ' data-subtext="ID ' + $('<div>').text(employee.code).html() + '"' : '';

            parts.push(
                '<option value="' + employee.id + '"' + subtext + '>'
                + $('<div>').text(employee.name).html()
                + '</option>'
            );
        });

        var markup = parts.join('');

        $('.erp-employee-select').each(function () {
            var $select = $(this);
            $select.html(markup);

            // The top suggestion stays pre-selected, as it was when the
            // options were rendered server-side.
            var selected = $select.data('selected');

            if (selected) {
                $select.val(String(selected));
            }
        });
    })();

    $('.erp-employee-select').selectpicker({
        liveSearch: true,
        liveSearchPlaceholder: 'Type a name…',
        noneResultsText: 'No employee matches {0}',
        size: 8,
        width: '100%',
        // Same reason: the row is inside a scrolling table.
        container: 'body'
    });

    /**
     * The chosen employee for a row.
     *
     * Reads the native select rather than anything the plugin renders, so it
     * keeps working if Bootstrap Select fails to load.
     */
    function chosenEmployee(row) {
        var value = row.find('select.erp-employee-select').val();

        return (value === null || value === undefined || value === '') ? null : value;
    }

    function refreshBulkBar() {
        var count = $('.row-select:checked').length;
        $('#bulk-count').text(count);
        $('#bulk-bar').toggleClass('d-none', count === 0);
    }

    $('#select-all').on('change', function () {
        $('.row-select').prop('checked', $(this).is(':checked'));
        refreshBulkBar();
    });

    $('.row-select').on('change', refreshBulkBar);

    // Clicking a suggestion just fills the picker — the link still needs
    // confirming, so a misread suggestion can never apply itself.
    $('.use-suggestion').on('click', function (e) {
        e.preventDefault();
        // selectpicker('val') both sets the value and redraws the button text;
        // a plain .val() would change the select but leave the visible label
        // showing the previous choice.
        $(this).closest('td').find('.erp-employee-select').selectpicker('val', String($(this).data('user')));
    });

    $('#sync-employees').on('click', function () {
        var button = $(this);
        button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i> Syncing…');

        $.easyAjax({
            url: "{{ route('biometric.employees.sync') }}",
            type: 'POST',
            data: { _token: csrf },
            blockUI: true,
            success: function (response) {
                // A refusal (for example nothing on the device side yet): show it and give the button back.
                if (response.status !== 'success') {
                    button.prop('disabled', false).html('<i class="fa fa-refresh mr-1"></i> Sync all employees');
                    Swal.fire({ icon: 'info', title: 'Nothing to sync yet', text: response.message });
                    return;
                }

                var next = response.data ? response.data.next : response.next;

                // Conflicts: say so, then go straight to them.
                Swal.fire({
                    icon: next ? 'warning' : 'success',
                    title: next ? 'Some employees need you' : 'All employees synced',
                    text: response.message,
                    confirmButtonText: next ? 'Show the conflicts' : 'OK'
                }).then(function () {
                    window.location.href = next || window.location.href;
                });
            },
            error: function () {
                button.prop('disabled', false).html('<i class="fa fa-refresh mr-1"></i> Sync all employees');
            }
        });
    });

    // The machine's own employee list: every enrolled person is compared, not only those who punched.
    $('#upload-machine-list').on('click', function () { $('#machine-list-file').val('').trigger('click'); });
    $('#machine-list-file').on('change', function () {
        if (!this.files.length) { return; }
        var data = new FormData();
        data.append('file', this.files[0]);
        data.append('_token', csrf);
        var button = $('#upload-machine-list');
        button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i> Loading…');

        $.ajax({
            url: "{{ route('biometric.employees.machine-list') }}", type: 'POST', data: data, processData: false, contentType: false,
            success: function (response) {
                var conflicts = (response.data ? response.data.conflicts : response.conflicts) || [];
                Swal.fire({
                    icon: response.status === 'success' ? (conflicts.length ? 'warning' : 'success') : 'error',
                    title: response.status === 'success' ? 'Device employee list loaded' : 'Not loaded',
                    html: $('<div>').text(response.message).html()
                        + (conflicts.length ? '<div class="text-left f-12 mt-3"><strong>Set aside: the machine has another person under these codes</strong><br>'
                            + conflicts.map(function (c) { return $('<div>').text(c).html(); }).join('<br>') + '</div>' : ''),
                    width: 640
                }).then(function () { if (response.status === 'success') { window.location.reload(); } });
            },
            error: function (xhr) {
                var r = xhr.responseJSON || {};
                Swal.fire({ icon: 'error', text: r.message || 'The file could not be loaded.' });
            },
            complete: function () { button.prop('disabled', false).html('<i class="fa fa-upload mr-1"></i> Upload device employee list'); }
        });
    });

    $('.map-action').on('click', function () {
        var row = $(this).closest('tr');
        var action = $(this).data('action');
        var userId = chosenEmployee(row);

        if (action === 'map' && !userId) {
            Swal.fire({
                icon: 'warning',
                text: 'Choose an ERP employee for ' + (row.find('td').eq(1).text().trim() || 'this code') + ' first.'
            });
            return;
        }

        $.easyAjax({
            url: "{{ url('account/biometric/employees') }}/" + row.data('id') + "/map",
            type: 'POST',
            data: { action: action, user_id: userId, _token: csrf },
            success: function () { window.location.reload(); }
        });
    });

    $('.bulk-action').on('click', function () {
        var action = $(this).data('action');
        var ids = [];
        var pairs = {};

        $('.row-select:checked').each(function () {
            var row = $(this).closest('tr');
            var id = row.data('id');
            ids.push(id);
            var chosen = chosenEmployee(row);
            if (chosen) { pairs[id] = chosen; }
        });

        if (!ids.length) { return; }

        var wording = {
            map: 'Link the selected codes to the chosen employees?',
            accept_suggestions: 'Link each selected code to its top suggestion? Review the scores first — low-scoring suggestions are guesses.',
            ignore: 'Ignore the selected codes? They will leave the review queue.',
            unmap: 'Unlink the selected codes from their employees?'
        };

        Swal.fire({
            title: 'Apply to ' + ids.length + ' row(s)?',
            text: wording[action],
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Apply',
            cancelButtonText: '@lang('app.cancel')'
        }).then(function (result) {
            if (!result.isConfirmed) { return; }

            $.easyAjax({
                url: "{{ route('biometric.employees.bulk-map') }}",
                type: 'POST',
                data: { action: action, ids: ids, pairs: pairs, _token: csrf },
                success: function () { window.location.reload(); }
            });
        });
    });
</script>
@endpush
