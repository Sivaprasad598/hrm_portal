<div class="row">
    <div class="col-sm-12">
        <x-form id="editExitForm">
            <div class="add-client bg-white rounded">
                <h4 class="mb-0 p-20 f-21 font-weight-700 text-capitalize border-bottom-grey">
                    Edit Exit — {{ $exit->employee->name }}
                </h4>

                <input type="hidden" name="_method" value="PUT">

                <div class="row p-20">
                    {{-- Exit Type --}}
                    <div class="col-lg-6 col-md-6">
                        <x-forms.select fieldId="edit_exit_type" :fieldLabel="'Exit Type'" fieldName="exit_type" :fieldRequired="true">
                            <option value="">-- Select --</option>
                            @foreach(['resignation'=>'Resignation','termination'=>'Termination','contract_end'=>'Contract End','retirement'=>'Retirement','absconded'=>'Absconded','other'=>'Other'] as $val => $label)
                                <option value="{{ $val }}" {{ $exit->exit_type === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </x-forms.select>
                    </div>

                    {{-- Resignation Date --}}
                    <div class="col-lg-6 col-md-6">
                        <x-forms.datepicker fieldId="edit_resignation_date" :fieldLabel="'Resignation / Exit Date'" fieldName="resignation_date"
                            :fieldValue="$exit->resignation_date ? $exit->resignation_date->format(company()->date_format) : ''" :fieldRequired="true" />
                    </div>

                    {{-- Notice Period Days --}}
                    <div class="col-lg-6 col-md-6">
                        <x-forms.number fieldId="edit_notice_period_days" :fieldLabel="'Notice Period (Days)'" fieldName="notice_period_days"
                            :fieldValue="$exit->notice_period_days ?? 30" :fieldRequired="true" />
                    </div>

                    {{-- Last Working Date --}}
                    <div class="col-lg-6 col-md-6">
                        <x-forms.datepicker fieldId="edit_last_working_date" :fieldLabel="'Last Working Date'" fieldName="last_working_date"
                            :fieldValue="$exit->last_working_date ? $exit->last_working_date->format(company()->date_format) : ''" :fieldRequired="true" />
                    </div>

                    {{-- Clearance Status --}}
                    <div class="col-12 mt-2 mb-2">
                        <h6 class="f-14 font-weight-700 text-dark-grey border-bottom pb-2">Clearance Status</h6>
                    </div>

                    <div class="col-lg-4 col-md-6">
                        <x-forms.select fieldId="edit_exit_interview_status" :fieldLabel="'Exit Interview'" fieldName="exit_interview_status">
                            <option value="pending"   {{ $exit->exit_interview_status === 'pending'   ? 'selected' : '' }}>Pending</option>
                            <option value="completed" {{ $exit->exit_interview_status === 'completed' ? 'selected' : '' }}>Completed</option>
                            <option value="waived"    {{ $exit->exit_interview_status === 'waived'    ? 'selected' : '' }}>Waived</option>
                        </x-forms.select>
                    </div>

                    <div class="col-lg-4 col-md-6">
                        <x-forms.select fieldId="edit_asset_clearance_status" :fieldLabel="'Asset Clearance'" fieldName="asset_clearance_status">
                            <option value="pending"   {{ $exit->asset_clearance_status === 'pending'   ? 'selected' : '' }}>Pending</option>
                            <option value="completed" {{ $exit->asset_clearance_status === 'completed' ? 'selected' : '' }}>Completed</option>
                        </x-forms.select>
                    </div>

                    <div class="col-lg-4 col-md-6">
                        <x-forms.select fieldId="edit_finance_clearance_status" :fieldLabel="'Finance Clearance'" fieldName="finance_clearance_status">
                            <option value="pending"   {{ $exit->finance_clearance_status === 'pending'   ? 'selected' : '' }}>Pending</option>
                            <option value="completed" {{ $exit->finance_clearance_status === 'completed' ? 'selected' : '' }}>Completed</option>
                        </x-forms.select>
                    </div>

                    <div class="col-lg-4 col-md-6">
                        <x-forms.select fieldId="edit_hr_clearance_status" :fieldLabel="'HR Clearance'" fieldName="hr_clearance_status">
                            <option value="pending"   {{ $exit->hr_clearance_status === 'pending'   ? 'selected' : '' }}>Pending</option>
                            <option value="completed" {{ $exit->hr_clearance_status === 'completed' ? 'selected' : '' }}>Completed</option>
                        </x-forms.select>
                    </div>

                    <div class="col-lg-4 col-md-6">
                        <x-forms.select fieldId="edit_final_settlement_status" :fieldLabel="'Final Settlement'" fieldName="final_settlement_status">
                            <option value="pending"   {{ $exit->final_settlement_status === 'pending'   ? 'selected' : '' }}>Pending</option>
                            <option value="completed" {{ $exit->final_settlement_status === 'completed' ? 'selected' : '' }}>Completed</option>
                        </x-forms.select>
                    </div>

                    {{-- Exit Reason --}}
                    <div class="col-12">
                        <x-forms.textarea fieldId="edit_exit_reason" :fieldLabel="'Exit Reason'" fieldName="exit_reason"
                            :fieldValue="$exit->exit_reason" />
                    </div>

                    {{-- Remarks --}}
                    <div class="col-12">
                        <x-forms.textarea fieldId="edit_remarks" :fieldLabel="'HR Remarks'" fieldName="remarks"
                            :fieldValue="$exit->remarks" />
                    </div>
                </div>

                <x-form-actions>
                    <x-forms.button-primary id="saveEditExitBtn" class="mr-3" icon="check">
                        @lang('app.save')
                    </x-forms.button-primary>
                    <x-forms.button-secondary id="closeEditExitForm">
                        @lang('app.cancel')
                    </x-forms.button-secondary>
                </x-form-actions>
            </div>
        </x-form>
    </div>
</div>

<script>
    $(function () {
        $('#saveEditExitBtn').on('click', function () {
            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

            $.ajax({
                url: '{{ route('employee-exit.update', $exit->id) }}',
                method: 'POST',
                data: $('#editExitForm').serialize(),
                success: function (res) {
                    if (res.status === 'success') {
                        toastr.success(res.message);
                        if (typeof window['employee-exit-table'] !== 'undefined') {
                            window['employee-exit-table'].draw();
                        }
                        $(RIGHT_MODAL).modal('hide');
                    } else {
                        toastr.error(res.message);
                    }
                },
                error: function (xhr) {
                    var errors = xhr.responseJSON && xhr.responseJSON.errors;
                    if (errors) {
                        Object.values(errors).forEach(function(msgs) { toastr.error(msgs[0]); });
                    } else {
                        toastr.error('An error occurred.');
                    }
                },
                complete: function () {
                    btn.prop('disabled', false).html('<i class="fa fa-check mr-1"></i> @lang('app.save')');
                }
            });
        });

        $('#closeEditExitForm').on('click', function () {
            $(RIGHT_MODAL).modal('hide');
        });
    });
</script>
