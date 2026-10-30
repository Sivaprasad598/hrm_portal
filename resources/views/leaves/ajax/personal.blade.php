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
<div class="tab-pane fade show active mt-5" role="tabpanel" aria-labelledby="nav-email-tab">

    {{-- The balance at a glance: the total left, then each leave type - what is
         left of what was given and a bar of what is used. The person's own
         details are not repeated here (they are in the profile). --}}
    @php
        $balanceTypes = collect($hasLeaveQuotas ? $allowedEmployeeLeavesQuotas : [])
            ->filter(fn ($quota) => $quota->leaveType && !$quota->leaveType->deleted_at)
            ->map(function ($quota) {
                $type = $quota->leaveType;
                $unlimited = method_exists($type, 'isUnlimited') && $type->isUnlimited();
                $breakdown = $quota->quotaDisplay['breakdown'];
                $given = (float) $breakdown['total_allocated'];

                return [
                    'name' => $type->type_name,
                    'color' => $type->color,
                    'paid' => $type->paid == 1,
                    'unlimited' => $unlimited,
                    'left' => $breakdown['total_remaining'],
                    'given' => $breakdown['total_allocated'],
                    'used' => $quota->leaves_used,
                    'usedPct' => $unlimited || $given <= 0 ? 0 : min(100, round($quota->leaves_used / $given * 100)),
                ];
            })->values();
    @endphp

    <div class="leave-balance bg-white mb-4">
        <div class="leave-balance__total">
            <span class="leave-balance__label">@lang('modules.leaves.remainingLeaves')</span>
            <span class="leave-balance__figure">{{ $allowedLeaves }}</span>
            <span class="leave-balance__sub">days left this year, all types</span>
        </div>

        <div class="leave-balance__types">
            @forelse ($balanceTypes as $type)
                <div class="leave-balance__type">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="leave-balance__name">
                            <i style="background: {{ $type['color'] }}"></i>{{ $type['name'] }}
                        </span>
                        <span class="leave-balance__tag {{ $type['paid'] ? '' : 'is-unpaid' }}">{{ $type['paid'] ? __('app.paid') : __('app.unpaid') }}</span>
                    </div>

                    @if ($type['unlimited'])
                        <div class="leave-balance__left">&infin;</div>
                        <div class="leave-balance__of">{{ $type['used'] }} taken</div>
                    @else
                        <div class="leave-balance__left">{{ $type['left'] }} <small>of {{ $type['given'] }}</small></div>
                        <div class="leave-balance__bar"><span style="width: {{ $type['usedPct'] }}%; background: {{ $type['color'] }}"></span></div>
                        <div class="leave-balance__of">{{ $type['used'] }} taken</div>
                    @endif
                </div>
            @empty
                <p class="text-dark-grey mb-0">No leave types are given to you yet.</p>
            @endforelse
        </div>
    </div>

    <x-cards.data :title="__('app.menu.leavesQuota')">


        <div class="d-flex flex-wrap justify-content-between" id="comment-list">
            @include('employees.leaves_quota')
        </div>

    </x-cards.data>
</div>
<!-- TAB CONTENT END -->

<script>
    $(document).ready(function() {
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
