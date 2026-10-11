@if ($hasLeaveQuotas)
    <div class="w-100">
        @foreach ($allowedEmployeeLeavesQuotas as $leavesQuota)
            @php
                $display = $leavesQuota->quotaDisplay;
                $breakdown = $display['breakdown'];
                $leaveType = $leavesQuota->leaveType;
                $canEdit = isset($updateLeaveQuotaPermission) && $updateLeaveQuotaPermission == 'all';
            @endphp
            <div class="card border mb-3 {{ $leaveType->deleted_at ? 'opacity-75' : '' }}">
                {{-- Header --}}
                <div class="card-header bg-light d-flex flex-wrap align-items-start justify-content-between">
                    <div class="d-flex flex-wrap align-items-center">
                        <span class="badge text-white mr-2 mb-1" style="background-color:{{ $leaveType->color }}">
                            {{ $leaveType->type_name }}
                        </span>
                        @if ($leaveType->deleted_at)
                            <span class="text-muted f-12 mr-2 mb-1">(@lang('app.leaveArchive'))</span>
                        @endif
                        @if ($display['is_locked'])
                            <span class="badge badge-primary mr-2 mb-1">@lang('modules.leaves.manualOverride')</span>
                        @endif
                        <span class="badge badge-light border text-dark mr-2 mb-1">{{ $display['accrual_hint'] }}</span>
                        @if ($leaveType->paid == 1)
                            <span class="badge badge-success mr-2 mb-1">@lang('app.paid')</span>
                        @else
                            <span class="badge badge-secondary mr-2 mb-1">@lang('app.unpaid')</span>
                        @endif
                        @if ($display['supports_carry_forward'])
                            <span class="badge badge-warning mr-2 mb-1">@lang('modules.leaves.carryForwardPolicy')</span>
                        @endif
                        @if ($leaveType->monthly_limit > 0)
                            <span class="badge badge-light border text-dark mr-2 mb-1">
                                @lang('modules.leaves.monthLimit'): {{ $leaveType->monthly_limit }}
                            </span>
                        @endif
                    </div>
                    <div class="text-right ml-auto">
                        @if (method_exists($leaveType, 'isUnlimited') && $leaveType->isUnlimited())
                            <div class="text-uppercase text-muted f-11">@lang('app.unlimitedLeaveType')</div>
                            <div class="f-18 font-weight-bold text-primary">@lang('app.unlimitedLeaveType')</div>
                        @else
                            <div class="text-uppercase text-muted f-11">@lang('modules.leaves.totalRemaining')</div>
                            <div class="f-20 font-weight-bold text-primary">{{ $breakdown['total_remaining'] }}</div>
                        @endif
                    </div>
                </div>

                <div class="card-body">
                    @if (method_exists($leaveType, 'isUnlimited') && $leaveType->isUnlimited())
                        <div class="d-flex justify-content-between f-14">
                            <span class="text-dark-grey">@lang('app.totalLeavesTaken')</span>
                            <span class="font-weight-bold">{{ $leavesQuota->leaves_used }}</span>
                        </div>
                    @else
                        <div class="d-flex justify-content-between f-14 mb-2">
                            <span class="text-dark-grey">@lang('modules.leaves.periodAllocation')</span>
                            <span class="font-weight-bold">{{ $breakdown['current_year_allocated'] }}</span>
                        </div>

                        @if ($display['supports_carry_forward'] && !($breakdown['carry_forward_expired'] ?? false))
                            <div class="d-flex justify-content-between f-14 mb-2">
                                <span class="text-dark-grey">+ @lang('modules.leaves.carryForwardBalance')</span>
                                <span class="font-weight-bold text-warning">{{ $breakdown['carry_forward_allocated'] }}</span>
                            </div>
                        @endif

                        <div class="d-flex justify-content-between f-14 mb-2 border-top pt-2">
                            <span class="font-weight-bold">@lang('modules.leaves.totalAllocated')</span>
                            <span class="font-weight-bold">{{ $breakdown['total_allocated'] }}</span>
                        </div>

                        <div class="d-flex justify-content-between f-14 mb-2">
                            <span class="text-dark-grey">− @lang('app.totalLeavesTaken')</span>
                            <span class="font-weight-bold">{{ $leavesQuota->leaves_used }}</span>
                        </div>

                        <div class="d-flex justify-content-between f-14 mb-2 border-top pt-2">
                            <span class="font-weight-bold">= @lang('modules.leaves.remaining')</span>
                            <span class="font-weight-bold text-success f-16">{{ $breakdown['total_remaining'] }}</span>
                        </div>

                        @if ($breakdown['show_remaining_split'] ?? false)
                            <div class="pl-3 mb-2 border-left border-warning">
                                <div class="d-flex justify-content-between f-12 text-warning mb-1">
                                    <span>
                                        @lang('modules.leaves.expiringOn', [
                                            'date' => $breakdown['carry_forward_expiry']->translatedFormat(company()->date_format),
                                        ])
                                    </span>
                                    <span class="font-weight-bold">{{ $breakdown['remaining_expiring'] }}</span>
                                </div>
                                <div class="d-flex justify-content-between f-12 text-dark-grey">
                                    <span>@lang('modules.leaves.nonExpiringBalance')</span>
                                    <span class="font-weight-bold text-dark">{{ $breakdown['remaining_non_expiring'] }}</span>
                                </div>
                            </div>
                        @endif

                        @if ($breakdown['overutilised'] > 0)
                            <div class="d-flex justify-content-between f-14 alert alert-danger py-2 px-3 mb-2">
                                <span>@lang('modules.leaves.overUtilized')</span>
                                <span class="font-weight-bold">{{ $breakdown['overutilised'] }}</span>
                            </div>
                        @endif

                        @if ($breakdown['show_expired_unused'] ?? false)
                            <div class="alert alert-danger py-2 px-3 mb-2">
                                <div class="d-flex justify-content-between f-14">
                                    <div>
                                        @lang('modules.leaves.expiredUnusedLeaves')
                                        @if ($breakdown['expired_unused_on'])
                                            <div class="f-12 font-weight-normal mt-1">
                                                @lang('modules.leaves.expiredOn', [
                                                    'date' => $breakdown['expired_unused_on']->translatedFormat(company()->date_format),
                                                ])
                                            </div>
                                        @endif
                                    </div>
                                    <span class="font-weight-bold">{{ $breakdown['expired_unused_leaves'] }}</span>
                                </div>
                            </div>
                        @endif

                        @if ($breakdown['show_lapsed_unused'] ?? false)
                            <div class="d-flex justify-content-between f-14 alert alert-secondary py-2 px-3 mb-2">
                                <span>@lang('modules.leaves.lapsedUnusedLeaves')</span>
                                <span class="font-weight-bold">{{ $breakdown['lapsed_unused_leaves'] }}</span>
                            </div>
                        @endif

                        @if ($breakdown['show_regular_unused'] ?? false)
                            <div class="d-flex justify-content-between f-14 alert alert-secondary py-2 px-3 mb-2">
                                <span>@lang('modules.leaves.unusedLeaves')</span>
                                <span class="font-weight-bold">{{ $breakdown['unused'] }}</span>
                            </div>
                        @endif

                        @if ($display['supports_carry_forward'] && !empty($display['expiry_events']) && count($display['expiry_events']) > 0)
                            <div class="mt-3 border-top pt-3">
                                <p class="text-uppercase text-muted f-12 font-weight-bold mb-2">
                                    @lang('modules.leaves.carryForwardLapsedHistory')
                                </p>
                                @foreach ($display['expiry_events'] as $event)
                                    <div class="alert alert-danger py-2 px-3 mb-2 f-12">
                                        <div class="d-flex flex-wrap justify-content-between">
                                            <span class="font-weight-bold">
                                                @lang('modules.leaves.carryForwardLapsed', ['amount' => $event->amount])
                                            </span>
                                            <span>
                                                @lang('modules.leaves.carryForwardProcessedOn'):
                                                {{ $event->processed_at->timezone(company()->timezone)->translatedFormat(company()->date_format) }}
                                            </span>
                                        </div>
                                        <p class="mb-0 mt-1">
                                            @lang('modules.leaves.carryForwardLapsedDetail', [
                                                'lapsed' => $event->amount,
                                                'total' => $event->carry_forward_total,
                                                'used' => $event->carry_forward_used,
                                            ])
                                            @if ($event->expired_on)
                                                · @lang('modules.leaves.carryForwardExpiryDate'):
                                                {{ $event->expired_on->translatedFormat(company()->date_format) }}
                                            @endif
                                        </p>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @endif
                </div>

                @if ($canEdit && !(method_exists($leaveType, 'isUnlimited') && $leaveType->isUnlimited()))
                    <div class="card-footer bg-white">
                        <a href="javascript:;"
                           class="quota-adjust-toggle d-flex justify-content-between align-items-center text-dark-grey f-14"
                           data-quota-id="{{ $leavesQuota->id }}">
                            <span>@lang('modules.leaves.adjustAllocation')</span>
                            <i class="fa fa-chevron-down quota-adjust-chevron-{{ $leavesQuota->id }}"></i>
                        </a>

                        <div id="quota-adjust-panel-{{ $leavesQuota->id }}" class="quota-adjust-panel mt-3 d-none">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="f-14 text-dark-grey" for="period-leaves-{{ $leavesQuota->id }}">
                                            @lang('modules.leaves.periodAllocation')
                                        </label>
                                        <input type="number" min="0" step="0.01"
                                               id="period-leaves-{{ $leavesQuota->id }}"
                                               value="{{ $display['period_allocated'] }}"
                                               class="form-control height-35 period-leaves-{{ $leavesQuota->id }}">
                                    </div>
                                </div>
                                @if ($display['supports_carry_forward'])
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="f-14 text-dark-grey" for="carry-forward-{{ $leavesQuota->id }}">
                                                @lang('modules.leaves.carryForwardBalance')
                                            </label>
                                            <input type="number" min="0" step="0.01"
                                                   id="carry-forward-{{ $leavesQuota->id }}"
                                                   value="{{ $display['raw_carry_forward'] }}"
                                                   class="form-control height-35 carry-forward-{{ $leavesQuota->id }}">
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="form-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1"
                                           id="lock-policy-{{ $leavesQuota->id }}"
                                           {{ $display['is_locked'] ? 'checked' : '' }}>
                                    <label class="form-check-label f-14" for="lock-policy-{{ $leavesQuota->id }}"
                                           data-toggle="popover" data-placement="top"
                                           data-content="{{ __('modules.leaves.lockFromPolicyUpdatesHelp') }}"
                                           title="@lang('modules.leaves.lockFromPolicyUpdates')">
                                        @lang('modules.leaves.lockFromPolicyUpdates')
                                        <i class="fa fa-question-circle text-lightest"></i>
                                    </label>
                                </div>
                            </div>

                            <div class="text-right">
                                <button type="button"
                                        data-quota-id="{{ $leavesQuota->id }}"
                                        data-supports-cf="{{ $display['supports_carry_forward'] ? '1' : '0' }}"
                                        class="btn btn-primary update-quota-btn">
                                    @lang('app.save')
                                </button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@else
    <x-cards.no-record icon="redo" :message="__('messages.noRecordFound')" />
