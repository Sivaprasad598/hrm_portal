{{-- Step 7 of Add / Edit Employee: the final declaration.

     $required     true on Add (everything is asked for), false on Edit (all optional:
                   most employees from before this step existed have none of it)
     $declaration  the saved declaration (Edit) or null
     $onboardingDocs  the documents on file (Edit) or null

     Saved exactly as before: reported_duty_day / _month / _year, candidate_signature,
     hr_signature, hr_comments and the two signed copies. The duty date is picked
     once from a calendar and written into the three day / month / year fields. --}}
@php
    $required = $required ?? false;
    $monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    // the saved date (its month may be a name, a short name or a number)
    $savedDay = (int) ($declaration->reported_duty_day ?? 0);
    $savedYear = (int) ($declaration->reported_duty_year ?? 0);
    $rawMonth = strtolower(trim((string) ($declaration->reported_duty_month ?? '')));
    $savedMonth = ctype_digit($rawMonth) ? (int) $rawMonth : 0;
    foreach ($monthNames as $i => $name) {
        if ($rawMonth !== '' && str_starts_with(strtolower($name), substr($rawMonth, 0, 3))) { $savedMonth = $i + 1; }
    }
    $savedDate = ($savedDay && $savedMonth && $savedYear && checkdate($savedMonth, $savedDay, $savedYear))
        ? \Carbon\Carbon::create($savedYear, $savedMonth, $savedDay) : null;

    // HR signs as the person filling the form in, unless a name is already saved
    $hrName = trim(preg_replace('/[^A-Za-z ]+/', ' ', (string) user()->name));
    $star = $required ? ' <sup class="text-red f-14">*</sup>' : '';
@endphp

