{{-- Shared behaviour for the Add / Edit Employee wizard.

     Included inside the page's $(document).ready(), after its own validate*
     functions, wizardGoTo() and buildWizardReview(), so it can use them. The
     page defines before including this:
       onboardingStepChecks  {step number: function () -> bool} - its own checks per step
       onboardingForm        the form selector
     and passes $onboardingMode ('create' | 'edit') and $originalAccount (Edit).

     What it adds:
       - every step can be opened at any time; leaving a step marks it red when
         something there still needs doing
       - Save checks every step, opens the first one with a problem and scrolls
         to the field; errors from the server are taken back to their step too
       - resume upload and "Extract with AI"
       - CTC -> monthly salary and its breakdown
       - IFSC lookup and the re-entered account number
       - a document behind every ticked checklist item, both declaration copies
       - the documents in the Review step --}}

    var onboardingMode = @json($onboardingMode);
    var onboardingOriginalAccount = @json($originalAccount ?? '');
    var onboardingStepNames = ['', 'Personal Info', 'Bank Details', 'Employment', 'Passport', 'Checklist', 'Review', 'Declaration'];
    var onboardingMaxBytes = 5 * 1024 * 1024;
    var ifscState = { code: null, status: null, message: '' };

    function obEl(id) { return document.getElementById(id); }

    function obEscape(text) {
        return $('<div>').text(text == null ? '' : String(text)).html();
    }

    function obMoney(value) {
        return Number(value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function obToast(icon, text) {
        Swal.fire({
            icon: icon, text: text, toast: true, position: 'top-end', timer: 6000, timerProgressBar: true,
            showConfirmButton: false, showClass: { popup: 'swal2-noanimation', backdrop: 'swal2-noanimation' }
        });
    }

    // ══════════════════════════════════════════════════════════
    //  DOCUMENT LINKS: PDFs and images open in a new tab; anything
    //  else opens a small window on this page with a download link.
    //  A file chosen but not yet saved opens from the browser's copy.
    // ══════════════════════════════════════════════════════════
    var obBlobUrls = new Map();

    function obBlobUrl(file) {
        if (!obBlobUrls.has(file)) { obBlobUrls.set(file, URL.createObjectURL(file)); }
        return obBlobUrls.get(file);
    }

    function obViewable(mime, name) {
        return /^(application\/pdf|image\/)/i.test(mime || '') || /\.(pdf|png|jpe?g|gif|webp)$/i.test(name || '');
    }

    function obChip(name, url, mime, note, download) {
        return '<a href="' + obEscape(url) + '" target="_blank" rel="noopener" class="doc-chip ob-doc-link"'
            + ' data-mime="' + obEscape(mime || '') + '" data-name="' + obEscape(name) + '"'
            + (download ? ' data-download="' + obEscape(download) + '"' : '') + '>'
            + '<i class="fa fa-' + (obViewable(mime, name) ? (/pdf/i.test(mime || name) ? 'file-pdf' : 'file-image') : 'file') + '"></i> '
            + obEscape(name) + (note ? ' <span class="f-11 text-lightest">' + obEscape(note) + '</span>' : '') + '</a>';
    }

    function obChipForFile(file) {
        return obChip(file.name, obBlobUrl(file), file.type, obFileSize(file.size) + ' · not saved yet');
    }

    function obChipForLink(link) {
        var a = $(link);
        return obChip(a.data('name') || a.text().trim(), a.attr('href'), a.data('mime'), '', a.data('download'));
    }

    $(document).on('click', '.ob-doc-link', function (e) {
        var link = $(this);

        if (obViewable(link.data('mime'), link.data('name'))) { return; }   // the browser shows it in a new tab

        e.preventDefault();
        var modal = $('#ob-doc-modal');

        if (!modal.length) {
            modal = $('<div class="modal fade" id="ob-doc-modal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">'
                + '<div class="modal-header"><h5 class="modal-title f-16"></h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>'
                + '<div class="modal-body f-14"></div></div></div></div>').appendTo('body');
        }

        var download = link.data('download') || link.attr('href');
        modal.find('.modal-title').text(link.data('name'));
        modal.find('.modal-body').html('<p class="mb-3">This type of file cannot be shown in the browser.</p>'
            + '<a class="btn btn-primary" href="' + obEscape(download) + '" download="' + obEscape(link.data('name')) + '">'
            + '<i class="fa fa-download mr-1"></i> Download</a>');
        modal.modal('show');
    });

    // ══════════════════════════════════════════════════════════
    //  NAVIGATION: every step open at any time
    // ══════════════════════════════════════════════════════════
    function obRunStepCheck(step) {
        var ok = true;

        if (typeof onboardingStepChecks[step] === 'function') {
            ok = onboardingStepChecks[step]() !== false;
        }

        if (step === 2) { ok = obCheckBank() && ok; }
        if (step === 5) { ok = obCheckChecklist() && ok; }
        if (step === 7) { ok = obCheckDeclarationFiles() && ok; }

        return ok;
    }

    function obMarkStep(step, ok) {
        $('.wizard-step-item[data-step="' + step + '"]').toggleClass('has-error', !ok);
    }

    // The page's own handlers only let a valid step move on; these replace them.
    $(document).off('click', '.wizardNext').off('click', '.wizard-step-item.completed');

    $(document).on('click', '.wizardNext', function () {
        obMarkStep(wizardStep, obRunStepCheck(wizardStep));
        wizardGoTo(parseInt($(this).data('to')));
    });

    $(document).on('click', '.wizard-step-item', function () {
        var to = parseInt($(this).data('step'));

        if (to !== wizardStep) {
            obMarkStep(wizardStep, obRunStepCheck(wizardStep));
            wizardGoTo(to);
        }
    });

    function obFocusFirstInvalid(step) {
        var target = $('#wizardStep' + step).find('.is-invalid').first();

        if (!target.length) { return; }

        setTimeout(function () {
            $('html, body').animate({ scrollTop: target.offset().top - 140 }, 200);
            var input = target.is('input, select, textarea') ? target : target.find('input, select, textarea, button').first();
            input.trigger('focus');
        }, 260);
    }

    // ══════════════════════════════════════════════════════════
    //  SAVE: check everything, then send, then map server errors back
    // ══════════════════════════════════════════════════════════
    window.onboardingSubmit = function (url, buttonSelector, onSuccess) {
        var failing = [];

        for (var step = 1; step <= 7; step++) {
            var ok = obRunStepCheck(step);
            obMarkStep(step, ok);
            if (!ok) { failing.push(step); }
        }

        if (failing.length) {
            wizardGoTo(failing[0]);
            obFocusFirstInvalid(failing[0]);
            obToast('warning', 'Still needed in: ' + failing.map(function (s) {
                return onboardingStepNames[s] + ' (' + Math.max(1, $('#wizardStep' + s).find('.is-invalid').length) + ')';
            }).join(', '));
            return;
        }

        $.easyAjax({
            url: url,
            container: onboardingForm,
            type: 'POST',
            disableButton: true,
            blockUI: true,
            buttonSelector: buttonSelector,
            file: true,
            data: $(onboardingForm).serialize(),
            success: function (response) {
                if (response.status === 'success') {
                    onSuccess(response);
                } else if (response.errors) {
                    obShowServerErrors(response.errors);
                }
            },
            error: function (xhr) {
                var body = xhr.responseJSON || {};

                if (body.errors) {
                    obShowServerErrors(body.errors);
                } else {
                    obToast('error', body.message || 'The employee could not be saved. Please try again.');
                }
            }
        });
    };

    /** A server error key ("checklist_files.nda", "exp_company.2") -> the field it belongs to. */
    function obFieldForError(key) {
        var parts = key.split('.');

        if (parts[0] === 'checklist_files' && parts[1]) { return obEl('checklist_file_' + parts[1]); }

        var byId = obEl(key) || obEl(parts[0]);
        if (byId) { return byId; }

        var named = $(onboardingForm).find('[name="' + key + '"]');
        if (named.length) { return named[0]; }

        if (parts.length > 1 && /^\d+$/.test(parts[1])) {
            var list = $(onboardingForm).find('[name="' + parts[0] + '[]"]');
            if (list.length > parseInt(parts[1])) { return list[parseInt(parts[1])]; }
        }

        return null;
    }

    function obShowServerErrors(errors) {
        var firstStep = null;
        var unplaced = [];

        Object.keys(errors).forEach(function (key) {
            var message = [].concat(errors[key])[0];
            var field = obFieldForError(key);

            if (!field) { unplaced.push(message); return; }

            setFieldState(field, false, message);

            var pane = $(field).closest('.wizard-pane');
            var step = pane.length ? parseInt(pane.attr('id').replace('wizardStep', '')) : null;

            if (step) {
                obMarkStep(step, false);
                if (firstStep === null || step < firstStep) { firstStep = step; }
            }
        });

        if (firstStep !== null) {
            wizardGoTo(firstStep);
            obFocusFirstInvalid(firstStep);
        }

        obToast('error', unplaced.length ? unplaced.join(' ') : 'Some details need correcting — the step is opened for you.');
    }

    // ══════════════════════════════════════════════════════════
    //  RESUME + AI
    // ══════════════════════════════════════════════════════════
    $('#resume_file').on('change', function () {
        var file = this.files[0];
        $('#resume-extract-result').html('');

        if (!file) { return; }

        if (!/\.pdf$/i.test(file.name)) {
            setFieldState(this, false, 'The resume must be a PDF.');
            this.value = '';
        } else if (file.size > onboardingMaxBytes) {
            setFieldState(this, false, 'The resume can be at most 5 MB.');
            this.value = '';
        } else {
            clearFieldState(this);
        }
    });

    $('#resume-extract').on('click', function () {
        var input = obEl('resume_file');
        var button = $(this);

        if (!input.files.length) {
            $('#resume-extract-result').html('<span class="text-red">Choose the resume PDF first.</span>');
            return;
        }

        var data = new FormData();
        data.append('resume_file', input.files[0]);
        data.append('_token', '{{ csrf_token() }}');

        button.prop('disabled', true).find('i').attr('class', 'fa fa-spinner fa-spin mr-1');
        $('#resume-extract-result').html('<span class="text-lightest">Reading the resume — this can take up to a minute…</span>');

        $.ajax({
            url: "{{ route('employees.onboarding.resume') }}",
            type: 'POST',
            data: data,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.status === 'success') {
                    obFillFromResume(response.data, response.provider);
                } else {
                    $('#resume-extract-result').html('<span class="text-red">' + obEscape(response.message) + '</span>');
                }
            },
            error: function (xhr) {
                var body = xhr.responseJSON || {};
                var message = body.message || 'The resume could not be read.';
                if (body.errors) { message = [].concat(body.errors[Object.keys(body.errors)[0]])[0]; }
                $('#resume-extract-result').html('<span class="text-red">' + obEscape(message) + '</span>');
            },
            complete: function () {
                button.prop('disabled', false).find('i').attr('class', 'fa fa-magic mr-1');
            }
        });
    });

    function obFillFromResume(data, provider) {
        var filled = [];
        var kept = [];

        // Only empty fields are filled: anything HR has typed stays as it is.
        function put(id, value, label) {
            var el = obEl(id);
            if (!el || value === null || value === undefined || value === '') { return; }

            if (String(el.value || '').trim() !== '') { kept.push(label); return; }

            if (el.tagName === 'SELECT') {
                $(el).val(value);
                if ($(el).val() !== value) { return; }
                if ($.fn.selectpicker) { $(el).selectpicker('refresh'); }
                $(el).closest('.bootstrap-select').find('.btn').addClass('ai-filled');
            } else {
                el.value = value;
            }

            $(el).addClass('ai-filled').trigger('input').trigger('change');
            filled.push(label);
        }

        if (obEl('first_name')) {
            put('first_name', data.first_name, 'First name');
            put('middle_name', data.middle_name, 'Middle name');
            put('last_name', data.last_name, 'Last name');
        } else {
            put('name', [data.first_name, data.middle_name, data.last_name].filter(Boolean).join(' '), 'Name');
        }

        put('email', data.email, 'Email');
        put('personal_email', data.email, 'Personal email');
        put('mobile', data.mobile, 'Mobile');
        put('date_of_birth', data.date_of_birth, 'Date of birth');
        put('gender', data.gender, 'Gender');
        put('address', data.address, 'Address');
        put('qualification', data.qualification, 'Qualification');

        if (typeof generateAutoPassword === 'function') { generateAutoPassword(); }

        // Work experience: added as cards when none have been entered yet
        var jobs = data.work_experience || [];
        var hasCards = $('#experienceCards .experience-card').filter(function () {
            return $(this).find('.exp-company').val();
        }).length > 0;

        if (jobs.length && !hasCards && typeof addExpCard === 'function') {
            $('#experienceCards .experience-card').filter(function () { return !$(this).find('.exp-company').val(); }).remove();

            jobs.forEach(function (job) {
                addExpCard();
                var card = $('#experienceCards .experience-card').last();
                card.find('.exp-company').val(job.company_name || '').addClass('ai-filled');
                card.find('.exp-location').val(job.location || '').addClass('ai-filled');
                card.find('.exp-designation').val(job.designation || '').addClass('ai-filled');
                card.find('.exp-joining-date').val(job.joining_date || '').addClass('ai-filled');
                card.find('.exp-leaving-date').val(job.leaving_date || '').addClass('ai-filled');
                if (typeof updateExpCardDuration === 'function') { updateExpCardDuration(card); }
            });

            if (typeof renumberExpCards === 'function') { renumberExpCards(); }
            if (typeof calcTotalExperience === 'function') { calcTotalExperience(); }
            filled.push(jobs.length + ' work experience entr' + (jobs.length === 1 ? 'y' : 'ies'));
        } else if (jobs.length) {
            kept.push('work experience');
        }

        // Skills, where the page has the skills tag box
        var tags = document.querySelector('input[name=tags]');
        if (tags && tags.__tagify && (data.skills || []).length && !tags.__tagify.value.length) {
            tags.__tagify.addTags(data.skills);
            filled.push('skills');
        }

        // The resume is in hand, so the checklist's resume item is too
        if (obEl('resume_copy') && !obEl('resume_copy').checked) {
            obEl('resume_copy').checked = true;
            $(obEl('resume_copy')).trigger('change');
        }

        var html = filled.length
            ? '<span class="text-success"><i class="fa fa-check-circle mr-1"></i>Read with ' + obEscape(provider) + '. Filled: '
                + obEscape(filled.join(', ')) + '. Check the highlighted fields.</span>'
            : '<span class="text-dark-grey">Nothing new to fill from the resume.</span>';

        if (kept.length) {
            html += '<br><span class="text-lightest">Kept what was already entered for: ' + obEscape(kept.join(', ')) + '.</span>';
        }

        $('#resume-extract-result').html(html);
    }

    $(document).on('input change', '.ai-filled', function (e) {
        if (e.originalEvent) { $(this).removeClass('ai-filled'); }   // HR has touched it
    });

    // ══════════════════════════════════════════════════════════
    //  CTC -> MONTHLY SALARY AND BREAKDOWN
    // ══════════════════════════════════════════════════════════
    var salaryTimer = null;
    var breakdownOpen = false;

    function obRefreshSalary() {
        var ctc = parseFloat($('#employee_ctc').val());

        if (!(ctc > 0)) {
            $('#monthly_salary').val('');
            $('#salary-breakdown-toggle').addClass('d-none');
            $('#salary-breakdown').addClass('d-none').html('');
            return;
        }

        $('#monthly_salary').val((ctc / 12).toFixed(2));
        $('#salary-breakdown-toggle').removeClass('d-none');

        if (breakdownOpen) {
            clearTimeout(salaryTimer);
            salaryTimer = setTimeout(obLoadBreakdown, 350);
        }
    }

    function obLoadBreakdown() {
        var ctc = parseFloat($('#employee_ctc').val());
        if (!(ctc > 0)) { return; }

        $.get("{{ route('employees.onboarding.salary') }}", { ctc: ctc, basic: $('#basic_for_payroll').val() })
            .done(function (r) {
                function rows(list) {
                    return list.map(function (row) {
                        return '<tr><td>' + obEscape(row[0]) + '</td><td class="text-right">' + obMoney(row[1]) + '</td></tr>';
                    }).join('');
                }

                // The calculation sheet's layout: earnings to Gross, deductions
                // (PF and ESI both shares) to Net, and the per-day rate, which
                // depends on how long the pay cycle is.
                $('#salary-breakdown').removeClass('d-none').html(
                    '<div class="border rounded p-2 mb-3 bg-white">'
                    + '<p class="f-12 text-lightest mb-1">One month, fully worked — Basic ' + obEscape(r.basic_pct) + '% of gross, by the payroll settings (Payroll → Payslips → Payslip Calculation).</p>'
                    + '<div class="row f-13">'
                    + '<div class="col-md-4"><table class="w-100"><tr><td colspan="2" class="f-w-500">Earnings</td></tr>' + rows(r.earnings)
                    + '<tr class="f-w-500 border-top"><td>Gross</td><td class="text-right">' + obMoney(r.monthly) + '</td></tr></table></div>'
                    + '<div class="col-md-4"><table class="w-100"><tr><td colspan="2" class="f-w-500">Deductions</td></tr>' + rows(r.deductions)
                    + '<tr class="border-top"><td>Total deductions</td><td class="text-right">' + obMoney(r.total_deductions) + '</td></tr>'
                    + '<tr class="f-w-500 text-success"><td>Net Salary</td><td class="text-right">' + obMoney(r.net) + '</td></tr></table></div>'
                    + '<div class="col-md-4"><table class="w-100"><tr><td colspan="2" class="f-w-500">Per day <span class="text-lightest f-11">(Net ÷ days in the pay cycle)</span></td></tr>' + rows(r.per_day)
                    + '<tr><td colspan="2" class="f-11 text-lightest pt-1">The pay cycle runs 21st to 20th, so a month has 30 or 31 days; unpaid days are taken off at that month\'s rate.</td></tr></table></div>'
                    + '</div></div>'
                );
            });
    }

    $('#salary-breakdown-toggle').on('click', function () {
        breakdownOpen = !breakdownOpen;
        $(this).find('span').text(breakdownOpen ? 'Hide monthly breakdown' : 'Show monthly breakdown');

        if (breakdownOpen) { obLoadBreakdown(); } else { $('#salary-breakdown').addClass('d-none'); }
    });

    $('#employee_ctc, #basic_for_payroll').on('input change', obRefreshSalary);
    obRefreshSalary();

    // ══════════════════════════════════════════════════════════
    //  BANK: IFSC lookup and the re-entered account number
    // ══════════════════════════════════════════════════════════
    var ifscTimer = null;

    function obAccountProblem(value) {
        if (!/^[0-9]{9,18}$/.test(value)) { return 'Bank account number must be 9 to 18 digits.'; }
        if (/^(\d)\1+$/.test(value)) { return 'Bank account number cannot be one digit repeated.'; }
        if ('0123456789012345678'.indexOf(value) !== -1 || '9876543210987654321'.indexOf(value) !== -1) {
            return 'Bank account number looks like a placeholder (a run of digits).';
        }
        return null;
    }

    function obLookupIfsc() {
        var input = obEl('ifsc_code');
        if (!input) { return; }

        var code = input.value.trim().toUpperCase();
        var result = $('#ifsc-result');

        if (!/^[A-Z]{4}0[A-Z0-9]{6}$/.test(code)) {
            ifscState = { code: null, status: null, message: '' };
            result.removeClass('ok bad').html('<span class="text-lightest">Enter the 11-character IFSC to see the bank and branch.</span>');
            return;
        }

        if (ifscState.code === code && ifscState.status && ifscState.status !== 'checking') { return; }

        ifscState = { code: code, status: 'checking', message: '' };
        result.removeClass('ok bad').html('<span class="text-lightest"><i class="fa fa-spinner fa-spin mr-1"></i>Checking ' + code + '…</span>');

        $.get("{{ route('employees.onboarding.ifsc', ':code') }}".replace(':code', code))
            .done(function (r) {
                if (obEl('ifsc_code').value.trim().toUpperCase() !== code) { return; }   // typed on since

                ifscState = { code: code, status: r.status, message: r.message };

                if (r.status === 'valid') {
                    result.addClass('ok').removeClass('bad').html('<i class="fa fa-check-circle mr-1"></i>' + obEscape(r.message));
                    setFieldState(input, true);
                } else if (r.status === 'invalid') {
                    result.addClass('bad').removeClass('ok').html('<i class="fa fa-times-circle mr-1"></i>' + obEscape(r.message));
                    setFieldState(input, false, r.message);
                } else {
                    result.removeClass('ok bad').html('<span class="text-warning"><i class="fa fa-exclamation-circle mr-1"></i>' + obEscape(r.message) + '</span>');
                }
            })
            .fail(function () {
                ifscState = { code: code, status: 'unavailable', message: '' };
                result.removeClass('ok bad').html('<span class="text-warning">The IFSC could not be checked just now; the format is correct.</span>');
            });
    }

    $('#ifsc_code').on('input', function () {
        clearTimeout(ifscTimer);
        ifscTimer = setTimeout(obLookupIfsc, 350);
    }).on('blur', obLookupIfsc);

    if (obEl('ifsc_code') && obEl('ifsc_code').value && !$('#ifsc-result').hasClass('ok')) { obLookupIfsc(); }
    if (obEl('ifsc_code') && $('#ifsc-result').hasClass('ok')) {
        ifscState = { code: obEl('ifsc_code').value.trim().toUpperCase(), status: 'valid', message: $('#ifsc-result').text().trim() };
    }

    function obConfirmNeeded() {
        var account = obEl('personal_account_number');
        return account && account.value !== '' && (onboardingMode === 'create' || account.value !== onboardingOriginalAccount);
    }

    function obCheckBank() {
        var account = obEl('personal_account_number');
        var confirm = obEl('personal_account_number_confirmation');
        var ifsc = obEl('ifsc_code');
        var ok = true;

        if (account && account.value) {
            var problem = obAccountProblem(account.value);
            if (problem) { setFieldState(account, false, problem); ok = false; }
        }

        if (confirm) {
            if (obConfirmNeeded() && confirm.value !== account.value) {
                setFieldState(confirm, false, confirm.value ? 'Does not match the account number.' : 'Re-enter the account number to confirm it.');
                ok = false;
            } else if (obConfirmNeeded()) {
                setFieldState(confirm, true);
            } else {
                clearFieldState(confirm);
            }
        }

        if (ifsc && ifsc.value && ifscState.code === ifsc.value.trim().toUpperCase() && ifscState.status === 'invalid') {
            setFieldState(ifsc, false, ifscState.message);
            ok = false;
        }

        return ok;
    }

    $('#personal_account_number_confirmation, #personal_account_number').on('input blur', function () {
        var confirm = obEl('personal_account_number_confirmation');
        if (confirm && (confirm.value || $(confirm).hasClass('is-invalid'))) { obCheckBank(); }
    });

    // ══════════════════════════════════════════════════════════
    //  CHECKLIST DOCUMENTS AND DECLARATION COPIES
    // ══════════════════════════════════════════════════════════
    function obFileProblem(file) {
        if (!/\.(pdf|jpe?g|png)$/i.test(file.name)) { return file.name + ' is not a PDF, JPG or PNG.'; }
        if (file.size > onboardingMaxBytes) { return file.name + ' is over 5 MB.'; }
        return null;
    }

    function obFileSize(bytes) {
        return bytes > 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
    }

    $(document).on('change', '.checklist-file', function () {
        var item = $(this).closest('.checklist-item');
        var problems = [];
        var chips = '';

        Array.prototype.forEach.call(this.files, function (file) {
            var problem = obFileProblem(file);
            if (problem) { problems.push(problem); }
            chips += obChipForFile(file);
        });

        item.find('.checklist-file-list').html(chips);

        if (problems.length) {
            setFieldState(this, false, problems.join(' '));
            return;
        }

        clearFieldState(this);

        if (this.files.length) {
            var toggle = obEl(item.data('key'));
            toggle.checked = true;
            item.addClass('is-ticked');
        }
    });

    $(document).on('change', '.checklist-toggle', function () {
        var item = $(this).closest('.checklist-item');
        item.toggleClass('is-ticked', this.checked);
        if (!this.checked) { clearFieldState(obEl('checklist_file_' + item.data('key'))); }
    });

    // Removing a document already on file (Edit): marked here, deleted on save
    $(document).on('click', '.remove-existing-doc', function () {
        var chip = $(this).closest('.existing-doc');
        var id = chip.data('doc-id');
        var removing = !chip.hasClass('is-removed');

        chip.toggleClass('is-removed', removing);
        $(this).html(removing ? '<i class="fa fa-undo"></i>' : '&times;').attr('title', removing ? 'Keep it' : 'Remove');

        $(onboardingForm).find('input[name="remove_document_ids[]"][value="' + id + '"]').remove();
        if (removing) {
            $(onboardingForm).append('<input type="hidden" name="remove_document_ids[]" value="' + id + '">');
        }
    });

    function obCheckChecklist() {
        var ok = true;

        $('.checklist-item').each(function () {
            var key = $(this).data('key');
            var toggle = obEl(key);
            var input = obEl('checklist_file_' + key);
            var onFile = $(this).find('.existing-doc').not('.is-removed').length;
            var problems = Array.prototype.map.call(input.files, obFileProblem).filter(Boolean);

            if (problems.length) {
                setFieldState(input, false, problems.join(' '));
                ok = false;
            } else if (toggle.checked && !onFile && !input.files.length) {
                setFieldState(input, false, 'Upload the document for this item, or untick it.');
                ok = false;
            } else {
                clearFieldState(input);
            }
        });

        return ok;
    }

    function obCheckDeclarationFiles() {
        var ok = true;

        $('.declaration-file').each(function () {
            var onFile = $(this).data('on-file') === 1 || $(this).data('on-file') === '1';

            if (this.files.length) {
                var problem = obFileProblem(this.files[0]);
                if (problem) { setFieldState(this, false, problem); ok = false; return; }
                setFieldState(this, true);
            } else if (!onFile) {
                setFieldState(this, false, this.id === 'declaration_hr_copy'
                    ? 'Upload the declaration signed by HR.' : 'Upload the declaration signed by the employee.');
                ok = false;
            } else {
                clearFieldState(this);
            }
        });

        return ok;
    }

    $(document).on('change', '.declaration-file', obCheckDeclarationFiles);

    // ══════════════════════════════════════════════════════════
    //  REVIEW: salary, bank and every document
    // ══════════════════════════════════════════════════════════
    function onboardingReviewHtml() {
        function row(label, value) {
            return '<div class="review-row"><span class="review-label">' + label + '</span><span class="review-value">'
                + (value || '<span class="text-muted">—</span>') + '</span></div>';
        }

        function chips(names) {
            return names.map(function (n) { return '<span class="doc-chip"><i class="fa fa-paperclip"></i> ' + obEscape(n) + '</span>'; }).join('');
        }

        var h = '';
        var monthly = $('#monthly_salary').val();

        h += '<p class="review-section-title"><i class="fa fa-rupee-sign mr-2"></i>Salary &amp; Bank</p>';
        h += row('Monthly Salary', monthly ? obMoney(monthly) : '');
        h += row('Bank & Branch', ifscState.status === 'valid' ? obEscape(ifscState.message)
            : (ifscState.status === 'invalid' ? '<span class="text-red">' + obEscape(ifscState.message) + '</span>' : ''));

        h += '<p class="review-section-title"><i class="fa fa-folder-open mr-2"></i>Joining Checklist &amp; Documents</p>';

        var resumeInput = obEl('resume_file');
        if (resumeInput && resumeInput.files.length) {
            h += row('Resume', obChipForFile(resumeInput.files[0]) + ' <span class="f-11 text-lightest">replaces the one on file</span>');
        } else {
            h += row('Resume', $('#resume-box .ob-doc-link').map(function () { return obChipForLink(this); }).get().join(''));
        }

        $('.checklist-item').each(function () {
            var key = $(this).data('key');
            var label = $(this).find('label').first().text().trim();
            var ticked = obEl(key).checked;
            var onFile = $(this).find('.existing-doc').not('.is-removed').find('.ob-doc-link')
                .map(function () { return obChipForLink(this); }).get();
            var picked = Array.prototype.map.call(obEl('checklist_file_' + key).files, obChipForFile);
            var value;

            if (!ticked && !onFile.length && !picked.length) {
                value = '<span class="review-check-no"><i class="fa fa-times-circle mr-1"></i>Not received</span>';
            } else if (ticked && !onFile.length && !picked.length) {
                value = '<span class="text-red"><i class="fa fa-exclamation-circle mr-1"></i>Ticked, document missing</span>';
            } else {
                value = '<span class="review-check-yes mr-2"><i class="fa fa-check-circle mr-1"></i>Received</span>'
                    + onFile.concat(picked).join('');
            }

            h += row(obEscape(label), value);
        });

        h += row('Declaration copies', '<span class="text-lightest">Uploaded on the next step (Declaration)</span>');

        return h;
    }
