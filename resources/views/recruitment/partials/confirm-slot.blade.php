{{-- A slot the system suggested for a round, confirmed by HR where they see it (the quick view,
     My Interviews, the interview page). One click confirms it as suggested (the panel is told,
     the candidate emailed if ticked); a time already gone, or no panel, is set with "Set & confirm".
     $interview (status proposed); optional $confirmInModal (a panel opened from inside a panel). --}}
@if ($interview->status === 'proposed' && \App\Services\Recruit\RecruitAccess::can('manage'))
    @php $confirmBlocker = $interview->confirmBlocker(); @endphp
    <span class="rc-confirm d-inline-flex align-items-center flex-wrap" style="gap: 8px">
        @if ($confirmBlocker)
            <span class="f-12 text-warning"><i class="fa fa-exclamation-circle mr-1"></i>{{ $confirmBlocker === 'no panel' ? 'Choose the panel' : 'The suggested time has passed' }}</span>
            <a href="{{ route('recruit-interviews.edit', $interview->id) }}" class="btn btn-sm btn-primary openRightModal {{ !empty($confirmInModal) ? 'inModal' : '' }}"><i class="fa fa-calendar-alt mr-1"></i>Set &amp; confirm</a>
        @else
            <label class="f-12 text-dark-grey mb-0 d-inline-flex align-items-center" style="gap: 4px">
                <input type="checkbox" class="rc-confirm-email" @checked($interview->application?->candidate?->email) @disabled(!$interview->application?->candidate?->email)> email candidate
            </label>
            <a href="{{ route('recruit-interviews.edit', $interview->id) }}" class="btn btn-sm btn-outline-secondary openRightModal {{ !empty($confirmInModal) ? 'inModal' : '' }}">Change slot</a>
            <button type="button" class="btn btn-sm btn-primary rc-confirm-slot" data-id="{{ $interview->id }}"><i class="fa fa-check mr-1"></i>Confirm slot</button>
        @endif
    </span>

    @once
        <script>
            // Bound once, by name: the partial can be on the page and in a panel at the same time
            $('body').off('click.rcConfirmSlot').on('click.rcConfirmSlot', '.rc-confirm-slot', function () {
                var button = $(this);
                $.easyAjax({
                    url: '{{ route('recruit-interviews.confirm', ':id') }}'.replace(':id', button.data('id')), type: 'POST', blockUI: true,
                    data: {_token: '{{ csrf_token() }}', email_candidate: button.closest('.rc-confirm').find('.rc-confirm-email').is(':checked') ? 1 : 0},
                    success: function (response) { if (response.status === 'success') { PageSwap.reload(); } }
                });
            });
        </script>
    @endonce
@endif
