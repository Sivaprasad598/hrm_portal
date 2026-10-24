@php
$viewLeadCategoryPermission = user()->permission('view_lead_category');
$viewLeadSourcesPermission = user()->permission('view_lead_sources');
$addLeadSourcesPermission = user()->permission('add_lead_sources');
$addLeadCategoryPermission = user()->permission('add_lead_category');
$addProductPermission = user()->permission('add_product');
$addLeadAgentPermission = user()->permission('add_lead_agent');
$viewLeadAgentPermission = user()->permission('view_lead_agents');
// Deal section: edit the lead's deal, or create one when it has none
$showDealSection = $deal ? $canEditDeal : in_array($addDealPermission, ['all', 'added']);
$dealCloseDate = $deal?->close_date ? $deal->close_date->translatedFormat(company()->date_format)
    : now(company()->timezone)->addDays(30)->translatedFormat(company()->date_format);
$dealProductIds = $deal ? $deal->products->pluck('id')->all() : [];
@endphp

<link rel="stylesheet" href="{{ asset('vendor/css/dropzone.min.css') }}">

<div class="row">
    <div class="col-sm-12">
        <x-form id="save-lead-data-form" method="PUT">
            <div class="add-client bg-white rounded">
                <h4 class="mb-0 p-20 f-21 font-weight-normal  border-bottom-grey">
                    @lang('modules.leadContact.leadDetails')</h4>

                <div class="row p-20">
                    <div class="col-lg-4 col-md-6">
                        <x-forms.select fieldId="salutation" :fieldLabel="__('modules.client.salutation')"
                            fieldName="salutation">
                            <option value="">--</option>
                            @foreach ($salutations as $salutation)
                                <option value="{{ $salutation->value }}" @selected($leadContact->salutation == $salutation)>{{ $salutation->label() }}</option>
                            @endforeach
                        </x-forms.select>
                    </div>

                    <div class="col-lg-4 col-md-6">
                        <x-forms.text :fieldLabel="__('app.name')" fieldName="client_name"
                            fieldId="client_name" fieldPlaceholder="" fieldRequired="true"
                            :fieldValue="$leadContact->client_name" />
                    </div>

                    <div class="col-lg-4 col-md-6">
                        <x-forms.email fieldId="client_email" :fieldLabel="__('app.email')"
                            fieldName="client_email" :fieldPlaceholder="__('placeholders.email')"
                            :fieldValue="$leadContact->client_email" :fieldHelp="__('modules.lead.leadEmailInfo')">
                        </x-forms.email>
                    </div>

                    {{-- Project Name: pick one, or "Add" a new name in the pop-up --}}
                    <div class="col-lg-4 col-md-6">
                        <x-forms.label class="my-3" fieldId="lead_project_id" fieldLabel="Project Name" fieldRequired="true">
                        </x-forms.label>
                        <x-forms.input-group>
                            <select class="form-control select-picker" name="lead_project_id" id="lead_project_id"
                                data-live-search="true">
                                <option value="">--</option>
                                @foreach ($leadProjects as $leadProject)
                                    <option value="{{ $leadProject->id }}" @selected($leadContact->lead_project_id == $leadProject->id)>{{ $leadProject->name }}</option>
                                @endforeach
                            </select>

                            <x-slot name="append">
                                <button type="button"
                                    class="btn btn-outline-secondary border-grey add-lead-project"
                                    data-toggle="tooltip" data-original-title="Add Project Name">
                                    @lang('app.add')</button>
                            </x-slot>
                        </x-forms.input-group>
                    </div>

                    @if ($viewLeadSourcesPermission != 'none')
                        <div class="col-lg-4 col-md-6">
                            <x-forms.label class="my-3" fieldId="source_id" :fieldLabel="__('modules.lead.leadSource')">
                            </x-forms.label>
                            <x-forms.input-group>
                                <select class="form-control select-picker" name="source_id" id="source_id"
                                    data-live-search="true">
                                    <option value="">--</option>
                                    @foreach ($sources as $source)
                                        <option @if ($leadContact->source_id == $source->id) selected @endif value="{{ $source->id }}">
                                            {{ $source->type }}</option>
                                    @endforeach
                                </select>

                                @if ($addLeadSourcesPermission == 'all' || $addLeadSourcesPermission == 'added')
                                    <x-slot name="append">
                                        <button type="button"
                                            class="btn btn-outline-secondary border-grey add-lead-source"
                                            data-toggle="tooltip" data-original-title="{{ __('app.add').' '.__('modules.lead.leadSource') }}">@lang('app.add')</button>
                                    </x-slot>
                                @endif
                            </x-forms.input-group>
                        </div>
                    @endif

                    @if ($addPermission == 'all')
                        <div class="col-lg-4 col-md-6">
                            <x-forms.select fieldId="added_by" :fieldLabel="__('app.added').' '.__('app.by')"
                                fieldName="added_by">
                                <option value="">--</option>
                                @foreach ($employees as $item)
                                    <x-user-option :user="$item" :selected="$leadContact->added_by == $item->id" />
                                @endforeach
                            </x-forms.select>
                        </div>
                    @endif

                    <div class="col-lg-4 col-md-6">
                        <x-forms.select fieldId="lead_owner" :fieldLabel="__('app.owner')" fieldName="lead_owner">
                            <option value="">--</option>
                            @foreach ($employees as $item)
                                <x-user-option :user="$item" :selected="($leadContact->lead_owner == $item->id)" />
                            @endforeach
                        </x-forms.select>
                    </div>

                </div>

                <div class="row p-20">
                    @if ($showDealSection)
                        <div class="col-lg-2 col-md-6">
                            <div class="form-group">
                                <div class="mt-2 d-flex">
                                    <x-forms.checkbox fieldId="create_deal"
                                                    :fieldLabel="$deal ? 'Edit Deal' : __('app.create') .' '. __('modules.deal.title')"
                                                    fieldName="create_deal" :checked="(bool) $deal"/>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="col-lg-4 col-md-6">
                        <div class="form-group">
                            <div class="mt-2 d-flex">
                                <x-forms.checkbox fieldId="create_client"
                                                :fieldLabel="__('modules.deal.createClient')"
                                                fieldName="create_client" :checked="isset($deal) && $deal->create_client == 1"/>
                            </div>
                        </div>
                    </div>

                    {{-- Assign Team: employees working this lead, picked by department and designation --}}
                    <div class="col-lg-3 col-md-6">
                        <div class="form-group">
                            <div class="mt-2 d-flex">
                                <x-forms.checkbox fieldId="assign_team" fieldLabel="Assign Team"
                                                fieldName="assign_team" :checked="count($teamMembers) > 0"/>
                            </div>
                        </div>
                    </div>
                </div>

                @include('lead-contact.ajax.assign-team')

                @if ($showDealSection)
                <div class="row p-20 {{ $deal ? '' : 'd-none' }}" id="add_deal">
                    <div class="col-lg-4 col-md-6">
                        <x-forms.text :fieldLabel="__('modules.deal.dealName')" fieldName="name"
                                      fieldId="name" :fieldPlaceholder="__('placeholders.name')" fieldRequired="true"
                                      :fieldValue="$deal->name ?? ''" :popover="__('modules.deal.dealnameInfo')"/>
                    </div>
                    <div class="col-lg-4">
                        <x-forms.select fieldId="pipelineData" :fieldLabel="__('modules.deal.pipeline')"
                                        fieldName="pipeline" fieldRequired="true"
                                        :popover="__('modules.lead.pipelineInfo')">
                            @foreach ($leadPipelines as $pipeline)
                                <option value="{{ $pipeline->id }}" @selected($deal && $deal->lead_pipeline_id == $pipeline->id)>{{ $pipeline->name }}</option>
                            @endforeach
                        </x-forms.select>
                    </div>
                    <div class="col-lg-4">
                        <x-forms.select fieldId="stages" :fieldLabel="__('modules.deal.stages')" fieldName="stage_id"
                                        fieldRequired="true">
                        </x-forms.select>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <x-forms.label class="my-3" fieldId="value" :fieldLabel="__('modules.deal.dealValue')"
                                       fieldRequired="true">
                        </x-forms.label>
                        <x-forms.input-group>
                            <x-slot name="prepend">
                                <span
                                    class="input-group-text f-14">{{company()->currency->currency_code }} ({{ company()->currency->currency_symbol }})</span>
                            </x-slot>
                            <input type="number" name="value" id="value" class="form-control height-35 f-14" value="{{ $deal->value ?? 0 }}"/>
                        </x-forms.input-group>
                    </div>
                    <div class="col-md-5 col-lg-4 dueDateBox mt-1">
                        <x-forms.datepicker fieldId="close_date" fieldRequired="true"
                                            :fieldLabel="__('modules.deal.closeDate')"
                                            fieldName="close_date" :fieldPlaceholder="__('placeholders.date')"
                                            :fieldValue="$dealCloseDate"/>
                    </div>
                    @if ($viewLeadCategoryPermission != 'none')
                        <div class="col-lg-4 col-md-6">
                            <x-forms.label class="my-3" fieldId="category_id" :fieldLabel="__('modules.deal.dealCategory')">
                            </x-forms.label>
                            <x-forms.input-group>
                                <select class="form-control select-picker" name="category_id" id="category_id"
                                    data-live-search="true">
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected($deal && $deal->category_id == $category->id)>{{ $category->category_name }}
                                        </option>
                                    @endforeach
                                </select>

                                @if ($addLeadCategoryPermission == 'all' || $addLeadCategoryPermission == 'added')
                                    <x-slot name="append">
                                        <button type="button"
                                            class="btn btn-outline-secondary border-grey add-lead-category"
                                            data-toggle="tooltip" data-original-title="{{ __('app.add').' '.__('modules.lead.leadCategory') }}">
                                            @lang('app.add')</button>
                                    </x-slot>
                                @endif
                            </x-forms.input-group>
                        </div>
                    @endif
                    @if ($viewLeadAgentPermission != 'none')
                        <div class="col-lg-4 col-md-6">
                            <x-forms.label class="mt-3" fieldId="deal_agent_id" :fieldLabel="__('modules.deal.dealAgent')">
                            </x-forms.label>
                            <x-forms.input-group>
                                <select class="form-control select-picker" name="agent_id" id="deal_agent_id"
                                        data-live-search="true">
                                    <option value="">--</option>
                                </select>

                                @if ($addLeadAgentPermission == 'all' || $addLeadAgentPermission == 'added')
                                    <x-slot name="append">
                                        <button type="button"
                                                class="btn btn-outline-secondary border-grey add-lead-agent"
                                                data-toggle="tooltip"
                                                data-original-title="{{ __('app.add').'  '.__('app.new').' '.__('modules.tickets.agents') }}">@lang('app.add')</button>
                                    </x-slot>
                                @endif
                            </x-forms.input-group>
                        </div>
                    @elseif(in_array(user()->id, $leadAgentArray))
                        <input type="hidden" value="{{ $deal?->leadAgent?->user_id ?? user()->id }}" name="agent_id">
                    @endif

                    @if(in_array('products', user_modules()) || in_array('purchase', user_modules()))
                        <div class="col-lg-4 mt-3">
                            <div class="form-group">
                                <x-forms.label fieldId="selectProduct" :fieldLabel="__('app.menu.products')">
                                </x-forms.label>
                                <x-forms.input-group>
                                    <select class="form-control select-picker" data-live-search="true" data-size="8"
                                            name="product_id[]" multiple id="add-products"
                                            title="{{ __('app.menu.selectProduct') }}">
                                        @foreach ($products as $item)
                                            <option data-content="{{ $item->name }}" value="{{ $item->id }}" @selected(in_array($item->id, $dealProductIds))>
                                                {{ $item->name }}</option>
                                        @endforeach
                                    </select>
                                </x-forms.input-group>
                            </div>
                        </div>
                    @endif

                    <div class="col-lg-4 col-md-6">
                        <x-forms.select fieldId="deal_watcher" :fieldLabel="__('app.dealWatcher')"
                                        fieldName="deal_watcher">
                            <option value="">--</option>
                            @foreach ($employees as $item)
                                <x-user-option :user="$item" :selected="$deal ? $deal->deal_watcher == $item->id : user()->id == $item->id"/>
                            @endforeach
                        </x-forms.select>
                    </div>
                </div>
                @endif

                <h4 class="mb-0 p-20 f-21 font-weight-normal  border-top-grey">
                    <a href="javascript:;" class="text-dark toggle-other-details"><i class="fa fa-chevron-up"></i>
                        @lang('modules.lead.companyDetails')</a>
                </h4>


                <div class="row p-20" id="other-details">

                    <div class="col-lg-3 col-md-6">
                        <x-forms.text :fieldLabel="__('modules.lead.companyName')" fieldName="company_name"
                            fieldId="company_name" :fieldPlaceholder="__('placeholders.company')"
                            :fieldValue="$leadContact->company_name" />
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <x-forms.text :fieldLabel="__('modules.lead.website')" fieldName="website" fieldId="website"
                            :fieldPlaceholder="__('placeholders.website')" :fieldValue="$leadContact->website" />
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <x-forms.tel fieldId="mobile" :fieldLabel="__('modules.lead.mobile')" fieldName="mobile"
                           :fieldPlaceholder="__('placeholders.mobile')" :fieldValue="$leadContact->mobile"></x-forms.tel>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <x-forms.text :fieldLabel="__('modules.client.officePhoneNumber')" fieldName="office"
                            fieldId="office" fieldPlaceholder="" :fieldValue="$leadContact->office" />
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <x-forms.select fieldId="country" :fieldLabel="__('app.country')" fieldName="country"
                            search="true">
                            <option value="">--</option>
                            @foreach ($countries as $item)
                                <option @if ($leadContact->country == $item->nicename) selected @endif data-tokens="{{ $item->iso3 }}"
                                    data-content="<span class='flag-icon flag-icon-{{ strtolower($item->iso) }} flag-icon-squared'></span> {{ $item->nicename }}"
                                    value="{{ $item->nicename }}">{{ $item->nicename }}</option>
                            @endforeach
                        </x-forms.select>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <x-forms.text :fieldLabel="__('modules.stripeCustomerAddress.state')" fieldName="state"
                            fieldId="state" :fieldPlaceholder="__('placeholders.state')" :fieldValue="$leadContact->state" />
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <x-forms.text :fieldLabel="__('modules.stripeCustomerAddress.city')" fieldName="city" :fieldValue="$leadContact->city"
                            fieldId="city" :fieldPlaceholder="__('placeholders.city')" />
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <x-forms.text :fieldLabel="__('modules.stripeCustomerAddress.postalCode')"
                            fieldName="postal_code" fieldId="postal_code" :fieldPlaceholder="__('placeholders.postalCode')"
                            :fieldValue="$leadContact->postal_code" />
                    </div>

                    <div class="col-md-12">
                        <div class="form-group my-3">
                            <x-forms.textarea class="mr-0 mr-lg-2 mr-md-2" :fieldLabel="__('app.address')"
                                fieldName="address" fieldId="address" :fieldPlaceholder="__('placeholders.address')"
                                :fieldValue="$leadContact->address">
                            </x-forms.textarea>
                        </div>
                    </div>

                </div>
                    <x-forms.custom-field :fields="$fields" :model="$leadContact"></x-forms.custom-field>
                <x-form-actions>
                    <x-forms.button-primary id="save-lead-form" class="mr-3" icon="check">@lang('app.save')
                    </x-forms.button-primary>
                    <x-forms.button-cancel :link="route('lead-contact.index')" class="border-0">@lang('app.cancel')
                    </x-forms.button-cancel>
                </x-form-actions>

            </div>
        </x-form>

    </div>
