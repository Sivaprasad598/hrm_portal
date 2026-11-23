{{-- Add / edit an Epic, a Work item or a Task in the tracker (an ordinary task of the project). --}}
@php
    $editing = $item->exists;
    $type = $project->projectType;
    $fmt = company()->date_format;
    $level = $item->work_type;
    $assigned = $editing ? $item->users->pluck('id')->map(fn ($id) => (int) $id)->all() : [];
    $hours = (int) $item->estimate_hours + (int) $item->estimate_minutes / 60;
    $num = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
@endphp
<div class="row">
    <div class="col-sm-12">
        <x-form id="item-form" method="POST">
            @if ($editing)<input type="hidden" name="_method" value="PUT">@endif
            <div class="add-client bg-white rounded rc-form">
                <h4 class="mb-0 p-20 f-21 font-weight-normal border-bottom-grey">{{ $editing ? 'Edit' : 'Add' }}</h4>
                <div class="p-20">
                    {{-- the level --}}
                    <div class="it-levels" role="radiogroup" aria-label="Level">
                        @foreach (\App\Services\Tracker\ProjectTracker::LEVELS as $key => [$label, $icon, $colour])
                            <label style="--lc: {{ $colour }}"><input type="radio" name="work_type" value="{{ $key }}" @checked($level === $key)><i class="fa {{ $icon }} mr-1"></i>{{ $label }}</label>
                        @endforeach
                    </div>
                    <p class="rc-help it-hint"></p>

                    <div class="row">
                        <div class="col-md-8">
                            <x-forms.text fieldId="heading" fieldLabel="Name" fieldName="heading" :fieldRequired="true" :fieldValue="$item->heading"
                                fieldPlaceholder="e.g. Zone A — chainage 0–600 m" />
                        </div>
                        <div class="col-md-4 it-parent">
                            <x-forms.select fieldId="parent_id" fieldLabel="Under" fieldName="parent_id" search="true">
                                <option value="">— at the top —</option>
                                @foreach ($parents as $p)
                                    <option value="{{ $p->id }}" data-level="{{ $p->work_type }}" @selected((int) $item->parent_id === (int) $p->id)>{{ $p->work_type === 'epic' ? 'Epic: ' : 'Item: ' }}{{ $p->heading }}</option>
                                @endforeach
                            </x-forms.select>
                        </div>

                        <div class="col-md-4 it-work">
                            <x-forms.select fieldId="project_stage_id" fieldLabel="Step" fieldName="project_stage_id">
                                @foreach ($type?->stages ?? [] as $stage)
                                    <option value="{{ $stage->id }}" @selected((int) $item->project_stage_id === (int) $stage->id)>{{ $stage->name }}</option>
                                @endforeach
                            </x-forms.select>
                        </div>
                        @if ($type && $type->countsUnits())
                            <div class="col-md-4 col-6 it-work">
                                <div class="form-group">
                                    <x-forms.label fieldId="planned_units" fieldLabel="Planned" />
                                    <div class="input-group">
                                        <input type="number" step="any" min="0" class="form-control f-14" id="planned_units" name="planned_units" value="{{ $num($item->planned_units) }}" placeholder="0">
                                        <div class="input-group-append"><span class="input-group-text f-13">{{ $type->unitText() }}</span></div>
                                    </div>
                                </div>
                            </div>
                            @if ($editing)
                                <div class="col-md-4 col-6 it-work">
                                    <x-forms.number fieldId="done_units" fieldLabel="Done so far" fieldName="done_units" :fieldValue="$num($item->done_units)" />
                                </div>
                            @endif
                        @endif
                        <div class="col-md-4 col-6 it-work">
                            <div class="form-group">
                                <x-forms.label fieldId="estimate_hours" fieldLabel="Estimated hours" />
                                <input type="number" step="0.5" min="0" class="form-control f-14" id="estimate_hours" name="estimate_hours" value="{{ $hours ? $num($hours) : '' }}" placeholder="0">
                            </div>
                        </div>

                        <div class="col-md-4 col-6">
                            <x-forms.datepicker fieldId="it_start" fieldLabel="Start" fieldName="start_date" :fieldRequired="true" :fieldValue="($item->start_date ?? now())->format($fmt)" />
                        </div>
                        <div class="col-md-4 col-6">
                            <x-forms.datepicker fieldId="it_due" fieldLabel="Due" fieldName="due_date" :fieldRequired="true" :fieldValue="($item->due_date ?? now()->addWeek())->format($fmt)" />
                        </div>

                        <div class="col-12 it-work">
                            <x-forms.select fieldId="it_users" fieldLabel="Assigned to" fieldName="user_id[]" :multiple="true" search="true">
                                @foreach ($members as $member)
                                    <option value="{{ $member->id }}" @selected(in_array((int) $member->id, $assigned, true))
                                        data-content="<span class='d-inline-flex align-items-center'><img src='{{ $member->image_url }}' style='width:20px;height:20px;border-radius:50%;margin-right:6px'>{{ e($member->name) }}</span>">{{ $member->name }}</option>
                                @endforeach
                            </x-forms.select>
                            @if ($members->isEmpty())<small class="text-warning">Add people to the project (Members tab) to assign work.</small>@endif
                        </div>
                    </div>
                </div>
                <x-form-actions>
                    <x-forms.button-primary id="save-item" class="mr-3" icon="check">@lang('app.save')</x-forms.button-primary>
                    <x-forms.button-secondary id="cancel-item">@lang('app.cancel')</x-forms.button-secondary>
                </x-form-actions>
            </div>
        </x-form>
    </div>
