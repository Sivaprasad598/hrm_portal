<!-- LEAVE GENRAL SETTING START -->
<div class="col-lg-12 col-md-12 ntfcn-tab-content-left w-100 p-4">

    <div class="form-group">

        <div class="d-block d-lg-flex d-md-flex">
            <x-forms.radio fieldId="login-yes" :fieldLabel="__('modules.leaves.countLeavesFromDateOfJoining')"
                           fieldName="leaves_start_from" fieldValue="joining_date"
                           :checked="company()->leaves_start_from == 'joining_date'">
            </x-forms.radio>
            <x-forms.radio fieldId="login-no" :fieldLabel="__('modules.leaves.countLeavesFromStartOfYear')"
                           fieldValue="year_start" fieldName="leaves_start_from"
                           :checked="company()->leaves_start_from == 'year_start'">
            </x-forms.radio>
        </div>
        <div class="d-block d-lg-flex d-md-flex">
            <div class="col-lg-3" id="year_starts"
                 @if (company()->leaves_start_from == 'joining_date') style='display:none' @endif>
                <x-forms.select fieldId="year_starts_from" :fieldLabel="__('modules.accountSettings.yearStartsFrom')"
                                fieldName="year_starts_from" search="true">
                    @foreach(\App\Models\GlobalSetting::getMonthsOfYear() as $month)
                        <option value="{{ $loop->iteration }}"
                                @if (company()->year_starts_from == $loop->iteration) selected @endif>{{ $month }}</option>
                    @endforeach
                </x-forms.select>
            </div>
        </div>
    </div>

    {{-- Leave approval steps (App\Services\Leaves\LeaveApprovals) --}}
    @php
        $chainSetting = \App\Services\Leaves\LeaveApprovals::settings();
        $ticked = json_decode((string) ($chainSetting->approval_levels ?? ''), true) ?: [];
        $stepHelp = [
            'manager' => 'The employee\'s Reporting To (set on the employee).',
            'department_head' => 'The head of the employee\'s department (set on the department).',
            'hr' => 'Anyone with Approve / Reject Leaves (HR, Admin).',
        ];
    @endphp
    <div class="border rounded p-3 mb-4" id="approval-steps">
        <h4 class="f-15 font-weight-bold text-dark mb-1">Leave approval steps</h4>
        <p class="f-12 text-dark-grey mb-3">A leave goes through the ticked steps in this order and counts only after the last one approves it.
            A step with nobody for it (no manager or department head set, or the applicant themselves) is skipped.
            HR can approve ahead of a step, with a note.</p>
        @foreach (\App\Services\Leaves\LeaveApprovals::STEPS as $key => $label)
            <label class="d-flex align-items-start mb-2 cursor-pointer">
                <input type="checkbox" class="mt-1 mr-2 approval-level" value="{{ $key }}" @checked(in_array($key, $ticked, true))>
                <span><span class="f-14 text-dark">{{ $loop->iteration }}. {{ $label }}</span>
                    <span class="d-block f-12 text-lightest">{{ $stepHelp[$key] }}</span></span>
            </label>
        @endforeach
        <div class="d-flex align-items-center flex-wrap mt-3">
            <span class="f-14 mr-2">If the manager or department head has not decided in</span>
            <input type="number" min="0" max="30" id="escalate_after_days" class="form-control height-35 f-14 mr-2" style="width: 80px"
                   value="{{ (int) ($chainSetting->escalate_after_days ?? 2) }}">
            <span class="f-14">day(s), move it on to the next step <span class="text-lightest f-12">(0: wait)</span></span>
        </div>
        <button type="button" class="btn btn-primary btn-sm mt-3" id="save-approval-steps"><i class="fa fa-check mr-1"></i>Save approval steps</button>
    </div>

    {{-- Replaced by the approval steps above: the old "reporting manager can pre-approve" choice (kept, hidden).
    <div class="d-block d-lg-flex d-md-flex">
        <x-alert type="info">@lang('modules.leaves.leaveSettingNote')</x-alert>
    </div>

    <div class="d-block d-lg-flex d-md-flex">
        <p> @lang('modules.leaves.reportingManager') </p>
        <div class="col-lg-4">

            <select name="permission" class="form-control select-picker manager-permission"
                    onchange="changeStatus(this.value)">
                <option
                    @if ($leavePermission->manager_permission == 'pre-approve') @endif value="pre-approve">@lang('modules.leaves.preApprove')</option>
                <option @if ($leavePermission->manager_permission == 'approved') selected
                        @endif value="approved">@lang('modules.leaves.approve')</option>
                <option @if ($leavePermission->manager_permission == 'cannot-approve') selected
                        @endif value="cannot-approve">@lang('modules.leaves.canNotApprove')</option>
            </select>
        </div>
        <p> @lang('modules.leaves.theLeave') </p>
    </div>
    --}}
</div>

</div>
<!-- LEAVE GENRAL SETTING ENDS -->

<script>
    $('#save-approval-steps').click(function () {
        $.easyAjax({
            url: "{{ route('leaves-settings.approval') }}",
            type: 'POST',
            container: '#approval-steps',
            blockUI: true,
            data: {
                _token: '{{ csrf_token() }}',
                levels: $('.approval-level:checked').map(function () { return this.value; }).get(),
                escalate_after_days: $('#escalate_after_days').val()
            }
        });
    });

    $('input[name=leaves_start_from], #year_starts_from').on("click change", function () {
        var leaveCountFrom = $('input[name=leaves_start_from]:checked').val();
        var yearStartFrom = $('#year_starts_from').val();
        $.easyAjax({
            url: "{{ route('leaves-settings.store') }}",
            type: "POST",
            data: {
                '_token': '{{ csrf_token() }}',
                'leaveCountFrom': leaveCountFrom,
                'yearStartFrom': yearStartFrom
            }
        })
    });

    $(function () {
        $('input[name=leaves_start_from]').change(function () {
            ($(this).val() == 'year_start') ? $('#year_starts').show() : $('#year_starts').hide();
        });
    });

    function changeStatus(value) {

        var url = "{{ route('leaves-settings.changePermission') }}";
        var token = "{{ csrf_token() }}";
        var id = {{$leavePermission->id}};

        $.easyAjax({
            type: 'POST',
            url: url,
            data: {
                '_token': token,
                'value': value,
                'id': id,
            },
        });
    }
</script>
