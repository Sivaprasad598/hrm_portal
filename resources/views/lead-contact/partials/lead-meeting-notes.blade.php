{{--
    Lead Team Meeting notes: project qualification (lead_meetings) on a Lead Team Meetings card.
    $meeting (CustomerMeeting), $leadMeeting (LeadTeamMeeting|null)
--}}
@php
    $fitStyles = [
        'high' => ['fa-check-circle', '#16a34a', '#f0fdf4', '#bbf7d0'],
        'medium' => ['fa-question-circle', '#ea580c', '#fff7ed', '#fed7aa'],
        'low' => ['fa-times-circle', '#dc2626', '#fef2f2', '#fecaca'],
    ];
    $fit = $leadMeeting?->validate_fit;
    $style = $fitStyles[$fit] ?? null;
    $notDiscussed = '<span class="text-lightest">Not discussed</span>';
    $hasSource = trim((string) ($meeting->meet_transcript ?: $meeting->meet_notes)) !== '';
@endphp

<div class="lead-meeting-notes" data-meeting-id="{{ $meeting->id }}">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-2" style="gap: 6px;">
        <div>
            <b class="f-14">Lead Team Meeting Notes</b>
            @if ($leadMeeting)
                <span class="badge badge-light border ml-1 f-11" title="Lead Team Meeting ID">{{ $leadMeeting->reference }}</span>
            @endif
        </div>
        <div class="d-flex" style="gap: 6px;">
            <button type="button" class="btn btn-outline-primary btn-sm lmn-analyse" @disabled(!$hasSource)
                    title="{{ $hasSource ? 'Read the meeting transcript again and refill these notes' : 'Available once the meeting is recorded and transcribed' }}">
                <i class="fa fa-magic mr-1"></i>{{ $leadMeeting?->hasNotes() ? 'Re-analyse' : 'Analyse' }}</button>
            <button type="button" class="btn btn-outline-secondary btn-sm lmn-edit"><i class="fa fa-edit mr-1"></i>Edit</button>
        </div>
    </div>

    {{-- ---------- View ---------- --}}
    <div class="lmn-view">
        @if (!$leadMeeting?->hasNotes())
            <p class="text-lightest f-13 mb-0">
                {{ $hasSource ? 'Not analysed yet. Click Analyse to fill these notes from the meeting transcript.'
                    : 'Filled automatically from the transcript after the meeting is recorded by the notetaker bot.' }}
            </p>
        @else
            <table class="table table-sm table-bordered mb-1 lmn-table">
                <tbody>
                    <tr>
                        <th>Validate Fit</th>
                        <td>
                            @if ($style)
                                <span class="lmn-fit" style="color: {{ $style[1] }}; background: {{ $style[2] }}; border-color: {{ $style[3] }};">
                                    <i class="fa {{ $style[0] }} mr-1"></i>{{ $leadMeeting->fitLabel() }}</span>
                            @else
                                {!! $notDiscussed !!}
                            @endif
                            @if ($leadMeeting->fit_reason)
                                <div class="text-dark-grey f-12 mt-1">{{ $leadMeeting->fit_reason }}</div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>Service Requirements</th>
                        <td>
                            @forelse ($leadMeeting->service_requirements ?? [] as $item)
                                <div>• {{ $item }}</div>
                            @empty
                                {!! $notDiscussed !!}
                            @endforelse
                        </td>
                    </tr>
                    <tr><th>Buying Entity</th><td>{!! $leadMeeting->buying_entity ? e($leadMeeting->buying_entity) : $notDiscussed !!}</td></tr>
                    <tr><th>Timing</th><td>{!! $leadMeeting->timing ? e($leadMeeting->timing) : $notDiscussed !!}</td></tr>
                    <tr>
                        <th>Decision Makers</th>
                        <td>
                            @forelse ($leadMeeting->decision_makers ?? [] as $item)
                                <div>• {{ $item }}</div>
                            @empty
                                {!! $notDiscussed !!}
                            @endforelse
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="text-lightest f-11">
                @if ($leadMeeting->source === 'manual')
                    Edited by hand{{ $leadMeeting->updated_at ? ' · ' . $leadMeeting->updated_at->format('d M Y, h:i A') : '' }}.
                @else
                    From the meeting transcript{{ $leadMeeting->analysed_at ? ' · ' . $leadMeeting->analysed_at->format('d M Y, h:i A') : '' }}.
                @endif
                @if ($leadMeeting->team_capabilities)
                    Fit judged against: {{ collect($leadMeeting->team_capabilities)->keys()->implode(', ') }}.
                @endif
                Verify dates at the source before acting on them.
            </div>
        @endif
    </div>

    {{-- ---------- Edit ---------- --}}
    <form class="lmn-form" style="display: none;">
        <div class="form-group mb-2">
            <label class="f-12 mb-1">Validate Fit</label>
            <select name="validate_fit" class="form-control form-control-sm">
                <option value="">Not discussed</option>
                @foreach (\App\Models\LeadTeamMeeting::FITS as $value => $label)
                    <option value="{{ $value }}" @selected($fit === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group mb-2">
            <label class="f-12 mb-1">Fit reason</label>
            <input name="fit_reason" class="form-control form-control-sm" value="{{ $leadMeeting?->fit_reason }}">
        </div>
        <div class="form-group mb-2">
            <label class="f-12 mb-1">Service Requirements <span class="text-lightest">(one per line)</span></label>
            <textarea name="service_requirements" class="form-control form-control-sm" rows="3">{{ implode("\n", $leadMeeting?->service_requirements ?? []) }}</textarea>
        </div>
        <div class="form-group mb-2">
            <label class="f-12 mb-1">Buying Entity</label>
            <input name="buying_entity" class="form-control form-control-sm" value="{{ $leadMeeting?->buying_entity }}">
        </div>
        <div class="form-group mb-2">
            <label class="f-12 mb-1">Timing</label>
            <input name="timing" class="form-control form-control-sm" value="{{ $leadMeeting?->timing }}">
        </div>
        <div class="form-group mb-2">
            <label class="f-12 mb-1">Decision Makers <span class="text-lightest">(one per line)</span></label>
            <textarea name="decision_makers" class="form-control form-control-sm" rows="2">{{ implode("\n", $leadMeeting?->decision_makers ?? []) }}</textarea>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Save</button>
        <button type="button" class="btn btn-outline-secondary btn-sm lmn-cancel">Cancel</button>
    </form>
</div>
