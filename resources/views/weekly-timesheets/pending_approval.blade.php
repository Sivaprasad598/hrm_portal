@extends('layouts.app')

@push('styles')
    <style>
        .table .thead-light th,
        .table tr td,
        .table h5 {
            font-size: 12px;
        }
        .shift-request-change-count {
            left: 28px;
            top: -9px !important;
        }

        .change-shift {
            padding: 1rem 0.25rem !important;
        }

        #week-end-date, #week-start-date {
            z-index: 0;
        }

</style>

<style>

    .hours-td div {
        width: 70px;
    }

    .hours-td input {
        width: 70px;
        text-align: center;
    }

    .employee-td select {
        width: 240px;
    }

    .employee-td:hover > .work-setting-icon {
        display: inline-block;
    }

    .week-task {
        padding: 0.375rem 2.25rem 0.375rem 0.75rem;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 16px 12px;
        appearance: none;
    }

    @media screen and (min-width: 1200px) {
        .fixed-column {
            position: sticky;
            left: 0;
            /* z-index: 1; Ensures the sticky column is above horizontally scrolled content */
            box-shadow: 4px 0 5px -2px rgba(0,0,0,0.2); /* Adds shadow to the right side */

        }
    }

</style>


@endpush

@section('filter-section')
    @if(isset($employees) && $employees->count() > 0)
    <x-filters.filter-box>
        <!-- EMPLOYEE START -->
        <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.employee')</p>
            <div class="select-status">
                <select class="form-control select-picker" name="employee_id" id="employee_id" data-live-search="true" data-size="8">
                    @if ($employees->count() > 1 || in_array('admin', user_roles()))
                        <option value="all">@lang('app.all')</option>
                    @endif
                    @foreach ($employees as $employee)
                        <x-user-option :user="$employee" :selected="request('employee_id') == $employee->id"/>
                    @endforeach
                </select>
            </div>
        </div>
        <!-- EMPLOYEE END -->
    </x-filters.filter-box>
    @endif
@endsection

@section('content')
    <!-- CONTENT WRAPPER START -->


    <div class="px-4 content-wrapper">


        <div class="d-lg-flex d-md-flex d-block my-3 justify-content-between action-bar">
            
            <div class="d-flex align-items-center">

                <div class="d-flex align-items-center">
                    <h4 class="mb-0">@lang('modules.timeLogs.pendingApproval')</h4>

                    <x-forms.link-secondary :link="route('weekly-timesheets.index')" class="ml-3" >@lang('modules.timeLogs.addWeeklyTimesheet')</x-forms.link-secondary>
                    
                    @if (request()->has('id') && request()->id != '')
                        <x-forms.link-secondary :link="route('weekly-timesheets.index').'?view=pending_approval'" class="ml-3" >@lang('app.showAll') @lang('modules.timeLogs.pendingApproval')</x-forms.link-secondary>
                    @endif
                </div>
            </div>

            <div class="btn-group ml-3" role="group">
                @include('timelogs.timelog-menu')
            </div>
        </div>



        <x-cards.data class="bg-white">

            <div class="table-responsive">
                <x-table class="table-bordered mt-3 table-hover" headType="thead-light" id="weekly-timesheet-table" >
                    <x-slot name="thead">
                        <th class="px-2">
                            @lang('app.employee')
                        </th>
                        <th class="px-2">
                            @lang('app.duration')
                        </th>
                        <th class="text-right">
                            @lang('app.action')
                        </th>
                    </x-slot>

                    @forelse ($weeklyTimesheet as $timesheet)
                        <tr>
                            <td class="px-2"><x-employee :user="$timesheet->user" /></td>
                            <td class="px-2">{{ $timesheet->week_start_date->translatedFormat(company()->date_format) }} - {{ $timesheet->week_start_date->addDays(6)->translatedFormat(company()->date_format) }}</td>
                            <td class="text-right">
                                <x-forms.link-secondary :link="route('weekly-timesheets.show', $timesheet->id)" class="view-timesheet openRightModal mr-2" icon="fa fa-eye" >@lang('app.view')</x-forms.link-secondary>
                                
                                <x-forms.button-secondary type="button" class="change-timesheet-status  mr-2" data-status="draft" data-timesheet-id="{{ $timesheet->id }}" icon="fa fa-times" >@lang('app.reject')</x-forms.button-secondary>

                                <x-forms.button-primary type="button" class="change-timesheet-status" data-status="approved" data-timesheet-id="{{ $timesheet->id }}" icon="fa fa-check" >@lang('app.approve')</x-forms.button-primary>

                            </td>
                        </tr>
                        
                    @empty
                        <tr>
                            <td colspan="3" class="text-center">@lang('messages.noRecordFound')</td>
                        </tr>
                    @endforelse
                
                </x-table>
            </div>
        </x-cards.data>
    </div>
    <!-- CONTENT WRAPPER END -->
@endsection

@push('scripts')
    <script>

        $('#employee_id').on('change', function() {
            let employeeId = $(this).val();
            let url = "{{ route('weekly-timesheets.index') }}?view=pending_approval";
            
            if (employeeId && employeeId != 'all') {
                url += '&employee_id=' + employeeId;
            }
            
            @if (request()->has('id') && request()->id != '')
                url += '&id={{ request()->id }}';
            @endif
            
            window.location.href = url;
        });

        $('.change-timesheet-status').on('click', function() {
            let status = $(this).data('status');
            let timesheetId = $(this).data('timesheet-id');

            if(status == 'draft'){
                let searchQuery = "?status=" + status + "&timesheet_id=" + timesheetId;
                let url = "{{ route('weekly-timesheets.show_reject_modal') }}" + searchQuery;

                $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
                $.ajaxModal(MODAL_LG, url);
            }else{

                var url = "{{ route('weekly-timesheets.change_status') }}";

                Swal.fire({
                    title: "@lang('messages.sweetAlertTitle')",
                    text: "@lang('messages.changeWeeklyTimesheetStatusConfirmation')",
                    icon: 'warning',
                    showCancelButton: true,
                    focusConfirm: false,
                    confirmButtonText: "@lang('messages.confirm')",
                    cancelButtonText: "@lang('app.cancel')",
                    customClass: {
                        confirmButton: 'btn btn-primary mr-3',
                        cancelButton: 'btn btn-secondary'
                    },
                    showClass: {
                        popup: 'swal2-noanimation',
                        backdrop: 'swal2-noanimation'
                    },
                    buttonsStyling: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.easyAjax({
                            type: 'POST',
                            url: url,
                            blockUI: true,
                            data: {
                                'status': status,
                                'timesheetId': timesheetId,
                                '_token': '{{ csrf_token() }}'
                            },
                            success: function(response) {
                                if (response.status == 'success') {
                                    window.location.reload(); 
                                }
                            }
                        });
                    }
                });
            }

        });

    </script>
@endpush
