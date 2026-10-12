{{-- Meeting Purpose / Meeting Status / Meeting Result options used by Customers → Meetings --}}
<div class="table-responsive p-20">
    <x-table class="table-bordered">
        <x-slot name="thead">
            <th width="60">#</th>
            <th>{{ $meetingOptionLabel }}</th>
            <th class="text-right">@lang('app.action')</th>
        </x-slot>

        @forelse ($meetingOptions as $key => $option)
            <tr class="meeting-option-row{{ $option->id }}">
                <td>{{ $key + 1 }}</td>
                <td class="meeting-option-name">{{ $option->name }}</td>
                <td class="text-right">
                    <div class="task_view">
                        <a href="javascript:;" class="edit-meeting-option task_view_more d-flex align-items-center justify-content-center"
                           data-option-type="{{ $meetingOptionType }}" data-option-id="{{ $option->id }}"
                           data-option-name="{{ $option->name }}">
                            <i class="fa fa-edit icons mr-2"></i> @lang('app.edit')
                        </a>
                    </div>
                    <div class="task_view">
                        <a href="javascript:;" class="delete-meeting-option task_view_more d-flex align-items-center justify-content-center"
                           data-option-type="{{ $meetingOptionType }}" data-option-id="{{ $option->id }}"
                           data-option-name="{{ $option->name }}">
                            <i class="fa fa-trash icons mr-2"></i> @lang('app.delete')
                        </a>
                    </div>
                </td>
            </tr>
        @empty
            <x-cards.no-record-found-list colspan="3"/>
        @endforelse
    </x-table>
</div>
