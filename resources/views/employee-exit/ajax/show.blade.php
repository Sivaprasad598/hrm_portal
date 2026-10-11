<div class="row">
    <div class="col-sm-12">
        <div class="add-client bg-white rounded">
            <h4 class="mb-0 p-20 f-21 font-weight-700 text-capitalize border-bottom-grey d-flex align-items-center justify-content-between">
                <span>Exit Details — {{ $exit->employee->name }}</span>
                <a href="{{ route('employee-exit.edit', $exit->id) }}" class="btn btn-sm btn-outline-primary openRightModal">
                    <i class="fa fa-edit mr-1"></i> Edit
                </a>
            </h4>

            <div class="p-20">
                {{-- Status Banner --}}
                <div class="alert {{ $exit->employee->employeeDetail->employment_status === 'off_boarded' ? 'alert-danger' : 'alert-info' }} d-flex align-items-center mb-4">
                    @php $st = $exit->employee->employeeDetail->employment_status ?? 'notice_period'; @endphp
                    <i class="fa fa-info-circle mr-2 f-18"></i>
                    <div>
                        <strong>Status:</strong>
                        <span class="badge badge-{{ $st === 'off_boarded' ? 'danger' : ($st === 'notice_period' ? 'info' : 'warning') }} ml-1 px-2 py-1">
                            {{ ucfirst(str_replace('_', ' ', $st)) }}
                        </span>
                        @if($exit->last_working_date)
                            &nbsp;&nbsp;<strong>Last Working Date:</strong>
                            {{ $exit->last_working_date->format(company()->date_format) }}
                        @endif
                    </div>
                </div>

                {{-- Exit Info --}}
                <div class="row mb-3">
                    <div class="col-12 mb-3">
                        <h6 class="f-14 font-weight-700 text-uppercase text-dark-grey border-bottom pb-2">Exit Information</h6>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="f-12 text-muted mb-1">Exit Type</label>
                        <p class="mb-0 f-14 font-weight-600">{{ ucfirst(str_replace('_', ' ', $exit->exit_type ?? '--')) }}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="f-12 text-muted mb-1">Resignation / Exit Date</label>
                        <p class="mb-0 f-14 font-weight-600">{{ $exit->resignation_date ? $exit->resignation_date->format(company()->date_format) : '--' }}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="f-12 text-muted mb-1">Notice Period</label>
                        <p class="mb-0 f-14 font-weight-600">{{ $exit->notice_period_days ?? '--' }} days</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="f-12 text-muted mb-1">Last Working Date</label>
                        <p class="mb-0 f-14 font-weight-600">{{ $exit->last_working_date ? $exit->last_working_date->format(company()->date_format) : '--' }}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="f-12 text-muted mb-1">Initiated By</label>
                        <p class="mb-0 f-14 font-weight-600">{{ $exit->initiatedBy ? $exit->initiatedBy->name : '--' }}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="f-12 text-muted mb-1">Initiated Date</label>
                        <p class="mb-0 f-14 font-weight-600">{{ $exit->exit_initiated_date ? $exit->exit_initiated_date->format(company()->date_format) : '--' }}</p>
                    </div>
                    @if($exit->exit_reason)
                    <div class="col-12 mb-3">
                        <label class="f-12 text-muted mb-1">Exit Reason</label>
                        <p class="mb-0 f-14">{{ $exit->exit_reason }}</p>
                    </div>
                    @endif
                </div>

                {{-- Clearance Status --}}
                <div class="row mb-3">
                    <div class="col-12 mb-3">
                        <h6 class="f-14 font-weight-700 text-uppercase text-dark-grey border-bottom pb-2">Clearance Status</h6>
                    </div>
                    @php
                        $clearances = [
                            'Exit Interview'     => $exit->exit_interview_status,
                            'Asset Clearance'    => $exit->asset_clearance_status,
                            'Finance Clearance'  => $exit->finance_clearance_status,
                            'HR Clearance'       => $exit->hr_clearance_status,
                            'Final Settlement'   => $exit->final_settlement_status,
                        ];
                    @endphp
                    @foreach($clearances as $label => $status)
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="d-flex align-items-center">
                            <i class="fa {{ $status === 'completed' ? 'fa-check-circle text-success' : ($status === 'waived' ? 'fa-minus-circle text-muted' : 'fa-clock text-warning') }} mr-2 f-16"></i>
                            <div>
                                <p class="mb-0 f-13 text-muted">{{ $label }}</p>
                                <span class="badge badge-{{ $status === 'completed' ? 'success' : ($status === 'waived' ? 'secondary' : 'warning') }} px-2">
                                    {{ ucfirst($status) }}
                                </span>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Checklist Progress --}}
                @if($exit->checklists->count())
                <div class="mb-3">
                    <h6 class="f-14 font-weight-700 text-uppercase text-dark-grey border-bottom pb-2">Checklist Progress</h6>
                    @php
                        $total = $exit->checklists->count();
                        $done  = $exit->checklists->whereIn('status', ['completed', 'not_applicable'])->count();
                        $pct   = $total ? (int)round($done/$total*100) : 0;
                    @endphp
                    <div class="d-flex align-items-center mb-2">
                        <div class="progress flex-grow-1 mr-3" style="height:10px;">
                            <div class="progress-bar bg-success" style="width:{{ $pct }}%"></div>
                        </div>
                        <span class="f-13 font-weight-600">{{ $done }}/{{ $total }} ({{ $pct }}%)</span>
                    </div>
                    <a href="{{ route('employee-exit.checklist', $exit->id) }}" class="btn btn-sm btn-outline-secondary openRightModal">
                        <i class="fa fa-clipboard-check mr-1"></i> View Full Checklist
                    </a>
                </div>
                @endif

                @if($exit->remarks)
                <div class="mb-3">
                    <h6 class="f-14 font-weight-700 text-uppercase text-dark-grey border-bottom pb-2">Remarks</h6>
                    <p class="f-14">{{ $exit->remarks }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
