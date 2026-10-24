@extends('layouts.app')

@php
    $inr = fn ($v) => '₹' . \App\Services\Payroll\SalaryRevisionLetter::inr($v);
    $statuses = \App\Services\Payroll\SalaryRevisions::STATUSES;
    $fyIncrease = (float) ($fy->new_total ?? 0) - (float) ($fy->old_total ?? 0);
@endphp

@section('content')
<div class="content-wrapper">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h4 class="f-18 mb-0">Salary Revisions</h4>
            <p class="f-13 text-dark-grey mb-0">Hikes, promotions and appraisal cycles. HR prepares, the <strong>CFO</strong> and then the
                <strong>CEO</strong> approve; payroll pays the new salary from the effective month and the letters are issued.</p>
        </div>
        <div class="mt-2 mt-md-0">
            @if ($canPrepare)
                <a href="javascript:;" class="btn btn-outline-secondary rounded f-14 mr-1" id="sr-settings-open"><i class="fa fa-cog mr-1"></i>Letter settings</a>
                <a href="{{ route('salary-revisions.create') }}" class="btn btn-primary rounded f-14"><i class="fa fa-plus mr-1"></i>New appraisal cycle</a>
            @endif
        </div>
    </div>

    <div class="row mb-3">
        @foreach ([
            ['Awaiting your approval', $awaitingCount, $levels ? 'as ' . strtoupper(implode(' / ', $levels)) : 'you do not approve', $awaitingCount ? 'text-warning' : 'text-dark'],
            ['Revised in ' . $fyLabel, (int) ($fy->people ?? 0), (int) ($fy->promoted ?? 0) . ' promoted', 'text-primary'],
            ['Average increase', ($fy->old_total ?? 0) > 0 ? number_format($fyIncrease / $fy->old_total * 100, 2) . '%' : '–', 'approved this year', 'text-success'],
            ['Annual cost added', $inr($fyIncrease), $inr($fyIncrease / 12) . ' a month', 'text-dark'],
        ] as [$label, $value, $sub, $class])
            <div class="col-lg-3 col-md-6 mb-2">
                <div class="bg-white rounded b-shadow-4 p-3 h-100">
                    <p class="f-12 text-dark-grey text-uppercase mb-1">{{ $label }}</p>
                    <h3 class="mb-0 font-weight-bold {{ $class }}">{{ $value }}</h3>
                    <p class="f-12 text-lightest mb-0">{{ $sub }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="bg-white rounded b-shadow-4">
        <div class="p-3 border-bottom-grey d-flex flex-wrap align-items-center">
            @foreach (['' => 'All', 'mine' => 'Awaiting my approval', 'draft' => 'Drafts', 'pending_cfo' => 'Awaiting CFO', 'pending_ceo' => 'Awaiting CEO', 'rejected' => 'Sent back', 'approved' => 'Approved'] as $key => $label)
                <a href="{{ route('salary-revisions.index', $key ? ['status' => $key] : []) }}"
                   class="btn btn-sm mr-2 mb-1 {{ (string) $status === (string) $key ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</a>
            @endforeach
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 f-13">
                <thead class="bg-light">
                    <tr><th>Revision</th><th>Effective</th><th>Paid from</th><th class="text-right">Employees</th><th class="text-right">CTC before</th>
                        <th class="text-right">CTC after</th><th class="text-right">Increase</th><th>Status</th><th>Prepared by</th></tr>
                </thead>
                <tbody>
                @forelse ($batches as $b)
                    @php
                        $s = $sums[$b->id] ?? null;
                        $inc = $s ? $s->new_total - $s->old_total : 0;
                        [$label, $colour] = $statuses[$b->status] ?? [$b->status, 'light'];
                    @endphp
                    <tr>
                        <td><a href="{{ route('salary-revisions.show', $b->id) }}" class="font-weight-500 text-darkest-grey">{{ $b->name }}</a>
                            <br><span class="f-11 text-lightest">{{ $b->kind === 'single' ? 'Single employee' : 'Appraisal cycle' }}</span></td>
                        <td class="text-nowrap">{{ \Carbon\Carbon::parse($b->effective_date)->format(company()->date_format) }}</td>
                        <td class="text-nowrap">{{ \App\Services\Payroll\SalaryRevisions::monthLabel(\Carbon\Carbon::parse($b->applies_from)) }}</td>
                        <td class="text-right">{{ $s->people ?? 0 }}</td>
                        <td class="text-right">{{ $s ? $inr($s->old_total) : '–' }}</td>
                        <td class="text-right">{{ $s ? $inr($s->new_total) : '–' }}</td>
                        <td class="text-right {{ $inc >= 0 ? 'text-success' : 'text-red' }}">{{ $s && $s->old_total > 0 ? number_format($inc / $s->old_total * 100, 2) . '%' : '–' }}
                            @if ($s)<br><span class="f-11 text-lightest">{{ $inr($inc) }} a year</span>@endif</td>
                        <td><span class="badge badge-{{ $colour }}">{{ $label }}</span>
                            @if ($b->status === 'approved' && $b->released_at)<br><span class="f-11 text-success">letters released</span>@endif</td>
                        <td class="f-12">{{ $b->created_name }}<br><span class="text-lightest">{{ \Carbon\Carbon::parse($b->created_at)->format(company()->date_format) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="9"><x-cards.no-record icon="chart-line" message="No salary revisions here yet." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="f-12 text-lightest mt-2">To revise one employee, open their profile → Increment &amp; Promotions → Salary revision.</p>
</div>

@if ($canPrepare)
    <div class="modal fade" id="sr-settings" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title f-16">Letter settings</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
                <div class="modal-body">
                    <form id="sr-settings-form">
                        @csrf
                        <div class="form-group"><label class="f-14 text-dark-grey">Reference prefix</label>
                            <input type="text" name="letter_prefix" class="form-control" maxlength="40">
                            <small class="text-lightest">Letters are numbered PREFIX/2026-27/0001.</small></div>
                        <div class="form-group"><label class="f-14 text-dark-grey">Signatory name</label>
                            <input type="text" name="signatory_name" class="form-control" maxlength="120" placeholder="Authorised Signatory"></div>
                        <div class="form-group"><label class="f-14 text-dark-grey">Signatory designation</label>
                            <input type="text" name="signatory_designation" class="form-control" maxlength="120"></div>
                        <div class="form-group mb-0"><label class="f-14 text-dark-grey">HR contact (optional)</label>
                            <input type="text" name="hr_contact" class="form-control" maxlength="191"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded f-14" data-dismiss="modal">@lang('app.cancel')</button>
                    <button type="button" class="btn btn-primary rounded f-14" id="sr-settings-save">@lang('app.save')</button>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
    $('#sr-settings-open').on('click', function () {
        $.easyAjax({ url: "{{ route('salary-revisions.settings') }}", type: 'GET', blockUI: true, success: function (r) {
            var f = $('#sr-settings-form');
            $.each(r.settings, function (k, v) { f.find('[name=' + k + ']').val(v); });
            $('#sr-settings').modal('show');
        }});
    });
    $('#sr-settings-save').on('click', function () {
        $.easyAjax({ url: "{{ route('salary-revisions.settings') }}", type: 'POST', container: '#sr-settings-form', blockUI: true,
            data: $('#sr-settings-form').serialize(), success: function (r) { if (r.status === 'success') { $('#sr-settings').modal('hide'); } } });
    });
</script>
@endpush
