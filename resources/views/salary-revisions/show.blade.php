@extends('layouts.app')

@php
    $inr = fn ($v) => '₹' . \App\Services\Payroll\SalaryRevisionLetter::inr($v);
    [$statusLabel, $statusColour] = \App\Services\Payroll\SalaryRevisions::STATUSES[$batch->status] ?? [$batch->status, 'light'];
    $types = \App\Services\Payroll\SalaryRevisions::TYPES;
    $oldTotal = $revisions->sum('old_ctc');
    $newTotal = $revisions->sum('new_ctc');
    $when = fn ($t) => $t ? \Carbon\Carbon::parse($t)->timezone(company()->timezone)->format(company()->date_format . ' H:i') : '';
    $steps = [
        ['Prepared', $batch->created_name, $batch->submitted_at ?: $batch->created_at, $batch->status !== 'draft' || true],
        ['CFO approval', $batch->cfo_name, $batch->cfo_at, (bool) $batch->cfo_at],
        ['CEO approval', $batch->ceo_name, $batch->ceo_at, (bool) $batch->ceo_at],
    ];
    $actions = ['created' => 'Created', 'submitted' => 'Sent for approval', 'approved_cfo' => 'Approved by CFO', 'approved_ceo' => 'Approved by CEO (final)',
        'rejected' => 'Sent back', 'withdrawn' => 'Withdrawn', 'released' => 'Letters released to employees'];
@endphp

