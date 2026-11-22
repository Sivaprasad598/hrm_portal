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

        // What is on file for them: a compact strip at the top of the form - the person and the
        // offer at a glance, every other detail one click away (all values set as text)
        var sum = prefill.summary || {};
        var initials = String(prefill.candidate_name || '').split(/\s+/).filter(Boolean).slice(0, 2).map(function (w) { return w.charAt(0).toUpperCase(); }).join('');
        var card = $('<div class="rc-reg"></div>');
        var top = $('<div class="rc-reg-top"></div>');
        top.append($('<span class="rc-reg-av"></span>').text(initials));
        var who = $('<div class="rc-reg-who"></div>');
        who.append($('<div class="rc-reg-title"></div>').append('Registering ', $('<strong></strong>').text(prefill.candidate_name), ' from Recruitment'));
        var chips = $('<div class="rc-reg-chips"></div>');
        [['fa-briefcase', sum['Designation'] || sum['Position']], ['fa-sitemap', sum['Division']], ['fa-calendar-check', sum['Joining date'] ? 'Joins ' + sum['Joining date'] : null],
            ['fa-rupee-sign', sum['Annual CTC'] ? sum['Annual CTC'] + ' / year' : null], ['fa-file-signature', sum['Offer']], ['fa-user-friends', sum['Referred by'] ? 'Referred by ' + sum['Referred by'] : null]]
            .forEach(function (c) { if (c[1]) { chips.append($('<span class="rc-reg-chip"></span>').append($('<i class="fa mr-1"></i>').addClass(c[0]), document.createTextNode(c[1]))); } });
        who.append(chips);
        top.append(who);
        var links = $('<div class="rc-reg-links"></div>');
        links.append($('<a href="javascript:;" class="rc-reg-more">All details <i class="fa fa-chevron-down ml-1"></i></a>'));
        links.append($('<a target="_blank" class="rc-reg-link">Candidate record <i class="fa fa-external-link-alt ml-1"></i></a>').attr('href', prefill.candidate_url));
        top.append(links);
        card.append(top);

        var grid = $('<dl class="rc-reg-grid" style="display:none"></dl>');
        $.each(sum, function (label, value) {
            grid.append($('<div></div>').append($('<dt></dt>').text(label), $('<dd></dd>').text(value)));
        });
        card.append(grid);
        card.append($('<p class="rc-reg-note"></p>').text('The highlighted fields are filled from the candidate and the accepted offer: check them, complete the rest and save.'
            + (prefill.resume ? ' The resume and the signed offer go to the employee’s documents when you save.' : '')));
        card.on('click', '.rc-reg-more', function () {
            var open = !grid.is(':visible');
            grid.slideToggle(150);
            $(this).html(open ? 'Fewer details <i class="fa fa-chevron-up ml-1"></i>' : 'All details <i class="fa fa-chevron-down ml-1"></i>');
        });

        $('<style>'
            + '.rc-reg{background:#fff;border:1px solid var(--epic-line,#eaecf0);border-left:4px solid var(--epic-blue,#cb6b4f);border-radius:10px;padding:14px 18px;margin:0 0 16px;box-shadow:0 1px 3px rgba(16,24,40,.05)}'
            + '.rc-reg-top{display:flex;align-items:center;gap:14px;flex-wrap:wrap}'
            + '.rc-reg-av{flex:0 0 42px;width:42px;height:42px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:600;font-size:15px;background:color-mix(in srgb,var(--epic-blue,#cb6b4f) 14%,#fff);color:var(--epic-blue,#cb6b4f)}'
            + '.rc-reg-who{flex:1;min-width:240px}'
            + '.rc-reg-title{font-size:15px;color:var(--epic-text,#1d2939)}'
            + '.rc-reg-chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}'
            + '.rc-reg-chip{display:inline-flex;align-items:center;font-size:12.5px;color:#475467;background:#f2f4f7;border-radius:999px;padding:3px 10px;white-space:nowrap}'
            + '.rc-reg-chip i{color:#98a2b3}'
            + '.rc-reg-links{display:flex;gap:16px;align-items:center;font-size:13px;margin-left:auto}'
            + '.rc-reg-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px 18px;margin:14px 0 0;padding-top:12px;border-top:1px dashed var(--epic-line,#eaecf0)}'
            + '.rc-reg-grid dt{font-size:12px;font-weight:500;color:#667085;margin:0}'
            + '.rc-reg-grid dd{font-size:13.5px;color:var(--epic-text,#1d2939);margin:0;word-break:break-word}'
            + '.rc-reg-note{font-size:12.5px;color:#667085;margin:10px 0 0}'
            + '@media (max-width:575.98px){.rc-reg-links{margin-left:0}}'
            + '</style>').appendTo(card);

        form.prepend(card);
    })(@json($recruitPrefill));
@endif
