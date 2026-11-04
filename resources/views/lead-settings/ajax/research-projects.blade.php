{{-- Research projects: the "Research projects" dropdown of Lead Contacts → Search Projects (administrators) --}}
<div class="table-responsive p-20">
    <x-table class="table-bordered">
        <x-slot name="thead">
            <th width="60">#</th>
            <th>Research Project</th>
            <th>Added By</th>
            <th>Added On</th>
            <th class="text-right">@lang('app.action')</th>
        </x-slot>

        @forelse ($researchProjects as $key => $project)
            <tr class="research-project-row{{ $project->id }}">
                <td>{{ $key + 1 }}</td>
                <td>{{ $project->name }}</td>
                <td>{{ $project->added_by_name ?: '--' }}</td>
                <td>{{ $project->created_at ? \Carbon\Carbon::parse($project->created_at)->timezone(company()->timezone)->translatedFormat(company()->date_format) : '--' }}</td>
                <td class="text-right">
                    <div class="task_view">
                        <a href="javascript:;" class="edit-research-project task_view_more d-flex align-items-center justify-content-center"
                           data-project-id="{{ $project->id }}" data-project-name="{{ $project->name }}">
                            <i class="fa fa-edit icons mr-2"></i> @lang('app.edit')
                        </a>
                    </div>
                    <div class="task_view">
                        <a href="javascript:;" class="delete-research-project task_view_more d-flex align-items-center justify-content-center"
                           data-project-id="{{ $project->id }}" data-project-name="{{ $project->name }}">
                            <i class="fa fa-trash icons mr-2"></i> @lang('app.delete')
                        </a>
                    </div>
                </td>
            </tr>
        @empty
            <x-cards.no-record-found-list colspan="5"/>
        @endforelse
    </x-table>
</div>
