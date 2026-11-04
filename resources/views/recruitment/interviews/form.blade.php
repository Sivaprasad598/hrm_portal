@php
    $editing = $interview->exists;
    $start = $interview->starts_at->copy()->tz(company()->timezone);
    $selected = array_map('intval', $interview->interviewer_ids ?: []);
@endphp
<div class="row">
    <div class="col-sm-12">
        <x-form id="interview-form" method="POST">
            @if ($editing)
                <input type="hidden" name="_method" value="PUT">
            @else
                <input type="hidden" name="job_application_id" value="{{ $application->id }}">
            @endif

            <div class="add-client bg-white rounded">
                <h4 class="mb-0 p-20 f-21 font-weight-normal border-bottom-grey">{{ $editing ? 'Reschedule Interview' : 'Schedule Interview' }}</h4>

                <div class="row p-20">
                    <div class="col-12 f-14 mb-2">
                        <strong>{{ $application->candidate->name }}</strong> for {{ $application->positionLabel() }}
                        <span class="text-dark-grey">· {{ $application->candidate->email ?: 'no email on file' }}</span>
                    </div>

                    <div class="col-md-6">
                        <x-forms.label fieldId="round" fieldLabel="Round" :fieldRequired="true" />
                        <input type="text" class="form-control height-35 f-14" name="round" id="round" list="round-list" value="{{ $interview->round }}">
                        <datalist id="round-list">
                            @foreach ($rounds as $round)
                                <option value="{{ $round }}">
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-md-6">
                        <x-forms.select fieldId="mode" fieldLabel="Mode" fieldName="mode" :fieldRequired="true">
                            @foreach (\App\Models\RecruitInterview::MODES as $key => [$label])
                                <option value="{{ $key }}" @selected($interview->mode === $key)>{{ $label }}</option>
                            @endforeach
                        </x-forms.select>
                    </div>

                    <div class="col-md-4">
                        <x-forms.datepicker fieldId="interview_date" fieldLabel="Date" fieldName="interview_date" :fieldRequired="true"
                            :fieldValue="$start->format(company()->date_format)" :fieldPlaceholder="__('placeholders.date')" />
                    </div>
                    <div class="col-md-4">
                        <div class="bootstrap-timepicker timepicker">
                            <x-forms.text fieldId="start_time" fieldLabel="Start Time" fieldName="start_time" :fieldRequired="true"
                                :fieldValue="$start->format(company()->time_format)" />
                        </div>
                    </div>
                    <div class="col-md-4">
                        <x-forms.select fieldId="duration" fieldLabel="Duration" fieldName="duration" :fieldRequired="true">
                            @foreach (\App\Models\RecruitInterview::DURATIONS as $minutes)
                                <option value="{{ $minutes }}" @selected((int) $interview->duration === $minutes)>{{ $minutes }} minutes</option>
                            @endforeach
                        </x-forms.select>
                    </div>

                    <div class="col-12">
                        <x-forms.select fieldId="interviewer_ids" fieldLabel="Interview Panel" fieldName="interviewer_ids[]" :multiple="true" search="true" :fieldRequired="true">
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected(in_array($employee->id, $selected, true))>{{ $employee->name }}{{ $employee->employee_id ? ' (' . $employee->employee_id . ')' : '' }}</option>
                            @endforeach
                        </x-forms.select>
                        <small class="text-muted">Each interviewer gets it in their calendar and by email, and is asked for a scorecard afterwards.</small>
                    </div>

                    <div class="col-md-6" id="link-box">
                        <x-forms.text fieldId="meeting_link" fieldLabel="Meeting Link" fieldName="meeting_link" :fieldValue="$interview->meeting_link"
                            fieldPlaceholder="https://meet.google.com/..." />
                    </div>
                    <div class="col-md-6">
                        <x-forms.text fieldId="location" fieldLabel="Venue / Phone" fieldName="location" :fieldValue="$interview->location"
                            fieldPlaceholder="Office address, room, or the number to call" />
                    </div>

                    <div class="col-12">
                        <x-forms.textarea fieldId="notes" fieldLabel="Notes for the panel" fieldName="notes" :fieldValue="$interview->notes"
                            fieldPlaceholder="What to focus on, documents to bring …" />
                    </div>

                    <div class="col-12 mt-2">
                        <x-forms.checkbox fieldId="email_candidate" fieldName="email_candidate" fieldValue="1" :checked="(bool) $application->candidate->email"
                            :fieldLabel="$editing ? 'Email the candidate the new details' : 'Email the candidate an invitation'" />
                        @if (!$application->candidate->email)
                            <small class="text-danger">There is no email on file for this candidate.</small>
                        @endif
                    </div>
                </div>

                <x-form-actions>
                    <x-forms.button-primary id="save-interview" class="mr-3" icon="check">{{ $editing ? 'Save' : 'Schedule' }}</x-forms.button-primary>
                    <x-forms.button-secondary id="cancel-interview">@lang('app.cancel')</x-forms.button-secondary>
                </x-form-actions>
            </div>
        </x-form>
    </div>
</div>

@include('recruitment.partials.submit-js')

<script>
    $(function () {
        datepicker('#interview_date', {position: 'bl', ...datepickerConfig});
        $('#start_time').timepicker({
            @if (company()->time_format == 'H:i')
                showMeridian: false,
            @endif
        });

        function syncMode() { $('#link-box').toggle($('#mode').val() === 'video'); }
        $('#mode').on('change', syncMode);
        syncMode();

        $('#save-interview').on('click', function () {
            recruitSubmit('interview-form', '{{ $editing ? route('recruit-interviews.update', $interview->id) : route('recruit-interviews.store') }}', 'save-interview');
        });
        $('#cancel-interview').on('click', function () { recruitClose('{{ route('candidates.show', $application->candidate_id) }}'); });
        init(RIGHT_MODAL);
    });
</script>
