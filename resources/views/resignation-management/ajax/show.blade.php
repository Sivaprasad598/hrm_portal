@php
    $employee = $resignation->employee;
    $employeeDetail = $employee->employeeDetail;
@endphp
<div class="row">
    <div class="col-sm-12">
        <div class="add-client bg-white rounded">
            <h4 class="mb-0 p-20 f-21 font-weight-700 text-capitalize border-bottom-grey d-flex align-items-center justify-content-between">
                <span>Resignation Details — {{ $employee->name }}</span>
                <span class="badge px-2 py-1 {{ match($resignation->effective_status ?? $resignation->status) {
                    'notice_period' => 'badge-info',
                    'rejected'      => 'badge-danger',
                    'completed'     => 'badge-secondary',
                    default         => 'badge-warning',
                } }}">
                    {{ ucfirst(str_replace('_', ' ', $resignation->status === 'approved'
                        ? ($resignation->last_working_date && $resignation->last_working_date->isPast() ? 'completed' : 'notice_period')
                        : $resignation->status)) }}
                </span>
            </h4>

            <div class="p-20">

                {{-- Employee Information --}}
                <div class="row mb-3">
                    <div class="col-12 mb-3">
                        <h6 class="f-14 font-weight-700 text-uppercase text-dark-grey border-bottom pb-2">Employee Information</h6>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="f-12 text-muted mb-1">Employee</label>
                        <p class="mb-0 f-14 font-weight-600">{{ $employee->name }}</p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="f-12 text-muted mb-1">Employee ID</label>
                        <p class="mb-0 f-14 font-weight-600">{{ $employeeDetail->employee_id ?? '--' }}</p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="f-12 text-muted mb-1">Department</label>
                        <p class="mb-0 f-14 font-weight-600">{{ optional($employeeDetail->department ?? null)->team_name ?? '--' }}</p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="f-12 text-muted mb-1">Designation</label>
                        <p class="mb-0 f-14 font-weight-600">{{ optional($employeeDetail->designation ?? null)->name ?? '--' }}</p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="f-12 text-muted mb-1">Date of Joining</label>
                        <p class="mb-0 f-14 font-weight-600">
                            {{ optional($employeeDetail)->joining_date ? \Carbon\Carbon::parse($employeeDetail->joining_date)->format(company()->date_format) : '--' }}
                        </p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="f-12 text-muted mb-1">Employment Status</label>
                        <p class="mb-0"><span class="badge badge-light border px-2 py-1">{{ ucfirst(str_replace('_', ' ', $employmentStatus)) }}</span></p>
                    </div>
                </div>

                {{-- Resignation Information --}}
                <div class="row mb-3">
                    <div class="col-12 mb-3">
                        <h6 class="f-14 font-weight-700 text-uppercase text-dark-grey border-bottom pb-2">Resignation Information</h6>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="f-12 text-muted mb-1">Resignation Date</label>
                        <p class="mb-0 f-14 font-weight-600">{{ optional($resignation->resignation_date)->format(company()->date_format) ?? '--' }}</p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="f-12 text-muted mb-1">Notice Period</label>
                        @if($resignation->status === 'pending')
                            <select id="approveNoticePeriod" class="form-control form-control-sm">
                                <option value="">Select Notice Period</option>
                                @foreach($noticePeriodOptions as $option)
                                    <option value="{{ $option->id }}">
                                        {{ $option->name ?: ($option->months . ' Month' . ($option->months == 1 ? '' : 's')) }}
                                    </option>
                                @endforeach
                            </select>
                            @if($noticePeriodOptions->isEmpty())
                                <p class="mb-0 f-11 text-danger mt-1">No notice periods configured yet. Add one in Notice Period Settings before approving.</p>
                            @endif
                        @else
                            <p class="mb-0 f-14 font-weight-600">{{ $resignation->notice_period_days ?? '--' }} Days</p>
                        @endif
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="f-12 text-muted mb-1">Selected Notice Period</label>
                        <p class="mb-0 f-14 font-weight-600">{{ $resignation->status === 'pending' ? '--' : $selectedNoticePeriodLabel }}</p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="f-12 text-muted mb-1">Notice Start Date</label>
                        <p class="mb-0 f-14 font-weight-600">{{ optional($resignation->notice_start_date)->format(company()->date_format) ?? '--' }}</p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="f-12 text-muted mb-1">Proposed Last Working Date</label>
                        <p class="mb-0 f-14 font-weight-600">{{ optional($resignation->last_working_date)->format(company()->date_format) ?? '--' }}</p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="f-12 text-muted mb-1">Notice Period Served</label>
                        <p class="mb-0 f-14 font-weight-600">{{ $resignation->notice_served ? ucfirst($resignation->notice_served) : '--' }}</p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="f-12 text-muted mb-1">Exit Type</label>
                        <p class="mb-0 f-14 font-weight-600">{{ ucfirst($resignation->exit_type ?? '--') }}</p>
                    </div>
                </div>

                {{-- Reason --}}
                <div class="mb-3">
                    <h6 class="f-14 font-weight-700 text-uppercase text-dark-grey border-bottom pb-2">Employee Reason</h6>
                    <p class="mb-1 f-14 font-weight-600">{{ $resignation->reason ?? '--' }}</p>
                    @if($resignation->remarks)
                        <p class="mb-0 f-13 text-muted">{{ $resignation->remarks }}</p>
                    @endif
                </div>

                @if($resignation->status === 'rejected' && $resignation->admin_remarks)
                    <div class="alert alert-danger">
                        <strong>Rejection Reason:</strong> {{ $resignation->admin_remarks }}
                    </div>
                @endif

                {{-- Communication --}}
                <div class="mb-3">
                    <h6 class="f-14 font-weight-700 text-uppercase text-dark-grey border-bottom pb-2">Resignation Communication</h6>

                    <div class="p-3 rounded" style="background:#fafbfc; border:1px solid #e5e9ef;">
                        <div class="p-2 mb-2 rounded bg-white border">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="f-12 font-weight-700">{{ $employee->name }} — Employee</span>
                                <span class="f-11 text-muted">{{ $resignation->created_at->format(company()->date_format . ' h:i A') }}</span>
                            </div>
                            <div class="f-13 text-dark-grey">
                                I would like to formally submit my resignation. Please consider this as my official resignation request.
                            </div>
                        </div>

                        @forelse($resignation->replies as $reply)
                            <div class="p-2 mb-2 rounded bg-white border">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="f-12 font-weight-700 {{ $reply->sender_type === 'admin' ? 'text-primary' : '' }}">
                                        {{ $reply->sender_type === 'admin' ? ($reply->sender->name ?? 'Admin') : $employee->name }}
                                        — {{ $reply->sender_type === 'admin' ? 'Admin' : 'Employee' }}
                                    </span>
                                    <span class="f-11 text-muted">{{ $reply->created_at->format(company()->date_format . ' h:i A') }}</span>
                                </div>
                                <div class="f-13 text-dark-grey">{{ $reply->message }}</div>
                            </div>
                        @empty
                            <div class="p-2 mb-0 rounded bg-white border">
                                <span class="f-13 text-muted">No response yet.</span>
                            </div>
                        @endforelse

                        <div class="mt-3">
                            <label class="f-12 font-weight-600 mb-1">Reply to Employee</label>
                            <textarea id="adminReply" class="form-control f-13" rows="3" placeholder="Enter your response to the employee..."></textarea>
                            <button type="button" id="sendReplyBtn" class="btn btn-sm btn-primary mt-2" onclick="sendResignationReply({{ $resignation->id }})">
                                <i class="fa fa-paper-plane mr-1"></i> Send Reply
                            </button>
                        </div>
                    </div>
                </div>

                @if($resignation->status === 'pending')
                    <div class="d-flex justify-content-end border-top pt-3" style="gap:10px;">
                        <button type="button" class="btn btn-outline-danger" onclick="rejectResignation({{ $resignation->id }})">
                            <i class="fa fa-times mr-1"></i> Reject Resignation
                        </button>
                        <button type="button" class="btn btn-success" onclick="approveResignation({{ $resignation->id }})">
                            <i class="fa fa-check mr-1"></i> Approve Resignation
                        </button>
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>

<script>
    function sendResignationReply(id) {
        var message = $('#adminReply').val().trim();
        if (!message) {
            toastr.error('Please enter a response before sending.');
            return;
        }

        var btn = $('#sendReplyBtn');
        btn.prop('disabled', true);

        $.post('{{ url('account/resignation-management') }}/' + id + '/reply', {
            _token: '{{ csrf_token() }}',
            message: message,
        }).done(function (res) {
            if (res.status === 'success') {
                toastr.success(res.message);
                $('#adminReply').val('');
                $.get('{{ url('account/resignation-management') }}/' + id, function (showRes) {
                    if (showRes.status === 'success') {
                        $(RIGHT_MODAL_CONTENT).html(showRes.html);
                    }
                });
            } else {
                toastr.error(res.message);
            }
        }).always(function () {
            btn.prop('disabled', false);
        });
    }
</script>
