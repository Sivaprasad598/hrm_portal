<div class="d-grid d-lg-flex d-md-flex action-bar">
    {{-- Applying is self-service: everybody applies for their own leave here. --}}
    <div class="flex-grow-1 align-items-center">
        @if (\App\Http\Controllers\LeaveController::access()['apply'] && user()->employeeDetail)
            <x-forms.link-primary :link="route('leaves.create')" data-redirect-url="{{ route('leaves.personal') }}"
                class="mr-3 openRightModal float-left" icon="plus">
                Apply leave
            </x-forms.link-primary>
        @endif
    </div>

    @include('leaves.partials.view-switch', ['active' => 'personal', 'class' => 'ml-3'])
</div>

<!-- TAB CONTENT START -->
<div class="tab-pane fade show active mt-4" role="tabpanel" aria-labelledby="nav-email-tab">
    @php
        $rows = collect($hasLeaveQuotas ? $allowedEmployeeLeavesQuotas : [])
            ->filter(fn ($quota) => $quota->leaveType && !$quota->leaveType->deleted_at)
            ->map(function ($quota) {
                $type = $quota->leaveType;
                $unlimited = method_exists($type, 'isUnlimited') && $type->isUnlimited();
                $b = $quota->quotaDisplay['breakdown'];
                $given = (float) $b['total_allocated'];
                return (object) [
                    'name' => $type->type_name, 'color' => $type->color, 'paid' => $type->paid == 1, 'unlimited' => $unlimited,
                    'policy' => $quota->quotaDisplay['accrual_hint'], 'carry' => (float) ($b['carry_forward_allocated'] ?? 0),
                    'monthLimit' => (int) $type->monthly_limit, 'left' => $b['total_remaining'], 'given' => $b['total_allocated'],
                    'used' => $quota->leaves_used, 'pct' => $unlimited || $given <= 0 ? 0 : min(100, round($quota->leaves_used / $given * 100)),
                    'expiring' => (float) ($b['remaining_expiring'] ?? 0), 'expiresOn' => $b['carry_forward_expiry'] ?? null,
                ];
            })->values();
        $mine = \App\Models\Leave::where('user_id', $employee->id);
        $taken = $rows->sum('used');
        $pending = (clone $mine)->where('status', 'pending')->count();
        $next = (clone $mine)->where('status', 'approved')->whereDate('leave_date', '>=', now(company()->timezone)->toDateString())
            ->with('type')->orderBy('leave_date')->first();
        $recent = (clone $mine)->with('type')->orderByDesc('leave_date')->take(6)->get();
        $fmt = company()->date_format;
        $statusPill = ['approved' => 'ok', 'rejected' => 'bad', 'pending' => 'warn'];
    @endphp

    <div class="card bg-white border-0 b-shadow-4">
        <div class="card-header bg-white border-0 pt-4">
            <h4 class="f-18 f-w-500 mb-0">My Leaves</h4>
            <p class="ms-intro">Your leave balance for this leave year, by type, and your latest requests. Apply above; your manager or HR approves.</p>
        </div>
        <div class="card-body pt-2">
            <div class="ms-stats">
                <div class="ms-stat is-total">
                    <div class="lbl"><span class="ic" style="--ic-bg:var(--epic-blue,#cb6b4f);--ic-fg:#fff"><i class="fa fa-umbrella-beach"></i></span>Days left</div>
                    <div class="val">{{ $allowedLeaves }}</div>
                    <div class="sub">this year, all leave types</div>
                </div>
                <div class="ms-stat">
                    <div class="lbl"><span class="ic" style="--ic-bg:#e0f2fe;--ic-fg:#0369a1"><i class="fa fa-calendar-check"></i></span>Taken</div>
                    <div class="val">{{ $taken }}</div>
                    <div class="sub">day(s) used this year</div>
                </div>
                <div class="ms-stat {{ $pending ? 'is-warn' : '' }}">
                    <div class="lbl"><span class="ic" style="--ic-bg:#fff4e5;--ic-fg:#b45309"><i class="fa fa-hourglass-half"></i></span>Waiting for approval</div>
                    <div class="val">{{ $pending }}</div>
                    <div class="sub">{{ $pending ? 'request(s) not decided yet' : 'nothing pending' }}</div>
                </div>
                <div class="ms-stat">
                    <div class="lbl"><span class="ic" style="--ic-bg:#e7f6ec;--ic-fg:#15803d"><i class="fa fa-plane-departure"></i></span>Next leave</div>
                    <div class="val">{{ $next ? $next->leave_date->translatedFormat('d M') : '--' }}</div>
                    <div class="sub">{{ $next ? ($next->type?->type_name . ' · ' . $next->leave_date->translatedFormat('l')) : 'no approved leave ahead' }}</div>
                </div>
            </div>

            <div class="ms-sec">
                <h5>Balance by leave type <span>given this year, taken, and what is left</span></h5>
                @if ($rows->isNotEmpty())<a href="javascript:;" class="f-12" id="lq-toggle">Show full breakdown</a>@endif
            </div>
            @if ($rows->isEmpty())
                <div class="ms-empty"><i class="fa fa-calendar-times"></i><b>No leave types are given to you yet</b>HR assigns your leave types and quota. Ask HR if you need to apply for leave.</div>
            @else
                <div class="table-responsive">
                    <table class="ms-table">
                        <thead><tr><th>Leave type</th><th>Rule</th><th class="num">Given</th><th class="num">Taken</th><th class="num">Left</th><th style="width: 22%">Used</th></tr></thead>
                        <tbody>
                        @foreach ($rows as $r)
                            <tr>
                                <td><span class="lq-dot" style="background: {{ $r->color }}"></span><b>{{ $r->name }}</b>
                                    <span class="ms-pill ml-1 {{ $r->paid ? 'ok' : '' }}">{{ $r->paid ? 'Paid' : 'Unpaid' }}</span></td>
                                <td class="text-dark-grey">{{ $r->unlimited ? 'No limit' : $r->policy }}
                                    @if ($r->monthLimit)<div class="muted">max {{ $r->monthLimit }} a month</div>@endif</td>
                                <td class="num">@if ($r->unlimited)<span class="nil">—</span>@else{{ $r->given }}@endif
                                    @if ($r->carry > 0)<div class="muted">incl. {{ $r->carry }} carried over</div>@endif</td>
                                <td class="num">{{ $r->used }}</td>
                                <td class="num">
                                    @if ($r->unlimited)<b class="f-15">&infin;</b>@else<b class="f-15 {{ (float) $r->left <= 0 ? 'text-danger' : '' }}">{{ $r->left }}</b>@endif
                                    @if ($r->expiring > 0 && $r->expiresOn)<div class="muted text-warning">{{ $r->expiring }} expire {{ \Carbon\Carbon::parse($r->expiresOn)->format($fmt) }}</div>@endif
                                </td>
                                <td>@unless ($r->unlimited)<div class="lq-bar"><span style="width: {{ $r->pct }}%; background: {{ $r->color }}"></span></div><div class="muted mt-1">{{ $r->pct }}% used</div>@endunless</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                {{-- The detailed sums (accrued, carried over, expired) for whoever wants them --}}
                <div class="d-none mt-3" id="lq-detail">@include('employees.leaves_quota')</div>
            @endif

            <div class="ms-sec mt-4">
                <h5>Recent requests <span>your latest leave, newest first</span></h5>
                <a href="{{ route('leaves.index') }}" class="f-12">See all</a>
            </div>
            @if ($recent->isEmpty())
                <div class="ms-empty py-3">You have not applied for leave yet.</div>
            @else
                <div class="table-responsive">
                    <table class="ms-table">
                        <thead><tr><th>Date</th><th>Type</th><th>Duration</th><th>Status</th><th class="num"></th></tr></thead>
                        <tbody>
                        @foreach ($recent as $l)
                            <tr>
                                <td><b>{{ $l->leave_date->translatedFormat($fmt) }}</b> <span class="muted ml-1">{{ $l->leave_date->translatedFormat('l') }}</span></td>
                                <td><span class="lq-dot" style="background: {{ $l->type?->color }}"></span>{{ $l->type?->type_name }}</td>
                                <td>{{ $l->duration === 'half day' ? 'Half day' : ($l->duration === 'multiple' ? 'Multiple days' : 'Full day') }}</td>
                                <td><span class="ms-pill {{ $statusPill[$l->status] ?? '' }}">{{ ucfirst($l->status) }}</span></td>
                                <td class="num"><a href="{{ route('leaves.show', $l->id) }}" class="btn btn-sm btn-outline-secondary openRightModal"><i class="fa fa-eye mr-1"></i>View</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
