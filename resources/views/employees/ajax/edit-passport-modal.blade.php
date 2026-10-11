
<link rel="stylesheet" href="{{ asset('vendor/css/dropzone.min.css') }}">

<div class="modal-header">
    <h5 class="modal-title">@lang('app.editPassport')</h5>
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
</div>
<div class="modal-body">
    <div class="portlet-body">
        <x-form id="save-passport-data-form" method="PUT" class="ajax-form">

            <input type="hidden" value="{{ request()->empid }}" name="emp_id">

            <div class="row">
                <!-- First Row: Passport Number and Nationality -->
                <div class="col-lg-6">
                    <x-forms.text :fieldLabel="__('modules.employees.passportNumber')"
                        fieldName="passport_number" fieldId="passport_number"
                        :fieldValue="$passport->passport_number ?? '' " :fieldRequired="true" />
                </div>

                <div class="col-lg-6">
                    <x-forms.select fieldId="nationality" :fieldLabel="__('app.nationality')" fieldName="nationality"
                        search="true" :fieldRequired="true">
                        <option value="">--</option>
                        @foreach ($countries as $item)
                            <option @if ($passport->country_id == $item->id) selected @endif data-tokens="{{ $item->iso3 }}" data-content="<span
                            class='flag-icon flag-icon-{{ strtolower($item->iso) }} flag-icon-squared'></span>
                            {{ $item->nationality .' ('.  $item->name . ')'}}" value="{{ $item->id }}">{{ $item->nationality }}</option>
                        @endforeach
                    </x-forms.select>
                </div>
            </div>

            <div class="row">
                <!-- Second Row: Issue Date, Expiry Date, and Alert Before -->
                <div class="col-lg-4">
                    <x-forms.datepicker fieldId="issue_date" fieldRequired="true"
                                        :fieldLabel="__('modules.employees.issueDate')" fieldName="issue_date"
                                        :fieldValue="$passport ? $passport->issue_date->format(company()->date_format) :  now(company()->timezone)->format(company()->date_format)"
                                        :fieldPlaceholder="__('placeholders.date')"/>
                </div>

                <div class="col-lg-4">
                    <x-forms.datepicker fieldId="expiry_date" fieldRequired="true"
                                        :fieldLabel="__('modules.employees.expiryDate')" fieldName="expiry_date"
                                        :fieldValue="$passport ? $passport->expiry_date->format(company()->date_format) : now(company()->timezone)->format(company()->date_format)"
                                        :fieldPlaceholder="__('placeholders.date')"/>
                </div>

                <div class="col-lg-4">
                    <x-forms.select fieldId="alert_before_months" :fieldLabel="__('modules.employees.alertBeforeMonths')" fieldName="alert_before_months"
                        :fieldRequired="false">
                        <option value="0" @if($passport->alert_before_months == 0) selected @endif>@lang('app.noAlert')</option>
                        <option value="1" @if($passport->alert_before_months == 1) selected @endif>1 @lang('app.month')</option>
                        <option value="2" @if($passport->alert_before_months == 2) selected @endif>2 @lang('app.monthPlural')</option>
                        <option value="3" @if($passport->alert_before_months == 3) selected @endif>3 @lang('app.monthPlural')</option>
                        <option value="4" @if($passport->alert_before_months == 4) selected @endif>4 @lang('app.monthPlural')</option>
                        <option value="5" @if($passport->alert_before_months == 5) selected @endif>5 @lang('app.monthPlural')</option>
                        <option value="6" @if($passport->alert_before_months == 6) selected @endif>6 @lang('app.monthPlural')</option>
                        <option value="7" @if($passport->alert_before_months == 7) selected @endif>7 @lang('app.monthPlural')</option>
                        <option value="8" @if($passport->alert_before_months == 8) selected @endif>8 @lang('app.monthPlural')</option>
                        <option value="9" @if($passport->alert_before_months == 9) selected @endif>9 @lang('app.monthPlural')</option>
                        <option value="10" @if($passport->alert_before_months == 10) selected @endif>10 @lang('app.monthPlural')</option>
                        <option value="11" @if($passport->alert_before_months == 11) selected @endif>11 @lang('app.monthPlural')</option>
                        <option value="12" @if($passport->alert_before_months == 12) selected @endif>12 @lang('app.monthPlural')</option>
                    </x-forms.select>
                </div>
            </div>

            <div class="row">
                <!-- Third Row: Upload file section (full width) -->
                <div class="col-lg-12">
                    <x-forms.file allowedFileExtensions="png jpg jpeg svg pdf doc docx" class="mr-0 mr-lg-2 mr-md-2"
                        :fieldLabel="__('modules.employees.scanCopy')" fieldName="file"
                        :fieldValue="$passport->file ? $passport->image_url : '' "
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
                    url: "{{ route('passport.update', $passport->id) }}",
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
