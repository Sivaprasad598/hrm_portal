@php
    $fmt = fn ($v) => \App\Services\Payroll\OtBank::fmt((float) $v);
    $types = [
        'earned' => ['Overtime earned', 'success'],
        'compensated' => ['Covered LOP', 'primary'],
        'encashed' => ['Encashed', 'info'],
        'adjusted' => ['Correction', 'secondary'],
    ];
    $lastPast = $otGoLive->copy()->subDay();

    // The figures for the cards
    $countedHours = $otMonths->sum('bank') / 60;
    $countedDays = (int) $otMonths->sum('days');
    // Movements since go-live; only those already on a payslip (or HR corrections)
    // count against the balance - the rest are pending payroll. Past OT is kept
    // net of what was used before go-live, so it is not counted twice.
    $done = $otHistory->filter(fn ($r) => $r->payslip_number || $r->type === 'adjusted');
    $usedLop = abs((float) $done->where('type', 'compensated')->sum('hours'));
    $usedLopDays = (float) $done->where('type', 'compensated')->sum('days');
    $encashed = abs((float) $done->where('type', 'encashed')->sum('hours'));
    $encashedAmt = (float) $done->where('type', 'encashed')->sum('amount');
    $adjusted = (float) $done->where('type', 'adjusted')->sum('hours');
    $pendingRows = $otHistory->filter(fn ($r) => !$r->payslip_number && $r->type !== 'adjusted');
    $pending = $pendingRows->count();
    $pastSpent = (float) $otPast->sum('used_hours') + (float) $otPast->sum('encashed_hours');
    $pastMonths = $otPast->groupBy(fn ($r) => $r->year . '-' . $r->month)->count();

    // Which panel opens first: what the system counted, else the past record
    $open = $otMonths->isNotEmpty() ? 'counted' : ($otPast->isNotEmpty() ? 'past' : 'counted');
    $hasNotes = $otPast->contains(fn ($r) => $r->date === null && filled($r->note));
@endphp

