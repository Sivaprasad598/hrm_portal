{{-- Employee Mapping -> Upload device list: the cross-check, before anything is linked.
     The machine's own employee list (Employee.xlsx) against the active employees here,
     matched on code AND name. Nothing is saved until Apply. --}}
@php
    $c = $check['counts'];
    $allLocations = $locations === [];
    $chosen = array_map(fn ($l) => \App\Services\Iapp\Sync\MachineListImport::locKey($l), $locations);
    $isChosen = fn ($loc) => $allLocations || in_array(\App\Services\Iapp\Sync\MachineListImport::locKey($loc), $chosen, true)
        || collect($chosen)->contains(fn ($w) => str_contains(\App\Services\Iapp\Sync\MachineListImport::locKey($loc), $w));
    $query = $allLocations ? ['all_locations' => 1] : ['locations' => $locations];
    $groups = [
        'link' => ['Will link', 'Code and name agree', 'ok', $c['link_new']],
        'conflict' => ['Name mismatch', 'Same code, another person', 'bad', $c['conflict']],
        'suggest' => ['Name match only', 'Tick to link', 'warn', $c['suggest']],
        'missing' => ['Not on device', 'To be enrolled', 'bad', $c['missing']],
        'device_only' => ['Device only', 'Nobody here (left?)', 'muted', $c['device_only']],
        'shared' => ['Shared cards', 'Temp, guest, security', 'muted', $c['shared']],
    ];
