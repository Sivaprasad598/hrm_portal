{{-- Every employment of one employee: joined, left, rejoined. Used on the
     Rejoin page and the employee profile. $periods: EmploymentPeriods::all(). --}}
@php
    $fmt = company()->date_format;
    $show = fn ($v) => $v ? \Carbon\Carbon::parse($v)->translatedFormat($fmt) : null;
    $canEdit = user()->permission('edit_employees') === 'all';
@endphp

@forelse ($periods as $i => $p)
    @php $isCurrent = $loop->last && !$p->left_on; @endphp
    <div class="timeline-period {{ $isCurrent ? 'current' : '' }}">
        <div class="d-flex flex-wrap justify-content-between">
            <div>
                <strong class="f-14">
                    {{ $p->period_no == 1 ? 'Joined' : 'Rejoined' }} {{ $show($p->joined_on) ?? '—' }}
                    @if ($p->left_on) — left {{ $show($p->left_on) }} @elseif ($isCurrent) — <span class="text-success">current</span> @endif
                </strong>
                <div class="f-12 text-dark-grey">
                    {{ collect([$p->designation_name, $p->department_name, $p->employee_code ? 'ID ' . $p->employee_code : null])->filter()->implode(' · ') }}
                    @if ($p->employee_ctc) · CTC {{ company()->currency->currency_symbol }}{{ number_format((float) $p->employee_ctc) }} @endif
                </div>
                @if ($p->left_on)
                    <div class="f-12 text-dark-grey mt-1">
                        Exit: {{ ucfirst($p->exit_type ?? 'other') }}{{ $p->exit_reason ? ' — ' . $p->exit_reason : '' }}
                        @if ($p->settlement_status)
                            · Settlement {{ $p->settlement_status }}{{ $p->settlement_amount ? ' (' . company()->currency->currency_symbol . number_format((float) $p->settlement_amount, 2) . ')' : '' }}
                        @endif
                    </div>
                @endif
            </div>

            @if ($p->left_on)
                <div class="f-12 text-right">
                    <span class="text-lightest d-block">Eligible for rehire</span>
                    @if ($canEdit)
                        <select class="form-control form-control-sm d-inline-block w-auto rehire-flag" data-no-search
                                data-url="{{ route('employees.rejoin.eligibility', $p->id) }}">
                            <option value="" @selected($p->eligible_for_rehire === null)>Not decided</option>
                            <option value="1" @selected((string) $p->eligible_for_rehire === '1')>Yes</option>
                            <option value="0" @selected((string) $p->eligible_for_rehire === '0')>No</option>
                        </select>
                    @else
                        <strong>{{ $p->eligible_for_rehire === null ? 'Not decided' : ((string) $p->eligible_for_rehire === '1' ? 'Yes' : 'No') }}</strong>
                    @endif
                    @if ((string) $p->eligible_for_rehire === '0' && $p->not_eligible_reason)
                        <div class="text-red mt-1">{{ $p->not_eligible_reason }}</div>
                    @endif
                </div>
            @endif
        </div>
    </div>
@empty
    <p class="f-13 text-lightest mb-0">No employment on record.</p>
@endforelse

@once
    @push('scripts')
        <script>
            $(document).on('change', '.rehire-flag', function () {
                var select = $(this);
                var value = select.val();
                if (value === '') { return; }

                var send = function (reason) {
                    $.easyAjax({
                        url: select.data('url'), type: 'POST',
                        data: { _token: '{{ csrf_token() }}', eligible_for_rehire: value, not_eligible_reason: reason || '' },
                        success: function (r) { if (r.status === 'success') { window.location.reload(); } }
                    });
                };

                if (value === '1') { send(); return; }

                Swal.fire({ title: 'Not eligible for rehire', input: 'text', inputLabel: 'Reason', showCancelButton: true,
                    inputValidator: function (v) { return !v ? 'Give the reason' : undefined; },
                    customClass: { confirmButton: 'btn btn-primary mr-2', cancelButton: 'btn btn-secondary' }, buttonsStyling: false
                }).then(function (r) { if (r.isConfirmed) { send(r.value); } else { window.location.reload(); } });
            });
        </script>
    @endpush
@endonce
