<!-- TAB CONTENT START -->
<div class="tab-pane fade show active mt-5" role="tabpanel" aria-labelledby="nav-email-tab">

    <x-alert type="info" icon="info-circle">
        @lang('messages.leaveQuotaShowing', ['start_date' => $leaveStartDate, 'end_date' => $leaveEndDate])
    </x-alert>

    <div class="row my-3">
        <div class="col-lg-4 col-md-6">
            <x-cards.widget icon="sign-out-alt" :title="__('modules.leaves.remainingLeaves')" :value="$allowedLeaves" />
        </div>
    </div>

    <div class="card border">
        <div class="card-header bg-white border-bottom-0">
            <h4 class="mb-0 f-16 font-weight-bold">@lang('app.menu.leavesQuota')</h4>
        </div>
        <div class="card-body" id="comment-list">
            @include('employees.leaves_quota')
        </div>
    </div>
</div>
<!-- TAB CONTENT END -->
