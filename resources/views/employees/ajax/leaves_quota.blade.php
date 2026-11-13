<!-- TAB CONTENT START -->
<div class="tab-pane fade show active mt-5" role="tabpanel" aria-labelledby="nav-email-tab">

    <x-alert type="info" icon="info-circle">
        @lang('messages.leaveQuotaShowing', ['start_date' => $leaveStartDate, 'end_date' => $leaveEndDate])
    </x-alert>

    {{-- Remaining leave by type: the same figures as the Leaves Quota cards below --}}
    <div class="lq-cards my-3">
        <div class="lq-card lq-card--total">
            <span class="lq-card__label">Total Remaining Leaves</span>
            <span class="lq-card__value">{{ $allowedLeaves }}</span>
            <span class="lq-card__sub">all leave types</span>
        </div>
        @if ($hasLeaveQuotas)
            @foreach ($allowedEmployeeLeavesQuotas as $leavesQuota)
                @php
                    $lqType = $leavesQuota->leaveType;
                    $lqBreakdown = $leavesQuota->quotaDisplay['breakdown'] ?? [];
                    $lqUnlimited = method_exists($lqType, 'isUnlimited') && $lqType->isUnlimited();
                @endphp
                <div class="lq-card" style="--lq-colour: {{ $lqType->color ?: '#98a2b3' }}">
                    <span class="lq-card__label"><i class="fa fa-circle" style="color: var(--lq-colour)"></i> {{ $lqType->type_name }}</span>
                    <span class="lq-card__value">{{ $lqUnlimited ? '∞' : ($lqBreakdown['total_remaining'] ?? 0) }}</span>
                    <span class="lq-card__sub">
                        @if ($lqUnlimited)
                            {{ $leavesQuota->leaves_used }} used · unlimited
                        @else
                            {{ $leavesQuota->leaves_used }} of {{ $lqBreakdown['total_allocated'] ?? 0 }} used
                        @endif
                    </span>
                </div>
            @endforeach
        @endif
    </div>
    <style>
        .lq-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 14px; }
        .lq-card { display: flex; flex-direction: column; gap: 2px; padding: 16px 18px; background: #fff;
            border: 1px solid var(--border-color, #e8eef3); border-left: 4px solid var(--lq-colour, var(--epic-blue, #c0714f));
            border-radius: var(--card-radius, 12px); box-shadow: var(--card-shadow, 0 1px 3px rgba(16,24,40,.06)); }
        .lq-card--total { --lq-colour: var(--epic-blue, #c0714f); }
        .lq-card__label { font-size: 12.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; color: #475467; }
        .lq-card__label .fa-circle { font-size: 9px; vertical-align: middle; margin-right: 4px; }
        .lq-card__value { font-size: 28px; font-weight: 600; line-height: 1.2; color: #101828; }
        .lq-card__sub { font-size: 12.5px; color: #667085; }
    </style>

    <div class="card border">
        <div class="card-header bg-white border-bottom-0">
            <h4 class="mb-0 f-16 font-weight-bold">@lang('app.menu.leavesQuota')</h4>
        </div>
        <div class="card-body" id="comment-list">
            @include('employees.leaves_quota')
        </div>
    </div>
</div>
<!-- TAB CONTENT END -->
