<div class="row">
    <div class="col-sm-12">
        <x-form id="initiateExitForm">
            <div class="add-client bg-white rounded">
                <h4 class="mb-0 p-20 f-21 font-weight-700 text-capitalize border-bottom-grey">
                    Initiate Exit — {{ $employee->name }}
                </h4>

                <input type="hidden" name="user_id" value="{{ $employee->id }}">

                <div class="row p-20">
                    {{-- Employee Info --}}
                    <div class="col-12 mb-3">
                        <div class="d-flex align-items-center p-3 rounded" style="background:#f8f9fa;">
                            @if($employee->image)
                                <img src="{{ asset_url('avatar/' . $employee->image) }}" class="rounded-circle mr-3" width="48" height="48" alt="">
                            @else
                                <img src="{{ asset('img/gravatar.png') }}" class="rounded-circle mr-3" width="48" height="48" alt="">
                            @endif
                            <div>
                                <strong class="f-15">{{ $employee->name }}</strong><br>
                                <span class="text-muted f-13">{{ $employee->email }}</span><br>
                                @if($employee->employeeDetail)
                                    <span class="text-muted f-12">Employee ID: {{ $employee->employeeDetail->employee_id ?? '--' }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Exit Type --}}
                    <div class="col-lg-6 col-md-6">
                        <x-forms.select fieldId="exit_type" :fieldLabel="'Exit Type'" fieldName="exit_type" :fieldRequired="true">
                            <option value="">-- Select Exit Type --</option>
                            <option value="resignation">Resignation</option>
                            <option value="termination">Termination</option>
                            <option value="contract_end">Contract End</option>
                            <option value="retirement">Retirement</option>
                            <option value="absconded">Absconded</option>
                            <option value="other">Other</option>
                        </x-forms.select>
                    </div>

                    {{-- Resignation Date --}}
                    <div class="col-lg-6 col-md-6">
                        <x-forms.datepicker fieldId="resignation_date" :fieldLabel="'Resignation / Exit Date'" fieldName="resignation_date"
                            :fieldValue="now()->format(company()->date_format)" :fieldRequired="true" />
                    </div>

                    {{-- Notice Period Days --}}
                    <div class="col-lg-6 col-md-6">
                        <x-forms.number fieldId="notice_period_days" :fieldLabel="'Notice Period (Days)'" fieldName="notice_period_days"
                            fieldValue="30" :fieldRequired="true" />
                    </div>

                    {{-- Last Working Date (auto-calculated) --}}
                    <div class="col-lg-6 col-md-6">
                        <x-forms.datepicker fieldId="last_working_date" :fieldLabel="'Last Working Date'" fieldName="last_working_date"
                            :fieldValue="now()->addDays(30)->format(company()->date_format)" :fieldRequired="true" />
                    </div>

                    {{-- Exit Reason --}}
                    <div class="col-12">
                        <x-forms.textarea fieldId="exit_reason" :fieldLabel="'Exit Reason'" fieldName="exit_reason" :fieldRequired="false"
                            fieldPlaceholder="Describe the reason for exit..." />
                    </div>

                    {{-- Remarks --}}
                    <div class="col-12">
                        <x-forms.textarea fieldId="exit_remarks" :fieldLabel="'HR Remarks'" fieldName="remarks" :fieldRequired="false"
                            fieldPlaceholder="Internal remarks (not visible to employee)..." />
                    </div>
                </div>

                <x-form-actions>
                    <x-forms.button-primary id="initiateExitBtn" class="mr-3" icon="check">
                        Initiate Exit
                    </x-forms.button-primary>
                    <x-forms.button-secondary id="closeInitiateForm">
                        @lang('app.cancel')
                    </x-forms.button-secondary>
                </x-form-actions>
            </div>
        </x-form>
    </div>
</div>

<script>
    $(function () {
        // Auto-calculate last working date when resignation date or notice days changes
        function recalcLastDate() {
            var resDate = $('#resignation_date').val();
            var days = parseInt($('#notice_period_days').val()) || 0;
            if (resDate) {
                var d = moment(resDate, '{{ company()->moment_date_format ?? 'DD-MM-YYYY' }}');
                if (d.isValid()) {
                    var lwd = d.add(days, 'days');
                    $('#last_working_date').val(lwd.format('{{ company()->moment_date_format ?? 'DD-MM-YYYY' }}'));
                    if (typeof $('#last_working_date').data('datepicker') !== 'undefined') {
                        $('#last_working_date').datepicker('update', lwd.toDate());
                    }
                }
            }
        }

        $('#resignation_date').on('change', recalcLastDate);
        $('#notice_period_days').on('input change', recalcLastDate);

        $('#initiateExitBtn').on('click', function () {
            var btn = $(this);
            var form = $('#initiateExitForm');

            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

            $.ajax({
                url: '{{ route('employee-exit.store') }}',
                method: 'POST',
                data: form.serialize(),
                success: function (res) {
                    if (res.status === 'success') {
                        toastr.success(res.message);
                        if (window.LaravelDataTables && window.LaravelDataTables['employee-exit-table']) {
                            window.LaravelDataTables['employee-exit-table'].draw();
                        }
                        $(RIGHT_MODAL).modal('hide');
                    } else {
                        toastr.error(res.message);
                    }
                },
                error: function (xhr) {
                    var errors = xhr.responseJSON && xhr.responseJSON.errors;
                    if (errors) {
                        Object.values(errors).forEach(function(msgs) {
                            toastr.error(msgs[0]);
                        });
                    } else {
                        toastr.error('An error occurred. Please try again.');
                    }
                },
                complete: function () {
                    btn.prop('disabled', false).html('<i class="fa fa-check mr-1"></i> Initiate Exit');
                }
            });
        });

        $('#closeInitiateForm').on('click', function () {
            $(RIGHT_MODAL).modal('hide');
        });
    });
</script>
