{{-- Finance -> Invoices -> settings (side panel, InvoiceController::settingsForm): the company's GSTIN
     and state, how invoices are numbered, and the wording every new invoice starts with. --}}
@php $s = $settings; @endphp
<div class="row">
    <div class="col-sm-12">
        <x-form id="iv-settings-form" method="POST">
            <div class="add-client bg-white rounded rc-form">
                <h4 class="mb-0 p-20 f-21 font-weight-normal border-bottom-grey">Invoice settings</h4>
                <div class="p-20">
                    <div class="row">
                        <div class="col-md-6">
                            <x-forms.text fieldId="ivs_gstin" fieldLabel="Company GSTIN" fieldName="gstin" :fieldValue="$s->gstin" fieldPlaceholder="37AAACA1234A1Z5" />
                        </div>
                        <div class="col-md-6">
                            <x-forms.select fieldId="ivs_state" fieldLabel="Registered in (state)" fieldName="state_code" search="true" :fieldRequired="true">
                                @foreach ($states as $code => $name)<option value="{{ $code }}" @selected((string) $s->state_code === (string) $code)>{{ $name }} ({{ $code }})</option>@endforeach
                            </x-forms.select>
                        </div>
                        <div class="col-md-4 col-6">
                            <x-forms.text fieldId="ivs_prefix" fieldLabel="Number prefix" fieldName="prefix" :fieldValue="$s->prefix" fieldPlaceholder="INV" />
                        </div>
                        <div class="col-md-4 col-6">
                            <x-forms.number fieldId="ivs_digits" fieldLabel="Digits" fieldName="digits" :fieldValue="$s->digits" minValue="1" maxValue="6" />
                        </div>
                        <div class="col-md-4">
                            <x-forms.number fieldId="ivs_due" fieldLabel="Due after (days)" fieldName="due_after" :fieldValue="$s->due_after" minValue="0" />
                        </div>
                        <div class="col-12">
                            <p class="rc-help mt-n2 mb-3">Numbers look like <b id="ivs-sample">{{ $s->prefix }}/{{ \AppModules\Finance\Services\GstInvoice::financialYear(now()) }}/{{ str_pad('1', $s->digits, '0', STR_PAD_LEFT) }}</b> and start again each financial year (April). GST allows at most 16 characters.</p>
                        </div>
                        <div class="col-12">
                            <x-forms.textarea fieldId="ivs_bank" fieldLabel="Bank details (printed on invoices)" fieldName="bank" :fieldValue="$s->bank"
                                fieldPlaceholder="Account name, account number, bank and branch, IFSC" />
                        </div>
                        <div class="col-12">
                            <x-forms.textarea fieldId="ivs_terms" fieldLabel="Terms (new invoices start with these)" fieldName="terms" :fieldValue="$s->terms"
                                fieldPlaceholder="e.g. Payment within 15 days. Interest at 18% a year on late payments." />
                        </div>
                        <div class="col-md-6">
                            <x-forms.text fieldId="ivs_sign" fieldLabel="Authorised signatory" fieldName="signatory" :fieldValue="$s->signatory" fieldPlaceholder="Name" />
                        </div>
                    </div>
                    <p class="rc-help mb-0">Company name, address and contact details come from the letterhead (Letter Settings), so letters and invoices match.</p>
                </div>
                <x-form-actions>
                    <x-forms.button-primary id="save-iv-settings" class="mr-3" icon="check">@lang('app.save')</x-forms.button-primary>
                    <x-forms.button-cancel id="cancel-iv-settings" class="border-0">@lang('app.cancel')</x-forms.button-cancel>
                </x-form-actions>
            </div>
        </x-form>
    </div>
</div>

@include('recruitment.partials.submit-js')

<script>
    $(function () {
        var fy = @json(\AppModules\Finance\Services\GstInvoice::financialYear(now()));
        function sample() {
            var p = ($('#ivs_prefix').val() || 'INV').toUpperCase().replace(/[^A-Z0-9-]/g, '');
            var d = Math.max(1, Math.min(6, parseInt($('#ivs_digits').val(), 10) || 3));
            $('#ivs-sample').text(p + '/' + fy + '/' + '1'.padStart(d, '0'));
        }
        $('#ivs_prefix, #ivs_digits').on('input', sample);
        $('#ivs_gstin').on('input', function () {
            var v = this.value.toUpperCase().trim();
            this.value = v;
            if (/^[0-9]{2}/.test(v) && $('#ivs_state option[value="' + v.substr(0, 2) + '"]').length) { $('#ivs_state').selectpicker('val', v.substr(0, 2)); }
        });
        $('#save-iv-settings').on('click', function () { recruitSubmit('iv-settings-form', '{{ route('invoices.settings.save') }}', 'save-iv-settings'); });
        $('#cancel-iv-settings').on('click', function () { recruitClose('{{ route('invoices.index') }}'); });
        init(RIGHT_MODAL);
    });
</script>