@section('content')
<div class="content-wrapper">
    <div class="d-flex flex-wrap justify-content-between align-items-start mb-3">
        <div>
            <h4 class="f-18 mb-1">{{ $batch->name }} <span class="badge badge-{{ $statusColour }} f-12 align-middle">{{ $statusLabel }}</span></h4>
            <p class="f-13 text-dark-grey mb-0">
                Effective <strong>{{ \Carbon\Carbon::parse($batch->effective_date)->format(company()->date_format) }}</strong> · paid from
                <strong>{{ $payMonth }}</strong> payroll · {{ $revisions->count() }} employee(s)
                @if ($batch->note)<br><span class="text-lightest">{{ $batch->note }}</span>@endif
            </p>
        </div>
        <div class="mt-2 mt-md-0 text-right">
            <a href="{{ route('salary-revisions.index') }}" class="btn btn-outline-secondary rounded f-14 mb-1">Back</a>
            @if ($canPrepare && in_array($batch->status, ['draft', 'rejected']))
                <a href="{{ route('salary-revisions.edit', $batch->id) }}" class="btn btn-outline-primary rounded f-14 mb-1"><i class="fa fa-edit mr-1"></i>Edit</a>
                <button class="btn btn-outline-danger rounded f-14 mb-1 sr-act" data-do="delete" data-confirm="Delete this draft?"><i class="fa fa-trash mr-1"></i>Delete</button>
                <button class="btn btn-primary rounded f-14 mb-1 sr-act" data-do="submit" data-confirm="Send to the CFO for approval?"><i class="fa fa-paper-plane mr-1"></i>Send for approval</button>
            @endif
            @if ($canPrepare && $batch->status === 'pending_cfo')
                <button class="btn btn-outline-secondary rounded f-14 mb-1 sr-act" data-do="withdraw" data-confirm="Withdraw it and make it a draft again?">Withdraw</button>
            @endif
            @if ($canAct)
                <button class="btn btn-outline-danger rounded f-14 mb-1 sr-act" data-do="reject"><i class="fa fa-undo mr-1"></i>Send back</button>
                <button class="btn btn-success rounded f-14 mb-1 sr-act" data-do="approve"><i class="fa fa-check mr-1"></i>Approve as {{ strtoupper($myLevel) }}</button>
            @endif
            @if ($batch->status === 'approved')
                <a href="{{ route('salary-revisions.letters', $batch->id) }}" target="_blank" class="btn btn-outline-primary rounded f-14 mb-1"><i class="fa fa-file-pdf mr-1"></i>All letters</a>
                @if ($canPrepare && !$batch->released_at)
                    <button class="btn btn-primary rounded f-14 mb-1 sr-act" data-do="release" data-confirm="Release the letters? Each employee will see theirs on their profile."><i class="fa fa-share mr-1"></i>Release letters</button>
                @endif
            @else
                <a href="{{ route('salary-revisions.letters', $batch->id) }}" target="_blank" class="btn btn-outline-secondary rounded f-14 mb-1" title="Preview, marked DRAFT"><i class="fa fa-eye mr-1"></i>Preview letters</a>
            @endif
        </div>
    </div>

    @if ($batch->status === 'rejected')
        <div class="alert alert-danger f-13"><strong>Sent back by {{ $batch->rejected_name }}</strong> on {{ $when($batch->rejected_at) }}: {{ $batch->reject_reason }}</div>
    @elseif ($myLevel && !$canAct && in_array($myLevel, $levels))
        <div class="alert alert-info f-13">You prepared this revision, so somebody else has to approve it.</div>
    @elseif ($myLevel)
        <div class="alert alert-warning f-13">Waiting for the <strong>{{ strtoupper($myLevel) }}</strong>'s approval.</div>
    @elseif ($batch->status === 'approved')
        <div class="alert alert-success f-13">Approved. The new salaries are paid from <strong>{{ $payMonth }}</strong> payroll; employee records are updated when that month starts.
            {{ $batch->released_at ? 'Letters released on ' . $when($batch->released_at) . '.' : 'Release the letters when you are ready to hand them over.' }}</div>
    @endif

    {{-- Approval steps --}}
    <div class="bg-white rounded b-shadow-4 p-3 mb-3">
        <div class="row">
            @foreach ($steps as $i => [$label, $who, $at, $done])
                @php
                    $current = ($i === 1 && $batch->status === 'pending_cfo') || ($i === 2 && $batch->status === 'pending_ceo');
                    $isDone = $i === 0 ? $batch->status !== 'draft' : $done;
                @endphp
                <div class="col-md-4 mb-2 mb-md-0">
                    <div class="d-flex align-items-center">
                        <span class="sr-step {{ $isDone ? 'done' : ($current ? 'now' : '') }}">{!! $isDone ? '<i class="fa fa-check"></i>' : $i + 1 !!}</span>
                        <div class="ml-2">
                            <div class="font-weight-500 f-14">{{ $label }}</div>
                            <div class="f-12 text-lightest">{{ $isDone ? trim(($who ?: '') . ' · ' . $when($at), ' ·') : ($current ? 'waiting' : ($i === 0 ? 'draft' : '—')) }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="row mb-3">
        @foreach ([['Employees', $revisions->count(), $revisions->whereNotNull('new_designation_id')->count() . ' promoted'],
                   ['Annual CTC before', $inr($oldTotal), $inr($oldTotal / 12) . ' a month'],
                   ['Annual CTC after', $inr($newTotal), $inr($newTotal / 12) . ' a month'],
                   ['Increase', $oldTotal > 0 ? number_format(($newTotal - $oldTotal) / $oldTotal * 100, 2) . '%' : '–', $inr($newTotal - $oldTotal) . ' a year · ' . $inr(($newTotal - $oldTotal) / 12) . ' a month']] as [$l, $v, $s])
            <div class="col-lg-3 col-md-6 mb-2"><div class="bg-white rounded b-shadow-4 p-3 h-100">
                <p class="f-12 text-dark-grey text-uppercase mb-1">{{ $l }}</p><h4 class="mb-0 font-weight-bold">{{ $v }}</h4><p class="f-12 text-lightest mb-0">{{ $s }}</p>
            </div></div>
        @endforeach
    </div>

    <div class="bg-white rounded b-shadow-4 mb-3">
        <div class="table-responsive">
            <table class="table table-hover mb-0 f-13">
                <thead class="bg-light">
                    <tr><th>Employee</th><th>Type</th><th>Designation</th><th class="text-right">CTC before</th><th class="text-right">CTC after</th>
                        <th class="text-right">Hike</th><th class="text-right">Monthly gross</th><th>Arrears</th><th>Letter</th></tr>
                </thead>
                <tbody>
                @foreach ($revisions as $r)
                    @php $inc = $r->new_ctc - $r->old_ctc; @endphp
                    <tr>
                        <td><a href="{{ route('employees.show', $r->user_id) }}" class="font-weight-500 text-darkest-grey">{{ $r->name }}</a>
                            <br><span class="f-11 text-lightest">{{ $r->code }}{{ $r->old_department ? ' · ' . $r->old_department : '' }}</span>
                            @if ($r->reason)<br><span class="f-11 text-dark-grey">{{ $r->reason }}</span>@endif</td>
                        <td>{{ $types[$r->type] ?? $r->type }}</td>
                        <td>{{ $r->old_designation ?: '—' }}
                            @if ($r->new_designation)<br><span class="text-success"><i class="fa fa-arrow-up f-10"></i> {{ $r->new_designation }}</span>@endif
                            @if ($r->new_department)<br><span class="f-11 text-primary">→ {{ $r->new_department }}</span>@endif</td>
                        <td class="text-right">{{ $inr($r->old_ctc) }}</td>
                        <td class="text-right font-weight-600">{{ $inr($r->new_ctc) }}</td>
                        <td class="text-right {{ $inc >= 0 ? 'text-success' : 'text-red' }}">{{ $r->old_ctc > 0 ? number_format($inc / $r->old_ctc * 100, 2) . '%' : '–' }}<br><span class="f-11">{{ $inr($inc) }}</span></td>
                        <td class="text-right">{{ $inr($r->old_ctc / 12) }} → <strong>{{ $inr($r->new_ctc / 12) }}</strong></td>
                        <td class="f-12">{{ $r->arrears_amount ? $inr($r->arrears_amount) : '—' }}@if ($r->arrears_note)<br><span class="text-lightest">{{ $r->arrears_note }}</span>@endif</td>
                        <td class="text-nowrap">
                            <a href="{{ route('salary-revisions.letter', $r->id) }}" target="_blank" class="f-12"><i class="fa fa-file-pdf mr-1"></i>{{ $r->letter_no ?: 'Preview' }}</a>
                            @if ($r->applied_at)<br><span class="badge badge-success f-10">On record</span>@elseif ($batch->status === 'approved')<br><span class="badge badge-light border f-10">From {{ $payMonth }}</span>@endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded b-shadow-4 p-3 mb-4">
        <h5 class="f-14 font-weight-600 mb-2">History</h5>
        @foreach ($logs as $l)
            <div class="f-13 mb-1"><span class="text-lightest">{{ $when($l->created_at) }}</span> · <strong>{{ $actions[$l->action] ?? $l->action }}</strong>
                {{ $l->user_name ? 'by ' . $l->user_name : '' }}@if ($l->note) — <span class="text-dark-grey">{{ $l->note }}</span>@endif</div>
        @endforeach
    </div>
</div>

<style>
    .sr-step { width: 30px; height: 30px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: #eef0f3; color: #99a5b5; font-weight: 600; }
    .sr-step.done { background: #2cb100; color: #fff; }
    .sr-step.now { background: #fcbd01; color: #fff; }
</style>
@endsection

@push('scripts')
<script>
    $('.sr-act').on('click', function () {
        var act = $(this).data('do'), btn = $(this);
        var send = function (note) {
            $.easyAjax({ url: "{{ route('salary-revisions.action', $batch->id) }}", type: 'POST', blockUI: true,
                data: { _token: '{{ csrf_token() }}', 'do': act, note: note || '' } });
        };

        if (act === 'approve') {
            Swal.fire({ title: 'Approve this revision?', input: 'text', inputPlaceholder: 'Comment (optional)', icon: 'question', showCancelButton: true,
                confirmButtonText: 'Approve', cancelButtonText: '@lang('app.cancel')',
                customClass: { confirmButton: 'btn btn-success mr-3', cancelButton: 'btn btn-secondary' }, buttonsStyling: false
            }).then(function (r) { if (r.isConfirmed) { send(r.value); } });
        } else if (act === 'reject') {
            Swal.fire({ title: 'Send it back to HR?', input: 'textarea', inputPlaceholder: 'Why (required)', icon: 'warning', showCancelButton: true,
                confirmButtonText: 'Send back', cancelButtonText: '@lang('app.cancel')',
                inputValidator: function (v) { return !v && 'Say why it is sent back.'; },
                customClass: { confirmButton: 'btn btn-danger mr-3', cancelButton: 'btn btn-secondary' }, buttonsStyling: false
            }).then(function (r) { if (r.isConfirmed) { send(r.value); } });
        } else {
            Swal.fire({ title: btn.data('confirm'), icon: 'question', showCancelButton: true, confirmButtonText: 'Yes', cancelButtonText: '@lang('app.cancel')',
                customClass: { confirmButton: 'btn btn-primary mr-3', cancelButton: 'btn btn-secondary' }, buttonsStyling: false
            }).then(function (r) { if (r.isConfirmed) { send(); } });
        }
    });
</script>
@endpush
