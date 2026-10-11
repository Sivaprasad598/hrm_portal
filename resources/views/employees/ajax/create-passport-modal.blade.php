
<link rel="stylesheet" href="{{ asset('vendor/css/dropzone.min.css') }}">

<div class="modal-header">
    <h5 class="modal-title">@lang('app.addPassport')</h5>
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
</div>
<div class="modal-body">
    <div class="portlet-body">
        <x-form id="save-passport-data-form" method="POST" class="ajax-form">

            <input type="hidden" value="{{ request()->empid }}" name="emp_id">

            <div class="row">
                <!-- First Row: Passport Number and Nationality -->
                <div class="col-lg-6">
                    <x-forms.text :fieldLabel="__('modules.employees.passportNumber')"
                        fieldName="passport_number" fieldId="passport_number"
                        fieldValue="" :fieldRequired="true" />
                </div>
                <div class="col-lg-6">
                    <x-forms.select fieldId="nationality" :fieldLabel="__('app.nationality')" fieldName="nationality"
                        search="true" :fieldRequired="true">
                        <option value="">--</option>
                        @foreach ($countries as $item)
                            <option data-tokens="{{ $item->iso3 }}"
                                data-content="<span class='flag-icon flag-icon-{{ strtolower($item->iso) }} flag-icon-squared'></span> {{ $item->nationality .' ('.  $item->name . ')'}}"
                                value="{{ $item->id }}">{{ $item->nationality }}</option>
                        @endforeach
                    </x-forms.select>
                </div>
            </div>

            <div class="row">
                <!-- Second Row: Issue Date, Expiry Date, and Alert Before -->
                <div class="col-lg-4">
                    <x-forms.datepicker fieldId="issue_date" fieldRequired="true"
                                        :fieldLabel="__('modules.employees.issueDate')" fieldName="issue_date"
                                        :fieldValue="now(company()->timezone)->format(company()->date_format)"
                                        :fieldPlaceholder="__('placeholders.date')"/>
                </div>

                <div class="col-lg-4">
                    <x-forms.datepicker fieldId="expiry_date" fieldRequired="true"
                                        :fieldLabel="__('modules.employees.expiryDate')" fieldName="expiry_date"
                                        :fieldValue="now(company()->timezone)->format(company()->date_format)"
                                        :fieldPlaceholder="__('placeholders.date')"/>
                </div>

                <div class="col-lg-4">
                    <x-forms.select fieldId="alert_before_months" :fieldLabel="__('modules.employees.alertBeforeMonths')" fieldName="alert_before_months"
                        :fieldRequired="false">
                        <option value="0">@lang('app.noAlert')</option>
                        <option value="1">1 @lang('app.month')</option>
                        <option value="2">2 @lang('app.monthPlural')</option>
                        <option value="3">3 @lang('app.monthPlural')</option>
                        <option value="4">4 @lang('app.monthPlural')</option>
                        <option value="5">5 @lang('app.monthPlural')</option>
                        <option value="6">6 @lang('app.monthPlural')</option>
                        <option value="7">7 @lang('app.monthPlural')</option>
                        <option value="8">8 @lang('app.monthPlural')</option>
                        <option value="9">9 @lang('app.monthPlural')</option>
                        <option value="10">10 @lang('app.monthPlural')</option>
                        <option value="11">11 @lang('app.monthPlural')</option>
                        <option value="12">12 @lang('app.monthPlural')</option>
                    </x-forms.select>
                </div>
            </div>

            <div class="row">
                <!-- Third Row: Upload file section (full width) -->
                <div class="col-lg-12">
                    <x-forms.file allowedFileExtensions="png jpg jpeg svg pdf doc docx" class="mr-0 mr-lg-2 mr-md-2"
                        :fieldLabel="__('modules.employees.scanCopy')" fieldName="file"
                        fieldId="file">
                    </x-forms.file>
                </div>
            </div>
        </x-form>
    </div>
</div>
<div class="modal-footer">
    <x-forms.button-cancel data-dismiss="modal" class="border-0 mr-3">@lang('app.cancel')</x-forms.button-cancel>
    <x-forms.button-primary id="save-passport-form" icon="check">@lang('app.save')</x-forms.button-primary>
</div>

<script>
    datepicker('#issue_date', {
            position: 'bl',
            ...datepickerConfig
        });

    datepicker('#expiry_date', {
        position: 'bl',
        ...datepickerConfig
    });

    $('#save-passport-form').click(function(){
        $.easyAjax({
                    url: "{{ route('passport.store') }}",
                    container: '#save-passport-data-form',
                    type: "POST",
                    disableButton: true,
                    blockUI: true,
                    buttonSelector: 'save-passport-form',
                    file: true,
                    data: $('#save-passport-data-form').serialize(),
                    success: function (response) {
                        if (response.status === 'success') {
                            window.location.reload();
                        }
                    }
        });
    });

    init(MODAL_LG);
</script>