</div>

<style>
    .it-levels { display: inline-flex; padding: 3px; background: #f2f4f7; border-radius: 9px; margin-bottom: 6px; }
    .it-levels label { margin: 0 !important; padding: 7px 14px; border-radius: 7px; font-size: 13.5px; font-weight: 500; color: #475467; cursor: pointer; display: inline-flex !important; align-items: center; }
    .it-levels input { display: none; }
    .it-levels label:has(input:checked) { background: #fff; color: var(--lc); box-shadow: 0 1px 3px rgba(16, 24, 40, .12); }
</style>

@include('recruitment.partials.submit-js')

<script>
    $(function () {
        datepicker('#it_start', {position: 'bl', ...datepickerConfig});
        datepicker('#it_due', {position: 'bl', ...datepickerConfig});

        var hints = {
            epic: 'A part of the project — a zone, a phase, a module. It groups Work items.',
            item: 'A piece of work with a quantity and an owner (e.g. 120 masts in Zone A). It goes through the steps.',
            task: 'A step of a Work item done by someone (e.g. QC of Zone A).'
        };
        function sync() {
            var level = $('input[name=work_type]:checked').val();
            $('.it-hint').text(hints[level]);
            $('.it-work').toggle(level !== 'epic');
            $('.it-parent').toggle(level !== 'epic');
            // a Work item sits under an Epic; a Task under an Epic or a Work item
            $('#parent_id option[data-level]').each(function () {
                this.disabled = level === 'item' && this.dataset.level !== 'epic';
            });
            if ($('#parent_id option:selected').prop('disabled')) { $('#parent_id').val(''); }
            if ($.fn.selectpicker) { $('#parent_id').selectpicker('refresh'); }
        }
        $('input[name=work_type]').on('change', sync);
        sync();

        $('#save-item').on('click', function () {
            recruitSubmit('item-form', '{{ $editing ? route('project-tracker.items.update', [$project->id, $item->id]) : route('project-tracker.items.store', $project->id) }}', 'save-item');
        });
        $('#cancel-item').on('click', function () { recruitClose('{{ route('projects.show', $project->id) }}?tab=tracker'); });
        init(RIGHT_MODAL);
    });
</script>
