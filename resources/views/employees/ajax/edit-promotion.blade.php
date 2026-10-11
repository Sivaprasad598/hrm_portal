<x-form id="save-promotion-form" method="PUT">
    <div class="modal-header">
        <h5 class="modal-title">{{ $pageTitle }}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
    </div>
    <div class="modal-body">
        <div class="portlet-body">
            <div class="add-client bg-white rounded">
                <div class="row">
                    <div class="col-xl-12 col-lg-12 col-sm-12">
                        <input type="hidden" name="user_id" value="{{ $userId }}">

                        <div class="row">
                            <div class="col-md-6 col-sm-6">
                                <x-forms.label class="my-3" fieldId="old_designation_id"
                                    :fieldLabel="__('modules.incrementPromotion.oldDesignation')"></x-forms.label>
                                <span class="input-group-text" id="old_designation_id">{{ $promotion->previousDesignation->name }}</span>
                            </div>
                            <div class="col-md-6 col-sm-6">
                                <x-forms.label class="my-3" fieldId="old_department_id"
                                    :fieldLabel="__('modules.incrementPromotion.oldDepartment')"></x-forms.label>
                                <span class="input-group-text" id="old_department_id">{{ $promotion->previousDepartment->team_name }}</span>
                            </div>
                            <div class="col-md-6 col-sm-6">
                                <x-forms.select fieldId="current_designation_id" :fieldLabel="__('modules.incrementPromotion.newDesignation')" fieldName="current_designation_id" search="true"
                                    fieldRequired="true" class="select-picker">
                                    @foreach ($designations as $designation)
                                    <option value="{{ $designation->id }}" @if($designation->id == $promotion->current_designation_id) selected @endif>{{ $designation->name }}</option>
                                    @endforeach
                                </x-forms.select>
                            </div>
                            <div class="col-md-6 col-sm-6">
                                <x-forms.select fieldId="current_department_id" :fieldLabel="__('modules.incrementPromotion.newDepartment')" fieldName="current_department_id" search="true"
                                    fieldRequired="true" class="select-picker">
                                    @foreach ($departments as $department)
                                    <option value="{{ $department->id }}" @if($department->id == $promotion->current_department_id) selected @endif>{{ $department->team_name }}</option>
                                    @endforeach
                                </x-forms.select>
                            </div>
                            <div class="col-md-6">
                                <x-forms.text :fieldLabel="__('app.date')" fieldName="date" fieldId="date" :fieldPlaceholder="__('app.date')"
                                    :fieldValue="$promotion->date ? \Carbon\Carbon::parse($promotion->date)->translatedFormat(company()->date_format) : now(company()->timezone)->translatedFormat(company()->date_format)" fieldRequired />
                            </div>
                            <div class="col-md-3 mt-5">
                                <x-forms.checkbox class="mr-0 mr-lg-2 mr-md-2" :fieldLabel="__('app.sendNotification')"
                                    fieldName="send_notification" fieldId="send_notification" fieldValue="yes"
                                    fieldRequired="true" :checked='$promotion->send_notification == "yes"'/>
                            </div>

                            <div class="col-md-3 mt-5">
                                <div class="form-group d-flex align-items-center">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" name="promotion" @checked($promotion->promotion == 1) class="custom-control-input"
                                               id="promotion">
                                        <label class="custom-control-label cursor-pointer f-14" for="promotion"></label>
                                    </div>
                                    <span class="f-14 text-dark-grey" id="promotion-text">{{ __('modules.incrementPromotion.promotion') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <x-forms.button-cancel data-dismiss="modal" class="border-0 mr-3">@lang('app.cancel')</x-forms.button-cancel>
        <x-forms.button-primary id="save-promotion" icon="check">@lang('app.save')</x-forms.button-primary>
    </div>
</x-form>

<script>
    datepicker('#date', {
        position: 'bl',
        ...datepickerConfig
    });

    function initPromotionToggle() {
        const $toggle = $('#promotion');
        const $promotionText = $('#promotion-text');

        if ($toggle.length && $promotionText.length) {
            function updateLabelText() {
                const text = $toggle.is(':checked')
                    ? '{{ __("modules.incrementPromotion.promotion") }}'
                    : '{{ __("modules.incrementPromotion.demotion") }}';
                $promotionText.text(text);
            }

            // Initial load
            updateLabelText();

            // Remove existing event listener to prevent duplicates
            $toggle.off('change.promotionToggle');

            // On toggle change
            $toggle.on('change.promotionToggle', updateLabelText);
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        initPromotionToggle();
    });

    // Initialize when modal is shown (in case it's loaded via AJAX)
    $(document).on('shown.bs.modal', function() {
        initPromotionToggle();
    });

    $('.select-picker').selectpicker('refresh');

    $('#save-promotion').click(function() {
        const url = "{{ route('promotions.update', $promotion->id) }}";

        $.easyAjax({
            url: url,
            container: '#save-promotion-form',
            type: "POST",
            disableButton: true,
            blockUI: true,
            buttonSelector: "#save-promotion",
            data: $('#save-promotion-form').serialize(),
            success: function(response) {
                if (response.status === 'success') {
                    window.location.reload();
                }
            }
        })
    });
</script>
