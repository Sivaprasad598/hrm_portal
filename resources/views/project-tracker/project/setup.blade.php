{{-- A project's tracker setup: its type, the planned quantity and its grace days. --}}
@php $type = $project->projectType; @endphp
<div class="row">
    <div class="col-sm-12">
        <x-form id="setup-form" method="POST">
            <div class="add-client bg-white rounded rc-form">
                <h4 class="mb-0 p-20 f-21 font-weight-normal border-bottom-grey">Tracker setup</h4>
                <div class="p-20">
                    <div class="rc-form-intro"><i class="fa fa-project-diagram mr-1 text-lightest"></i><strong>{{ $project->project_name }}</strong>
                        @if ($project->deadline) · deadline {{ $project->deadline->format(company()->date_format) }}@endif</div>

                    <label class="f-13 text-dark-grey mb-2 d-block">Project type <sup class="text-danger">*</sup></label>
                    <div class="st-types">
                        @foreach ($types as $option)
                            <label class="st-type" style="--tc: {{ $option->color ?: '#cb6b4f' }}">
                                <input type="radio" name="project_type_id" value="{{ $option->id }}" data-unit="{{ $option->unitText() }}" data-counts="{{ $option->countsUnits() ? 1 : 0 }}"
                                       data-grace="{{ $option->grace_days }}" @checked((int) $project->project_type_id === (int) $option->id)>
                                <span class="st-type-body">
                                    <b>{{ $option->name }}</b>
                                    <small>{{ $option->division?->team_name ?? 'Any Division' }} · {{ $option->unitText() }}</small>
                                    <span class="st-steps">{{ $option->stages->pluck('name')->implode(' → ') }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @if ($types->isEmpty())
                        <p class="text-warning">No project types yet — add them in Settings → Project Tracker.</p>
                    @endif

                    <div class="row mt-2">
                        <div class="col-md-6 st-qty">
                            <div class="form-group">
                                <x-forms.label fieldId="planned_units" fieldLabel="Planned quantity" />
                                <div class="input-group">
                                    <input type="number" step="any" min="0" class="form-control f-14" id="planned_units" name="planned_units" value="{{ $project->planned_units !== null ? rtrim(rtrim(number_format($project->planned_units, 2, '.', ''), '0'), '.') : '' }}" placeholder="e.g. 1200">
                                    <div class="input-group-append"><span class="input-group-text f-13" id="unit-text">{{ $type?->unitText() ?? 'units' }}</span></div>
                                </div>
                                <small class="rc-help">The whole job. Leave empty to add up what the Work items plan.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <x-forms.label fieldId="grace_days" fieldLabel="Grace days after the deadline" />
                                <input type="number" min="0" max="365" class="form-control f-14" id="grace_days" name="grace_days" value="{{ $project->grace_days }}" placeholder="{{ $type?->grace_days ?? 0 }} (the type's)">
                                <small class="rc-help">Empty: the type's grace days.</small>
                            </div>
                        </div>
                    </div>

                    @if ($type)
                        <p class="rc-help mb-0"><i class="fa fa-info-circle mr-1"></i>Changing the type puts the work back at the new type's first step.</p>
                    @endif
                </div>
                <x-form-actions>
                    <x-forms.button-primary id="save-setup" class="mr-3" icon="check">@lang('app.save')</x-forms.button-primary>
                    <x-forms.button-secondary id="cancel-setup">@lang('app.cancel')</x-forms.button-secondary>
                </x-form-actions>
            </div>
        </x-form>
    </div>
</div>

<style>
    .st-types { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 10px; }
    .st-type { margin: 0 !important; display: flex !important; gap: 10px; padding: 12px 14px; border: 1.5px solid #eaecf0; border-left: 4px solid var(--tc); border-radius: 10px; cursor: pointer; }
    .st-type:has(input:checked) { border-color: var(--tc); background: color-mix(in srgb, var(--tc) 6%, #fff); }
    .st-type input { margin-top: 3px; accent-color: var(--tc); }
    .st-type-body { display: flex; flex-direction: column; min-width: 0; }
    .st-type-body b { font-size: 14px; color: #101828; }
    .st-type-body small { font-size: 12px; color: #667085; }
    .st-steps { font-size: 11.5px; color: #98a2b3; margin-top: 4px; }
</style>

@include('recruitment.partials.submit-js')

<script>
    $(function () {
        function sync() {
            var picked = $('input[name=project_type_id]:checked');
            $('#unit-text').text(picked.data('unit') || 'units');
            $('.st-qty').toggle(!picked.length || +picked.data('counts') === 1);
            $('#grace_days').attr('placeholder', (picked.data('grace') ?? 0) + " (the type's)");
        }
        $('input[name=project_type_id]').on('change', sync);
        sync();
        $('#save-setup').on('click', function () { recruitSubmit('setup-form', '{{ route('project-tracker.setup', $project->id) }}', 'save-setup'); });
        $('#cancel-setup').on('click', function () { recruitClose('{{ route('projects.show', $project->id) }}?tab=tracker'); });
        init(RIGHT_MODAL);
    });
</script>
