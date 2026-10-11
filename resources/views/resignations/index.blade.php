@extends('layouts.app')

@section('content')
<div class="content-wrapper">

    <div class="d-flex justify-content-between action-bar mb-3">
        <div id="table-actions">
            <span class="f-15 font-weight-600 text-dark">Resignation</span>
        </div>
    </div>

    @if($pendingResignation)
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3"
                     style="width:60px;height:60px;background:#fff3cd;">
                    <i class="fa fa-hourglass-half text-warning f-24"></i>
                </div>
                <h5 class="font-weight-700 mb-2">Resignation request pending review</h5>
                <p class="text-muted mb-0">
                    You submitted a resignation on
                    {{ $pendingResignation->resignation_date->format(company()->date_format) }}.
                    HR will review it shortly.
                </p>
            </div>
        </div>
    @elseif(in_array($employmentStatus, ['notice_period', 'off_boarded']))
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <p class="text-muted mb-0">Your exit is already being processed by HR. Please contact HR for details.</p>
            </div>
        </div>
    @else
        <x-form id="resignationForm">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">

                    {{-- Employee Information --}}
                    <h6 class="font-weight-700 mb-3">Employee Information</h6>
                    <div class="d-flex align-items-center p-3 rounded mb-3" style="background:#f8f9fa;">
                        @if($employee->image)
                            <img src="{{ asset_url('avatar/' . $employee->image) }}" class="rounded-circle mr-3" width="48" height="48" alt="">
                        @else
                            <img src="{{ asset('img/gravatar.png') }}" class="rounded-circle mr-3" width="48" height="48" alt="">
                        @endif
                        <div>
                            <strong class="f-15">{{ $employee->name }}</strong><br>
                            <span class="text-muted f-13">{{ $employee->email }}</span>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-3 col-sm-6 mb-2">
                            <p class="f-11 text-uppercase text-muted mb-1">Employee ID</p>
                            <p class="mb-0 font-weight-600">{{ $employeeDetail->employee_id ?? '--' }}</p>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2">
                            <p class="f-11 text-uppercase text-muted mb-1">Department</p>
                            <p class="mb-0 font-weight-600">{{ optional($employeeDetail->department)->team_name ?? '--' }}</p>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2">
                            <p class="f-11 text-uppercase text-muted mb-1">Designation</p>
                            <p class="mb-0 font-weight-600">{{ optional($employeeDetail->designation)->name ?? '--' }}</p>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2">
                            <p class="f-11 text-uppercase text-muted mb-1">Date of Joining</p>
                            <p class="mb-0 font-weight-600">
                                {{ optional($employeeDetail)->joining_date ? \Carbon\Carbon::parse($employeeDetail->joining_date)->format(company()->date_format) : '--' }}
                            </p>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2">
                            <p class="f-11 text-uppercase text-muted mb-1">Employment Status</p>
                            <span class="badge px-2 py-1 {{ match($employmentStatus) {
                                'probation' => 'badge-warning',
                                default => 'badge-success',
                            } }}">
                                {{ ucfirst(str_replace('_', ' ', $employmentStatus)) }}
                            </span>
                        </div>
                    </div>

                    <hr>

                    {{-- Resignation Details --}}
                    <h6 class="font-weight-700 mb-3 mt-3">Resignation Details</h6>

                    <div class="row">
                        <div class="col-lg-6 col-md-6">
                            <x-forms.datepicker fieldId="resignation_date" :fieldLabel="'Resignation Date'" fieldName="resignation_date"
                                fieldPlaceholder="" :fieldValue="now()->format(company()->date_format)" :fieldRequired="true" />
                        </div>

                        <div class="col-lg-6 col-md-6">
                            <x-forms.datepicker fieldId="notice_start_date" :fieldLabel="'Notice Period Start Date'" fieldName="notice_start_date"
                                fieldPlaceholder="" :fieldValue="now()->format(company()->date_format)" />
                        </div>

                        <div class="col-lg-6 col-md-6">
                            <label class="d-block">Notice Period Served?</label>
                            <x-forms.radio fieldId="noticeServedYes" fieldLabel="Yes" fieldName="notice_served" fieldValue="yes" />
                            <x-forms.radio fieldId="noticeServedNo" fieldLabel="No" fieldName="notice_served" fieldValue="no" :checked="true" />
                        </div>

                        <div class="col-lg-6 col-md-6">
                            <x-forms.select fieldId="reason" :fieldLabel="'Reason for Resignation'" fieldName="reason" :fieldRequired="true">
                                <option value="">Select reason</option>
                                <option>Better Opportunity</option>
                                <option>Personal Reasons</option>
                                <option>Relocation</option>
                                <option>Higher Studies</option>
                                <option>Health / Family Reasons</option>
                                <option>Career Change</option>
                                <option>Other</option>
                            </x-forms.select>
                        </div>

                        <div class="col-12">
                            <x-forms.textarea fieldId="remarks" :fieldLabel="'Resignation Remarks'" fieldName="remarks" :fieldRequired="false"
                                fieldPlaceholder="Enter additional details about your resignation..." />
                        </div>
                    </div>
                </div>

                <x-form-actions class="justify-content-end">
                    <x-forms.button-primary id="submitResignationBtn" icon="check">
                        Submit Resignation
                    </x-forms.button-primary>
                </x-form-actions>
            </div>
        </x-form>
    @endif

    {{-- Past requests --}}
    @if($resignations->isNotEmpty())
        <div class="d-flex flex-column w-tables rounded mt-4 bg-white">
            <div class="p-3 border-bottom">
                <span class="f-14 font-weight-600">Your Resignation History</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover border-0 w-100 mb-0">
                    <thead>
                        <tr>
                            <th>Submitted On</th>
                            <th>Resignation Date</th>
                            <th>Last Working Date</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($resignations as $resignation)
                            <tr>
                                <td>{{ $resignation->created_at->format(company()->date_format) }}</td>
                                <td>{{ optional($resignation->resignation_date)->format(company()->date_format) ?? '--' }}</td>
                                <td>{{ optional($resignation->last_working_date)->format(company()->date_format) ?? '--' }}</td>
                                <td>{{ $resignation->reason ?? '--' }}</td>
                                <td>
                                    <span class="badge px-2 py-1 {{ match($resignation->status) {
                                        'approved' => 'badge-success',
                                        'rejected' => 'badge-danger',
                                        default => 'badge-warning',
                                    } }}">
                                        {{ ucfirst($resignation->status) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex" style="gap:5px;">
                                        <a href="#" class="btn btn-xs btn-outline-secondary" onclick="viewResignationMessages({{ $resignation->id }}); return false;">
                                            <i class="fa fa-comments mr-1"></i>Messages
                                        </a>
                                        @if($resignation->status === 'pending')
                                            <a href="#" class="btn btn-xs btn-outline-primary" onclick="editResignation({{ $resignation->id }}); return false;">
                                                <i class="fa fa-edit mr-1"></i>Edit
                                            </a>
                                            <a href="#" class="btn btn-xs btn-outline-danger" onclick="deleteResignation({{ $resignation->id }}); return false;">
                                                <i class="fa fa-trash mr-1"></i>Delete
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</div>
@endsection

@push('scripts')
<script>
    $(function () {
        function recalcDates() {
            var resDate = $('#resignation_date').val();
            if (!resDate) {
                return;
            }
            var d = moment(resDate, '{{ company()->moment_date_format ?? 'DD-MM-YYYY' }}');
            if (!d.isValid()) {
                return;
            }

            $('#notice_start_date').val(d.format('{{ company()->moment_date_format ?? 'DD-MM-YYYY' }}'));
            if (typeof $('#notice_start_date').data('datepicker') !== 'undefined') {
                $('#notice_start_date').datepicker('update', d.toDate());
            }
        }

        $('#resignation_date').on('change', recalcDates);

        $('#submitResignationBtn').on('click', function () {
            var btn = $(this);
            var form = $('#resignationForm');

            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Submitting...');

            $.ajax({
                url: '{{ route('resignations.store') }}',
                method: 'POST',
                data: form.serialize(),
                success: function (res) {
                    if (res.status === 'success') {
                        toastr.success(res.message);
                        setTimeout(function () {
                            window.location.href = res.redirectUrl || '{{ route('resignations.index') }}';
                        }, 800);
                    } else {
                        toastr.error(res.message);
                        btn.prop('disabled', false).html('<i class="fa fa-check mr-1"></i> Submit Resignation');
                    }
                },
                error: function (xhr) {
                    var errors = xhr.responseJSON && xhr.responseJSON.errors;
                    if (errors) {
                        Object.values(errors).forEach(function (msgs) {
                            toastr.error(msgs[0]);
                        });
                    } else {
                        toastr.error('An error occurred. Please try again.');
                    }
                    btn.prop('disabled', false).html('<i class="fa fa-check mr-1"></i> Submit Resignation');
                }
            });
        });
    });

    const resignationData = @json($resignationJsData);

    const RESIGNATION_REASONS = ['Better Opportunity', 'Personal Reasons', 'Relocation', 'Higher Studies', 'Health / Family Reasons', 'Career Change', 'Other'];

    function escapeHtml(text) {
        return $('<div>').text(text || '').html();
    }

    function viewResignationMessages(id) {
        const data = resignationData[id];
        let html = '<div class="p-2 mb-2 rounded bg-white border">'
            + '<div class="d-flex justify-content-between mb-1"><span class="f-12 font-weight-700">You</span>'
            + '<span class="f-11 text-muted">' + escapeHtml(data.submitted_at) + '</span></div>'
            + '<div class="f-13 text-dark-grey">I would like to formally submit my resignation. Please consider this as my official resignation request.</div></div>';

        data.messages.forEach(function (m) {
            html += '<div class="p-2 mb-2 rounded bg-white border">'
                + '<div class="d-flex justify-content-between mb-1"><span class="f-12 font-weight-700' + (m.sender_type === 'admin' ? ' text-primary' : '') + '">'
                + escapeHtml(m.sender_name) + ' — ' + (m.sender_type === 'admin' ? 'Admin' : 'You') + '</span>'
                + '<span class="f-11 text-muted">' + escapeHtml(m.time) + '</span></div>'
                + '<div class="f-13 text-dark-grey">' + escapeHtml(m.message) + '</div></div>';
        });

        if (data.messages.length === 0) {
            html += '<div class="p-2 rounded bg-white border"><span class="f-13 text-muted">No response from HR yet.</span></div>';
        }

        html += '<div class="mt-3">'
            + '<label class="f-12 font-weight-600 mb-1">Send a message to HR</label>'
            + '<textarea id="employeeReplyMessage" class="form-control f-13" rows="3" placeholder="Type your message..."></textarea>'
            + '</div>';

        $('#modelHeading').text('Resignation Messages');
        $(MODAL_LG).find('.modal-body').html('<div class="p-3 rounded" style="background:#fafbfc;border:1px solid #e5e9ef;">' + html + '</div>');
        $(MODAL_LG).find('.modal-footer').html('<button type="button" class="btn-cancel rounded mr-3" data-dismiss="modal">Close</button><button type="button" class="btn-primary rounded" id="sendResignationMessageBtn"><i class="fa fa-paper-plane mr-1"></i>Send</button>');
        $('#sendResignationMessageBtn').off('click').on('click', function () {
            sendResignationMessage(id);
        });
        $(MODAL_LG).modal('show');
    }

    function sendResignationMessage(id) {
        const message = $('#employeeReplyMessage').val().trim();
        if (!message) {
            toastr.error('Please enter a message before sending.');
            return;
        }

        const btn = $('#sendResignationMessageBtn');
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i>Sending...');

        $.ajax({
            url: '{{ url('account/resignation') }}/' + id + '/reply',
            method: 'POST',
            data: {_token: '{{ csrf_token() }}', message: message},
            success: function (res) {
                if (res.status === 'success') {
                    toastr.success(res.message);
                    $(MODAL_LG).modal('hide');
                    setTimeout(function () {
                        window.location.reload();
                    }, 600);
                } else {
                    toastr.error(res.message);
                    btn.prop('disabled', false).html('<i class="fa fa-paper-plane mr-1"></i>Send');
                }
            },
            error: function () {
                toastr.error('An error occurred. Please try again.');
                btn.prop('disabled', false).html('<i class="fa fa-paper-plane mr-1"></i>Send');
            }
        });
    }

    function editResignation(id) {
        const data = resignationData[id];

        let reasonOptions = '<option value="">Select reason</option>';
        RESIGNATION_REASONS.forEach(function (r) {
            reasonOptions += '<option value="' + r + '"' + (data.reason === r ? ' selected' : '') + '>' + r + '</option>';
        });

        const html = '<form id="editResignationForm">'
            + '<div class="form-group"><label>Resignation Date <span class="text-danger">*</span></label>'
            + '<input type="date" class="form-control" id="edit_resignation_date" name="resignation_date" value="' + (data.resignation_date_ymd || '') + '"></div>'
            + '<div class="form-group"><label>Notice Period Served?</label><br>'
            + '<div class="custom-control custom-radio custom-control-inline"><input type="radio" class="custom-control-input" id="edit_noticeServedYes" name="notice_served" value="yes"' + (data.notice_served === 'yes' ? ' checked' : '') + '><label class="custom-control-label" for="edit_noticeServedYes">Yes</label></div>'
            + '<div class="custom-control custom-radio custom-control-inline"><input type="radio" class="custom-control-input" id="edit_noticeServedNo" name="notice_served" value="no"' + (data.notice_served !== 'yes' ? ' checked' : '') + '><label class="custom-control-label" for="edit_noticeServedNo">No</label></div>'
            + '</div>'
            + '<div class="form-group"><label>Reason for Resignation <span class="text-danger">*</span></label>'
            + '<select class="form-control" id="edit_reason" name="reason">' + reasonOptions + '</select></div>'
            + '<div class="form-group"><label>Resignation Remarks</label>'
            + '<textarea class="form-control" id="edit_remarks" name="remarks" rows="3">' + escapeHtml(data.remarks) + '</textarea></div>'
            + '</form>';

        $('#modelHeading').text('Edit Resignation Request');
        $(MODAL_LG).find('.modal-body').html(html);
        $(MODAL_LG).find('.modal-footer').html('<button type="button" class="btn-cancel rounded mr-3" data-dismiss="modal">Cancel</button><button type="button" class="btn-primary rounded" id="saveEditResignationBtn">Save changes</button>');
        $('#saveEditResignationBtn').off('click').on('click', function () {
            submitEditResignation(id);
        });
        $(MODAL_LG).modal('show');
    }

    function submitEditResignation(id) {
        const btn = $('#saveEditResignationBtn');
        btn.prop('disabled', true).text('Saving...');

        $.ajax({
            url: '{{ url('account/resignation') }}/' + id,
            method: 'PUT',
            data: {
                _token: '{{ csrf_token() }}',
                resignation_date: $('#edit_resignation_date').val(),
                notice_served: $('input[name=notice_served]:checked').val(),
                reason: $('#edit_reason').val(),
                remarks: $('#edit_remarks').val(),
            },
            success: function (res) {
                if (res.status === 'success') {
                    toastr.success(res.message);
                    $(MODAL_LG).modal('hide');
                    setTimeout(function () {
                        window.location.href = res.redirectUrl || '{{ route('resignations.index') }}';
                    }, 600);
                } else {
                    toastr.error(res.message);
                    btn.prop('disabled', false).text('Save changes');
                }
            },
            error: function (xhr) {
                const errors = xhr.responseJSON && xhr.responseJSON.errors;
                if (errors) {
                    Object.values(errors).forEach(function (msgs) {
                        toastr.error(msgs[0]);
                    });
                } else {
                    toastr.error('An error occurred. Please try again.');
                }
                btn.prop('disabled', false).text('Save changes');
            }
        });
    }

    function deleteResignation(id) {
        Swal.fire({
            title: 'Withdraw Resignation?',
            text: 'This will permanently withdraw your resignation request.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Withdraw',
            confirmButtonColor: '#dc3545',
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ url('account/resignation') }}/' + id,
                    method: 'DELETE',
                    data: {_token: '{{ csrf_token() }}'},
                    success: function (res) {
                        if (res.status === 'success') {
                            toastr.success(res.message);
                            setTimeout(function () {
                                window.location.href = res.redirectUrl || '{{ route('resignations.index') }}';
                            }, 600);
                        } else {
                            toastr.error(res.message);
                        }
                    },
                    error: function () {
                        toastr.error('An error occurred. Please try again.');
                    }
                });
            }
        });
    }
</script>
@endpush
