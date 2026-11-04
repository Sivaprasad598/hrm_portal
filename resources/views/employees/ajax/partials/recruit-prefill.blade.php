{{-- Add Employee opened from Recruitment ("Convert to Employee"): fill the form from the
     candidate and the accepted offer (App\Services\Recruit\CandidateConversion::prefill).
     Included inside the page's $(document).ready(), after onboarding-script, so
     obFillFromResume() is there to fill the personal details, work history and skills. --}}
@if (!empty($recruitPrefill))
    (function (prefill) {
        var form = $(onboardingForm);
        form.append($('<input type="hidden" name="recruit_candidate_id">').val(prefill.candidate_id));

        // The resume checklist item needs a document on the form; the candidate's resume is
        // copied in after saving, so it is left as HR had it.
        var resumeTicked = document.getElementById('resume_copy') ? document.getElementById('resume_copy').checked : null;

        if (typeof obFillFromResume === 'function') {
            obFillFromResume(prefill.resume_data, 'the candidate record');
        }

        if (resumeTicked === false && document.getElementById('resume_copy').checked) {
            document.getElementById('resume_copy').checked = false;
            $('#resume_copy').trigger('change');
        }

        function pick(id, value) {
            var el = $('#' + id);
            if (!el.length || value === null || value === undefined || value === '') { return; }
            el.val(String(value));
            if ($.fn.selectpicker && el.hasClass('select-picker')) { el.selectpicker('refresh'); }
            el.addClass('ai-filled').trigger('change');
        }

        pick('employee_designation', prefill.job.designation);
        pick('employee_department', prefill.job.department);
        pick('employee_branch', prefill.job.branch);
        pick('joining_date', prefill.job.joining_date);
        pick('employee_ctc', prefill.job.employee_ctc);
        $('#employee_ctc').trigger('input');

        var banner = $('<div class="alert alert-success f-14 mx-3 mt-3 mb-0"></div>').html(
            '<i class="fa fa-user-check mr-1"></i>Adding <strong></strong> from Recruitment'
            + (prefill.position ? ' (<span class="rc-pos"></span>)' : '')
            + '. The details, job and CTC are filled from the candidate and the accepted offer — check the highlighted fields.'
            + (prefill.resume ? ' Their resume and the signed offer are copied to the employee\'s documents when you save.' : '')
        );
        banner.find('strong').text(prefill.candidate_name);
        banner.find('.rc-pos').text(prefill.position || '');
        form.prepend(banner);
    })(@json($recruitPrefill));
@endif
