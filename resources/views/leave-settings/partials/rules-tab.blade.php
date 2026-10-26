{{-- Leave type -> Rules: the supporting document, how far back and how far
     ahead it may be applied, and the most days at a time (App\Services\Leaves\LeaveRules).
     $type: the leave type being edited, or null when adding one. --}}
@php
    $type = $type ?? null;
    $docRequired = $type->doc_required ?? 'none';
    // 3.0 shows as 3; only zeros after a decimal point are dropped (30 stays 30).
    $val = function ($field) use ($type) {
        $v = $type?->{$field};
        $v = $v === null ? '' : (str_contains((string) $v, '.') ? rtrim(rtrim((string) $v, '0'), '.') : (string) $v);

        return old($field, $v);
    };
@endphp

<div class="tab-pane" id="leave-rules">
    <h3 class="heading-h3 mt-4">Rules</h3>
    <p class="f-13 text-dark-grey mb-0">Checked when an employee applies for their own leave. Leave a field blank for no rule.</p>

    <h4 class="f-14 font-weight-bold text-dark mt-4 mb-0">Supporting document</h4>
    <div class="row">
        <div class="col-lg-4 col-md-6">
            <div class="form-group my-3">
                <label class="f-14 text-dark-grey mb-12" for="doc_required">Document needed</label>
                <select class="form-control height-35 f-14" name="doc_required" id="doc_required">
                    <option value="none" @selected($docRequired === 'none')>Not needed</option>
                    <option value="over_days" @selected($docRequired === 'over_days')>When longer than … days</option>
                    <option value="always" @selected($docRequired === 'always')>Always</option>
                </select>
            </div>
        </div>
        <div class="col-lg-4 col-md-6 doc-rule-field doc-days-field">
            <x-forms.number fieldLabel="Longer than (days)" fieldName="doc_min_days" fieldId="doc_min_days" minValue="0"
                :fieldValue="$val('doc_min_days')" fieldPlaceholder="e.g. 3"
                popover="Sick leave longer than 3 days needs a medical certificate: enter 3." />
        </div>
        <div class="col-lg-4 col-md-6 doc-rule-field">
            <x-forms.text fieldLabel="Document name" fieldName="doc_label" fieldId="doc_label"
                :fieldValue="old('doc_label', $type->doc_label ?? '')" fieldPlaceholder="e.g. Medical certificate" />
        </div>
        <div class="col-lg-4 col-md-6 doc-rule-field">
            <x-forms.number fieldLabel="Can follow within (days after the leave)" fieldName="doc_later_days" fieldId="doc_later_days" minValue="0"
                :fieldValue="$val('doc_later_days')" fieldPlaceholder="Attach when applying"
                popover="Blank: the document is attached when applying. A number: it may be uploaded that many days after the leave; the leave is not approved until it is." />
        </div>
    </div>

    <h4 class="f-14 font-weight-bold text-dark mt-3 mb-0">When it can be applied</h4>
    <div class="row">
        <div class="col-lg-4 col-md-6">
            <x-forms.number fieldLabel="Apply at least (days before)" fieldName="notice_days" fieldId="notice_days" minValue="0"
                :fieldValue="$val('notice_days')" fieldPlaceholder="No notice needed"
                popover="Casual leave applied 1 day ahead: enter 1. Past dates follow the next rule instead." />
        </div>
        <div class="col-lg-4 col-md-6">
            <x-forms.number fieldLabel="Can be applied afterwards (up to days)" fieldName="backdate_days" fieldId="backdate_days" minValue="0"
                :fieldValue="$val('backdate_days')" fieldPlaceholder="No limit"
                popover="Sick or emergency leave is taken first and applied for on return: enter how many days afterwards it may still be applied. 0: never for a past date." />
        </div>
        <div class="col-lg-4 col-md-6">
            <x-forms.number fieldLabel="Most days in one application" fieldName="max_days" fieldId="max_days" minValue="0"
                :fieldValue="$val('max_days')" fieldPlaceholder="No limit" />
        </div>
    </div>
</div>

<script>
    (function () {
        function docFields() {
            var need = $('#doc_required').val();
            $('.doc-rule-field').toggle(need !== 'none');
            $('.doc-days-field').toggle(need === 'over_days');
        }
        $(document).off('change.leaveRules').on('change.leaveRules', '#doc_required', docFields);
        docFields();
    })();
</script>
