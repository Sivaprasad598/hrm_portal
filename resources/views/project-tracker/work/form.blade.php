{{-- My Work -> Update (TrackerWorkController::edit): hours on a day, units done so far and the step,
     saved together; the item's recent history underneath. --}}
@php
    $fmt = company()->date_format;
    $num = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    $estimate = (int) $task->estimate_hours + (int) $task->estimate_minutes / 60;
    $current = $task->project_stage_id;
    $currentSort = $stages->firstWhere('id', $current)?->sort;
@endphp
<div class="row">
    <div class="col-sm-12">
        <x-form id="work-form" method="POST">
            <div class="add-client bg-white rounded rc-form">
                <h4 class="mb-0 p-20 f-21 font-weight-normal border-bottom-grey">Update</h4>
                <div class="p-20">
                    <div class="rc-form-intro">
                        <b>{{ $task->heading }}</b> · {{ $task->project?->project_name }}
                        <div class="text-dark-grey f-12 mt-1">
                            {{ $num($spent) }} of {{ $estimate > 0 ? $num($estimate) : '—' }} h spent
                            @if ($task->due_date) · due {{ $task->due_date->format($fmt) }} @endif
                            · you have logged {{ rtrim(rtrim(number_format((float) $loggedToday, 1), "0"), ".") ?: 0 }} h today
                        </div>
                    </div>

                    @if ($blocker->isNotEmpty())
                        <p class="wf-warn"><i class="fa fa-hand-paper mr-1"></i>Waiting on {{ $blocker->pluck('name')->filter()->implode(', ') }}: {{ $blocker->first()->need }}</p>
                    @endif

                    {{-- Hours --}}
                    <div class="wf-block">
                        <div class="wf-head"><b><i class="fa fa-clock mr-1"></i>Hours spent</b><span>Goes into your timesheet</span></div>
                        <div class="row">
                            <div class="col-md-4 col-12">
                                <x-forms.datepicker fieldId="wf_date" fieldLabel="Day" fieldName="date" :fieldRequired="true" :fieldValue="now(company()->timezone)->format($fmt)" />
                            </div>
                            <div class="col-md-4 col-6">
                                <x-forms.label fieldId="wf_hours" fieldLabel="Hours" />
                                <div class="wf-quick">
                                    <input type="number" min="0" max="16" class="form-control f-14" id="wf_hours" name="hours" placeholder="0">
                                </div>
                            </div>
                            <div class="col-md-4 col-6">
                                <x-forms.label fieldId="wf_minutes" fieldLabel="Minutes" />
                                <select class="form-control f-14" id="wf_minutes" name="minutes">
                                    @foreach ([0, 15, 30, 45] as $m)<option value="{{ $m }}">{{ $m }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                        <div class="wf-chips" aria-label="Quick hours">
                            @foreach ([1, 2, 4, 8] as $h)<button type="button" class="wf-chip" data-hours="{{ $h }}">{{ $h }} h</button>@endforeach
                        </div>
                        <x-forms.textarea fieldId="wf_memo" fieldLabel="What you did" fieldName="memo" fieldPlaceholder="e.g. Digitised poles 120–180, attributes filled" />
                    </div>

                    {{-- Units --}}
                    @if ($task->planned_units !== null)
                        <div class="wf-block">
                            <div class="wf-head"><b><i class="fa fa-tasks mr-1"></i>Done so far</b><span>The total, not just today</span></div>
                            <div class="input-group wf-units">
                                <input type="number" step="0.01" min="0" max="{{ $num($task->planned_units) }}" class="form-control f-14" name="done_units" value="{{ $num($task->done_units) }}" aria-label="Done so far">
                                <div class="input-group-append"><span class="input-group-text f-13">of {{ $num($task->planned_units) }} {{ $unit }}</span></div>
                            </div>
                        </div>
                    @endif

                    {{-- Step --}}
                    @if ($stages->isNotEmpty())
                        <div class="wf-block">
                            <div class="wf-head"><b><i class="fa fa-stream mr-1"></i>Step</b><span>Move it on when your part is done</span></div>
                            <div class="wf-steps" role="radiogroup" aria-label="Step">
                                @foreach ($stages as $i => $stage)
                                    <label style="--sc: {{ $stage->colour($i) }}" class="{{ (int) $current === (int) $stage->id ? 'active' : '' }}">
                                        <input type="radio" name="stage_id" value="{{ $stage->id }}" data-sort="{{ $stage->sort }}" @checked((int) $current === (int) $stage->id)>{{ $stage->name }}
                                    </label>
                                @endforeach
                            </div>
                            <div class="wf-reason mt-3" style="display: none">
                                <x-forms.textarea fieldId="wf_reason" fieldLabel="What needs fixing" fieldName="reason" :fieldRequired="true"
                                    fieldPlaceholder="e.g. 14 poles missing attributes in chainage 200–400" />
                            </div>
                        </div>
                    @endif

                    {{-- History --}}
                    @if ($history->isNotEmpty())
                        <div class="wf-block">
                            <div class="wf-head"><b><i class="fa fa-history mr-1"></i>Recent</b></div>
                            <ul class="wf-history">
                                @foreach ($history as $h)
                                    <li class="{{ $h->rework ? 'is-back' : '' }}">
                                        <span class="wf-when">{{ \Carbon\Carbon::parse($h->logged_at)->format('d M') }}</span>
                                        <span>
                                            <b>{{ $h->by_name ?? 'Someone' }}</b>
                                            @if ($h->stage_to && $h->stage_to !== $h->stage_from)
                                                {{ $h->rework ? 'sent it back to' : 'moved it to' }} {{ $h->to_name }}
                                            @endif
                                            @if (abs((float) $h->units_delta) > 0.0001)
                                                {{ $h->stage_to !== $h->stage_from ? '·' : '' }} {{ $h->units_delta > 0 ? '+' : '' }}{{ $num($h->units_delta) }} {{ $unit }}
                                            @endif
                                            @if ($h->note)<em>“{{ $h->note }}”</em>@endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                <x-form-actions>
                    <x-forms.button-primary id="save-work" class="mr-3" icon="check">@lang('app.save')</x-forms.button-primary>
                    <x-forms.button-cancel id="cancel-work" class="border-0">@lang('app.cancel')</x-forms.button-cancel>
                </x-form-actions>
            </div>
        </x-form>
    </div>
</div>

<style>
    .wf-block { padding: 14px 0 4px; border-top: 1px solid #f2f4f7; }
    .wf-block:first-of-type { border-top: 0; }
    .wf-head { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; margin-bottom: 10px; }
    .wf-head b { font-size: 14px; color: #1d2939; } .wf-head span { font-size: 12px; color: #98a2b3; }
    .wf-chips { display: flex; gap: 6px; margin: -6px 0 14px; }
    .wf-chip { border: 1px solid #eaecf0; background: #fff; border-radius: 16px; padding: 3px 12px; font-size: 12.5px; color: #475467; }
    .wf-chip:hover, .wf-chip.active { border-color: var(--epic-blue, #cb6b4f); color: var(--epic-blue, #cb6b4f); }
    .wf-units { max-width: 320px; margin-bottom: 12px; }
    .wf-steps { display: flex; flex-wrap: wrap; gap: 6px; }
    .wf-steps label { margin: 0; padding: 6px 12px; border-radius: 18px; font-size: 13px; cursor: pointer; border: 1px solid color-mix(in srgb, var(--sc) 35%, #fff);
        color: var(--sc); background: color-mix(in srgb, var(--sc) 7%, #fff); }
    .wf-steps label input { display: none; }
    .wf-steps label.active { background: var(--sc); color: #fff; font-weight: 600; }
    .wf-warn { background: #fffaeb; color: #93370d; padding: 8px 12px; border-radius: 8px; font-size: 13px; }
    .wf-history { list-style: none; margin: 0; padding: 0; }
    .wf-history li { display: flex; gap: 12px; padding: 7px 0; border-bottom: 1px dashed #f2f4f7; font-size: 13px; color: #475467; }
    .wf-history li.is-back { color: #912018; }
    .wf-history em { display: block; color: #667085; font-size: 12.5px; }
    .wf-when { flex: 0 0 48px; color: #98a2b3; }
</style>

@include('recruitment.partials.submit-js')

<script>
    $(function () {
        var stageWas = {{ $currentSort === null ? -1 : (int) $currentSort }};
        $('#work-form').on('change', 'input[name=stage_id]', function () {
            $('.wf-steps label').removeClass('active');
            $(this).closest('label').addClass('active');
            var sort = parseInt($(this).data('sort'), 10);
            $('.wf-reason').toggle(stageWas >= 0 && sort < stageWas);
        });
        $('.wf-chip').on('click', function () {
            $('.wf-chip').removeClass('active');
            $(this).addClass('active');
            $('#wf_hours').val($(this).data('hours'));
            $('#wf_minutes').val(0);
        });
        $('#wf_hours').on('input', function () { $('.wf-chip').removeClass('active'); });

        datepicker('#wf_date', {
            position: 'bl', maxDate: new Date(), minDate: new Date(Date.now() - 7 * 864e5),
            ...datepickerConfig
        });

        $('#save-work').on('click', function () {
            recruitSubmit('work-form', '{{ route('project-tracker.my-work.update', $task->id) }}', 'save-work');
        });
        $('#cancel-work').on('click', function () { recruitClose('{{ route('project-tracker.my-work') }}'); });
        init(RIGHT_MODAL);
    });
</script>
