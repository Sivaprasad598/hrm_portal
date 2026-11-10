@php
    $editing = $candidate->exists;
    $fmt = company()->date_format;
    $jobs = $candidate->work_experience ?: [];
@endphp

<div class="row">
    <div class="col-sm-12">
        <x-form id="candidate-form" method="POST">
            @if ($editing)
                <input type="hidden" name="_method" value="PUT">
            @endif
            <input type="hidden" name="ai_filled" id="ai_filled" value="0">

            <div class="add-client bg-white rounded cand-form">
                <h4 class="mb-0 p-20 f-21 font-weight-normal border-bottom-grey">{{ $editing ? 'Edit Candidate' : 'Add Candidate' }}</h4>

                {{-- 1. Resume: upload it first, and AI can fill the rest --}}
                <div class="cand-section cand-section--resume">
                    <div class="cand-section__head">
                        <span class="cand-section__no">1</span>
                        <div>
                            <h5 class="cand-section__title">Resume</h5>
                            <p class="cand-section__hint">Upload it first: "Read resume with AI" fills the empty fields below for you to check.</p>
                        </div>
                    </div>
                    <div class="row align-items-end">
                        <div class="col-md-7">
                            <label class="f-14 text-dark-grey mb-1" for="resume_file">Resume file <span class="f-12 text-lightest">(PDF or Word, up to 5 MB)</span></label>
                            <input type="file" class="form-control-file f-14" name="resume_file" id="resume_file" accept=".pdf,.doc,.docx">
                            @if ($editing && $candidate->resume)
                                <small class="text-muted d-block mt-1">On file: <a href="{{ $candidate->resume->url }}" target="_blank">{{ $candidate->resume->filename }}</a>. A new file is kept alongside it.</small>
                            @endif
                        </div>
                        <div class="col-md-5 text-md-right mt-2 mt-md-0">
                            @if ($aiReady)
                                <button type="button" class="btn btn-secondary rounded f-14" id="resume-ai"><i class="fa fa-magic mr-1"></i>Read resume with AI</button>
                            @else
                                <span class="f-12 text-dark-grey">AI is off — fill the details by hand, or switch it on in Settings → AI Settings.</span>
                            @endif
                        </div>
                        <div class="col-12 f-13 mt-2" id="resume-ai-result"></div>
                    </div>
                </div>

                {{-- 2. Who they are --}}
                <div class="cand-section">
                    <div class="cand-section__head">
                        <span class="cand-section__no">2</span>
                        <div>
                            <h5 class="cand-section__title">Personal details</h5>
                            <p class="cand-section__hint">How to reach the candidate.</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <x-forms.text fieldId="name" fieldLabel="Full Name" fieldName="name" :fieldRequired="true" :fieldValue="$candidate->name" />
                        </div>
                        <div class="col-md-6">
                            <x-forms.text fieldId="email" fieldLabel="Email" fieldName="email" :fieldValue="$candidate->email" />
                        </div>
                        <div class="col-md-4">
                            <x-forms.text fieldId="mobile" fieldLabel="Mobile" fieldName="mobile" :fieldValue="$candidate->mobile" />
                        </div>
                        <div class="col-md-4">
                            <x-forms.select fieldId="gender" fieldLabel="Gender" fieldName="gender">
                                <option value="">--</option>
                                @foreach (['male' => 'Male', 'female' => 'Female', 'others' => 'Others'] as $key => $label)
                                    <option value="{{ $key }}" @selected($candidate->gender === $key)>{{ $label }}</option>
                                @endforeach
                            </x-forms.select>
                        </div>
                        <div class="col-md-4">
                            <x-forms.datepicker fieldId="date_of_birth" fieldLabel="Date of Birth" fieldName="date_of_birth"
                                :fieldValue="$candidate->date_of_birth?->format($fmt)" :fieldPlaceholder="__('placeholders.date')" />
                        </div>
                        <div class="col-12">
                            <x-forms.textarea fieldId="address" fieldLabel="Address" fieldName="address" :fieldValue="$candidate->address" />
                        </div>
                    </div>
                </div>

                {{-- 3. What they are applying for, and where they came from --}}
                <div class="cand-section">
                    <div class="cand-section__head">
                        <span class="cand-section__no">3</span>
                        <div>
                            <h5 class="cand-section__title">{{ $editing ? 'Source' : 'Application' }}</h5>
                            <p class="cand-section__hint">{{ $editing ? 'Where the candidate came from.' : 'The position they are applying for (or keep them in the talent pool), and where they came from.' }}</p>
                        </div>
                    </div>
                    <div class="row">
                        @if (!$editing)
                            <div class="col-md-6">
                                <x-forms.select fieldId="job_position_id" fieldLabel="Applying For" fieldName="job_position_id" search="true">
                                    <option value="">Not for a position yet (talent pool)</option>
                                    @foreach ($positions as $position)
                                        <option value="{{ $position->id }}" @selected($positionId === $position->id)>{{ $position->title }} ({{ $position->code }})</option>
                                    @endforeach
                                </x-forms.select>
                            </div>
                        @endif
                        <div class="col-md-{{ $editing ? '4' : '3' }}">
                            <x-forms.select fieldId="source" fieldLabel="Source" fieldName="source">
                                @foreach (\App\Models\Candidate::SOURCES as $key => [$label])
                                    <option value="{{ $key }}" @selected($candidate->source === $key)>{{ $label }}</option>
                                @endforeach
                            </x-forms.select>
                        </div>
                        <div class="col-md-{{ $editing ? '8' : '3' }}">
                            <x-forms.text fieldId="source_detail" fieldLabel="Source Detail" fieldName="source_detail" :fieldValue="$candidate->source_detail"
                                fieldPlaceholder="e.g. referred by Ravi (EMP-0042), Naukri, walk-in" />
                        </div>
                        @if (!$editing)
                            <div class="col-12">
                                <x-forms.textarea fieldId="cover_note" fieldLabel="Cover Note" fieldName="cover_note" fieldPlaceholder="What the candidate wrote, or how they came to us" />
                            </div>
                        @endif
                    </div>
                </div>

                {{-- 4. Their background and their present job --}}
                <div class="cand-section">
                    <div class="cand-section__head">
                        <span class="cand-section__no">4</span>
                        <div>
                            <h5 class="cand-section__title">Qualification &amp; current job</h5>
                            <p class="cand-section__hint">Their education and skills, where they work now, and what they earn and expect.</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <x-forms.text fieldId="qualification" fieldLabel="Highest Qualification" fieldName="qualification" :fieldValue="$candidate->qualification" />
                        </div>
                        <div class="col-md-6">
                            <x-forms.text fieldId="skills" fieldLabel="Skills" fieldName="skills" :fieldValue="implode(', ', $candidate->skills ?? [])"
                                fieldPlaceholder="Comma separated: AutoCAD, Excel, site supervision" />
                        </div>
                        <div class="col-md-4">
                            <x-forms.text fieldId="current_company" fieldLabel="Current Company" fieldName="current_company" :fieldValue="$candidate->current_company" />
                        </div>
                        <div class="col-md-4">
                            <x-forms.text fieldId="current_designation" fieldLabel="Current Designation" fieldName="current_designation" :fieldValue="$candidate->current_designation" />
                        </div>
                        <div class="col-md-4">
                            <x-forms.text fieldId="current_location" fieldLabel="Current Location" fieldName="current_location" :fieldValue="$candidate->current_location" />
                        </div>
                        <div class="col-md-3 col-6">
                            <x-forms.text fieldId="total_experience" fieldLabel="Experience (years)" fieldName="total_experience" :fieldValue="$candidate->total_experience"
                                fieldPlaceholder="0 for a fresher" />
                        </div>
                        <div class="col-md-3 col-6">
                            <x-forms.number fieldId="notice_period_days" fieldLabel="Notice Period (days)" fieldName="notice_period_days" :fieldValue="$candidate->notice_period_days" />
                        </div>
                        <div class="col-md-3 col-6">
                            <x-forms.number fieldId="current_ctc" fieldLabel="Current CTC (per year)" fieldName="current_ctc" :fieldValue="$candidate->current_ctc" />
                        </div>
                        <div class="col-md-3 col-6">
                            <x-forms.number fieldId="expected_ctc" fieldLabel="Expected CTC (per year)" fieldName="expected_ctc" :fieldValue="$candidate->expected_ctc" />
                        </div>
                    </div>
                </div>

                {{-- 5. Earlier jobs --}}
                <div class="cand-section">
                    <div class="cand-section__head">
                        <span class="cand-section__no">5</span>
                        <div>
                            <h5 class="cand-section__title">Work history</h5>
                            <p class="cand-section__hint">Latest first. Leave "To" empty for the job they are in now.</p>
                        </div>
                    </div>
                    <div class="exp-grid exp-grid--head d-none d-md-grid">
                        <span>Company</span><span>Designation</span><span>Location</span><span>From</span><span>To</span><span></span>
                    </div>
                    <div id="exp-rows">
                        @foreach ($jobs ?: [[]] as $job)
                            <div class="exp-grid exp-row">
                                <input class="form-control f-14" name="exp_company[]" placeholder="Company" value="{{ $job['company_name'] ?? '' }}">
                                <input class="form-control f-14" name="exp_designation[]" placeholder="Designation" value="{{ $job['designation'] ?? '' }}">
                                <input class="form-control f-14" name="exp_location[]" placeholder="Location" value="{{ $job['location'] ?? '' }}">
                                <input class="form-control f-14" name="exp_joining[]" placeholder="{{ $fmt }}" value="{{ $job['joining_date'] ?? '' }}">
                                <input class="form-control f-14" name="exp_leaving[]" placeholder="Empty = now" value="{{ $job['leaving_date'] ?? '' }}">
                                <button type="button" class="btn exp-remove" title="Remove this job"><i class="fa fa-times"></i></button>
                            </div>
                        @endforeach
                    </div>
                    <a href="javascript:;" id="exp-add" class="f-13"><i class="fa fa-plus mr-1"></i>Add a job</a>
                </div>

                {{-- 6. For HR's eyes --}}
                <div class="cand-section">
                    <div class="cand-section__head">
                        <span class="cand-section__no">6</span>
                        <div>
                            <h5 class="cand-section__title">Internal notes</h5>
                            <p class="cand-section__hint">Seen by the recruitment team only, never by the candidate.</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <textarea class="form-control f-14 pt-2" rows="3" name="notes" id="notes"
                            placeholder="Impressions from a call, salary flexibility, anything the panel should know">{{ $candidate->notes }}</textarea>
                    </div>
                </div>

                <x-form-actions>
                    <x-forms.button-primary id="save-candidate" class="mr-3" icon="check">@lang('app.save')</x-forms.button-primary>
                    <x-forms.button-secondary id="cancel-candidate">@lang('app.cancel')</x-forms.button-secondary>
                </x-form-actions>
            </div>
        </x-form>
    </div>
</div>

<style>
    /* Add / Edit Candidate in numbered sections */
    .cand-form .cand-section { padding: 22px 24px 8px; border-bottom: 1px solid var(--epic-line, #e8eef3); }
    .cand-form .cand-section--resume { background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 6%, #fff); padding-bottom: 18px; }
    .cand-form .cand-section__head { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 18px; }
    .cand-form .cand-section__no { flex: 0 0 26px; width: 26px; height: 26px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
        font-size: 13px; font-weight: 600; color: #fff; background: var(--epic-blue, #cb6b4f); margin-top: 1px; }
    .cand-form .cand-section__title { font-size: 15px; font-weight: 600; margin: 0; color: var(--epic-text, #28313c); line-height: 26px; }
    .cand-form .cand-section__hint { font-size: 12.5px; margin: 0; color: var(--epic-muted, #99a5b5); }

    /* Text, number, date, dropdown and box fields: the label always right on top
       of its field, the same gap below every field, the same height for every
       control - the dropdown component put its label outside its group with
       other margins, so it sat lower than the fields beside it. */
    .cand-form .cand-section .form-group { margin: 0 0 18px !important; }
    .cand-form .cand-section label { display: block; margin: 0 0 6px !important; font-size: 13.5px; line-height: 1.3; }
    .cand-form .cand-section .form-control:not(textarea),
    .cand-form .cand-section .bootstrap-select > .dropdown-toggle { height: 40px !important; min-height: 40px; }
    .cand-form .cand-section .bootstrap-select > .btn.dropdown-toggle { display: flex; align-items: center; height: 40px !important; min-height: 40px !important; box-sizing: border-box; }
    .cand-form .cand-section .bootstrap-select { width: 100% !important; }
    .cand-form .cand-section textarea.form-control { min-height: 84px; }
    .cand-form .cand-section .form-control-file { margin-top: 2px; }

    /* Work history: headings and inputs on the same columns */
    .cand-form .exp-grid { display: grid; grid-template-columns: 3fr 3fr 2fr 1.6fr 1.6fr 40px; gap: 10px; align-items: center; margin-bottom: 10px; }
    .cand-form .exp-grid--head { margin-bottom: 4px; font-size: 12px; color: var(--epic-muted, #99a5b5); }
    @media (min-width: 768px) { .cand-form .d-md-grid { display: grid !important; } }
    @media (max-width: 767.98px) { .cand-form .exp-grid { grid-template-columns: 1fr 1fr; } .cand-form .exp-grid .exp-remove { justify-self: start; } }
    .cand-form .exp-remove { width: 40px; height: 40px; padding: 0; color: #d64545; background: transparent; border: 1px solid var(--epic-line, #e8eef3); border-radius: 6px; }
    .cand-form .exp-remove:hover { background: #fdecea; }
    .cand-form #exp-add { display: inline-block; margin: 4px 0 16px; }
</style>

@include('recruitment.partials.submit-js')

<script>
    $(function () {
        datepicker('#date_of_birth', {position: 'bl', ...datepickerConfig});

        var rowTemplate = $('#exp-rows .exp-row').first().clone();
        rowTemplate.find('input').val('');

        $('#exp-add').on('click', function () { $('#exp-rows').append(rowTemplate.clone()); });
        $('#exp-rows').on('click', '.exp-remove', function () {
            if ($('#exp-rows .exp-row').length > 1) { $(this).closest('.exp-row').remove(); } else { $(this).closest('.exp-row').find('input').val(''); }
        });

        function escapeHtml(text) { return $('<div>').text(text == null ? '' : String(text)).html(); }

        // Fill only what is empty: anything typed stays.
        function fillFromResume(data, provider) {
            var filled = [];
            function put(id, value, label) {
                var el = $('#' + id);
                if (!el.length || value === null || value === undefined || value === '' || String(el.val() || '').trim() !== '') { return; }
                el.val(value).trigger('change');
                if (el.is('select') && $.fn.selectpicker) { el.selectpicker('refresh'); }
                filled.push(label);
            }

            put('name', [data.first_name, data.middle_name, data.last_name].filter(Boolean).join(' '), 'name');
            put('email', data.email, 'email');
            put('mobile', data.mobile, 'mobile');
            put('gender', data.gender, 'gender');
            put('date_of_birth', data.date_of_birth, 'date of birth');
            put('address', data.address, 'address');
            put('qualification', data.qualification, 'qualification');
            put('total_experience', data.total_experience_years, 'experience');
            put('skills', (data.skills || []).join(', '), 'skills');

            var jobs = data.work_experience || [];
            var empty = $('#exp-rows input[name="exp_company[]"]').filter(function () { return this.value; }).length === 0;

            if (jobs.length && empty) {
                $('#exp-rows').empty();
                jobs.forEach(function (job) {
                    var row = rowTemplate.clone();
                    row.find('[name="exp_company[]"]').val(job.company_name || '');
                    row.find('[name="exp_designation[]"]').val(job.designation || '');
                    row.find('[name="exp_location[]"]').val(job.location || '');
                    row.find('[name="exp_joining[]"]').val(job.joining_date || '');
                    row.find('[name="exp_leaving[]"]').val(job.leaving_date || '');
                    $('#exp-rows').append(row);
                });
                filled.push(jobs.length + ' job(s)');

                if (!jobs[0].leaving_date) {
                    put('current_company', jobs[0].company_name, 'current company');
                    put('current_designation', jobs[0].designation, 'current designation');
                }
            }

            $('#ai_filled').val('1');
            $('#resume-ai-result').html(filled.length
                ? '<span class="text-success"><i class="fa fa-check-circle mr-1"></i>Read with ' + escapeHtml(provider) + '. Filled: ' + escapeHtml(filled.join(', ')) + '. Please check them.</span>'
                : '<span class="text-dark-grey">Nothing new to fill from the resume.</span>');
        }

        $('#resume-ai').on('click', function () {
            var input = document.getElementById('resume_file');
            var button = $(this);

            if (!input.files.length) {
                $('#resume-ai-result').html('<span class="text-danger">Choose the resume PDF first.</span>');
                return;
            }

            var data = new FormData();
            data.append('resume_file', input.files[0]);
            data.append('_token', '{{ csrf_token() }}');
            button.prop('disabled', true).find('i').attr('class', 'fa fa-spinner fa-spin mr-1');
            $('#resume-ai-result').html('<span class="text-lightest">Reading the resume — this can take up to a minute…</span>');

            $.ajax({
                url: '{{ route('candidates.ai-preview') }}', type: 'POST', data: data, processData: false, contentType: false,
                success: function (response) {
                    if (response.status === 'success') { fillFromResume(response.data, response.provider); }
                    else { $('#resume-ai-result').html('<span class="text-danger">' + escapeHtml(response.message) + '</span>'); }
                },
                error: function (xhr) {
                    var body = xhr.responseJSON || {};
                    var message = body.message || 'The resume could not be read.';
                    if (body.errors) { message = [].concat(body.errors[Object.keys(body.errors)[0]])[0]; }
                    $('#resume-ai-result').html('<span class="text-danger">' + escapeHtml(message) + '</span>');
                },
                complete: function () { button.prop('disabled', false).find('i').attr('class', 'fa fa-magic mr-1'); }
            });
        });

        $('#save-candidate').on('click', function () {
            recruitSubmit('candidate-form', '{{ $editing ? route('candidates.update', $candidate->id) : route('candidates.store') }}', 'save-candidate');
        });

        $('#cancel-candidate').on('click', function () { recruitClose('{{ $editing ? route('candidates.show', $candidate->id) : route('candidates.index') }}'); });

        init(RIGHT_MODAL);
    });
</script>
