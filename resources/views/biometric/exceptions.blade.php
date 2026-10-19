@extends('layouts.app')

@php
    use App\Models\BiometricException as BE;
@endphp

@section('content')
    <div class="content-wrapper arw-dash">

        @include('biometric.nav', ['active' => $activeTab])

        @php
            $tiles = [
                ['sync', 'Sync issues', $tiles['sync'], 'text-red', 'device and ERP disagree, or data missing', ['category' => 'sync']],
                ['punch', 'Missing punches', $tiles['punch'], 'text-yellow', 'single punch, missing entry or exit', ['category' => 'punch']],
                ['unlinked', 'Unlinked device codes', $tiles['unlinked'], 'text-dark-grey', 'link on Employee Mapping, or ignore', ['type' => 'unlinked_code']],
                ['resolved', 'Resolved this week', $tiles['resolved_week'], 'text-success', 'by hand or by a later cross-check', ['status' => 'resolved']],
            ];
        @endphp

        <div class="row mb-2">
            @foreach ($tiles as [$key, $label, $value, $colour, $sub, $link])
                <div class="col-md-3 col-6 mb-2">
                    <a href="{{ route('biometric.exceptions', $link) }}" class="text-decoration-none">
                        <div class="card bg-white border-0 b-shadow-4 h-100">
                            <div class="card-body p-3">
                                <p class="mb-1 f-12 text-lightest text-uppercase">{{ $label }}</p>
                                <h4 class="mb-0 f-21 {{ $value > 0 ? $colour : 'text-dark-grey' }}">{{ $value }}</h4>
                                <p class="mb-0 f-11 text-lightest">{{ $sub }}</p>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <p class="f-12 text-lightest mb-3">
            @if ($lastRun)
                Last cross-check {{ $lastRun->reconciled_at->timezone(company()->timezone)->format('d M Y, H:i') }}
                (for {{ $lastRun->date->format('d M') }}).
            @else
                No cross-check has run yet.
            @endif
            The cross-check runs every night after ERP attendance is written; a problem it no longer finds closes by itself.
        </p>

        <x-cards.data padding="false" class="mb-3">
            {{-- Two sections, as tabs over one list --}}
            <ul class="nav nav-tabs px-3 pt-2 border-bottom-grey">
                @foreach ([null => 'All', 'sync' => 'Sync issues', 'punch' => 'Missing punches'] as $cat => $label)
                    <li class="nav-item">
                        <a class="nav-link f-14 {{ $category === ($cat ?: null) ? 'active' : '' }}"
                           href="{{ route('biometric.exceptions', array_filter(['category' => $cat ?: null, 'status' => $status])) }}">{{ $label }}</a>
                    </li>
                @endforeach
            </ul>

            <form method="GET" action="{{ route('biometric.exceptions') }}" class="p-3 border-bottom-grey">
                @if ($category)<input type="hidden" name="category" value="{{ $category }}">@endif
                <div class="row align-items-end">
                    <div class="col-md-2 mb-2">
                        <label class="f-12 text-dark-grey">Status</label>
                        <select name="status" class="form-control f-14" data-no-search>
                            @foreach (['open' => 'Open', 'resolved' => 'Resolved', 'ignored' => 'Ignored', 'all' => 'All'] as $value => $label)
                                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="f-12 text-dark-grey">Issue</label>
                        <select name="type" class="form-control f-14">
                            <option value="">All issues</option>
                            @foreach (BE::TYPES as $key => [$cat, $label])
                                @if (!$category || $category === $cat)
                                    <option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="f-12 text-dark-grey">@lang('app.startDate')</label>
                        <input type="date" name="start_date" value="{{ $startDate }}" class="form-control f-14">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="f-12 text-dark-grey">@lang('app.endDate')</label>
                        <input type="date" name="end_date" value="{{ $endDate }}" class="form-control f-14">
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="f-12 text-dark-grey">Employee or code</label>
                        <div class="d-flex">
                            <input type="text" name="search" value="{{ $search }}" class="form-control f-14 mr-1" placeholder="Search…">
                            <button type="submit" class="btn btn-primary f-14 mr-1">@lang('app.apply')</button>
                            <a href="{{ route('biometric.exceptions') }}" class="btn btn-secondary f-14">@lang('app.reset')</a>
                        </div>
                    </div>
                </div>
            </form>

            @if ($canAct && $exceptionRows->count())
                <div class="px-3 py-2 border-bottom-grey d-flex align-items-center flex-wrap" id="bulk-bar">
                    <span class="f-13 text-dark-grey mr-3" id="bulk-count">Select rows to act on several at once</span>
                    <button type="button" class="btn btn-sm btn-outline-primary mr-2 bulk-act" data-action="resolve" disabled>Resolve selected</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary mr-2 bulk-act" data-action="ignore" disabled>Ignore selected</button>
                    <span class="f-11 text-lightest">Ignoring an unlinked device code also marks the code Ignored on Employee Mapping.</span>
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-hover border-0 mb-0">
                    <thead>
                        <tr>
                            @if ($canAct)<th class="f-12" style="width:28px"><input type="checkbox" id="check-all"></th>@endif
                            <th class="f-12">@lang('app.date')</th>
                            <th class="f-12">Issue</th>
                            <th class="f-12">What was found</th>
                            <th class="f-12">Status</th>
                            <th class="f-12 text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($exceptionRows as $row)
                            <tr>
                                @if ($canAct)<td><input type="checkbox" class="row-check" value="{{ $row->id }}"></td>@endif
                                <td class="f-13 text-nowrap">{{ $row->date?->format('D, d M Y') }}</td>
                                <td class="f-13">
                                    <span class="badge badge-{{ $row->category === BE::SYNC ? 'danger' : 'warning' }} f-11">{{ $row->label() }}</span>
                                </td>
                                <td class="f-13">
                                    {{ $row->title }}
                                    <div class="f-11 text-lightest">{{ $row->help() }}</div>
                                    @if ($row->note && $row->status !== BE::OPEN)
                                        <div class="f-11 text-dark-grey mt-1"><i class="fa fa-comment-o mr-1"></i>{{ $row->note }}</div>
                                    @endif
                                </td>
                                <td class="f-12 text-nowrap">
                                    @if ($row->status === BE::OPEN)
                                        <span class="badge badge-light f-11">Open</span>
                                        @if ($row->first_seen_at && $row->last_seen_at && $row->first_seen_at->diffInDays($row->last_seen_at) >= 1)
                                            <div class="f-11 text-lightest">seen since {{ $row->first_seen_at->format('d M') }}</div>
                                        @endif
                                    @else
                                        <span class="badge badge-{{ $row->status === BE::IGNORED ? 'secondary' : 'success' }} f-11">{{ ucfirst($row->status) }}</span>
                                        <div class="f-11 text-lightest">
                                            {{ $row->resolution === 'auto' ? 'by the cross-check' : ($row->resolver->name ?? '') }},
                                            {{ $row->resolved_at?->format('d M H:i') }}
                                        </div>
                                    @endif
                                </td>
                                <td class="text-right text-nowrap">
                                    @if ($row->emp_code && isset($profileIds[$row->emp_code]) && !in_array($row->type, ['unlinked_code', 'mapping_conflict'], true))
                                        <a href="{{ route('biometric.profile', ['employee' => $profileIds[$row->emp_code], 'start_date' => $row->date->toDateString(), 'end_date' => $row->date->toDateString()]) }}"
                                           class="btn btn-sm btn-outline-secondary f-11">View day</a>
                                    @endif
                                    @if ($row->type === 'unlinked_code')
                                        <a href="{{ route('biometric.employees', ['search' => $row->emp_code]) }}" class="btn btn-sm btn-outline-secondary f-11">Link</a>
                                    @elseif ($row->type === 'mapping_conflict')
                                        <a href="{{ route('biometric.employees', ['map_status' => 'attention', 'search' => $row->emp_code]) }}" class="btn btn-sm btn-outline-secondary f-11">Fix on Employee Mapping</a>
                                    @endif
                                    @if ($canAct)
                                        @if (!in_array($row->type, ['unlinked_code', 'payslip_mismatch', 'mapping_conflict'], true))
                                            <button type="button" class="btn btn-sm btn-outline-primary f-11 resync-day" data-date="{{ $row->date->toDateString() }}">Re-sync day</button>
                                        @endif
                                        @if ($row->status === BE::OPEN)
                                            <button type="button" class="btn btn-sm btn-outline-success f-11 row-act" data-id="{{ $row->id }}" data-action="resolve">Resolve</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary f-11 row-act" data-id="{{ $row->id }}" data-action="ignore">Ignore</button>
                                        @else
                                            <button type="button" class="btn btn-sm btn-outline-secondary f-11 row-act" data-id="{{ $row->id }}" data-action="reopen">Reopen</button>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <p class="mb-1 f-15 text-dark-grey">Nothing {{ $status === 'all' ? '' : $status }} here.</p>
                                    <p class="mb-0 f-13 text-lightest">Device and ERP agree for everything the cross-check has looked at.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($exceptionRows->hasPages())
                <div class="p-3">{{ $exceptionRows->links() }}</div>
            @endif
        </x-cards.data>

        {{-- Punches the devices could not attribute to anyone --}}
        <x-cards.data :title="'Unidentified punches (last 7 days)'" padding="false">
            <div class="table-responsive">
                <table class="table table-hover border-0 mb-0">
                    <thead>
                        <tr>
                            <th class="f-12">@lang('app.date')</th>
                            <th class="f-12">Device</th>
                            <th class="f-12">Punches</th>
                            <th class="f-12">First</th>
                            <th class="f-12">Last</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($unidentified as $group)
                            <tr>
                                <td class="f-13">{{ \Carbon\Carbon::parse($group->day)->format('D, d M Y') }}</td>
                                <td class="f-13">{{ $devices[$group->controller_code] ?? ($group->controller_code ?: '--') }}</td>
                                <td class="f-13"><span class="text-red f-w-500">{{ $group->punches }}</span></td>
                                <td class="f-13">{{ \Carbon\Carbon::parse($group->first_seen)->format('H:i') }}</td>
                                <td class="f-13">{{ \Carbon\Carbon::parse($group->last_seen)->format('H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-4 f-13 text-lightest">Every punch in the last week carried an employee code.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="f-11 text-lightest px-3 py-2 mb-0 border-top-grey">
                Punches with no employee code: a failed read, an unenrolled card, or someone following another
                person through a door. A device with a sudden spike is worth checking.
            </p>
        </x-cards.data>

    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        var token = '{{ csrf_token() }}';

        function act(ids, action) {
            var titles = { resolve: 'Resolve', ignore: 'Ignore', reopen: 'Reopen' };
            var ask = action === 'reopen' ? Promise.resolve({ isConfirmed: true, value: '' }) : Swal.fire({
                title: titles[action] + (ids.length > 1 ? ' ' + ids.length + ' exceptions' : ''),
                input: 'text',
                inputLabel: 'Note (optional): what was done, or why it does not matter',
                showCancelButton: true,
                confirmButtonText: titles[action],
                customClass: { confirmButton: 'btn btn-primary mr-2', cancelButton: 'btn btn-secondary' },
                buttonsStyling: false
            });

            ask.then(function (r) {
                if (!r.isConfirmed) { return; }

                $.easyAjax({
                    url: '{{ route('biometric.exceptions.action') }}',
                    type: 'POST',
                    data: { _token: token, ids: ids, action: action, note: r.value || '' },
                    success: function (res) { if (res.status === 'success') { window.location.reload(); } }
                });
            });
        }

        $('.row-act').on('click', function () {
            act([$(this).data('id')], $(this).data('action'));
        });

        function selected() {
            return $('.row-check:checked').map(function () { return this.value; }).get();
        }

        function refreshBulk() {
            var n = selected().length;
            $('.bulk-act').prop('disabled', n === 0);
            $('#bulk-count').text(n ? n + ' selected' : 'Select rows to act on several at once');
        }

        $('#check-all').on('change', function () {
            $('.row-check').prop('checked', this.checked);
            refreshBulk();
        });
        $('.row-check').on('change', refreshBulk);
        $('.bulk-act').on('click', function () { act(selected(), $(this).data('action')); });

        $('.resync-day').on('click', function () {
            var button = $(this), date = button.data('date');

            Swal.fire({
                title: 'Re-sync ' + date + '?',
                text: 'Pulls the day again from the selected devices, rebuilds it, writes ERP attendance and cross-checks it. Takes up to a couple of minutes.',
                showCancelButton: true,
                confirmButtonText: 'Re-sync',
                customClass: { confirmButton: 'btn btn-primary mr-2', cancelButton: 'btn btn-secondary' },
                buttonsStyling: false
            }).then(function (r) {
                if (!r.isConfirmed) { return; }

                $('.resync-day').prop('disabled', true);
                button.html('<i class="fa fa-spinner fa-spin"></i> Working…');

                $.easyAjax({
                    url: '{{ route('biometric.exceptions.resync') }}',
                    type: 'POST',
                    data: { _token: token, date: date },
                    success: function (res) {
                        if (res.status === 'success') {
                            Swal.fire({ icon: 'success', text: res.message }).then(function () { window.location.reload(); });
                        } else {
                            $('.resync-day').prop('disabled', false);
                            button.text('Re-sync day');
                        }
                    },
                    error: function () {
                        $('.resync-day').prop('disabled', false);
                        button.text('Re-sync day');
                    }
                });
            });
        });
    });
</script>
@endpush