@endif

@if (isset($updateLeaveQuotaPermission) && $updateLeaveQuotaPermission == 'all')
<script>
    $(document).ready(function() {
        $(document).on('keydown', '.quota-adjust-panel input', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                return false;
            }
        });

        setTimeout(function() {
            $('[data-toggle="popover"]').popover();
        }, 300);

        $('.quota-adjust-toggle').off('click').on('click', function() {
            var quotaId = $(this).data('quota-id');
            $('#quota-adjust-panel-' + quotaId).toggleClass('d-none');
            $('.quota-adjust-chevron-' + quotaId).toggleClass('fa-chevron-down fa-chevron-up');
        });

        $('.update-quota-btn').off('click').on('click', function() {
            var quotaId = $(this).data('quota-id');
            var supportsCf = $(this).data('supports-cf') == 1;
            var periodLeaves = $('.period-leaves-' + quotaId).val();
            var carryForward = supportsCf ? ($('.carry-forward-' + quotaId).val() || 0) : 0;
            var leaveImpact = $('#lock-policy-' + quotaId).is(':checked') ? 1 : 0;

            var url = "{{ route('employee-leaves.update', ':id') }}";
            url = url.replace(':id', quotaId);

            $.easyAjax({
                type: 'POST',
                url: url,
                data: {
                    '_method': 'PUT',
                    '_token': "{{ csrf_token() }}",
                    'period_leaves': periodLeaves,
                    'carry_forward_leaves': carryForward,
                    'leaveimpact': leaveImpact,
                },
                success: function(response) {
                    if (response.status == 'success') {
                        window.location.reload();
                    }
                }
            });
        });
    });
</script>
@endif