@endphp
<style>
    .mlc { font-size: 13.5px; }
    .mlc-sec { padding: 16px 24px; border-bottom: 1px solid var(--epic-line, #e8eef3); }
    .mlc-sec h6 { font-size: 12px; text-transform: uppercase; letter-spacing: .06em; color: #98a2b3; margin: 0 0 10px; font-weight: 600; }
    .mlc-lead { color: #475467; margin: 0; }
    .mlc-locs { display: flex; flex-wrap: wrap; gap: 8px; }
    .mlc-loc { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border: 1px solid #eaecf0; border-radius: 999px; cursor: pointer; margin: 0; font-size: 13px; }
    .mlc-loc input { margin: 0; }
    .mlc-loc b { color: #101828; }
    .mlc-loc.is-on { border-color: var(--epic-blue, #cb6b4f); background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 8%, #fff); }
    .mlc-tiles { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
    @media (max-width: 767.98px) { .mlc-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .mlc-tile { --tone: #667085; text-align: left; padding: 12px 14px; border: 1px solid #eaecf0; border-radius: 10px; background: #fff; cursor: pointer; border-left: 4px solid var(--tone); }
    .mlc-tile b { display: block; font-size: 22px; line-height: 1.1; color: #101828; }
    .mlc-tile span { display: block; font-weight: 600; color: #344054; margin-top: 2px; }
    .mlc-tile small { color: #98a2b3; font-size: 12px; }
    .mlc-tile--ok { --tone: #12b76a; } .mlc-tile--bad { --tone: #f04438; } .mlc-tile--warn { --tone: #f79009; } .mlc-tile--muted { --tone: #98a2b3; }
    .mlc-tile.is-active { background: color-mix(in srgb, var(--tone) 7%, #fff); border-color: var(--tone); }
    .mlc-list { display: none; }
    .mlc-list.is-active { display: block; }
    .mlc-list .table { margin: 0; }
    .mlc-list th { font-size: 11.5px; text-transform: uppercase; letter-spacing: .04em; color: #667085; font-weight: 600; border-top: 0 !important; white-space: nowrap; }
    .mlc-list td { vertical-align: middle !important; font-size: 13px; }
    .mlc-scroll { max-height: 46vh; overflow: auto; border: 1px solid #eaecf0; border-radius: 10px; }
    .mlc-note { padding: 10px 12px; border-radius: 8px; background: #f9fafb; border: 1px solid #eaecf0; color: #475467; font-size: 12.5px; margin-bottom: 10px; }
    .mlc-note--warn { background: #fffaeb; border-color: #fedf89; color: #93370d; }
    .mlc-tag { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 11.5px; font-weight: 600; background: #f2f4f7; color: #475467; }
    .mlc-tag--ok { background: #ecfdf3; color: #067647; }
    .mlc-sub { color: #98a2b3; font-size: 12px; }
    .mlc-foot { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; padding: 16px 24px; position: sticky; bottom: 0; background: #fff; border-top: 1px solid var(--epic-line, #e8eef3); }
    .mlc-foot .mlc-foot-note { margin-left: auto; color: #98a2b3; font-size: 12px; }
    .mlc-search { width: 100%; height: 34px; border: 1px solid #e4e7ec; border-radius: 8px; padding: 0 10px; font-size: 13px; margin-bottom: 10px; }
</style>

<div class="mlc" data-token="{{ $token }}">

    <div class="mlc-sec">
        <p class="mlc-lead">
            <strong>{{ $c['read'] }}</strong> entries on the device list, checked against <strong>{{ $c['employees'] }}</strong> active employees
            by <strong>code and name together</strong>. Nothing is saved until you click <strong>Apply</strong>.
        </p>
    </div>

    <div class="mlc-sec">
        <h6>Locations to read</h6>
        <div class="mlc-locs">
            @foreach ($fileLocations as $loc => $n)
                <label class="mlc-loc {{ $isChosen($loc) ? 'is-on' : '' }}">
                    <input type="checkbox" class="mlc-loc-box" value="{{ $loc }}" @checked($isChosen($loc))> {{ $loc }} <b>{{ $n }}</b>
                </label>
            @endforeach
        </div>
        @if ($c['other_location'] > 0)
            <div class="mlc-sub mt-2">{{ $c['other_location'] }} entries at the unticked locations are not read.
                @if ($settingsLocations) (Biometric Settings reads: {{ implode(', ', $settingsLocations) }}.) @endif</div>
        @endif
    </div>

    <div class="mlc-sec">
        <div class="mlc-tiles">
            @foreach ($groups as $key => [$label, $hint, $tone, $n])
                <button type="button" class="mlc-tile mlc-tile--{{ $tone }} {{ $loop->first ? 'is-active' : '' }}" data-group="{{ $key }}">
                    <b>{{ $n }}</b><span>{{ $label }}</span><small>{{ $hint }}</small>
                </button>
            @endforeach
        </div>
    </div>

    <div class="mlc-sec">
        <input type="search" class="mlc-search" placeholder="Search name or code in this list" autocomplete="off">

        {{-- Will link --}}
        <div class="mlc-list is-active" data-list="link">
            <div class="mlc-note">The device code is this person's Employee ID and the name on the device is theirs (spelt the same or
                differently: initials, word order, a letter off). <strong>{{ $c['link_new'] }}</strong> will be linked;
                {{ $c['link'] - $c['link_new'] }} are already linked.</div>
            <div class="mlc-scroll"><table class="table table-sm">
                <thead><tr><th>Device code</th><th>Name on device</th><th>Employee</th><th></th></tr></thead>
                <tbody>
                @forelse (collect($check['link'])->sortBy('already') as $r)
                    <tr class="mlc-row"><td>{{ $r['code'] }}</td><td>{{ $r['device_name'] }}</td>
                        <td>{{ $r['name'] }} <div class="mlc-sub">{{ $r['employee_id'] }}@if ($r['division']) · {{ $r['division'] }}@endif</div></td>
                        <td class="text-right">@if ($r['already'])<span class="mlc-tag mlc-tag--ok">Linked</span>@else<span class="mlc-tag">New</span>@endif</td></tr>
                @empty
                    <tr><td colspan="4" class="text-center mlc-sub py-3">None.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>

        {{-- Name mismatch --}}
        <div class="mlc-list" data-list="conflict">
            <div class="mlc-note mlc-note--warn">The code is this employee's ID, but the device has <strong>another person</strong> under it.
                These are <strong>not linked</strong> (their punches would be paid to the wrong person). Correct the Employee ID here,
                or the code on the device, then upload again.</div>
            <div class="mlc-scroll"><table class="table table-sm">
                <thead><tr><th>Device code</th><th>Name on device</th><th>Employee with this ID</th></tr></thead>
                <tbody>
                @forelse ($check['conflict'] as $r)
                    <tr class="mlc-row"><td>{{ $r['code'] }}</td><td>{{ $r['device_name'] }}</td>
                        <td>{{ $r['name'] }} <div class="mlc-sub">{{ $r['employee_id'] }}@if ($r['division']) · {{ $r['division'] }}@endif</div></td></tr>
                @empty
                    <tr><td colspan="3" class="text-center mlc-sub py-3">None.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>

        {{-- Name match only --}}
        <div class="mlc-list" data-list="suggest">
            <div class="mlc-note">The device code is nobody's Employee ID, but the full name matches exactly one employee who has no code yet.
                Probably enrolled under an old or different code. <strong>Tick the ones that are the same person</strong>; only those are linked.</div>
            <div class="mlc-scroll"><table class="table table-sm">
                <thead><tr><th style="width:34px"><input type="checkbox" class="mlc-suggest-all" title="Tick all"></th><th>Device code</th><th>Name on device</th><th>Employee</th></tr></thead>
                <tbody>
                @forelse ($check['suggest'] as $r)
                    <tr class="mlc-row"><td><input type="checkbox" class="mlc-suggest" value="{{ $r['code'] }}|{{ $r['user_id'] }}"></td>
                        <td>{{ $r['code'] }}</td><td>{{ $r['device_name'] }} <div class="mlc-sub">{{ $r['location'] }}</div></td>
                        <td>{{ $r['name'] }} <div class="mlc-sub">{{ $r['employee_id'] }}@if ($r['division']) · {{ $r['division'] }}@endif</div></td></tr>
                @empty
                    <tr><td colspan="4" class="text-center mlc-sub py-3">None.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>

        {{-- Not on device --}}
        <div class="mlc-list" data-list="missing">
            <div class="mlc-note mlc-note--warn">Active employees found on the device list neither by code nor by name, and not linked
                through their punches. Enroll them on the device with their <strong>Employee ID as the code</strong>, then upload a fresh list.
                (People who joined after this list was exported are here too.)</div>
            <div class="mlc-scroll"><table class="table table-sm">
                <thead><tr><th>Employee</th><th>Employee ID</th><th>Division</th></tr></thead>
                <tbody>
                @forelse ($check['missing'] as $r)
                    <tr class="mlc-row"><td>{{ $r['name'] }}</td><td>{{ $r['employee_id'] ?: '—' }}</td><td>{{ $r['division'] ?: '—' }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-center mlc-sub py-3">Everyone is on the device.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>

        {{-- Device only --}}
        <div class="mlc-list" data-list="device_only">
            <div class="mlc-note">On the device, but neither code nor name is an active employee here: mostly people who have left (still
                enrolled), or not on the payroll. Nothing is done with them.</div>
            <div class="mlc-scroll"><table class="table table-sm">
                <thead><tr><th>Device code</th><th>Name on device</th><th>Location</th></tr></thead>
                <tbody>
                @forelse ($check['device_only'] as $r)
                    <tr class="mlc-row"><td>{{ $r['code'] }}</td><td>{{ $r['device_name'] }}</td><td>{{ $r['location'] }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-center mlc-sub py-3">None.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>

        {{-- Shared cards --}}
        <div class="mlc-list" data-list="shared">
            <div class="mlc-note">Temporary, guest, visitor, security and test cards: not a person, never linked.</div>
            <div class="mlc-scroll"><table class="table table-sm">
                <thead><tr><th>Device code</th><th>Name on device</th><th>Location</th></tr></thead>
                <tbody>
                @forelse ($check['shared'] as $r)
                    <tr class="mlc-row"><td>{{ $r['code'] }}</td><td>{{ $r['device_name'] }}</td><td>{{ $r['location'] }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-center mlc-sub py-3">None.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>

    <div class="mlc-foot">
        <button type="button" class="btn btn-primary rounded f-14 mlc-apply" @disabled($c['link_new'] === 0 && $c['suggest'] === 0)>
            <i class="fa fa-link mr-1"></i> Apply: link <span class="mlc-apply-n">{{ $c['link_new'] }}</span>
        </button>
        <a class="btn btn-secondary rounded f-14 no-swap" download
           href="{{ route('biometric.employees.machine-list.export', ['token' => $token] + $query) }}">
            <i class="fa fa-file-excel mr-1"></i> Download Excel
        </a>
        <span class="mlc-foot-note">Links made by hand are never changed.</span>
    </div>
</div>

<script>
    (function () {
        var $box = $('.mlc[data-token="{{ $token }}"]');
        var checkUrl = "{{ route('biometric.employees.machine-list.check', $token) }}";
        var applyUrl = "{{ route('biometric.employees.machine-list.apply', $token) }}";
        var linkNew = {{ (int) $c['link_new'] }};

        function chosenLocations() {
            return $box.find('.mlc-loc-box:checked').map(function () { return this.value; }).get();
        }

        function query() {
            var locs = chosenLocations(), all = locs.length === $box.find('.mlc-loc-box').length;
            return all || !locs.length ? { all_locations: 1 } : { locations: locs };
        }

        // a tile shows its list
        $box.on('click', '.mlc-tile', function () {
            $box.find('.mlc-tile').removeClass('is-active'); $(this).addClass('is-active');
            $box.find('.mlc-list').removeClass('is-active').filter('[data-list="' + $(this).data('group') + '"]').addClass('is-active');
            $box.find('.mlc-search').val('').trigger('input');
        });

        // search inside the open list
        $box.on('input', '.mlc-search', function () {
            var term = this.value.toLowerCase().trim();
            $box.find('.mlc-list.is-active .mlc-row').each(function () {
                $(this).toggle(!term || this.textContent.toLowerCase().indexOf(term) !== -1);
            });
        });

        // ticked suggestions add to the count on Apply
        function recount() {
            var n = linkNew + $box.find('.mlc-suggest:checked').length;
            $box.find('.mlc-apply-n').text(n);
            $box.find('.mlc-apply').prop('disabled', n === 0);
        }
        $box.on('change', '.mlc-suggest', recount);
        $box.on('change', '.mlc-suggest-all', function () { $box.find('.mlc-suggest').prop('checked', this.checked); recount(); });

        // another set of locations: the check again, in the same panel
        $box.on('change', '.mlc-loc-box', function () {
            $(this).closest('.mlc-loc').toggleClass('is-on', this.checked);
            $.easyAjax({
                url: checkUrl, type: 'GET', data: query(), blockUI: true, container: '.mlc',
                success: function (r) { if (r.html) { $box.replaceWith(r.html); } }
            });
        });

        $box.on('click', '.mlc-apply', function () {
            var n = $box.find('.mlc-apply-n').text();
            Swal.fire({
                title: 'Link ' + n + ' employee(s) to their device codes?',
                text: 'Name mismatches are set aside, not linked. Their punches and attendance days follow the link.',
                icon: 'question', showCancelButton: true, confirmButtonText: 'Apply', cancelButtonText: '@lang('app.cancel')'
            }).then(function (res) {
                if (!res.isConfirmed) { return; }
                var data = $.extend({ _token: '{{ csrf_token() }}', suggest: $box.find('.mlc-suggest:checked').map(function () { return this.value; }).get() }, query());
                $.easyAjax({
                    url: applyUrl, type: 'POST', data: data, blockUI: true, container: '.mlc', disableButton: true, buttonSelector: '.mlc-apply',
                    success: function (r) {
                        if (r.status !== 'success') { return; }
                        Swal.fire({ icon: 'success', title: 'Applied', text: r.message }).then(function () { PageSwap.reload(); });
                    }
                });
            });
        });
    })();
</script>
