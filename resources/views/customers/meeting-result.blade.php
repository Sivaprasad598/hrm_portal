@extends('layouts.app')

@php
    // Results saved earlier as "Follow-up Required" open as Meeting Result
    $result       = $meeting->result === 'ended' ? 'ended' : 'outcome';
    $followupType = $followupValues['meeting_type'] ?? 'online';
@endphp

@section('content')

<div class="content-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1">Meeting Result</h1>
            <p class="text-muted mb-0">
                {{ $meeting->customer->company_name ?? '-' }} ·
                {{ $meeting->purpose }} ·
                {{ $meeting->meeting_date ? $meeting->meeting_date->format('d M Y') : '-' }}
                {{ $meeting->start_time ? \Carbon\Carbon::parse($meeting->start_time)->format('h:i A') : '' }}
            </p>
        </div>
        <a href="{{ route('customers.index') }}#meetings" class="btn btn-outline-secondary">&larr; Back to Meetings</a>
    </div>

    <form id="meetingResultForm" novalidate>
        @csrf

        {{-- ===== Result choice ===== --}}
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h5 font-weight-bold mb-3">Meeting Result</h2>
                <div class="d-flex flex-wrap" style="gap: 28px;">
                    <label class="result-radio mb-0">
                        <input type="radio" name="result" value="ended" @checked($result === 'ended')> Discussion Ended
                    </label>
                    <label class="result-radio mb-0">
                        <input type="radio" name="result" value="outcome" @checked($result === 'outcome')> Meeting Result
                    </label>
                </div>
                @if ($meeting->result_at)
                    <p class="text-muted small mt-3 mb-0">Result saved on {{ $meeting->result_at->format('d M Y, h:i A') }}. Saving again updates it.</p>
                @endif
            </div>
        </div>

        {{-- ===== Meeting Result (optionally with a follow-up meeting) ===== --}}
        <div class="card mb-3" id="outcomeSection" @if ($result !== 'outcome') style="display: none;" @endif>
            <div class="card-body">
                <h2 class="h4 font-weight-bold mb-4">Meeting Result</h2>

                <div class="form-group row">
                    <label for="meetingOutcome" class="col-md-3 col-form-label">Meeting Result <span class="text-danger">*</span></label>
                    <div class="col-md-9">
                        <div class="d-flex" style="gap: 8px;">
                            <select name="outcome" id="meetingOutcome" class="form-control" data-option-type="result">
                                <option value="">Select Result</option>
                                @foreach ($meetingOutcomes as $outcome)
                                    <option value="{{ $outcome }}" @selected($meeting->outcome === $outcome)>{{ $outcome }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn-outline-secondary text-nowrap manage-meeting-options-btn"
                                    data-option-type="result" title="Add a meeting result">+ Add</button>
                        </div>
                        <div class="invalid-feedback d-block" data-error-for="outcome"></div>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="outcomeReason" class="col-md-3 col-form-label">Meeting Result Reason <span class="text-danger">*</span></label>
                    <div class="col-md-9">
                        <textarea name="outcome_reason" id="outcomeReason" class="form-control" rows="4"
                                  placeholder="Enter the reason briefly...">{{ $meeting->outcome_reason }}</textarea>
                        <div class="invalid-feedback d-block" data-error-for="outcome_reason"></div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" name="followup_required" value="1" id="followupRequired"
                           @checked($result === 'outcome' && $meeting->followup_meeting_id)>
                    <label class="custom-control-label font-weight-bold" for="followupRequired">Follow up required</label>
                    <p class="text-muted small mt-1 mb-0">If follow up is required, click on the check box. If not required, no need to click and save.</p>
                </div>

                <div class="mt-4" id="outcomeSaveRow">
                    <button type="submit" class="btn btn-primary" id="saveOutcomeBtn">Save</button>
                    <a href="{{ route('customers.index') }}#meetings" class="btn btn-outline-secondary ml-2">Cancel</a>
                </div>
            </div>
        </div>

        {{-- Follow-up meeting: same chooser and form as the Meetings tab.
             Shown when "Follow up required" is ticked. --}}
        <div id="followupMeetingSection" @if (!($result === 'outcome' && $meeting->followup_meeting_id)) style="display: none;" @endif>
            <div class="card mb-3">
                <div class="card-body">
                    <h3 class="h6 mb-1">Meet with Customer</h3>
                    <p class="text-muted small mb-3">Choose Online Meeting or Offline Meeting for the follow-up</p>
                    <div class="d-flex" style="gap: 10px;">
                        <button type="button" class="btn {{ $followupType === 'online' ? 'btn-primary' : 'btn-outline-primary' }}" id="onlineBtn">Online Meeting</button>
                        <button type="button" class="btn {{ $followupType === 'offline' ? 'btn-primary' : 'btn-outline-primary' }}" id="offlineBtn">Offline Meeting</button>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <h3 class="h5 mb-1 font-weight-bold" id="meetingFormTitle">Create {{ ucfirst($followupType) }} Meeting</h3>
                    <p class="text-muted small mb-4">Schedule the follow-up meeting with this customer</p>

                    <input type="hidden" name="meeting_type" id="meetingType" value="{{ $followupType }}">
                    @include('customers.partials.meeting-fields', ['values' => $followupValues, 'withCustomerAddButton' => false])

                    <button type="submit" class="btn btn-primary" id="saveFollowupBtn">Save Result &amp; Follow-up</button>
                    <a href="{{ route('customers.index') }}#meetings" class="btn btn-outline-secondary ml-2">Cancel</a>
                </div>
            </div>
        </div>

        {{-- ===== Discussion Ended ===== --}}
        <div class="card mb-3" id="endedSection" @if ($result !== 'ended') style="display: none;" @endif>
            <div class="card-body">
                <h3 class="result-title">Discussion Ended</h3>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Result</label>
                            <input type="text" class="form-control" value="Discussion Ended" readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="endReason">Reason <span class="text-danger">*</span></label>
                            <select name="end_reason" id="endReason" class="form-control">
                                <option value="">Select Reason</option>
                                @foreach ($endReasons as $reason)
                                    <option @selected($meeting->end_reason === $reason)>{{ $reason }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback d-block" data-error-for="end_reason"></div>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="endNotes">Additional Notes <span class="text-danger">*</span></label>
                            <textarea name="end_notes" id="endNotes" class="form-control" rows="3"
                                      placeholder="Enter details about why the discussion ended...">{{ $meeting->end_notes }}</textarea>
                            <div class="invalid-feedback d-block" data-error-for="end_notes"></div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-danger" id="closeLeadBtn">Close Lead</button>
                <a href="{{ route('customers.index') }}#meetings" class="btn btn-outline-secondary ml-2">Cancel</a>
            </div>
        </div>
    </form>
</div>

@include('customers.partials.meeting-options-modal')

@endsection

@push('scripts')
<style>
    .result-title { font-size: 15px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 18px; }
    .result-radio { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer; }
</style>
<script>
$(document).ready(function () {
    var form = $('#meetingResultForm');
    var emojiPattern = /[\u{1F000}-\u{1FAFF}\u{2300}-\u{23FF}\u{2600}-\u{27BF}\u{2B00}-\u{2BFF}\u{FE0F}\u{200D}\u{20E3}]/u;

    function result() { return form.find('[name="result"]:checked').val(); }

    function showError(name, message) {
        form.find('[name="' + name + '"]').toggleClass('is-invalid', !!message);
        form.find('[data-error-for="' + name + '"]').text(message || '');
    }

    function clearErrors() {
        form.find('.is-invalid').removeClass('is-invalid');
        form.find('[data-error-for]').text('');
    }

    function showServerErrors(xhr, fallbackField) {
        var body = xhr.responseJSON;
        if (!body) {
            try { body = JSON.parse(xhr.responseText); } catch (e) { body = null; }
        }

        if (body && body.errors) {
            Object.keys(body.errors).forEach(function (name) {
                showError(name, body.errors[name][0]);
            });
            form.find('.is-invalid').first().focus();
        } else {
            showError(fallbackField, (body && body.message) || 'Something went wrong. Please try again.');
        }
    }

    // Is the follow-up meeting form part of this save?
    function needsFollowupMeeting() {
        return result() === 'outcome' && $('#followupRequired').is(':checked');
    }

    function updateSections() {
        var mode = result();
        var withMeeting = needsFollowupMeeting();

        $('#endedSection').toggle(mode === 'ended');
        $('#outcomeSection').toggle(mode === 'outcome');
        $('#followupMeetingSection').toggle(withMeeting);
        // Meeting Result without a follow-up saves from its own card
        $('#outcomeSaveRow').toggle(mode === 'outcome' && !withMeeting);
    }

    form.on('change', '[name="result"]', function () {
        clearErrors();
        updateSections();
    });

    $('#followupRequired').on('change', function () {
        updateSections();
        if (this.checked) {
            $('html, body').animate({ scrollTop: $('#followupMeetingSection').offset().top - 80 }, 300);
        }
    });

    // ---- Online / Offline: same behaviour as the Meetings tab form ----
    function setMeetingType(type) {
        var online = type === 'online';

        $('#meetingType').val(type);
        $('#meetingFormTitle').text('Create ' + (online ? 'Online' : 'Offline') + ' Meeting');

        $('#onlineBtn').toggleClass('btn-primary', online).toggleClass('btn-outline-primary', !online);
        $('#offlineBtn').toggleClass('btn-primary', !online).toggleClass('btn-outline-primary', online);

        // Hidden fields are disabled so they are not submitted
        $('.online-only').toggle(online).find(':input').prop('disabled', !online);
        $('.offline-only').toggle(!online).find(':input').prop('disabled', online);
        updateGenerateMeetBtn();
    }

    // "Generate Link" is only for Google Meet
    function updateGenerateMeetBtn() {
        var online = $('#meetingType').val() === 'online';
        $('#generateMeetBtn').prop('disabled', !online);
    }

    $('#onlineBtn').on('click', function () { setMeetingType('online'); });
    $('#offlineBtn').on('click', function () { setMeetingType('offline'); });
    $('#meetingProvider').on('change', updateGenerateMeetBtn);

    function validateSlot() {
        var errors = {};
        var start = $('#meetingStart').val();
        var end = $('#meetingEnd').val();

        if (!$('#meetingCustomer').val()) errors.customer_id = 'Select a customer.';
        if (!$('#meetingDate').val()) errors.meeting_date = 'Select the meeting date.';
        if (!start) errors.start_time = 'Select the start time.';
        if (!end) errors.end_time = 'Select the end time.';
        if (start && end && end <= start) errors.end_time = 'End time must be after the start time.';

        ['customer_id', 'meeting_date', 'start_time', 'end_time'].forEach(function (name) {
            showError(name, errors[name] || '');
        });

        return errors;
    }

    $('#generateMeetBtn').on('click', function () {
        if (Object.keys(validateSlot()).length) {
            form.find('.is-invalid').first().focus();
            return;
        }

        var btn = $(this);
        btn.prop('disabled', true).text('Generating...');
        showError('meeting_link', '');

        $.ajax({
            url: "{{ route('customers.meetings.generate-meet') }}",
            type: 'POST',
            data: form.serialize(),
            success: function (r) {
                if (r.status === 'success' && r.meeting_link) {
                    $('#meetingLink').val(r.meeting_link);
                    $('#meetingGoogleEventId').val(r.google_event_id || '');
                    $('#meetingTeamsMeetingId').val(r.teams_meeting_id || '');
                    btn.text('Regenerate');
                } else {
                    btn.text('Generate Link');
                    showError('meeting_link', 'Could not create the Google Meet link. Please try again.');
                }
            },
            error: function (xhr) {
                btn.text($('#meetingGoogleEventId').val() || $('#meetingTeamsMeetingId').val() ? 'Regenerate' : 'Generate Link');
                showServerErrors(xhr, 'meeting_link');
            },
            complete: updateGenerateMeetBtn
        });
    });

    function validate() {
        var errors = {};

        if (result() === 'ended') {
            if (!$('#endReason').val()) errors.end_reason = 'Please select a reason.';
            if (!$.trim($('#endNotes').val())) errors.end_notes = 'Please enter additional notes.';
            ['end_reason', 'end_notes'].forEach(function (name) { showError(name, errors[name] || ''); });
        } else {
            if (!$('#meetingOutcome').val()) errors.outcome = 'Select the meeting result.';
            if (!$.trim($('#outcomeReason').val())) errors.outcome_reason = 'Enter the meeting result reason.';
            ['outcome', 'outcome_reason'].forEach(function (name) { showError(name, errors[name] || ''); });

            if (!needsFollowupMeeting()) {
                return Object.keys(errors).length === 0;
            }

            $.extend(errors, validateSlot());

            ['location', 'meeting_link'].forEach(function (name) {
                var field = form.find('[name="' + name + '"]');
                if (!field.prop('disabled') && emojiPattern.test(field.val())) errors[name] = 'Emojis are not allowed.';
            });

            ['location', 'meeting_link'].forEach(function (name) {
                showError(name, errors[name] || '');
            });
        }

        return Object.keys(errors).length === 0;
    }

    form.on('submit', function (e) {
        e.preventDefault();

        if (!validate()) {
            form.find('.is-invalid').first().focus();
            return;
        }

        var ended = result() === 'ended';
        var btn = ended ? $('#closeLeadBtn') : (needsFollowupMeeting() ? $('#saveFollowupBtn') : $('#saveOutcomeBtn'));
        var label = btn.text();

        if (ended && !confirm('Close this lead as Discussion Ended?')) return;

        btn.prop('disabled', true).text('Saving...');

        $.ajax({
            url: "{{ route('customers.meetings.result.save', $meeting->id) }}",
            type: 'POST',
            data: form.serialize(),
            success: function (r) {
                if (r.status === 'success') {
                    window.location.href = "{{ route('customers.index') }}#meetings";
                } else {
                    btn.prop('disabled', false).text(label);
                }
            },
            error: function (xhr) {
                btn.prop('disabled', false).text(label);
                showServerErrors(xhr, ended ? 'end_notes' : 'outcome_reason');
            }
        });
    });

    setMeetingType($('#meetingType').val());
    updateSections();
});
</script>
@endpush