</div>


<script>
    $(document).ready(function() {

        $('.custom-date-picker').each(function(ind, el) {
            datepicker(el, {
                position: 'bl',
                ...datepickerConfig
            });
        });

        $('#save-lead-form').click(function() {
            const url = "{{ route('lead-contact.update', [$leadContact->id]) }}";
            $.easyAjax({
                url: url,
                container: '#save-lead-data-form',
                type: "POST",
                disableButton: true,
                blockUI: true,
                file: true,
                buttonSelector: "#save-lead-form",
                data: $('#save-lead-data-form').serialize(),
                success: function(response) {
                    window.location.href = response.redirectUrl;
                }
            });
        });

        $('body').off('click', '.add-lead-project').on('click', '.add-lead-project', function() {
            $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_LG, '{{ route('lead-projects.create') }}');
        });

        $('body').on('click', '.add-lead-source', function() {
            const url = '{{ route('lead-source-settings.create') }}';
            $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_LG, url);
        });

        $('body').on('click', '.add-lead-category', function() {
            var url = '{{ route('leadCategory.create') }}';
            $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_LG, url);
        });

        $('#create_task_category').click(function() {
            const url = "{{ route('taskCategory.create') }}";
            $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_LG, url);
        });

        $('#department-setting').click(function() {
            const url = "{{ route('departments.create') }}";
            $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_LG, url);
        });

        $('#client_view_task').change(function() {
            $('#clientNotification').toggleClass('d-none');
        });

        $('#set_time_estimate').change(function() {
            $('#set-time-estimate-fields').toggleClass('d-none');
        });

        $('.toggle-other-details').click(function() {
            $(this).find('svg').toggleClass('fa-chevron-down fa-chevron-up');
            $('#other-details').toggleClass('d-none');
        });

        $('#createTaskLabel').click(function() {
            const url = "{{ route('task-label.create') }}";
            $(MODAL_XL + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_XL, url);
        });

        $('#add-project').click(function() {
            $(MODAL_XL).modal('show');
            const url = "{{ route('projects.create') }}";
            $.easyAjax({
                url: url,
                blockUI: true,
                container: MODAL_XL,
                success: function(response) {
                    if (response.status == "success") {
                        $(MODAL_XL + ' .modal-body').html(response.html);
                        $(MODAL_XL + ' .modal-title').html(response.title);
                        init(MODAL_XL);
                    }
                }
            });
        });

        $('#add-employee').click(function() {
            $(MODAL_XL).modal('show');

            const url = "{{ route('employees.create') }}";

            $.easyAjax({
                url: url,
                blockUI: true,
                container: MODAL_XL,
                success: function(response) {
                    if (response.status == "success") {
                        $(MODAL_XL + ' .modal-body').html(response.html);
                        $(MODAL_XL + ' .modal-title').html(response.title);
                        init(MODAL_XL);
                    }
                }
            });
        });

        <x-forms.custom-field-filejs/>

        init(RIGHT_MODAL);
    });

    function checkboxChange(parentClass, id){
        let checkedData = '';
        $('.'+parentClass).find("input[type= 'checkbox']:checked").each(function () {
            checkedData = (checkedData !== '') ? checkedData+', '+$(this).val() : $(this).val();
        });
        $('#'+id).val(checkedData);
    }