<style>
    .fd-block { border: 1px solid #eef0f3; border-radius: 12px; padding: 16px 18px; margin-bottom: 16px; background: #fff; }
    .fd-block h6 { font-size: 14px; font-weight: 600; color: #1f2937; margin: 0 0 2px; }
    .fd-block .fd-hint { font-size: 12.5px; color: #667085; margin: 0 0 12px; }
    .fd-date { max-width: 320px; }
    .fd-date .input-group-text { background: #fff; }
    .fd-same { font-size: 12.5px; }
    .fd-sign-preview { min-height: 34px; margin-top: 6px; padding: 2px 4px 4px; border-bottom: 1px dashed #c9ced6;
        font-family: 'Segoe Script', 'Brush Script MT', 'Lucida Handwriting', cursive; font-size: 22px; color: #1f2a44; line-height: 1.3; }
    .fd-sign-preview:empty::before { content: attr(data-empty); font-family: inherit; font-size: 13px; color: #b3b8c2; }
    .fd-optional { font-size: 12px; color: #98a2b3; font-weight: 400; }
</style>

<div class="col-12">
    {{-- 1. the day they reported to duty --}}
    <div class="fd-block">
        <h6>
            @lang('modules.employees.reportedToDutyOn')
            @unless ($required) <span class="fd-optional">(optional)</span> @endunless
        </h6>
        <p class="fd-hint">The day the employee actually joined and started work.</p>
        <div class="d-flex align-items-center flex-wrap" style="gap: 12px;">
            <div class="input-group fd-date">
                <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-calendar-alt text-lightest"></i></span></div>
                <input type="text" id="reported_duty_date" class="form-control height-35 f-14" autocomplete="off" readonly
                       placeholder="Pick the date" value="{{ $savedDate ? $savedDate->format(company()->date_format) : '' }}"
                       aria-label="@lang('modules.employees.reportedToDutyOn')">
            </div>
            <a href="javascript:;" class="fd-same" id="fd-same-as-joining"><i class="fa fa-magic mr-1"></i>Same as joining date</a>
            <a href="javascript:;" class="fd-same text-lightest {{ $savedDate ? '' : 'd-none' }}" id="fd-clear-date">Clear</a>
        </div>
        {{-- what is saved: the same three fields as before --}}
        <input type="hidden" id="reported_duty_day" name="reported_duty_day" value="{{ $savedDate ? $savedDate->day : '' }}">
        <input type="hidden" id="reported_duty_month" name="reported_duty_month" value="{{ $savedDate ? $monthNames[$savedDate->month - 1] : '' }}">
        <input type="hidden" id="reported_duty_year" name="reported_duty_year" value="{{ $savedDate ? $savedDate->year : '' }}">
    </div>

    {{-- 2. signatures: the names as signed; the signed copies themselves are uploaded below --}}
    <div class="fd-block">
        <h6>
            Signatures
            @unless ($required) <span class="fd-optional">(optional)</span> @endunless
        </h6>
        <p class="fd-hint">Type each person's full name as it is signed on the declaration (letters and spaces). The scanned, signed copies go in the next section.</p>
        <div class="row">
            <div class="col-lg-6 col-md-12">
                <div class="form-group mb-2">
                    <label class="f-14 text-dark-grey mb-1" for="candidate_signature">{{ __('modules.employees.signatureOfCandidate') }}{!! $star !!}</label>
                    <input type="text" class="form-control height-35 f-14" id="candidate_signature" name="candidate_signature" maxlength="100"
                           autocomplete="off" placeholder="Employee's full name" value="{{ $declaration->candidate_signature ?? '' }}"
                           oninput="this.value = this.value.replace(/[^A-Za-z\s]/g, '');" @if ($required) required @endif>
                    <div class="fd-sign-preview" data-for="candidate_signature" data-empty="The signature appears here"></div>
                </div>
            </div>
            <div class="col-lg-6 col-md-12">
                <div class="form-group mb-2">
                    <label class="f-14 text-dark-grey mb-1" for="hr_signature">{{ __('modules.employees.signatureOfHR') }}{!! $star !!}</label>
                    <input type="text" class="form-control height-35 f-14" id="hr_signature" name="hr_signature" maxlength="100"
                           autocomplete="off" placeholder="HR representative's full name"
                           value="{{ ($declaration->hr_signature ?? '') !== '' ? $declaration->hr_signature : ($required ? $hrName : '') }}"
                           oninput="this.value = this.value.replace(/[^A-Za-z\s]/g, '');" @if ($required) required @endif>
                    <div class="fd-sign-preview" data-for="hr_signature" data-empty="The signature appears here"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- 3. HR's note --}}
    <div class="fd-block">
        <h6>@lang('modules.employees.hrComments') <span class="fd-optional">(optional)</span></h6>
        <textarea class="form-control f-14" id="hr_comments" name="hr_comments" rows="3" maxlength="500"
                  placeholder="Anything to note about the joining (up to 500 characters)">{{ $declaration->hr_comments ?? '' }}</textarea>
    </div>
</div>

{{-- Signed declaration copies (onboarding-declaration-files) are no longer asked for on Add / Edit Employee. --}}

<script>
    (function () {
        var MONTHS = @json($monthNames);
        var shown = document.getElementById('reported_duty_date');
        if (!shown) { return; }

        var day = document.getElementById('reported_duty_day');
        var month = document.getElementById('reported_duty_month');
        var year = document.getElementById('reported_duty_year');
        var clear = document.getElementById('fd-clear-date');

        function setDate(date) {
            day.value = date ? date.getDate() : '';
            month.value = date ? MONTHS[date.getMonth()] : '';
            year.value = date ? date.getFullYear() : '';
            if (!date) { shown.value = ''; }
            clear.classList.toggle('d-none', !date);
            $(day).trigger('change');
        }

        var saved = day.value && month.value && year.value ? new Date(+year.value, MONTHS.indexOf(month.value), +day.value) : null;
        var picker = (typeof datepicker === 'function') ? datepicker('#reported_duty_date', {
            position: 'bl',
            ...datepickerConfig,
            dateSelected: saved || undefined,
            onSelect: function (instance, date) { setDate(date || null); }
        }) : null;

        // the joining date from step 1, in the company's date format
        document.getElementById('fd-same-as-joining').addEventListener('click', function () {
            var joining = document.getElementById('joining_date');
            var m = joining && joining.value ? moment(joining.value, company.moment_date_format, true) : null;

            if (!m || !m.isValid()) {
                Swal.fire({ icon: 'info', text: 'Enter the joining date on the Employment step first.' });
                return;
            }

            var date = m.toDate();
            shown.value = m.format(company.moment_date_format);
            if (picker) { picker.setDate(date, true); }
            setDate(date);
        });

        clear.addEventListener('click', function () {
            if (picker) { picker.setDate(); }
            setDate(null);
        });

        // the typed names, in a signature hand
        document.querySelectorAll('.fd-sign-preview').forEach(function (preview) {
            var input = document.getElementById(preview.getAttribute('data-for'));
            var draw = function () { preview.textContent = input.value.trim(); };
            input.addEventListener('input', draw);
            draw();
        });

        // the employee's own name, once, when the step is reached with the field still empty (Add)
        @if ($required)
            $(document).off('click.fdname').on('click.fdname', '.wizardNext[data-to="7"]', function () {
                var sign = document.getElementById('candidate_signature');
                if (sign && !sign.value.trim()) {
                    var first = (document.getElementById('first_name') || {}).value || '';
                    var last = (document.getElementById('last_name') || {}).value || '';
                    var full = (first + ' ' + last).replace(/[^A-Za-z ]+/g, ' ').replace(/\s+/g, ' ').trim();
                    if (full) { sign.value = full; $(sign).trigger('input'); }
                }
            });
        @endif
    })();
</script>