<!-- TAB CONTENT END -->

<style>
    .lq-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-right: 8px; vertical-align: 1px; }
    .lq-bar { height: 6px; border-radius: 999px; background: #eef0f3; overflow: hidden; }
    .lq-bar span { display: block; height: 100%; border-radius: 999px; }
</style>

<script>
    $(document).ready(function() {
        $('#lq-toggle').on('click', function () {
            var open = !$('#lq-detail').toggleClass('d-none').hasClass('d-none');
            $(this).text(open ? 'Hide full breakdown' : 'Show full breakdown');
        });

        $('#renew-contract').click(function() {
            $(this).closest('.row').addClass('d-none');
            $('#save-renew-data-form').removeClass('d-none');
        });

        $('#cancel-renew').click(function() {
            $('#save-renew-data-form').addClass('d-none');
            $('#renew-contract').closest('.row').removeClass('d-none');
        });

        $('.update-category').click(function() {
            var id = $(this).data('type-id');
            var leaves = $('.leave-count-' + id).val();
            var url = "{{ route('employee-leaves.update', ':id') }}";
            url = url.replace(':id', id);

            var token = "{{ csrf_token() }}";

            $.easyAjax({
                type: 'POST',
                url: url,
                data: {
                    '_method': 'PUT',
                    '_token': token,
                    'leaves': leaves
                },
                success: function(response) {
                    if (response.status == "success") {
                        window.location.reload();
                    }
                }
            });
        });

    });

</script>
