<!-- LEAVE ENCASHMENT SETTINGS -->
<div class="col-lg-12 col-md-12 w-100 p-4">

    <x-alert type="info">
        Leave that is <strong>paid</strong> at year end — or carried forward beyond its maximum and set to
        <strong>encash</strong> — is valued with the settings below and paid on the employee's next payslip.
        Which leave types encash is set on each leave type, under <em>Unused leave</em>.
    </x-alert>

    <form id="encashment-settings-form">
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <x-forms.select fieldId="encashment_basis" fieldLabel="Value an encashed day from"
                    fieldName="encashment_basis"
                    popover="The monthly salary figure a day of leave is worth a fraction of.">
                    <option value="basic" @selected(($settings->encashment_basis ?? 'basic') === 'basic')>Basic salary</option>
                    <option value="gross" @selected(($settings->encashment_basis ?? '') === 'gross')>Gross salary</option>
                </x-forms.select>
            </div>

            <div class="col-lg-3 col-md-6">
                <x-forms.number fieldId="encashment_divisor" fieldLabel="Divided by (days)"
                    fieldName="encashment_divisor" :fieldValue="(float) ($settings->encashment_divisor ?? 26)"
                    popover="Per-day value = monthly figure ÷ this number. 26 is the usual working-day basis; 30 uses calendar days." />
            </div>

            <div class="col-lg-3 col-md-6">
                <x-forms.select fieldId="encashment_salary_source" fieldLabel="Take the salary from"
                    fieldName="encashment_salary_source"
                    popover="CTC is available for everyone with a salary on file. The last payslip reflects what was actually paid but is missing for anyone not yet paid.">
                    <option value="ctc" @selected(($settings->encashment_salary_source ?? 'ctc') === 'ctc')>Employee CTC (÷ 12)</option>
                    <option value="last_payslip" @selected(($settings->encashment_salary_source ?? '') === 'last_payslip')>Latest payslip</option>
                </x-forms.select>
            </div>

            <div class="col-lg-3 col-md-6" id="basic-pct-field">
                <x-forms.number fieldId="encashment_basic_pct" fieldLabel="Basic as % of gross"
                    fieldName="encashment_basic_pct" :fieldValue="(float) ($settings->encashment_basic_pct ?? 50)"
                    popover="Used with CTC when valuing from basic. An employee's own basic % takes precedence where one is set." />
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <p class="f-13 text-dark-grey mb-1">Example</p>
                <p class="f-13 text-lightest" id="encashment-example"></p>
            </div>
        </div>

        <x-forms.button-primary id="save-encashment-settings" icon="check">@lang('app.save')</x-forms.button-primary>
    </form>
</div>

<script>
    (function () {
        function refresh() {
            var basis = $('#encashment_basis').val();
            var source = $('#encashment_salary_source').val();
            var divisor = parseFloat($('#encashment_divisor').val()) || 26;
            var pct = parseFloat($('#encashment_basic_pct').val()) || 50;

            // The basic % only matters when basic is derived from CTC.
            $('#basic-pct-field').toggle(basis === 'basic' && source === 'ctc');

            // Worked example on a round figure, so the setting is easy to sanity-check.
            var ctc = 360000, monthly = ctc / 12;
            var base = basis === 'gross' ? monthly : monthly * pct / 100;
            var text = source === 'ctc'
                ? 'CTC ₹3,60,000 → ₹30,000 a month' + (basis === 'basic' ? ' → basic at ' + pct + '% = ₹' + base.toLocaleString('en-IN') : ' gross')
                : (basis === 'basic' ? 'Basic' : 'Gross') + ' from the latest payslip, e.g. ₹' + base.toLocaleString('en-IN');
            text += ' → ÷ ' + divisor + ' = ₹' + (base / divisor).toFixed(2) + ' per day → 10 days = ₹' + (base / divisor * 10).toFixed(2);
            $('#encashment-example').text(text);
        }

        $('#encashment-settings-form').on('change keyup', 'select, input', refresh);
        refresh();

        $('#save-encashment-settings').on('click', function () {
            $.easyAjax({
                url: "{{ route('leaves-settings.encashment') }}",
                type: 'POST',
                container: '#encashment-settings-form',
                blockUI: true,
                data: $('#encashment-settings-form').serialize() + '&_token={{ csrf_token() }}'
            });
        });
    })();
</script>
