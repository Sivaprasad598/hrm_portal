@php
    $fmt = fn ($v) => \App\Services\Payroll\OtBank::fmt((float) $v);
    $types = [
        'earned' => ['Overtime earned', 'success'],
        'compensated' => ['Covered LOP', 'primary'],
        'encashed' => ['Encashed', 'info'],
        'adjusted' => ['Correction', 'secondary'],
    ];
    $lastPast = $otGoLive->copy()->subDay();
@endphp

<div class="bg-white rounded b-shadow-4 p-20">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <h4 class="f-18 font-weight-normal mb-0">OT Bank</h4>
        <div class="text-right">
            <span class="f-12 text-dark-grey d-block">Balance carried forward</span>
            <span class="f-21 font-weight-bold text-dark">{{ $fmt($otBalance) }}h</span>
            @if ($otPast->isNotEmpty())
                <span class="f-11 text-lightest d-block">includes {{ $fmt($otPastNet) }}h from past records</span>
            @endif
        </div>
    </div>
    <p class="f-12 text-dark-grey">
        Overtime is banked with no expiry, weighted by its rate (week-off and holiday hours count extra). It can cover LOP days or be
        encashed when payroll is generated. Movements of a period not yet paid show as pending.
    </p>

    {{-- ---------------- Past OT (before go-live) ---------------- --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
        <h5 class="f-14 font-weight-600 text-dark-grey mb-0">Past OT <span class="f-12 font-weight-normal text-lightest">up to {{ $lastPast->format('d M Y') }}, kept before the system</span></h5>
        @if ($canManageOt)
            <div>
                <button type="button" class="btn btn-sm btn-primary ot-past-open" data-year=""><i class="fa fa-plus mr-1"></i>Add past OT by month</button>
                <button type="button" class="btn btn-sm btn-outline-secondary ml-1" id="ot-past-day-add"><i class="fa fa-calendar-day mr-1"></i>Add a day</button>
            </div>
        @endif
    </div>
    <div class="table-responsive mb-4">
        <table class="table table-sm table-bordered f-13 mb-0">
            <thead class="bg-light">
                <tr>
                    <th>Salary month</th><th class="text-right">OT banked</th><th class="text-right">Used for LOP</th><th class="text-right">Encashed</th>
                    <th class="text-right">Net</th><th>Note</th><th>Entered</th>@if ($canManageOt)<th></th>@endif
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
                        <span class="f-11 text-lightest">{{ \App\Services\Payroll\PayPeriod::forMonth($first->month, $first->year)->describe() }}</span>
                        @if ($dayWise)<span class="badge badge-light ml-1">{{ $group->count() }} day(s)</span>@endif
                    </td>
                    <td class="text-right">{{ $fmt($group->sum('ot_hours')) }}h
                        @if ($group->whereNotNull('raw_hours')->isNotEmpty())<br><span class="f-11 text-lightest">worked {{ $fmt($group->sum('raw_hours')) }}h</span>@endif
                    </td>
                    <td class="text-right">{{ $first->used_hours > 0 ? $fmt($first->used_hours) . 'h' : '--' }}
                        @if ($first->used_days)<br><span class="f-11 text-lightest">{{ $fmt($first->used_days) }} day(s)</span>@endif
                    </td>
                    <td class="text-right">{{ $first->encashed_hours > 0 ? $fmt($first->encashed_hours) . 'h' : '--' }}
                        @if ($first->encashed_amount)<br><span class="f-11 text-lightest">₹{{ number_format((float) $first->encashed_amount, 2) }}</span>@endif
                    </td>
                    <td class="text-right font-weight-600 {{ $net < 0 ? 'text-red' : 'text-success' }}">{{ $fmt($net) }}h</td>
                    <td>{{ $dayWise ? '' : ($first->note ?: '') }}</td>
                    <td class="f-12 text-dark-grey text-nowrap">
                        <span class="badge badge-{{ $first->source === 'import' ? 'info' : 'secondary' }}">{{ $first->source === 'import' ? 'Imported' : 'Manual' }}</span>
                        {{ $first->updated_name ?: $first->created_name }}
                        <br><span class="f-11 text-lightest">{{ \Carbon\Carbon::parse($first->updated_at)->timezone(company()->timezone)->format(company()->date_format . ' H:i') }}</span>
                    </td>
                    @if ($canManageOt)
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
                            <td class="pl-4 text-dark-grey">{{ \Carbon\Carbon::parse($d->date)->format('D, ' . company()->date_format) }}</td>
                            <td class="text-right">{{ $fmt($d->ot_hours) }}h @if ($d->raw_hours !== null)<span class="text-lightest">(worked {{ $fmt($d->raw_hours) }}h)</span>@endif</td>
                            <td colspan="3"></td>
                            <td>{{ $d->note }}</td>
                            <td></td>
                            @if ($canManageOt)
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
                <tr><td colspan="{{ $canManageOt ? 8 : 7 }}" class="text-center text-dark-grey py-3">
                    No past OT entered.@if ($canManageOt) Add what this employee held before {{ $otGoLive->format('d M Y') }}, month by month.@endif
                </td></tr>
            @endforelse
            </tbody>
            @if ($otPast->isNotEmpty())
                <tfoot>
                    <tr class="font-weight-bold">
                        <td>Total</td>
                        <td class="text-right">{{ $fmt($otPast->sum('ot_hours')) }}h</td>
                        <td class="text-right">{{ $fmt($otPast->sum('used_hours')) }}h</td>
                        <td class="text-right">{{ $fmt($otPast->sum('encashed_hours')) }}h</td>
                        <td class="text-right text-success">{{ $fmt($otPastNet) }}h</td>
                        <td colspan="{{ $canManageOt ? 3 : 2 }}"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <h5 class="f-14 font-weight-600 text-dark-grey mb-2">Overtime by month <span class="f-12 font-weight-normal text-lightest">counted by the system from {{ $otGoLive->format('d M Y') }}</span></h5>
    <div class="table-responsive mb-4">
        <table class="table table-sm table-bordered f-13 mb-0">
            <thead class="bg-light"><tr><th>Salary period</th><th class="text-right">Days with OT</th><th class="text-right">Overtime</th><th class="text-right">Banked (× rate)</th></tr></thead>
            <tbody>
            @forelse ($otMonths as $m)
                @php [$y, $mo] = explode('-', $m->ym); @endphp
                <tr>
                    <td>{{ \App\Services\Payroll\PayPeriod::forMonth((int) $mo, (int) $y)->describe() }}</td>
                    <td class="text-right">{{ $m->days }}</td>
                    <td class="text-right">{{ \App\Services\Payroll\OtDaily::hm((int) $m->ot) }}</td>
                    <td class="text-right font-weight-500 text-success">{{ \App\Services\Payroll\OtDaily::hm((int) $m->bank) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-dark-grey py-3">No overtime yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <h5 class="f-14 font-weight-600 text-dark-grey mb-2">Used from the bank</h5>
    <div class="table-responsive">
        <table class="table table-sm table-bordered f-13 mb-0">
            <thead class="bg-light">
                <tr><th>Salary period</th><th>Movement</th><th class="text-right">Hours</th><th class="text-right">Days / Amount</th><th>Details</th><th>Payslip</th></tr>
            </thead>
            <tbody>
            @forelse ($otHistory as $row)
                @php [$label, $colour] = $types[$row->type] ?? [ucfirst($row->type), 'light']; @endphp
                <tr>
                    <td class="text-nowrap">{{ \App\Services\Payroll\PayPeriod::containing(\Carbon\Carbon::parse($row->period_start))->describe() }}</td>
                    <td><span class="badge badge-{{ $colour }}">{{ $label }}</span></td>
                    <td class="text-right font-weight-500 {{ $row->hours < 0 ? 'text-red' : 'text-success' }}">{{ $row->hours > 0 ? '+' : '' }}{{ $fmt($row->hours) }}h</td>
                    <td class="text-right">{{ $row->days ? $fmt($row->days) . ' day(s)' : ($row->amount ? number_format((float) $row->amount, 2) : '--') }}</td>
                    <td>{{ $row->note ?: '--' }}{{ $row->by_name && $row->type === 'adjusted' ? ' · by ' . $row->by_name : '' }}</td>
                    <td class="text-nowrap">
                        @if ($row->payslip_number)
                            {{ $row->payslip_number }}
                        @elseif ($row->type === 'adjusted')
                            --
                        @else
                            <span class="badge badge-warning">Pending</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-dark-grey py-4">Nothing used yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

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
