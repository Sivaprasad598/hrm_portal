<div class="row">
    <div class="col-sm-12">
        <div class="add-client bg-white rounded">
            <h4 class="mb-0 p-20 f-21 font-weight-700 text-capitalize border-bottom-grey">
                Exit Interview — {{ $exit->employee->name }}
            </h4>

            <div class="p-20">
                <form id="interviewForm">
                    @csrf

                    <div class="row">
                        {{-- Interview Date --}}
                        <div class="col-lg-6 col-md-6">
                            <x-forms.datepicker fieldId="interview_date" :fieldLabel="'Interview Date'" fieldName="interview_date"
                                :fieldValue="$interview ? $interview->interview_date?->format(company()->date_format) : now()->format(company()->date_format)"
                                :fieldRequired="true" />
                        </div>

                        {{-- Rehire Eligibility --}}
                        <div class="col-lg-6 col-md-6">
                            <x-forms.select fieldId="rehire_eligibility" :fieldLabel="'Rehire Eligibility'" fieldName="rehire_eligibility">
                                <option value="">-- Select --</option>
                                <option value="eligible"         {{ ($interview && $interview->rehire_eligibility === 'eligible')         ? 'selected' : '' }}>Eligible</option>
                                <option value="not_eligible"     {{ ($interview && $interview->rehire_eligibility === 'not_eligible')     ? 'selected' : '' }}>Not Eligible</option>
                                <option value="review_required"  {{ ($interview && $interview->rehire_eligibility === 'review_required')  ? 'selected' : '' }}>Review Required</option>
                            </x-forms.select>
                        </div>

                        {{-- Reason for Leaving --}}
                        <div class="col-12">
                            <x-forms.textarea fieldId="reason_for_leaving" :fieldLabel="'Primary Reason for Leaving'" fieldName="reason_for_leaving"
                                :fieldValue="$interview?->reason_for_leaving"
                                fieldPlaceholder="Describe the primary reason..." />
                        </div>

                        {{-- Rating Section --}}
                        <div class="col-12 mt-2 mb-2">
                            <h6 class="f-14 font-weight-700 text-dark-grey border-bottom pb-2">Ratings (1 = Poor, 5 = Excellent)</h6>
                        </div>

                        <div class="col-lg-6 col-md-6">
                            <div class="form-group">
                                <label class="f-14 text-dark-grey mb-12">Overall Experience</label>
                                <div class="star-rating d-flex" id="overall_exp_stars" data-field="overall_experience" data-value="{{ $interview?->overall_experience ?? 0 }}">
                                    @for($i=1;$i<=5;$i++)
                                        <i class="fa fa-star mr-1 f-20 star-btn" data-val="{{ $i }}"
                                           style="cursor:pointer; color:{{ ($interview && $interview->overall_experience >= $i) ? '#ffc107' : '#dee2e6' }};"></i>
                                    @endfor
                                    <input type="hidden" name="overall_experience" id="overall_experience" value="{{ $interview?->overall_experience ?? '' }}">
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6 col-md-6">
                            <div class="form-group">
                                <label class="f-14 text-dark-grey mb-12">Job Satisfaction</label>
                                <div class="star-rating d-flex" id="job_sat_stars" data-field="job_satisfaction" data-value="{{ $interview?->job_satisfaction ?? 0 }}">
                                    @for($i=1;$i<=5;$i++)
                                        <i class="fa fa-star mr-1 f-20 star-btn" data-val="{{ $i }}"
                                           style="cursor:pointer; color:{{ ($interview && $interview->job_satisfaction >= $i) ? '#ffc107' : '#dee2e6' }};"></i>
                                    @endfor
                                    <input type="hidden" name="job_satisfaction" id="job_satisfaction" value="{{ $interview?->job_satisfaction ?? '' }}">
                                </div>
                            </div>
                        </div>

                        {{-- Feedback Fields --}}
                        <div class="col-lg-6 col-md-6">
                            <x-forms.textarea fieldId="management_feedback" :fieldLabel="'Management Feedback'" fieldName="management_feedback"
                                :fieldValue="$interview?->management_feedback" fieldPlaceholder="How was your experience with management?" />
                        </div>

                        <div class="col-lg-6 col-md-6">
                            <x-forms.textarea fieldId="compensation_feedback" :fieldLabel="'Compensation Feedback'" fieldName="compensation_feedback"
                                :fieldValue="$interview?->compensation_feedback" fieldPlaceholder="Thoughts on salary and benefits?" />
                        </div>

                        <div class="col-lg-6 col-md-6">
                            <x-forms.textarea fieldId="work_environment_feedback" :fieldLabel="'Work Environment'" fieldName="work_environment_feedback"
                                :fieldValue="$interview?->work_environment_feedback" fieldPlaceholder="How was the work environment?" />
                        </div>

                        <div class="col-lg-6 col-md-6">
                            <x-forms.textarea fieldId="career_growth_feedback" :fieldLabel="'Career Growth'" fieldName="career_growth_feedback"
                                :fieldValue="$interview?->career_growth_feedback" fieldPlaceholder="Were there enough growth opportunities?" />
                        </div>

                        <div class="col-12">
                            <x-forms.textarea fieldId="suggestions" :fieldLabel="'Suggestions for Improvement'" fieldName="suggestions"
                                :fieldValue="$interview?->suggestions" fieldPlaceholder="Any suggestions for the company?" />
                        </div>

                        <div class="col-12">
                            <x-forms.textarea fieldId="additional_comments" :fieldLabel="'Additional Comments'" fieldName="additional_comments"
                                :fieldValue="$interview?->additional_comments" fieldPlaceholder="Any other comments..." />
                        </div>
                    </div>

                    <div class="d-flex mt-3">
                        <button type="button" id="saveInterviewBtn" class="btn btn-primary mr-3">
                            <i class="fa fa-save mr-1"></i> Save Interview
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeExitPanel()">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // The side panel is closed with closeTaskDetail() (main.js), not as a
    // Bootstrap modal; opened as a page of its own, go back to the list.
    window.closeExitPanel = function () {
        if ($(RIGHT_MODAL).hasClass('in') && typeof closeTaskDetail === 'function') {
            closeTaskDetail();
        } else {
            window.location.href = '{{ route('employee-exit.index') }}';
        }
    };
    $(function () {
        // Star rating
        $('.star-rating').each(function () {
            var container = $(this);
            var input = container.find('input[type="hidden"]');

            // Font Awesome swaps each <i> for an <svg> after load, so handlers
            // bound to the stars themselves are lost: listen on the container.
            var paint = function (val) {
                container.find('.star-btn').each(function () {
                    $(this).css('color', parseInt($(this).attr('data-val')) <= val ? '#ffc107' : '#dee2e6');
                });
            };

            container.on('click', '.star-btn', function () {
                var val = parseInt($(this).attr('data-val'));
                input.val(val);
                paint(val);
            });

            container.on('mouseenter', '.star-btn', function () {
                paint(parseInt($(this).attr('data-val')));
            });

            container.on('mouseleave', function () {
                paint(parseInt(input.val()) || 0);
            });
        });

        $('#saveInterviewBtn').on('click', function () {
            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

            $.ajax({
                url: '{{ route('employee-exit.store-interview', $exit->id) }}',
                method: 'POST',
                data: $('#interviewForm').serialize(),
                success: function (res) {
                    if (res.status === 'success') {
                        toastr.success(res.message);
                        if (window.LaravelDataTables && window.LaravelDataTables['employee-exit-table']) {
                            window.LaravelDataTables['employee-exit-table'].draw(false);
                        }
                        closeExitPanel();
                    } else {
                        toastr.error(res.message);
                    }
                },
                error: function (xhr) {
                    var errors = xhr.responseJSON && xhr.responseJSON.errors;
                    if (errors) {
                        Object.values(errors).forEach(function(msgs) { toastr.error(msgs[0]); });
                    } else {
                        toastr.error('Error saving interview.');
                    }
                },
                complete: function () {
                    btn.prop('disabled', false).html('<i class="fa fa-save mr-1"></i> Save Interview');
                }
            });
        });
    });
</script>
