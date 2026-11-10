@php
    $editing = $position->exists;
    $fmt = company()->date_format;
@endphp

<div class="row">
    <div class="col-sm-12">
        <x-form id="position-form" method="POST">
            @if ($editing)
                <input type="hidden" name="_method" value="PUT">
            @endif

            <div class="add-client bg-white rounded">
                <h4 class="mb-0 p-20 f-21 font-weight-normal border-bottom-grey">{{ $editing ? 'Edit Position' : 'Add Position' }}</h4>

                <div class="row p-20">
                    <div class="col-md-8">
                        <x-forms.text fieldId="title" fieldLabel="Job Title" fieldName="title" :fieldRequired="true"
                            fieldPlaceholder="e.g. Site Engineer (Civil)" :fieldValue="$position->title"
                            fieldHelp="Candidates who write this title in their email subject are matched to this position." />
                    </div>
                    <div class="col-md-4">
                        <x-forms.text fieldId="code" fieldLabel="Code" fieldName="code" :fieldRequired="true" :fieldValue="$position->code"
                            fieldHelp="The website can send this code." />
                    </div>

                    <div class="col-md-4">
                        <x-forms.select fieldId="department_id" fieldLabel="Division" fieldName="department_id" search="true">
                            <option value="">--</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected($position->department_id == $department->id)>{{ $department->team_name }}</option>
                            @endforeach
                        </x-forms.select>
                    </div>
                    <div class="col-md-4">
                        <x-forms.select fieldId="designation_id" fieldLabel="Designation" fieldName="designation_id" search="true">
                            <option value="">--</option>
                            @foreach ($designations as $designation)
                                <option value="{{ $designation->id }}" @selected($position->designation_id == $designation->id)>{{ $designation->name }}</option>
                            @endforeach
                        </x-forms.select>
                    </div>
                    <div class="col-md-4">
                        <x-forms.select fieldId="branch_id" fieldLabel="Branch" fieldName="branch_id">
                            <option value="">--</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected($position->branch_id == $branch->id)>{{ $branch->branch_name }}</option>
                            @endforeach
                        </x-forms.select>
                    </div>

                    <div class="col-md-3 col-6">
                        <x-forms.number fieldId="openings" fieldLabel="Openings" fieldName="openings" :fieldRequired="true" :fieldValue="$position->openings" />
                    </div>
                    <div class="col-md-3 col-6">
                        <x-forms.select fieldId="employment_type" fieldLabel="Type" fieldName="employment_type" :fieldRequired="true">
                            @foreach (\App\Models\JobPosition::TYPES as $key => $label)
                                <option value="{{ $key }}" @selected($position->employment_type === $key)>{{ $label }}</option>
                            @endforeach
                        </x-forms.select>
                    </div>
                    <div class="col-md-3 col-6">
                        <x-forms.select fieldId="status" fieldLabel="Status" fieldName="status" :fieldRequired="true">
                            @foreach (\App\Models\JobPosition::STATUSES as $key => [$label])
                                <option value="{{ $key }}" @selected($position->status === $key)>{{ $label }}</option>
                            @endforeach
                        </x-forms.select>
                    </div>
                    <div class="col-md-3 col-6">
                        <x-forms.datepicker fieldId="closing_date" fieldLabel="Closing Date" fieldName="closing_date"
                            :fieldValue="$position->closing_date?->format($fmt)" :fieldPlaceholder="__('placeholders.date')" />
                    </div>

                    <div class="col-md-3 col-6">
                        <x-forms.number fieldId="experience_min" fieldLabel="Experience from (yrs)" fieldName="experience_min" :fieldValue="$position->experience_min" />
                    </div>
                    <div class="col-md-3 col-6">
                        <x-forms.number fieldId="experience_max" fieldLabel="Experience to (yrs)" fieldName="experience_max" :fieldValue="$position->experience_max" />
                    </div>
                    <div class="col-md-3 col-6">
                        <x-forms.number fieldId="salary_min" fieldLabel="Salary from (CTC / year)" fieldName="salary_min" :fieldValue="$position->salary_min" />
                    </div>
                    <div class="col-md-3 col-6">
                        <x-forms.number fieldId="salary_max" fieldLabel="Salary to (CTC / year)" fieldName="salary_max" :fieldValue="$position->salary_max" />
                    </div>

                    <div class="col-md-6">
                        <x-forms.text fieldId="location" fieldLabel="Location" fieldName="location" :fieldValue="$position->location"
                            fieldPlaceholder="e.g. Hyderabad, site-based" />
                    </div>
                    <div class="col-md-6">
                        <x-forms.text fieldId="skills" fieldLabel="Key Skills" fieldName="skills" :fieldValue="$position->skills"
                            fieldPlaceholder="AutoCAD, site supervision, BOQ" />
                    </div>
                    <div class="col-12">
                        <x-forms.textarea fieldId="description" fieldLabel="Job Description" fieldName="description" :fieldValue="$position->description"
                            fieldPlaceholder="Responsibilities, requirements, what the role offers" />
                    </div>
                </div>

                {{-- Hiring rounds: the default plan, or this position's own --}}
                <div class="px-20 pb-20">
                    <h5 class="f-16 font-weight-600 mb-1 border-top-grey pt-3"><i class="fa fa-route mr-1"></i>Hiring Rounds</h5>
                    <p class="f-13 text-dark-grey mb-2">The rounds a shortlisted candidate goes through, in order, and who takes each one.</p>
                    <x-forms.checkbox fieldId="use_default_plan" fieldName="use_default_plan" fieldValue="1" :checked="!$ownPlan"
                        fieldLabel="Use the default plan (Recruitment Settings → Hiring Rounds)" />
                    <div id="own-plan" class="mt-2 {{ $ownPlan ? '' : 'd-none' }}">
                        @include('recruitment.partials.round-plan', ['plan' => $plan, 'employees' => $employees, 'planId' => 'position-plan'])
                    </div>
                    <div id="default-plan-view" class="mt-2 f-13 {{ $ownPlan ? 'd-none' : '' }}">
                        @foreach ($plan as $i => $round)
                            <span class="rc-chip">{{ $i + 1 }}. {{ $round->name }} · {{ $round->isTest() ? 'Test' : 'Interview' }} · {{ $round->passText() }}</span>
                        @endforeach
                    </div>
                </div>

                <x-form-actions>
                    <x-forms.button-primary id="save-position" class="mr-3" icon="check">@lang('app.save')</x-forms.button-primary>
                    <x-forms.button-secondary id="cancel-position">@lang('app.cancel')</x-forms.button-secondary>
                    @if ($editing)
                        <a href="javascript:;" id="delete-position" class="btn btn-link text-danger ml-auto f-14"><i class="fa fa-trash mr-1"></i>Delete</a>
                    @endif
                </x-form-actions>
            </div>
        </x-form>
    </div>
</div>

@include('recruitment.partials.submit-js')

<script>
    $(function () {
        datepicker('#closing_date', {position: 'bl', ...datepickerConfig});
        $('#description').attr('rows', 6);

        // Own plan: the editor starts from the default's rounds, to change
        $('#use_default_plan').on('change', function () {
            $('#own-plan').toggleClass('d-none', this.checked);
            $('#default-plan-view').toggleClass('d-none', !this.checked);
        });

        $('#save-position').on('click', function () {
            recruitSubmit('position-form', '{{ $editing ? route('job-positions.update', $position->id) : route('job-positions.store') }}', 'save-position');
        });

        $('#cancel-position').on('click', function () { recruitClose('{{ route('job-positions.index') }}'); });

        @if ($editing)
        $('#delete-position').on('click', function () {
            Swal.fire({
                title: "@lang('messages.sweetAlertTitle')",
                text: 'The position is deleted. Only a position with no candidates can be deleted; otherwise close it.',
                icon: 'warning', showCancelButton: true, confirmButtonText: "@lang('messages.confirmDelete')", cancelButtonText: "@lang('app.cancel')",
                customClass: {confirmButton: 'btn btn-primary mr-3', cancelButton: 'btn btn-secondary'}, buttonsStyling: false
            }).then(function (result) {
                if (result.isConfirmed) {
                    $.easyAjax({
                        url: '{{ route('job-positions.destroy', $position->id) }}', type: 'POST', blockUI: true,
                        data: {_token: '{{ csrf_token() }}', _method: 'DELETE'},
                        success: function (response) { if (response.status === 'success') { window.location.href = response.redirectUrl; } }
                    });
                }
            });
        });
        @endif

        init(RIGHT_MODAL);
    });
</script>