</script>

<script>
    // Deal section, as on the create form; an existing deal's stage and agent are selected once loaded
    $(document).ready(function () {
        const dealId = @json($deal?->id);
        const dealStageId = @json($deal?->pipeline_stage_id);

        $('#create_deal').change(function () {
            $('#add_deal').toggleClass('d-none', !this.checked);
        });

        $('#close_date').each(function (ind, el) {
            datepicker(el, {
                position: 'bl',
                ...datepickerConfig
            });
        });

        function getAgents(categoryId) {
            if (!categoryId || !$('#deal_agent_id').length) return;
            let url = "{{ route('deals.get_agents', ':id') }}".replace(':id', categoryId) + (dealId ? '?dealId=' + dealId : '');
            $.easyAjax({
                url: url,
                type: "GET",
                success: function (response) {
                    $('#deal_agent_id').html($.isArray(response.data)
                        ? '<option value="">--</option>' + response.data.join('')
                        : response.data);
                    $('#deal_agent_id').selectpicker('refresh');
                }
            });
        }

        function getStages(pipelineId, selectedId) {
            if (!pipelineId) return;
            $.easyAjax({
                url: "{{ route('deals.get-stage', ':id') }}".replace(':id', pipelineId),
                type: "GET",
                success: function (response) {
                    if (response.status == 'success') {
                        $('#stages').html(response.data.map(value =>
                            `<option data-content="<i class='fa fa-circle' style='color: ${value.label_color}'></i> ${value.name} " value="${value.id}" ${value.id == selectedId ? 'selected' : ''}> ${value.name}</option>`
                        ).join(''));
                        $('#stages').selectpicker('refresh');
                    }
                }
            });
        }

        getStages($('#pipelineData').val(), dealStageId);
        getAgents($('#category_id').val());

        $('#pipelineData').on('change', function () {
            getStages($(this).val());
        });

        $('#category_id').change(function () {
            getAgents($(this).val());
        });

        $('body').off('click', '.add-lead-agent').on('click', '.add-lead-agent', function () {
            var url = "{{ route('lead-agent-settings.create').'?categoryId='}}" + $('#category_id').val();
            $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_LG, url);
        });
    });
</script>
