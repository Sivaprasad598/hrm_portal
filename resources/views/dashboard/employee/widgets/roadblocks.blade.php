@php
    // Roadblocks where teammates are waiting on the logged-in user
    $waitingOnMe = in_array('tasks', dashboard_modules())
        ? \App\Models\TaskBlocker::with('task:id,heading,task_short_code', 'raisedBy', 'linkedTask:id,heading')
            ->whereHas('task')
            ->where('blocking_user_id', user()->id)
            ->open()
            ->orderByRaw("FIELD(urgency, 'urgent', 'normal')")
            ->orderByRaw('needed_by IS NULL, needed_by')
            ->get()
        : collect();
@endphp

@if ($waitingOnMe->isNotEmpty())
    <div class="row mb-3">
        <div class="col-md-12">
            <x-cards.data :title="'🚩 ' . __('modules.tasks.roadblockTeammatesWaiting') . ' (' . $waitingOnMe->count() . ')'" :padding="false">
                <x-table>
                    @foreach ($waitingOnMe as $blocker)
                        <tr @class(['table-danger' => $blocker->urgency == 'urgent' || $blocker->isOverdue()])>
                            <td class="pl-20 f-13">
                                <a href="{{ route('tasks.show', $blocker->task_id) }}?view=roadblocks" class="openRightModal text-darkest-grey f-w-500">
                                    {{ $blocker->task->heading }}
                                </a>
                                <div class="text-dark-grey f-12">{{ \Illuminate\Support\Str::limit($blocker->need, 100) }}</div>
                                @if ($blocker->linkedTask)
                                    <div class="f-11 text-lightest"><i class="fa fa-link"></i> {{ $blocker->linkedTask->heading }}</div>
                                @endif
                            </td>
                            <td class="f-12 text-dark-grey">{{ $blocker->raisedBy?->name }}</td>
                            <td class="f-12">
                                @if ($blocker->urgency == 'urgent')
                                    <span class="badge badge-danger">@lang('modules.tasks.roadblockUrgent')</span>
                                @endif
                                @if ($blocker->needed_by)
                                    <div @class(['text-danger f-w-500' => $blocker->isOverdue()])>
                                        {{ $blocker->needed_by->translatedFormat(company()->date_format) }}
                                    </div>
                                @endif
                            </td>
                            <td class="text-right pr-20">
                                <x-forms.button-primary icon="check" class="close-roadblock btn-xs"
                                                        data-blocker-id="{{ $blocker->id }}" data-action="resolve">
                                    @lang('modules.tasks.roadblockResolve')
                                </x-forms.button-primary>
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            </x-cards.data>
        </div>
    </div>
@endif