<style>
    .otb-sum { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: -6px 0 22px; padding: 10px 14px; border-radius: 8px;
        background: #fafbfc; border: 1px dashed var(--border-color, #e4e7ec); font-size: 13px; color: var(--epic-text, #475467); }
    .otb-sum b { color: var(--epic-muted, #99a5b5); font-weight: 600; }
    .otb-sum strong { color: var(--epic-blue, #cb6b4f); }
    .otb-sum .otb-pending { font-size: 12px; color: #b45309; }
    .otb-panel { display: none; }
    .otb-panel.active { display: block; }
    .otb-slip { color: var(--epic-blue, #cb6b4f); font-weight: 500; white-space: nowrap; }
    .otb-slip:hover { text-decoration: underline; }
    .otb-slip .fa-external-link-alt { font-size: 10px; opacity: .7; }
    /* the app's standard: cards on the grey page, sections as white rounded cards */
    .otb-std .ms-stats { margin-bottom: 16px; }
    .otb-std .ms-stats > * { background: #fff; border-radius: 12px !important; box-shadow: 0 1px 3px rgba(16, 24, 40, .06); }
    .otb-std .otb-sum { background: #fff; border: 0; border-radius: 12px; box-shadow: 0 1px 3px rgba(16, 24, 40, .06); margin: 0 0 16px; padding: 12px 18px; }
    .otb-std .otb-panel.active { display: block; background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(16, 24, 40, .06); padding: 18px 20px; }
    .otb-std .ms-sec { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
    body.epic .otb-std .ms-sec .btn-sm { height: 38px; border-radius: 8px !important; padding: 0 14px !important; display: inline-flex; align-items: center; font-size: 14px; }
</style>

{{-- (no heading or intro text: the top bar names the page) --}}
<div class="otb-std">
    <div>

    {{-- ---------------- The counts: each opens its details below ---------------- --}}
    <div class="ms-stats">
        <div class="ms-stat is-total">
            <div class="lbl"><span class="ic" style="--ic-bg:#cb6b4f;--ic-fg:#fff"><i class="fa fa-piggy-bank"></i></span>Available now</div>
            <div class="val">{{ $fmt($otBalance) }}<small>h</small></div>
            <div class="sub">balance you can use</div>
        </div>
        <button type="button" class="ms-stat {{ $open === 'counted' ? 'active' : '' }}" data-panel="counted">
            <span class="go"><i class="fa fa-chevron-down"></i></span>
            <div class="lbl"><span class="ic" style="--ic-bg:#e7f6ec;--ic-fg:#15803d"><i class="fa fa-business-time"></i></span>Overtime earned</div>
            <div class="val">{{ $fmt($countedHours) }}<small>h</small></div>
            <div class="sub">{{ $countedDays }} day(s) since {{ $otGoLive->format('d M Y') }}</div>
        </button>
        <button type="button" class="ms-stat {{ $open === 'past' ? 'active' : '' }}" data-panel="past">
            <span class="go"><i class="fa fa-chevron-down"></i></span>
            <div class="lbl"><span class="ic" style="--ic-bg:#eef2ff;--ic-fg:#4338ca"><i class="fa fa-history"></i></span>Past OT</div>
            <div class="val">{{ $fmt($otPastNet) }}<small>h</small></div>
            <div class="sub">{{ $pastMonths }} month(s) before {{ $otGoLive->format('M Y') }}{{ $pastSpent ? ', after ' . $fmt($pastSpent) . 'h used then' : '' }}</div>
        </button>
        <button type="button" class="ms-stat" data-panel="used" data-filter="compensated">
            <span class="go"><i class="fa fa-chevron-down"></i></span>
            <div class="lbl"><span class="ic" style="--ic-bg:#fff4e5;--ic-fg:#b45309"><i class="fa fa-user-clock"></i></span>Used for LOP</div>
            <div class="val">{{ $fmt($usedLop) }}<small>h</small></div>
            <div class="sub">{{ $usedLopDays ? $fmt($usedLopDays) . ' LOP day(s) covered' : 'no LOP covered yet' }}</div>
        </button>
        <button type="button" class="ms-stat" data-panel="used" data-filter="encashed">
            <span class="go"><i class="fa fa-chevron-down"></i></span>
            <div class="lbl"><span class="ic" style="--ic-bg:#e0f2fe;--ic-fg:#0369a1"><i class="fa fa-rupee-sign"></i></span>Encashed</div>
            <div class="val">{{ $fmt($encashed) }}<small>h</small></div>
            <div class="sub">{{ $encashedAmt ? '₹' . number_format($encashedAmt, 0) . ' paid with salary' : 'nothing paid out yet' }}</div>
        </button>
    </div>

    {{-- How the balance is made up, in one line --}}
    <div class="otb-sum">
        <span>{{ $fmt($otPastNet) }}h past</span><b>+</b><span>{{ $fmt($countedHours) }}h earned</span>
        <b>−</b><span>{{ $fmt($usedLop) }}h for LOP</span><b>−</b><span>{{ $fmt($encashed) }}h encashed</span>
        @if (abs($adjusted) > 0.001)<b>{{ $adjusted < 0 ? '−' : '+' }}</b><span>{{ $fmt(abs($adjusted)) }}h corrections</span>@endif
        <b>=</b><strong>{{ $fmt($otBalance) }}h available</strong>
        @if ($pending)
            <a href="javascript:;" class="otb-pending ml-auto" data-panel="used"><i class="fa fa-clock mr-1"></i>{{ $pending }} movement(s) waiting for payroll ({{ $fmt(abs($pendingRows->sum('hours'))) }}h) — not taken off yet</a>
        @endif
    </div>

    {{-- ---------------- Earned since go-live, by salary period ---------------- --}}
    <div class="otb-panel {{ $open === 'counted' ? 'active' : '' }}" id="otb-counted">
        <div class="ms-sec">
            <h5>Overtime earned <span>counted by the system from attendance, per salary period</span></h5>
        </div>
        <div class="table-responsive">
            <table class="ms-table">
                <thead><tr><th>Salary period</th><th class="num">Days with OT</th><th class="num">Hours worked extra</th><th class="num">Saved to bank</th></tr></thead>
                <tbody>
                @forelse ($otMonths as $m)
                    @php [$y, $mo] = explode('-', $m->ym); $p = \App\Services\Payroll\PayPeriod::forMonth((int) $mo, (int) $y); @endphp
                    <tr>
                        <td><strong>{{ $p->end->format('M Y') }}</strong> <span class="muted ml-1">{{ $p->describe() }}</span></td>
                        <td class="num">{{ $m->days }}</td>
                        <td class="num">{{ \App\Services\Payroll\OtDaily::hm((int) $m->ot) }}</td>
                        <td class="num"><span class="ms-pill plus">+{{ \App\Services\Payroll\OtDaily::hm((int) $m->bank) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4"><div class="ms-empty"><i class="fa fa-business-time"></i>No overtime counted yet.</div></td></tr>
                @endforelse
                </tbody>
                @if ($otMonths->count() > 1)
                    <tfoot><tr><td>Total</td><td class="num">{{ $countedDays }}</td><td class="num">{{ \App\Services\Payroll\OtDaily::hm((int) $otMonths->sum('ot')) }}</td>
                        <td class="num text-success">{{ \App\Services\Payroll\OtDaily::hm((int) $otMonths->sum('bank')) }}</td></tr></tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- ---------------- Past OT (before go-live) ---------------- --}}
    <div class="otb-panel {{ $open === 'past' ? 'active' : '' }}" id="otb-past">
        <div class="ms-sec">
            <h5>Past OT <span>held up to {{ $lastPast->format('d M Y') }}, before the system counted it</span></h5>
            @if ($canManageOt)
                <div>
                    <button type="button" class="btn btn-sm btn-primary ot-past-open" data-year=""><i class="fa fa-plus mr-1"></i>Add past OT by month</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary ml-1" id="ot-past-day-add"><i class="fa fa-calendar-day mr-1"></i>Add a day</button>
                </div>
            @endif
        </div>
        <div class="table-responsive">
            <table class="ms-table">
                <thead>
                    <tr>
                        <th>Salary month</th><th class="num">Saved</th><th class="num">Used for LOP</th><th class="num">Encashed</th><th class="num">Left</th>
                        @if ($hasNotes)<th>Note</th>@endif
                        @if ($canManageOt)<th>Entered by</th><th></th>@endif
                    </tr>
                </thead>
                <tbody>
                @php
                    // A month entered day by day shows as one line, its days listed under it.
                    $lines = $otPast->groupBy(fn ($r) => $r->year . '-' . str_pad($r->month, 2, '0', STR_PAD_LEFT))->sortKeysDesc();
                @endphp
                @forelse ($lines as $ym => $group)
                    @php
                        $first = $group->first();
                        $dayWise = $first->date !== null;
                        $net = $group->sum(fn ($r) => $r->ot_hours - $r->used_hours - $r->encashed_hours);
                        $label = \Carbon\Carbon::create($first->year, $first->month, 1)->format('M Y');
                    @endphp
                    <tr>
                        <td class="text-nowrap">
                            <strong>{{ $label }}</strong>
                            <span class="muted ml-1">{{ \App\Services\Payroll\PayPeriod::forMonth($first->month, $first->year)->describe() }}</span>
                            @if ($dayWise)<span class="badge badge-light ml-1">{{ $group->count() }} day(s)</span>@endif
                        </td>
                        <td class="num">{{ $fmt($group->sum('ot_hours')) }}h
                            @if ($group->whereNotNull('raw_hours')->isNotEmpty())<div class="muted">worked {{ $fmt($group->sum('raw_hours')) }}h</div>@endif
                        </td>
                        <td class="num">@if ($first->used_hours > 0)<span class="ms-pill minus">−{{ $fmt($first->used_hours) }}h</span>
                            @if ($first->used_days)<div class="muted">{{ $fmt($first->used_days) }} day(s)</div>@endif @else <span class="nil">—</span> @endif
                        </td>
                        <td class="num">@if ($first->encashed_hours > 0)<span class="ms-pill minus">−{{ $fmt($first->encashed_hours) }}h</span>
                            @if ($first->encashed_amount)<div class="muted">₹{{ number_format((float) $first->encashed_amount, 2) }}</div>@endif @else <span class="nil">—</span> @endif
                        </td>
                        <td class="num font-weight-bold {{ $net < 0 ? 'text-red' : '' }}">{{ $fmt($net) }}h</td>
                        @if ($hasNotes)<td>{{ $dayWise ? '' : ($first->note ?: '') }}</td>@endif
                        @if ($canManageOt)
                            <td class="text-nowrap">
                                {{ $first->updated_name ?: $first->created_name }}
                                <div class="muted">{{ $first->source === 'import' ? 'Imported' : 'Manual' }} · {{ \Carbon\Carbon::parse($first->updated_at)->timezone(company()->timezone)->format(company()->date_format) }}</div>
                            </td>
                            <td class="text-nowrap text-right">
                                @unless ($dayWise)
                                    <a href="javascript:;" class="ot-past-open mr-2" data-year="{{ $first->year }}" title="Edit {{ $first->year }}"><i class="fa fa-edit"></i></a>
                                    <a href="javascript:;" class="text-danger ot-past-delete" data-id="{{ $first->id }}" data-label="{{ $label }}" title="Remove"><i class="fa fa-trash"></i></a>
                                @endunless
                            </td>
                        @endif
                    </tr>
                    @if ($dayWise)
                        @foreach ($group->sortBy('date') as $d)
                            <tr class="f-12">
                                <td class="pl-4 text-dark-grey">{{ \Carbon\Carbon::parse($d->date)->format('D, ' . company()->date_format) }}{{ $d->note ? ' · ' . $d->note : '' }}</td>
                                <td class="num">{{ $fmt($d->ot_hours) }}h @if ($d->raw_hours !== null)<span class="muted">(worked {{ $fmt($d->raw_hours) }}h)</span>@endif</td>
                                <td colspan="3"></td>
                                @if ($hasNotes)<td></td>@endif
                                @if ($canManageOt)
                                    <td></td>
                                    <td class="text-nowrap text-right">
                                        <a href="javascript:;" class="ot-past-day-edit mr-2" data-id="{{ $d->id }}" data-date="{{ $d->date }}" data-hours="{{ $fmt($d->ot_hours) }}"
                                           data-raw="{{ $d->raw_hours !== null ? $fmt($d->raw_hours) : '' }}" data-note="{{ $d->note }}" title="Edit"><i class="fa fa-edit"></i></a>
                                        <a href="javascript:;" class="text-danger ot-past-delete" data-id="{{ $d->id }}" data-label="{{ \Carbon\Carbon::parse($d->date)->format('d M Y') }}" title="Remove"><i class="fa fa-trash"></i></a>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    @endif
                @empty
                    <tr><td colspan="{{ 5 + ($hasNotes ? 1 : 0) + ($canManageOt ? 2 : 0) }}"><div class="ms-empty"><i class="fa fa-history"></i>
                        No past OT entered.@if ($canManageOt) Add what this employee held before {{ $otGoLive->format('d M Y') }}, month by month.@endif
                    </div></td></tr>
                @endforelse
                </tbody>
                @if ($otPast->isNotEmpty())
                    <tfoot>
                        <tr>
                            <td>Total</td>
                            <td class="num">{{ $fmt($otPast->sum('ot_hours')) }}h</td>
                            <td class="num">{{ $fmt($otPast->sum('used_hours')) }}h</td>
                            <td class="num">{{ $fmt($otPast->sum('encashed_hours')) }}h</td>
                            <td class="num text-success">{{ $fmt($otPastNet) }}h</td>
                            <td colspan="{{ ($hasNotes ? 1 : 0) + ($canManageOt ? 2 : 0) }}"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- ---------------- What left the bank (LOP cover, encashment, corrections) ---------------- --}}
    <div class="otb-panel" id="otb-used">
        <div class="ms-sec">
            <h5>Bank movements <span id="otb-used-note">LOP cover, encashment and corrections, by salary period</span></h5>
            <a href="javascript:;" class="f-12 d-none" id="otb-used-all">Show all movements</a>
        </div>
        <div class="table-responsive">
            <table class="ms-table">
                <thead>
                    <tr><th>Salary period</th><th>What happened</th><th class="num">Hours</th><th class="num">Days / Amount</th><th>Details</th><th>Payslip</th></tr>
                </thead>
                <tbody>
                @forelse ($otHistory as $row)
                    @php [$label, $colour] = $types[$row->type] ?? [ucfirst($row->type), 'light']; @endphp
                    <tr data-type="{{ $row->type }}">
                        <td class="text-nowrap">{{ \App\Services\Payroll\PayPeriod::containing(\Carbon\Carbon::parse($row->period_start))->describe() }}</td>
                        <td><span class="badge badge-{{ $colour }}">{{ $label }}</span></td>
                        <td class="num"><span class="ms-pill {{ $row->hours < 0 ? 'minus' : 'plus' }}">{{ $row->hours > 0 ? '+' : '' }}{{ $fmt($row->hours) }}h</span></td>
                        <td class="num">{!! $row->days ? $fmt($row->days) . ' day(s)' : ($row->amount ? '₹' . number_format((float) $row->amount, 2) : '<span class="nil">—</span>') !!}</td>
                        <td>{{ $row->note ?: '' }}{{ $row->by_name && $row->type === 'adjusted' ? ' · by ' . $row->by_name : '' }}</td>
                        <td class="text-nowrap">
                            @if ($row->payslip_number)
                                {{-- Opens the payslip it was settled on, in a new tab --}}
                                <a href="{{ $employee->id == user()->id ? route('my-payslips.show', $row->payslip_id) : route('payslips.generated.pdf', $row->payslip_id) }}"
                                   target="_blank" rel="noopener" class="otb-slip"><i class="fa fa-file-invoice mr-1"></i>{{ $row->payslip_number }} <i class="fa fa-external-link-alt ml-1"></i></a>
                            @elseif ($row->type === 'adjusted')
                                <span class="nil">—</span>
                            @else
                                <span class="badge badge-warning">Pending payroll</span>
                            @endif
                        </td>
                    </tr>
                @empty
                @endforelse
                <tr class="otb-used-empty {{ $otHistory->isEmpty() ? '' : 'd-none' }}"><td colspan="6"><div class="ms-empty"><i class="fa fa-inbox"></i>Nothing here yet.</div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<script>
    // The cards switch the details shown below them.
    $(function () {
        var used = $('#otb-used');
        function showUsed(filter) {
            var rows = used.find('tbody tr[data-type]');
            rows.toggleClass('d-none', false);
            if (filter) { rows.filter(function () { return $(this).data('type') !== filter; }).addClass('d-none'); }
            used.find('.otb-used-empty').toggleClass('d-none', rows.not('.d-none').length > 0);
            $('#otb-used-all').toggleClass('d-none', !filter);
            $('#otb-used-note').text(filter === 'compensated' ? 'hours that covered LOP days' : (filter === 'encashed' ? 'hours paid out with salary' : 'LOP cover, encashment and corrections, by salary period'));
        }
        $('body').off('click.otbcard').on('click.otbcard', '.ms-stat[data-panel]', function () {
            var card = $(this), panel = card.data('panel');
            $('.ms-stat').removeClass('active');
            card.addClass('active');
            $('.otb-panel').removeClass('active');
            $('#otb-' + panel).addClass('active');
            if (panel === 'used') { showUsed(card.data('filter')); }
        });
        $('body').off('click.otbpend').on('click.otbpend', '.otb-pending', function () {
            $('.ms-stat').removeClass('active');
            $('.otb-panel').removeClass('active');
            $('#otb-used').addClass('active');
            showUsed(null);
        });
        $('body').off('click.otball').on('click.otball', '#otb-used-all', function () { showUsed(null); });
    });
</script>

@if ($canManageOt)
    {{-- A year of months --}}
    <div class="modal fade" id="ot-past-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title f-16">Past OT by month</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" id="ot-past-body"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded f-14" data-dismiss="modal">@lang('app.cancel')</button>
                    <button type="button" class="btn btn-primary rounded f-14" id="ot-past-save"><i class="fa fa-check mr-1"></i>Save year</button>
                </div>
            </div>
        </div>
    </div>

    {{-- One day --}}
    <div class="modal fade" id="ot-past-day-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title f-16">Past OT for a day</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <form id="ot-past-day-form">
                        @csrf
                        <input type="hidden" name="user_id" value="{{ $employee->id }}">
                        <input type="hidden" name="id" value="">
                        <p class="f-12 text-dark-grey">Use this only for a month you keep day by day; otherwise enter the month's total. A month is one or the other.</p>
                        <div class="form-group">
                            <label class="f-14 text-dark-grey">Date <span class="text-red">*</span></label>
                            <input type="date" name="date" class="form-control" max="{{ $lastPast->toDateString() }}">
                        </div>
                        <div class="row">
                            <div class="col-6 form-group">
                                <label class="f-14 text-dark-grey">OT banked (hours) <span class="text-red">*</span></label>
                                <input type="text" inputmode="decimal" name="ot_hours" class="form-control" placeholder="3 or 2:30">
                            </div>
                            <div class="col-6 form-group">
                                <label class="f-14 text-dark-grey">OT worked (hours)</label>
                                <input type="text" inputmode="decimal" name="raw_hours" class="form-control" placeholder="optional">
                            </div>
                        </div>
                        <div class="form-group mb-0">
                            <label class="f-14 text-dark-grey">Note</label>
                            <input type="text" name="note" maxlength="255" class="form-control">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded f-14" data-dismiss="modal">@lang('app.cancel')</button>
                    <button type="button" class="btn btn-primary rounded f-14" id="ot-past-day-save"><i class="fa fa-check mr-1"></i>@lang('app.save')</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(function () {
            var tabUrl = "{{ route('employees.show', $employee->id) }}?tab=ot-bank";

            function loadYear(year) {
                $.easyAjax({
                    url: "{{ route('ot-past.year') }}", type: 'GET', blockUI: true,
                    data: { user_id: {{ $employee->id }}, year: year },
                    success: function (r) {
                        if (r.status === 'success') { $('#ot-past-body').html(r.html); $('#ot-past-modal').modal('show'); }
                    }
                });
            }

            $('body').off('click.otpast').on('click.otpast', '.ot-past-open', function () { loadYear($(this).data('year')); });
            $('#ot-past-body').on('change', '#ot-past-year', function () { loadYear($(this).val()); });

            $('#ot-past-save').on('click', function () {
                $.easyAjax({
                    url: "{{ route('ot-past.year.save') }}", container: '#ot-past-year-form', type: 'POST', blockUI: true,
                    disableButton: true, buttonSelector: '#ot-past-save', data: $('#ot-past-year-form').serialize(),
                    success: function (r) { if (r.status === 'success') { window.location.href = tabUrl; } }
                });
            });

            $('#ot-past-day-add').on('click', function () {
                var f = $('#ot-past-day-form');
                f[0].reset();
                f.find('[name=id]').val('');
                $('#ot-past-day-modal').modal('show');
            });

            $('body').off('click.otpastday').on('click.otpastday', '.ot-past-day-edit', function () {
                var f = $('#ot-past-day-form'), d = $(this).data();
                f.find('[name=id]').val(d.id);
                f.find('[name=date]').val(d.date);
                f.find('[name=ot_hours]').val(d.hours);
                f.find('[name=raw_hours]').val(d.raw);
                f.find('[name=note]').val(d.note);
                $('#ot-past-day-modal').modal('show');
            });

            $('#ot-past-day-save').on('click', function () {
                $.easyAjax({
                    url: "{{ route('ot-past.day') }}", container: '#ot-past-day-form', type: 'POST', blockUI: true,
                    disableButton: true, buttonSelector: '#ot-past-day-save', data: $('#ot-past-day-form').serialize(),
                    success: function (r) { if (r.status === 'success') { window.location.href = tabUrl; } }
                });
            });

            $('body').off('click.otpastdel').on('click.otpastdel', '.ot-past-delete', function () {
                var id = $(this).data('id'), label = $(this).data('label');
                Swal.fire({
                    title: 'Remove past OT for ' + label + '?', text: 'The balance changes by its net hours.', icon: 'warning',
                    showCancelButton: true, confirmButtonText: 'Remove', cancelButtonText: "@lang('app.cancel')",
                    customClass: { confirmButton: 'btn btn-primary mr-3', cancelButton: 'btn btn-secondary' }, buttonsStyling: false
                }).then(function (res) {
                    if (!res.isConfirmed) { return; }
                    $.easyAjax({
                        url: "{{ route('ot-past.delete') }}", type: 'POST', blockUI: true,
                        data: { id: id, _token: '{{ csrf_token() }}' },
                        success: function (r) { if (r.status === 'success') { window.location.href = tabUrl; } }
                    });
                });
            });
        });
    </script>
@endif
