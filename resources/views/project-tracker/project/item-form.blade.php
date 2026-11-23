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
                                    <option value="{{ $stage->id }}" data-sort="{{ $stage->sort }}" @selected((int) $item->project_stage_id === (int) $stage->id)>{{ $stage->name }}</option>
                                @endforeach
                            </x-forms.select>
                        </div>
                        {{-- sent back to an earlier step: say why (shown on the item's history, counts in Quality) --}}
                        <div class="col-12 it-rework" style="display: none">
                            <x-forms.textarea fieldId="rework_reason" fieldLabel="What needs fixing" fieldName="rework_reason" :fieldRequired="true"
                                fieldPlaceholder="e.g. 14 poles missing attributes in chainage 200–400" />
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
                            {{-- the booking check: how free they are between the dates --}}
                            <div id="it-check" class="it-check" style="display: none"></div>
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
    .it-check { margin-top: 10px; padding: 10px 12px; border-radius: 10px; background: #f9fafb; border: 1px solid #eaecf0; font-size: 13px; }
    .it-check-row { display: flex; justify-content: space-between; gap: 10px; padding: 4px 0; }
    .it-check-row b { font-weight: 600; }
    .it-check-ok { color: #067647; } .it-check-tight { color: #b54708; } .it-check-no { color: #b42318; }
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

        // the booking check: each chosen person's free hours between the dates, against their share
        var checkTimer;
        function checkBooking() {
            var users = $('#it_users').val() || [];
            var box = $('#it-check');
            if (!users.length || $('input[name=work_type]:checked').val() === 'epic') { box.hide(); return; }
            clearTimeout(checkTimer);
            checkTimer = setTimeout(function () {
                $.get('{{ route('project-tracker.check') }}', {users: users, from: $('#it_start').val(), to: $('#it_due').val(),
                    hours: $('#estimate_hours').val() || 0, task: '{{ $editing ? $item->id : '' }}'}, function (r) {
                    if (!r.people || !r.people.length) { box.hide(); return; }
                    var rows = r.people.map(function (p) {
                        var tone = p.free <= 0 ? 'it-check-no' : (p.need > p.free ? 'it-check-no' : (p.need > p.free * 0.8 ? 'it-check-tight' : 'it-check-ok'));
                        var note = p.need > p.free ? ' — short by ' + Math.round((p.need - p.free) * 10) / 10 + ' h' : '';
                        return '<div class="it-check-row"><span>' + $('<span>').text(p.name).html() + '</span><span class="' + tone + '"><b>' + p.free + ' h free</b> of ' + p.capacity + ' h'
                            + (p.need ? ' · needs ' + p.need + ' h' : '') + note + '</span></div>';
                    }).join('');
                    box.html('<div class="text-dark-grey mb-1"><i class="fa fa-user-clock mr-1"></i>Free between the dates (their other work counted)</div>' + rows).show();
                });
            }, 300);
        }
        $('#it_users, #estimate_hours').on('change input', checkBooking);
        $('#it_start, #it_due').on('change blur', checkBooking);
        checkBooking();

        // going back a step needs a reason
        var stageWas = {{ $editing && $item->project_stage_id ? (int) ($type?->stages->firstWhere('id', $item->project_stage_id)?->sort ?? -1) : -1 }};
        function syncRework() {
            var sort = parseInt($('#project_stage_id option:selected').data('sort'), 10);
            $('.it-rework').toggle(stageWas >= 0 && !isNaN(sort) && sort < stageWas && $('input[name=work_type]:checked').val() !== 'epic');
        }
        $('#project_stage_id').on('change', syncRework);
        $('input[name=work_type]').on('change', syncRework);
        syncRework();

        $('#save-item').on('click', function () {
            recruitSubmit('item-form', '{{ $editing ? route('project-tracker.items.update', [$project->id, $item->id]) : route('project-tracker.items.store', $project->id) }}', 'save-item');
        });
        $('#cancel-item').on('click', function () { recruitClose('{{ route('projects.show', $project->id) }}?tab=tracker'); });
        init(RIGHT_MODAL);
    });
</script>
