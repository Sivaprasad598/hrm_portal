@extends('layouts.app')

@section('content')
<div class="content-wrapper">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h4 class="f-18 mb-0">{{ $batch ? 'Edit: ' . $batch->name : ($kind === 'single' ? 'Salary revision' : 'New appraisal cycle') }}</h4>
            <p class="f-13 text-dark-grey mb-0">Enter the increment as a %, an amount or the new CTC (annual). Change the designation for a promotion.
                Saved as a draft until you send it for approval (CFO, then CEO).</p>
        </div>
        <a href="{{ $batch ? route('salary-revisions.show', $batch->id) : route('salary-revisions.index') }}" class="btn btn-outline-secondary rounded f-14">Back</a>
    </div>

    @if ($batch && $batch->status === 'rejected')
        <div class="alert alert-danger f-13"><strong>Sent back:</strong> {{ $batch->reject_reason }}</div>
    @endif

    <form id="sr-form" class="bg-white rounded b-shadow-4 p-3 mb-3">
        @csrf
        <input type="hidden" name="id" value="{{ $batch->id ?? '' }}">
        <input type="hidden" name="kind" value="{{ $kind }}">
        <div class="row">
            <div class="col-md-5 form-group">
                <label class="f-14 text-dark-grey">Name <span class="text-red">*</span></label>
                <input type="text" name="name" class="form-control" maxlength="150" value="{{ $defaultName }}">
            </div>
            <div class="col-md-3 form-group">
                <label class="f-14 text-dark-grey">Effective date <span class="text-red">*</span></label>
                <input type="date" name="effective_date" id="sr-effective" class="form-control"
                       value="{{ $batch ? $batch->effective_date : \App\Services\Payroll\SalaryRevisions::appliesFrom(now()->addDay())->toDateString() }}">
            </div>
            <div class="col-md-4 form-group">
                <label class="f-14 text-dark-grey">Paid from</label>
                <div class="form-control bg-light" id="sr-applies">…</div>
                <small class="text-lightest">The first salary month starting on or after the effective date.</small>
            </div>
            <div class="col-md-12 form-group mb-0">
                <label class="f-14 text-dark-grey">Note for the approvers</label>
                <input type="text" name="note" class="form-control" maxlength="1000" value="{{ $batch->note ?? '' }}" placeholder="Optional: the basis of this cycle, budget, etc.">
            </div>
        </div>
    </form>

    @if ($kind === 'cycle')
        <div class="bg-white rounded b-shadow-4 p-3 mb-3">
            <div class="row align-items-end">
                <div class="col-lg-4 col-md-6 mb-2">
                    <label class="f-13 text-dark-grey mb-1">Add employees</label>
                    <select id="sr-pick" class="form-control select-picker" multiple data-live-search="true" data-actions-box="true" title="Choose employees" data-size="8">
                        @foreach ($employees as $e)<option value="{{ $e->id }}" data-subtext="{{ $e->employee_id }}">{{ $e->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-lg-1 col-md-2 mb-2"><button type="button" class="btn btn-outline-primary btn-block" id="sr-add">Add</button></div>
                <div class="col-lg-3 col-md-4 mb-2">
                    <label class="f-13 text-dark-grey mb-1">Add a department</label>
                    <div class="d-flex">
                        <select id="sr-dept" class="form-control select-picker mr-1" data-live-search="true">
                            <option value="all">Everyone with a salary</option>
                            @foreach ($departments as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach
                        </select>
                        <button type="button" class="btn btn-outline-primary" id="sr-add-dept">Add</button>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6 mb-2">
                    <label class="f-13 text-dark-grey mb-1">Same % for everyone</label>
                    <div class="input-group"><input type="number" step="0.01" id="sr-all-pct" class="form-control" placeholder="10">
                        <div class="input-group-append"><button type="button" class="btn btn-outline-secondary" id="sr-apply-pct">Apply</button></div></div>
                </div>
                <div class="col-lg-2 col-md-6 mb-2 text-right">
                    <a href="{{ route('salary-revisions.template') }}" class="btn btn-outline-secondary btn-sm mb-1" id="sr-template"><i class="fa fa-download mr-1"></i>Excel template</a>
                    <label class="btn btn-outline-secondary btn-sm mb-1"><i class="fa fa-upload mr-1"></i>Import<input type="file" id="sr-file" accept=".xlsx,.xls,.csv" hidden></label>
                </div>
            </div>
            <div id="sr-import-msg" class="f-12"></div>
        </div>
    @endif

    <div class="bg-white rounded b-shadow-4 mb-3">
        <div class="table-responsive sr-wrap">
            <table class="table table-sm mb-0 f-13 sr-grid" style="overflow: visible !important">
                <thead class="bg-light">
                    <tr>
                        <th class="sr-name">Employee</th><th class="text-right">Current CTC</th><th style="width:90px">Hike %</th><th style="width:120px">Hike (annual)</th>
                        <th style="width:130px">New CTC</th><th class="text-right">New monthly gross</th><th style="width:140px">Type</th>
                        <th style="width:190px">New designation</th><th style="width:170px">New department</th><th style="width:80px">Basic %</th><th style="width:200px">Reason</th><th></th>
                    </tr>
                </thead>
                <tbody id="sr-rows"></tbody>
                <tfoot><tr class="font-weight-bold bg-light" id="sr-total"></tr></tfoot>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-end mb-4">
        <button type="button" class="btn btn-secondary rounded f-14 mr-2 sr-save" data-submit="0"><i class="fa fa-save mr-1"></i>Save draft</button>
        <button type="button" class="btn btn-primary rounded f-14 sr-save" data-submit="1"><i class="fa fa-paper-plane mr-1"></i>Save &amp; send for approval</button>
    </div>
</div>

<style>
    .sr-wrap { max-height: 62vh; overflow: auto; }
    .sr-grid { border-collapse: separate; border-spacing: 0; }
    .sr-grid thead th { position: sticky; top: 0; z-index: 2; background: #f8f9fa; white-space: nowrap; }
    .sr-grid tfoot td { position: sticky; bottom: 0; background: #f8f9fa; z-index: 2; }
    .sr-grid .sr-name { position: sticky; left: 0; background: #fff; z-index: 1; min-width: 210px; }
    .sr-grid thead .sr-name, .sr-grid tfoot .sr-name { z-index: 3; background: #f8f9fa; }
    .sr-grid td { vertical-align: middle; }
    .sr-grid .form-control-sm { min-width: 70px; }
</style>
@endsection

@push('scripts')
<script>
$(function () {
    var rows = @json($rows);
    var designations = @json($designations);
    var departments = @json($departments);
    var types = @json($types);
    var inr = function (v) { return '₹' + Math.round(v || 0).toLocaleString('en-IN'); };
    var esc = function (v) { return $('<div>').text(v == null ? '' : v).html(); };
    var opts = function (list, sel, blank) {
        var h = '<option value="">' + blank + '</option>';
        $.each(list, function (id, name) { h += '<option value="' + id + '"' + (String(sel) === String(id) ? ' selected' : '') + '>' + esc(name) + '</option>'; });
        return h;
    };

    function draw() {
        $('#sr-rows').html(rows.map(function (r, i) {
            var inc = r.new_ctc - r.old_ctc, pct = r.old_ctc > 0 ? inc / r.old_ctc * 100 : 0;
            var typeOpts = ''; $.each(types, function (k, v) { typeOpts += '<option value="' + k + '"' + (r.type === k ? ' selected' : '') + '>' + v + '</option>'; });
            return '<tr data-i="' + i + '">' +
                '<td class="sr-name"><span class="font-weight-500">' + esc(r.name) + '</span><br><span class="f-11 text-lightest">' + esc(r.code) + ' · ' + esc(r.designation) + (r.department ? ' · ' + esc(r.department) : '') + '</span></td>' +
                '<td class="text-right">' + inr(r.old_ctc) + '<br><span class="f-11 text-lightest">' + inr(r.old_ctc / 12) + '/m</span></td>' +
                '<td><input type="number" step="0.01" class="form-control form-control-sm sr-pct" value="' + (Math.round(pct * 100) / 100) + '"></td>' +
                '<td><input type="number" step="1" class="form-control form-control-sm sr-amt" value="' + Math.round(inc) + '"></td>' +
                '<td><input type="number" step="1" class="form-control form-control-sm sr-new" value="' + Math.round(r.new_ctc) + '"></td>' +
                '<td class="text-right sr-gross">' + inr(r.new_ctc / 12) + '</td>' +
                '<td><select class="form-control form-control-sm sr-type">' + typeOpts + '</select></td>' +
                '<td><select class="form-control form-control-sm sr-desig">' + opts(designations, r.new_designation_id, 'No change') + '</select></td>' +
                '<td><select class="form-control form-control-sm sr-dept">' + opts(departments, r.new_department_id, 'No change') + '</select></td>' +
                '<td><input type="number" step="0.01" class="form-control form-control-sm sr-basic" value="' + (r.new_basic_pct == null ? '' : r.new_basic_pct) + '" placeholder="' + (r.old_basic_pct == null ? 'auto' : r.old_basic_pct) + '"></td>' +
                '<td><input type="text" class="form-control form-control-sm sr-reason" maxlength="500" value="' + esc(r.reason) + '"></td>' +
                '<td><a href="javascript:;" class="text-danger sr-remove" title="Remove"><i class="fa fa-times"></i></a></td></tr>';
        }).join('') || '<tr><td colspan="12" class="text-center text-lightest py-4">Add employees above, or import the Excel template.</td></tr>');
        totals();
    }

    function totals() {
        var o = 0, n = 0;
        rows.forEach(function (r) { o += +r.old_ctc; n += +r.new_ctc; });
        $('#sr-total').html('<td class="sr-name">' + rows.length + ' employee(s)</td><td class="text-right">' + inr(o) + '</td>' +
            '<td>' + (o > 0 ? (Math.round((n - o) / o * 10000) / 100) + '%' : '') + '</td><td>' + inr(n - o) + '</td><td>' + inr(n) + '</td>' +
            '<td class="text-right">' + inr((n - o) / 12) + ' more a month</td><td colspan="6"></td>');
    }

    $('#sr-rows').on('input change', 'input, select', function () {
        var tr = $(this).closest('tr'), r = rows[tr.data('i')], el = $(this);
        if (el.hasClass('sr-pct')) { r.new_ctc = Math.round(r.old_ctc * (1 + (parseFloat(el.val()) || 0) / 100)); tr.find('.sr-amt').val(r.new_ctc - r.old_ctc); tr.find('.sr-new').val(r.new_ctc); }
        if (el.hasClass('sr-amt')) { r.new_ctc = Math.round(r.old_ctc + (parseFloat(el.val()) || 0)); tr.find('.sr-new').val(r.new_ctc); tr.find('.sr-pct').val(r.old_ctc > 0 ? Math.round((r.new_ctc - r.old_ctc) / r.old_ctc * 10000) / 100 : 0); }
        if (el.hasClass('sr-new')) { r.new_ctc = parseFloat(el.val()) || 0; tr.find('.sr-amt').val(Math.round(r.new_ctc - r.old_ctc)); tr.find('.sr-pct').val(r.old_ctc > 0 ? Math.round((r.new_ctc - r.old_ctc) / r.old_ctc * 10000) / 100 : 0); }
        if (el.hasClass('sr-type')) { r.type = el.val(); }
        if (el.hasClass('sr-desig')) { r.new_designation_id = el.val() || null; if (r.new_designation_id && r.type === 'appraisal') { r.type = 'promotion'; tr.find('.sr-type').val('promotion'); } }
        if (el.hasClass('sr-dept')) { r.new_department_id = el.val() || null; }
        if (el.hasClass('sr-basic')) { r.new_basic_pct = el.val() === '' ? null : parseFloat(el.val()); }
        if (el.hasClass('sr-reason')) { r.reason = el.val(); }
        tr.find('.sr-gross').text(inr(r.new_ctc / 12));
        totals();
    });

    $('#sr-rows').on('click', '.sr-remove', function () { rows.splice($(this).closest('tr').data('i'), 1); draw(); });

    function merge(list) {
        var have = rows.map(function (r) { return String(r.user_id); });
        var added = 0;
        list.forEach(function (r) {
            var at = have.indexOf(String(r.user_id));
            if (at === -1) { rows.push(r); have.push(String(r.user_id)); added++; } else { rows[at] = r; }
        });
        rows.sort(function (a, b) { return a.name.localeCompare(b.name); });
        draw();
        return added;
    }

    function fetchRows(data) {
        data.effective_date = $('#sr-effective').val();
        $.easyAjax({ url: "{{ route('salary-revisions.rows') }}", type: 'GET', blockUI: true, data: data,
            success: function (r) { if (r.status === 'success') { merge(r.rows); } } });
    }

    $('#sr-add').on('click', function () { var ids = $('#sr-pick').val() || []; if (ids.length) { fetchRows({ user_ids: ids }); $('#sr-pick').selectpicker('deselectAll'); } });
    $('#sr-add-dept').on('click', function () { fetchRows({ department: $('#sr-dept').val() }); });
    $('#sr-apply-pct').on('click', function () {
        var p = parseFloat($('#sr-all-pct').val()) || 0;
        rows.forEach(function (r) { r.new_ctc = Math.round(r.old_ctc * (1 + p / 100)); });
        draw();
    });
    $('#sr-template').on('click', function (e) {
        var d = $('#sr-dept').val();
        if (d && d !== 'all') { e.preventDefault(); window.location = this.href + '?department=' + d; }
    });
    $('#sr-file').on('change', function () {
        if (!this.files.length) { return; }
        var fd = new FormData(); fd.append('import_file', this.files[0]); fd.append('_token', '{{ csrf_token() }}');
        var input = this;
        $.ajax({ url: "{{ route('salary-revisions.import') }}", type: 'POST', data: fd, processData: false, contentType: false,
            success: function (r) {
                if (r.status === 'success') {
                    var added = merge(r.rows);
                    $('#sr-import-msg').html('<span class="text-success">' + r.rows.length + ' row(s) read, ' + added + ' added.</span>' +
                        (r.errors.length ? '<div class="text-danger mt-1">' + r.errors.map(esc).join('<br>') + '</div>' : ''));
                } else { $('#sr-import-msg').html('<span class="text-danger">' + esc(r.message) + '</span>'); }
            },
            error: function (x) { $('#sr-import-msg').html('<span class="text-danger">' + esc((x.responseJSON && x.responseJSON.message) || 'The file could not be read.') + '</span>'); },
            complete: function () { input.value = ''; }
        });
    });

    function applies() {
        $.easyAjax({ url: "{{ route('salary-revisions.applies') }}", type: 'GET', data: { effective_date: $('#sr-effective').val() },
            success: function (r) { if (r.status === 'success') { $('#sr-applies').html('<strong>' + r.month + '</strong> payroll <span class="text-lightest">(' + r.period + ')</span>'); } } });
    }
    $('#sr-effective').on('change', applies);
    applies();

    $('.sr-save').on('click', function () {
        var submit = $(this).data('submit');
        var data = $('#sr-form').serializeArray().reduce(function (o, f) { o[f.name] = f.value; return o; }, {});
        data.submit = submit;
        data.rows = rows.map(function (r) { return { user_id: r.user_id, type: r.type, new_ctc: r.new_ctc, new_basic_pct: r.new_basic_pct,
            new_designation_id: r.new_designation_id, new_department_id: r.new_department_id, reason: r.reason }; });
        $.easyAjax({ url: "{{ route('salary-revisions.store') }}", type: 'POST', container: '#sr-form', blockUI: true, data: data,
            disableButton: true, buttonSelector: '.sr-save' });
    });

    draw();
});
</script>
@endpush
