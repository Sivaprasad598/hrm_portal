@extends('layouts.app')

@php
    $formatDuration = fn ($seconds) => is_null($seconds) ? '--'
        : ($seconds < 60 ? $seconds . 's' : \Carbon\CarbonInterval::seconds($seconds)->cascade()->forHumans(['short' => true, 'parts' => 2]));
@endphp

@section('filter-section')
    <x-filters.filter-box>
        <form method="GET" action="{{ route('task-blockers.index') }}" id="roadblock-filters" class="d-flex flex-wrap">
            <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
                <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.status')</p>
                <div class="select-status">
                    <select class="form-control select-picker" name="status" onchange="this.form.submit()">
                        <option value="open" @selected($status == 'open')>@lang('modules.tasks.roadblockOpen')</option>
                        <option value="resolved" @selected($status == 'resolved')>@lang('modules.tasks.roadblockResolved')</option>
                        <option value="cancelled" @selected($status == 'cancelled')>@lang('modules.tasks.roadblockCancelled')</option>
                        <option value="all" @selected($status == 'all')>@lang('app.all')</option>
                    </select>
                </div>
            </div>

            <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
                <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('modules.tasks.roadblockWaitingOn')</p>
                <div class="select-status">
                    <select class="form-control select-picker" name="blocking_user_id" data-live-search="true" data-size="8" onchange="this.form.submit()">
                        <option value="">@lang('app.all')</option>
                        @foreach ($employees as $employee)
                            <x-user-option :user="$employee" :selected="$blockingUserId == $employee->id"/>
                        @endforeach
                    </select>
                </div>
            </div>
        </form>
    </x-filters.filter-box>
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <h4 class="f-18 f-w-500 mb-0">@lang('modules.tasks.roadblockReport')</h4>
            <div class="btn-group" role="group">
                <a href="{{ route('tasks.index') }}" class="btn btn-secondary f-14" data-toggle="tooltip"
                    data-original-title="@lang('app.menu.tasks')"><i class="side-icon bi bi-list-ul"></i></a>
                <a href="{{ route('taskboards.index') }}" class="btn btn-secondary f-14" data-toggle="tooltip"
                    data-original-title="@lang('modules.tasks.taskBoard')"><i class="side-icon bi bi-kanban"></i></a>
                <a href="{{ route('task-calendar.index') }}" class="btn btn-secondary f-14" data-toggle="tooltip"
                    data-original-title="@lang('app.menu.calendar')"><i class="side-icon bi bi-calendar"></i></a>
                <a href="{{ route('tasks.waiting-approval') }}" class="btn btn-secondary f-14" data-toggle="tooltip"
                    data-original-title="@lang('app.menu.waiting-approval')"><i class="side-icon bi bi-clipboard-check"></i></a>
                @include('tasks.partials.roadblocks-button', ['active' => true])
            </div>
        </div>

        {{-- Per person: who is holding others up, and how fast they unblock --}}
        <x-cards.data :title="__('modules.tasks.roadblockWaitingOn')" :padding="false" class="mb-4">
            <x-table class="table-hover" headType="thead-light">
                <x-slot name="thead">
                    <th class="pl-20">@lang('app.employee')</th>
                    <th class="text-center">@lang('modules.tasks.roadblockOpenCount')</th>
                    <th class="text-center">@lang('modules.tasks.roadblockOverdue')</th>
                    <th class="text-center">@lang('modules.tasks.roadblockResolved')</th>
                    <th class="text-right pr-20">@lang('modules.tasks.roadblockAvgResolve')</th>
                </x-slot>
                @forelse ($summary as $row)
                    <tr>
                        <td class="pl-20">
                            @if ($row['user'])
                                <x-employee :user="$row['user']"/>
                            @endif
                        </td>
                        <td class="text-center f-w-500">{{ $row['open'] }}</td>
                        <td class="text-center {{ $row['overdue'] ? 'text-danger f-w-500' : '' }}">{{ $row['overdue'] }}</td>
                        <td class="text-center">{{ $row['resolved'] }}</td>
                        <td class="text-right pr-20">{{ $formatDuration($row['avg_seconds']) }}</td>
                    </tr>
                @empty
                    <x-cards.no-record-found-list colspan="5"/>
                @endforelse
            </x-table>
        </x-cards.data>

        <x-cards.data :title="__('modules.tasks.roadblocks')" :padding="false">
            <x-table class="table-hover" headType="thead-light">
                <x-slot name="thead">
                    <th class="pl-20">@lang('app.task')</th>
                    <th>@lang('modules.tasks.roadblockNeed')</th>
                    <th>@lang('modules.tasks.roadblockWaitingOn')</th>
                    <th>@lang('modules.tasks.roadblockRaisedBy')</th>
                    <th>@lang('modules.tasks.roadblockNeededBy')</th>
                    <th>@lang('modules.tasks.roadblockAge')</th>
                    <th>@lang('app.status')</th>
                    <th class="text-right pr-20">@lang('app.action')</th>
                </x-slot>
                @forelse ($blockers as $blocker)
                    <tr @class(['table-danger' => $blocker->status == 'open' && ($blocker->urgency == 'urgent' || $blocker->isOverdue())])>
                        <td class="pl-20 f-13">
                            <a href="{{ route('tasks.show', $blocker->task_id) }}?view=roadblocks" class="openRightModal text-darkest-grey f-w-500">
                                {{ $blocker->task?->heading }}
                            </a>
                            @if ($blocker->task?->task_short_code)
                                <div class="f-11 text-lightest">#{{ $blocker->task->task_short_code }}</div>
                            @endif
                        </td>
                        <td class="f-12">
                            @if ($blocker->urgency == 'urgent')
                                <span class="badge badge-danger">🚩 @lang('modules.tasks.roadblockUrgent')</span>
                            @endif
                            {{ \Illuminate\Support\Str::limit($blocker->need, 90) }}
                        </td>
                        <td class="f-12">{{ $blocker->blockingUser?->name }}</td>
                        <td class="f-12">{{ $blocker->raisedBy?->name }}</td>
                        <td class="f-12 {{ $blocker->isOverdue() ? 'text-danger f-w-500' : '' }}">
                            {{ $blocker->needed_by?->translatedFormat(company()->date_format) ?? '--' }}
                        </td>
                        <td class="f-12">
                            {{ $formatDuration(abs(($blocker->resolved_at ?? now())->diffInSeconds($blocker->created_at))) }}
                        </td>
                        <td class="f-12">
                            @if ($blocker->status == 'open')
                                <span class="badge badge-warning">@lang('modules.tasks.roadblockOpen')</span>
                            @elseif ($blocker->status == 'resolved')
                                <span class="badge badge-success">@lang('modules.tasks.roadblockResolved')</span>
                            @else
                                <span class="badge badge-secondary">@lang('modules.tasks.roadblockCancelled')</span>
                            @endif
                        </td>
                        <td class="text-right pr-20">
                            @if ($blocker->canResolve())
                                <x-forms.button-primary icon="check" class="close-roadblock btn-xs"
                                                        data-blocker-id="{{ $blocker->id }}" data-action="resolve">
                                    @lang('modules.tasks.roadblockResolve')
                                </x-forms.button-primary>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-cards.no-record-found-list colspan="8"/>
                @endforelse
            </x-table>
        </x-cards.data>

        <div class="mt-3">{{ $blockers->links() }}</div>
    </div>
@endsection
