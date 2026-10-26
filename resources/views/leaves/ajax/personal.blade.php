<div class="d-grid d-lg-flex d-md-flex action-bar">
    {{-- Applying is self-service: everybody applies for their own leave here. --}}
    <div class="flex-grow-1 align-items-center">
        @if (\App\Http\Controllers\LeaveController::access()['apply'] && user()->employeeDetail)
            <x-forms.link-primary :link="route('leaves.create')" data-redirect-url="{{ route('leaves.personal') }}"
                class="mr-3 openRightModal float-left" icon="plus">
                Apply leave
            </x-forms.link-primary>
        @endif
    </div>

    @include('leaves.partials.view-switch', ['active' => 'personal', 'class' => 'ml-3'])
</div>

<!-- TAB CONTENT START -->
<div class="tab-pane fade show active mt-5" role="tabpanel" aria-labelledby="nav-email-tab">

    <div class="row mb-4">
        <div class="col-lg-4">
            <x-cards.user :image="$employee->image_url">
                <div class="row">
                    <div class="col-10">
                        <h4 class="card-title f-15 f-w-500 text-darkest-grey mb-0">
                            {{ ($employee->salutation ? $employee->salutation->label() . ' ' : '') . $employee->name }}
                            @isset($employee->country)
                                <x-flag :country="$employee->country" />
                            @endisset
                        </h4>
                    </div>

                </div>

                <p class="f-13 font-weight-normal text-dark-grey mb-0">
                    {{ !is_null($employee->employeeDetail) && !is_null($employee->employeeDetail->designation) ? $employee->employeeDetail->designation->name : '' }}
                    &bull;
                    {{ isset($employee->employeeDetail) && !is_null($employee->employeeDetail->department) && !is_null($employee->employeeDetail->department) ? $employee->employeeDetail->department->team_name : '' }}
                </p>

                @if ($employee->status == 'active')
                    <p class="card-text f-12 text-lightest">@lang('app.lastLogin')

                        @if (!is_null($employee->last_login))
                            {{ $employee->last_login->timezone(company()->timezone)->translatedFormat(company()->date_format . ' ' . company()->time_format) }}
                        @else
                            --
                        @endif
                    </p>

                @else
                    <p class="card-text f-12 text-lightest">
                        <x-status :value="__('app.inactive')" color="red" />
                    </p>
                @endif

            </x-cards.user>
        </div>
        <div class="col-lg-4">
            <x-cards.widget icon="sign-out-alt" :title="__('modules.leaves.remainingLeaves')" :value="$allowedLeaves" />
        </div>
    </div>


    <x-cards.data :title="__('app.menu.leavesQuota')">


        <div class="d-flex flex-wrap justify-content-between" id="comment-list">
            @include('employees.leaves_quota')
        </div>

    </x-cards.data>
</div>
<!-- TAB CONTENT END -->

<script>
    $(document).ready(function() {
        $('#renew-contract').click(function() {
            $(this).closest('.row').addClass('d-none');
            $('#save-renew-data-form').removeClass('d-none');
        });

        $('#cancel-renew').click(function() {
            $('#save-renew-data-form').addClass('d-none');
            $('#renew-contract').closest('.row').removeClass('d-none');
        });

        $('.update-category').click(function() {
            var id = $(this).data('type-id');
            var leaves = $('.leave-count-' + id).val();
            var url = "{{ route('employee-leaves.update', ':id') }}";
            url = url.replace(':id', id);

            var token = "{{ csrf_token() }}";

            $.easyAjax({
                type: 'POST',
                url: url,
                data: {
                    '_method': 'PUT',
                    '_token': token,
                    'leaves': leaves
                },
                success: function(response) {
                    if (response.status == "success") {
                        window.location.reload();
                    }
                }
            });
        });

    });

</script>
