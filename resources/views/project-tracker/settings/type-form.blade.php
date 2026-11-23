{{-- Add / edit a project type (Settings -> Project Tracker): what its work is counted in,
     its steps in order (the last one is "done"), its grace days and the daily rates. --}}
@php
    $editing = $type->exists;
    $stages = $type->stages;
    $rates = $type->rates;
    $usedStageIds = $usedStageIds ?? [];
@endphp
<div class="row">
    <div class="col-sm-12">
        <x-form id="type-form" method="POST">
            @if ($editing)<input type="hidden" name="_method" value="PUT">@endif
            <div class="add-client bg-white rounded rc-form">
                <h4 class="mb-0 p-20 f-21 font-weight-normal border-bottom-grey">{{ $editing ? 'Edit Project Type' : 'Add Project Type' }}</h4>
                <div class="p-20">
                    <div class="row">
                        <div class="col-md-6">
                            <x-forms.text fieldId="name" fieldLabel="Name" fieldName="name" :fieldRequired="true" :fieldValue="$type->name" fieldPlaceholder="e.g. GIS, Steel detailing" />
                        </div>
                        <div class="col-md-6">
                            <x-forms.select fieldId="team_id" fieldLabel="Division" fieldName="team_id" search="true">
                                <option value="">Any Division</option>
                                @foreach ($divisions as $division)
                                    <option value="{{ $division->id }}" @selected((int) $type->team_id === (int) $division->id)>{{ $division->team_name }}</option>
                                @endforeach
                            </x-forms.select>
                        </div>
                        <div class="col-md-5">
                            <x-forms.select fieldId="unit_kind" fieldLabel="The work is counted in" fieldName="unit_kind" :fieldRequired="true">
                                @foreach (\App\Models\ProjectType::UNIT_KINDS as $key => $label)
                                    <option value="{{ $key }}" @selected($type->unit_kind === $key)>{{ $label }}</option>
                                @endforeach
                            </x-forms.select>
                        </div>
                        <div class="col-md-3 pt-unit-label">
                            <x-forms.text fieldId="unit_label" fieldLabel="Called" fieldName="unit_label" :fieldValue="$type->unit_kind === 'hours' ? '' : $type->unit_label"
                                fieldPlaceholder="features, sheets" />
                        </div>
                        <div class="col-md-2 col-6">
                            <x-forms.number fieldId="grace_days" fieldLabel="Grace days" fieldName="grace_days" :fieldRequired="true" :fieldValue="$type->grace_days ?? 0" />
                        </div>
                        <div class="col-md-2 col-6">
                            <div class="form-group">
                                <x-forms.label fieldId="color" fieldLabel="Colour" />
                                <input type="color" class="form-control pt-color" name="color" id="color" value="{{ $type->color ?: '#cb6b4f' }}">
                            </div>
                        </div>
                        <div class="col-12">
                            <x-forms.textarea fieldId="description" fieldLabel="Notes" fieldName="description" :fieldValue="$type->description"
                                fieldPlaceholder="What this kind of project delivers, the files it works on ..." />
                        </div>
                    </div>

                    {{-- The steps --}}
                    <div class="pt-block">
                        <div class="pt-block-head"><b>Steps</b><span>In order. A task moves through them; the last one means the work is done.</span></div>
                        <div id="stage-rows">
                            @foreach ($stages as $i => $stage)
                                @php $used = $stage->id && in_array($stage->id, $usedStageIds); @endphp
                                <div class="pt-row stage-row">
                                    <span class="pt-handle"><i class="fa fa-grip-vertical"></i></span>
                                    <input type="hidden" name="stage_id[]" value="{{ $stage->id }}">
                                    <input type="color" class="form-control pt-color pt-color--sm" name="stage_color[]" value="{{ $stage->colour($i) }}" title="Colour">
                                    <input type="text" class="form-control f-14" name="stage_name[]" value="{{ $stage->name }}" placeholder="Step name">
                                    <span class="pt-done">{{ $loop->last ? 'done' : '' }}</span>
                                    <button type="button" class="btn pt-icon stage-up" title="Move up"><i class="fa fa-arrow-up"></i></button>
                                    <button type="button" class="btn pt-icon stage-remove" title="{{ $used ? 'Tasks are at this step' : 'Remove' }}" @disabled($used)><i class="fa fa-times"></i></button>
                                </div>
                            @endforeach
                        </div>
                        <a href="javascript:;" id="stage-add" class="f-13"><i class="fa fa-plus mr-1"></i>Add a step</a>
                    </div>

                    {{-- Daily rates --}}
                    <div class="pt-block">
                        <div class="pt-block-head"><b>Daily rates</b><span>How much one person of a designation does in a working day — used to estimate how long the work takes.</span></div>
                        <div id="rate-rows">
                            @foreach ($rates->isEmpty() ? collect([new \App\Models\ProjectTypeRate()]) : $rates as $rate)
                                <div class="pt-row rate-row">
                                    <select name="rate_designation[]" class="form-control f-14">
                                        <option value="">Designation</option>
                                        @foreach ($designations as $designation)
                                            <option value="{{ $designation->id }}" @selected((int) $rate->designation_id === (int) $designation->id)>{{ $designation->name }}</option>
                                        @endforeach
                                    </select>
                                    <select name="rate_stage[]" class="form-control f-14">
                                        <option value="">Whole job</option>
                                        @foreach ($stages->filter(fn ($s) => $s->id) as $stage)
                                            <option value="{{ $stage->id }}" @selected((int) $rate->stage_id === (int) $stage->id)>{{ $stage->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="input-group pt-units">
                                        <input type="number" step="0.01" min="0" class="form-control f-14" name="rate_units[]" value="{{ $rate->units_per_day }}" placeholder="0">
                                        <div class="input-group-append"><span class="input-group-text f-13 unit-text">{{ $type->unitText() }}/day</span></div>
                                    </div>
                                    <button type="button" class="btn pt-icon rate-remove" title="Remove"><i class="fa fa-times"></i></button>
                                </div>
                            @endforeach
                        </div>
                        <a href="javascript:;" id="rate-add" class="f-13"><i class="fa fa-plus mr-1"></i>Add a rate</a>
                        @unless ($editing)<p class="rc-help mb-0">Rates for one step can be added once the type is saved.</p>@endunless
                    </div>

                    {{-- KPI weights: how much each measure counts in the score of people on this type's work --}}
                    @php $kpiWeights = \App\Services\Tracker\EmployeeKpi::weightsFor($type->exists ? $type : null); @endphp
                    <div class="pt-block">
                        <div class="pt-block-head"><b>KPI weights</b><span>How much each measure counts in the KPI score of the people whose hours go mostly to this type. 0 leaves a measure out; they are shares, so they need not add up to 100.</span></div>
                        <div class="pt-kpi">
                            @foreach (\App\Services\Tracker\EmployeeKpi::METRICS as $key => [$label, $hint])
                                <label class="pt-kpi-item" title="{{ $hint }}">
                                    <span>{{ $label }}</span>
                                    <div class="input-group">
                                        <input type="number" min="0" max="100" step="5" class="form-control f-14 kpi-weight" name="kpi[{{ $key }}]" value="{{ (int) ($kpiWeights[$key] ?? 0) }}">
                                        <div class="input-group-append"><span class="input-group-text f-13 kpi-share">%</span></div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        <p class="rc-help mb-0" id="kpi-total"></p>
                    </div>

                    <div class="mt-3">
                        <x-forms.checkbox fieldId="active" fieldName="active" fieldValue="1" :checked="$type->active ?? true" fieldLabel="In use (new projects can be of this type)" />
                    </div>
                </div>

                <x-form-actions>
                    <x-forms.button-primary id="save-type" class="mr-3" icon="check">@lang('app.save')</x-forms.button-primary>
                    <x-forms.button-secondary id="cancel-type">@lang('app.cancel')</x-forms.button-secondary>
                    @if ($editing && !$type->projects()->exists())
                        <a href="javascript:;" id="delete-type" class="btn btn-link text-danger ml-auto f-14">Delete this type</a>
                    @endif
                </x-form-actions>
            </div>
        </x-form>
    </div>
</div>

<style>
    .pt-color { height: 40px !important; padding: 4px !important; cursor: pointer; background: #fff; }
    .pt-color::-webkit-color-swatch-wrapper { padding: 0; }
    .pt-color::-webkit-color-swatch { border: 0; border-radius: 6px; }
    .pt-color::-moz-color-swatch { border: 0; border-radius: 6px; }
    .pt-color--sm { flex: 0 0 40px; width: 40px; }
    .pt-block { margin-top: 18px; padding-top: 16px; border-top: 1px solid #eaecf0; }
    .pt-block-head { margin-bottom: 10px; }
    .pt-block-head b { display: block; font-size: 14.5px; color: #1d2939; }
    .pt-block-head span { font-size: 12.5px; color: #667085; }
    .pt-row { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
    .pt-row .form-control { height: 40px; }
    .pt-handle { color: #d0d5dd; width: 14px; text-align: center; }
    .pt-done { flex: 0 0 38px; font-size: 11.5px; font-weight: 600; color: #12b76a; text-transform: uppercase; }
    .pt-icon { flex: 0 0 36px; width: 36px; height: 36px; padding: 0; border: 1px solid #eaecf0; border-radius: 8px; background: #fff; color: #667085; }
    .pt-icon:hover:not(:disabled) { background: #f9fafb; }
    .pt-icon:disabled { opacity: .35; cursor: not-allowed; }
    .rate-row select:first-child { flex: 2; }
    .rate-row select:nth-child(2) { flex: 1.3; }
    .pt-units { flex: 1.4; flex-wrap: nowrap; }
    #stage-add, #rate-add { display: inline-block; margin-top: 4px; }
    .pt-kpi { display: grid; grid-template-columns: repeat(auto-fit, minmax(118px, 1fr)); gap: 10px; }
    .pt-kpi-item { margin: 0; display: flex; flex-direction: column; gap: 4px; }
    .pt-kpi-item span { font-size: 12.5px; color: #475467; font-weight: 500; }
    .pt-kpi-item .form-control { height: 40px; }
</style>

@include('recruitment.partials.submit-js')

<script>
    $(function () {
        // KPI weights: show each as its share of the score
        function kpiShares() {
            var inputs = $('.kpi-weight'), total = 0;
            inputs.each(function () { total += Math.max(0, parseFloat(this.value) || 0); });
            inputs.each(function () {
                var v = Math.max(0, parseFloat(this.value) || 0);
                $(this).closest('.input-group').find('.kpi-share').text(total ? Math.round(v / total * 100) + '%' : '%');
            });
            $('#kpi-total').text(total ? '' : 'Give at least one measure a weight.').toggleClass('text-danger', !total);
        }
        $(document).off('input.kpi').on('input.kpi', '.kpi-weight', kpiShares);
        kpiShares();

        // the unit's name only matters for a count
        function syncUnit() {
            var kind = $('#unit_kind').val();
            $('.pt-unit-label').toggle(kind === 'count');
            var label = kind === 'hours' ? 'hours' : (kind === 'km' ? 'km' : (kind === 'sqkm' ? 'sq km' : ($('#unit_label').val() || 'units')));
            $('.unit-text').text(label + '/day');
        }
        $('#unit_kind').on('change', syncUnit);
        $('#unit_label').on('input', syncUnit);
        syncUnit();

        // steps: add, move up, remove; the last one is marked "done"
        function markDone() {
            $('#stage-rows .pt-done').text('');
            $('#stage-rows .stage-row:last .pt-done').text('done');
        }
        var stageTemplate = $('#stage-rows .stage-row').first().clone();
        stageTemplate.find('input[type=text]').val('');
        stageTemplate.find('input[type=hidden]').val('');
        stageTemplate.find('.stage-remove').prop('disabled', false).attr('title', 'Remove');
        $('#stage-add').on('click', function () {
            var row = stageTemplate.clone();
            row.find('input[type=color]').val('#7a5af8');
            $('#stage-rows').append(row);
            markDone();
            row.find('input[type=text]').trigger('focus');
        });
        $('#stage-rows').on('click', '.stage-up', function () {
            var row = $(this).closest('.stage-row');
            row.prev('.stage-row').before(row);
            markDone();
        });
        $('#stage-rows').on('click', '.stage-remove', function () {
            if ($('#stage-rows .stage-row').length > 2) { $(this).closest('.stage-row').remove(); markDone(); }
        });

        // rates
        var rateTemplate = $('#rate-rows .rate-row').first().clone();
        rateTemplate.find('select').val('');
        rateTemplate.find('input').val('');
        $('#rate-add').on('click', function () { $('#rate-rows').append(rateTemplate.clone()); });
        $('#rate-rows').on('click', '.rate-remove', function () {
            var row = $(this).closest('.rate-row');
            if ($('#rate-rows .rate-row').length > 1) { row.remove(); } else { row.find('select').val(''); row.find('input').val(''); }
        });

        $('#save-type').on('click', function () {
            recruitSubmit('type-form', '{{ $editing ? route('project-types.update', $type->id) : route('project-types.store') }}', 'save-type');
        });
        $('#cancel-type').on('click', function () { recruitClose('{{ route('project-tracker-settings.index') }}'); });

        @if ($editing)
        $('#delete-type').on('click', function () {
            Swal.fire({
                title: 'Delete this project type?', text: 'Its steps and rates go with it.', icon: 'warning', showCancelButton: true,
                confirmButtonText: 'Delete', cancelButtonText: 'Cancel', customClass: {confirmButton: 'btn btn-primary mr-3', cancelButton: 'btn btn-secondary'}, buttonsStyling: false
            }).then(function (r) {
                if (!r.isConfirmed) { return; }
                $.easyAjax({
                    url: '{{ route('project-types.destroy', $type->id) }}', type: 'POST', blockUI: true, data: {_token: '{{ csrf_token() }}', _method: 'DELETE'},
                    success: function (response) { if (response.status === 'success') { PageSwap.reload(); } }
                });
            });
        });
        @endif

        init(RIGHT_MODAL);
    });
</script>
