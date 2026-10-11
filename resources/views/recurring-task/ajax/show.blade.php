<div class="row">
    <div class="col-sm-12">
        <x-cards.data :title="__('app.recurring') . ' ' . __('app.details')" class=" mt-4">
            <x-cards.data-row :label="__('modules.tasks.repeatEvery')" :value="$task->repeat_every . ' ' . __('app.'.$task->repeat_type)" />
            <x-cards.data-row :label="__('modules.tasks.cycles')" :value="$task->repeat_cycles" />
            <div class="col-12 px-0 pb-3 d-block d-lg-flex d-md-flex">
                <p class="mb-0 text-lightest f-14 w-30 d-inline-block ">
                    @lang('modules.tasks.completedTotalTask')</p>
                <p class="mb-0 text-dark-grey f-14 ">
                    {{$completedTaskCount}}/{{$task->repeat_cycles}}
                </p>
            </div>
        </x-cards.data>
        <x-cards.data :title="__('app.task') . ' ' . __('app.details')" class=" mt-4">
            <div class="card bg-white border-0 b-shadow-4">
                <div class="card-body">

                    <x-cards.data-row :label="__('app.title')" :value="$task->heading"
                        html="true" />

                    @php
                        if ($task->project_id) {
                            if ($task->project->status == 'in progress') {
                                $project = '<i class="fa fa-circle mr-1 text-blue f-10"></i>';
                            } elseif ($task->project->status == 'on hold') {
                                $project = '<i class="fa fa-circle mr-1 text-yellow f-10"></i>';
                            } elseif ($task->project->status == 'not started') {
                                $project = '<i class="fa fa-circle mr-1 text-yellow f-10"></i>';
                            } elseif ($task->project->status == 'canceled') {
                                $project = '<i class="fa fa-circle mr-1 text-red f-10"></i>';
                            } elseif ($task->project->status == 'finished') {
                                $project = '<i class="fa fa-circle mr-1 text-dark-green f-10"></i>';
                            }
                            $project = $task->project->project_name;

                        } else {
                            $project = '--';
                        }
                    @endphp

                    @if (($taskSettings->project == 'yes' && in_array('client', user_roles())) || in_array('admin', user_roles()) || in_array('employee', user_roles()))
                        <x-cards.data-row :label="__('app.project')" :value="$project" html="true" />
                    @endif

                    @if (($taskSettings->priority == 'yes' && in_array('client', user_roles())) || in_array('admin', user_roles()) || in_array('employee', user_roles()) )
                        <div class="col-12 px-0 pb-3 d-block d-lg-flex d-md-flex">
                            <p class="mb-0 text-lightest f-14 w-30 d-inline-block ">
                                @lang('modules.tasks.priority')</p>
                            <p class="mb-0 text-dark-grey f-14 w-70">
                                @if ($task->priority == 'high')
                                    <i class="fa fa-circle mr-1 text-red f-10"></i>
                                @elseif ($task->priority == 'medium')
                                    <i class="fa fa-circle mr-1 text-yellow f-10"></i>
                                @else
                                    <i class="fa fa-circle mr-1 text-dark-green f-10"></i>
                                @endif
                                @lang('app.'.$task->priority)
                            </p>
                        </div>
                    @endif

                    @if (($taskSettings->project == 'yes' && in_array('client', user_roles())) || in_array('admin', user_roles()) || in_array('employee', user_roles()))
                        <div class="col-12 px-0 pb-3 d-block d-lg-flex d-md-flex">
                            <p class="mb-0 text-lightest f-14 w-30 d-inline-block ">@lang('app.status')</p>
                            <p class="mb-0 text-dark-grey f-14 w-70">
                                <i class="fa fa-circle mr-1 f-10 {{ $task->boardColumn->label_color }}"
                                style="color: {{ $task->boardColumn->label_color }}"></i>{{ $task->boardColumn->column_name }}
                            </p>
                        </div>
                    @endif

                    @if (($taskSettings->assigned_to == 'yes' && in_array('client', user_roles())) || in_array('admin', user_roles()) || in_array('employee', user_roles()))
                        <div class="col-12 px-0 pb-3 d-block d-lg-flex d-md-flex">
                            <p class="mb-0 text-lightest f-14 w-30 d-inline-block ">
                                @lang('modules.tasks.assignTo')</p>
                            @if (count($task->users) > 0)
                                @if (count($task->users) > 1)
                                    @foreach ($task->users as $item)
                                        <div class="taskEmployeeImg rounded-circle mr-1">
                                            <a href="{{ route('employees.show', $item->id) }}">
                                                <img data-toggle="tooltip" data-original-title="{{ $item->name }}"
                                                     src="{{ $item->image_url }}">
                                            </a>
                                        </div>
                                    @endforeach
                                @else
                                    @foreach ($task->users as $item)
                                        <x-employee :user="$item"/>
                                    @endforeach
                                @endif
                            @else
                                --
                            @endif
                        </div>
                    @endif

                     <x-cards.data-row :label="__('modules.projects.milestones')"
                                      :value="$task->milestone->milestone_title ?? '--'"/>

                    @if (($taskSettings->label == 'yes' && in_array('client', user_roles())) || in_array('admin', user_roles()) || in_array('employee', user_roles()))
                        <div class="col-12 px-0 pb-3 d-block d-lg-flex d-md-flex">
                            <p class="mb-0 text-lightest f-14 w-30 d-inline-block ">
                                @lang('app.label')</p>
                            <p class="mb-0 text-dark-grey f-14 w-70">
                                @forelse ($task->labels as $key => $label)
                                    <span class='badge badge-secondary'
                                          style='background-color: {{ $label->label_color }}'
                                          @if ($label->description)
                                                data-toggle="popover"
                                                data-placement="top"
                                                data-content="{!! $label->description !!}"
                                                data-html="true"
                                                data-trigger="hover"
                                            @endif
                                            >{!! $label->label_name !!} </span>
                                @empty
                                    --
                                @endforelse
                            </p>
                        </div>
                    @endif

                    @if (in_array('gitlab', user_modules()) && isset($gitlabIssue))
                        <div class="col-12 px-0 pb-3 d-block d-lg-flex d-md-flex">
                            <p class="mb-0 text-lightest f-14 w-30 d-inline-block ">
                                GitLab</p>
                            <div class="mb-0 w-70">
                                <div class='card border'>
                                    <div
                                        class="card-body bg-white d-flex justify-content-between p-2 align-items-center rounded">
                                        <h4 class="f-13 f-w-500 mb-0">
                                            <img src="{{ asset('img/gitlab-icon-rgb.png') }}" class="height-35">
                                            <a href="{{ $gitlabIssue['web_url'] }}" class="text-darkest-grey f-w-500"
                                               target="_blank">#{{ $gitlabIssue['iid'] }} {{ $gitlabIssue['title'] }} <i
                                                    class="fa fa-external-link-alt"></i></a>
                                        </h4>
                                        <div>
                                            <span
                                                class="badge badge-{{ $gitlabIssue['state'] == 'opened' ? 'danger' : 'success' }}">{{ $gitlabIssue['state'] }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if (($taskSettings->task_category == 'yes' && in_array('client', user_roles())) || in_array('admin', user_roles()) || in_array('employee', user_roles()))
                        <x-cards.data-row :label="__('modules.tasks.taskCategory')"
                                          :value="$task->category->category_name ?? '--'" html="true"/>
                    @endif

                    @if (($taskSettings->description == 'yes' && in_array('client', user_roles())) || in_array('admin', user_roles()) || in_array('employee', user_roles()))
                        <x-cards.data-row :label="__('app.description')"
                                          :value="!empty($task->description) ? $task->description : '--'"
                                          html="true"/>
                    @endif

                    {{-- Custom fields data --}}
                    @if (($taskSettings->custom_fields == 'yes' && in_array('client', user_roles())) || in_array('admin', user_roles()) || in_array('employee', user_roles()))
                        <x-forms.custom-field-show :fields="$fields" :model="$task"></x-forms.custom-field-show>
                    @endif

                </div>
            </div>
        </x-cards.data>
    </div>
</div>

