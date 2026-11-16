@extends('layouts.app')

@push('styles')
@include('letter-generator.partials.styles')
@endpush

@section('content')
@php $lastMonth = now()->subMonthNoOverflow()->format('Y-m'); @endphp
<div class="content-wrapper">
    @include('letter-generator.partials.header')

    <div class="lg-wrap">
        <form id="gen-form" class="lg-form" onsubmit="return false;">
            @csrf
            <input type="hidden" name="type" value="payslip">

            <div class="lg-card">
                <h5>Fill from an employee (optional)</h5>
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1 min-w-0">
                        <select id="gen-prefill" class="form-control select-picker" data-live-search="true" data-size="8" title="Search by name or employee ID">
                            @foreach ($people as $p)
                                <option value="{{ $p->id }}" data-tokens="{{ $p->code }} {{ $p->name }}" data-subtext="{{ $p->code }}{{ $p->last_date ? ' · left' : '' }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="button" id="gen-clear" class="btn btn-secondary rounded f-13 ml-2 px-3 d-none" title="Clear the employee and start a blank form">
                        <i class="fa fa-times mr-1"></i>Clear
                    </button>
                </div>
                <div class="lg-hint mt-1">Their record fills the form; your changes are for these payslips only. Nothing is saved.</div>
            </div>

            <div class="lg-card">
                <h5>Employee</h5>
                <div class="row">
                    <div class="col-8 form-group">
                        <label>Name *</label>
                        <input type="text" name="d[name]" class="form-control" maxlength="150">
                    </div>
                    <div class="col-4 form-group">
                        <label>Employee ID</label>
                        <input type="text" name="d[code]" class="form-control" maxlength="40">
                    </div>
                </div>
                <div class="row">
                    <div class="col-6 form-group">
                        <label>Designation</label>
                        <input type="text" name="d[designation]" class="form-control" list="gen-designations">
                    </div>
                    <div class="col-6 form-group">
                        <label>Department</label>
                        <input type="text" name="d[department]" class="form-control" list="gen-departments">
                    </div>
                </div>
                <div class="row">
                    <div class="col-6 form-group">
                        <label>PAN</label>
                        <input type="text" name="d[pan]" class="form-control" maxlength="20">
                    </div>
                    <div class="col-6 form-group">
                        <label>UAN / PF No</label>
                        <input type="text" name="d[uan]" class="form-control" maxlength="40">
                    </div>
                </div>
                <div class="row">
                    <div class="col-6 form-group">
                        <label>Bank A/c</label>
                        <input type="text" name="d[bank]" class="form-control" maxlength="40">
                    </div>
                    <div class="col-6 form-group">
                        <label>IFSC</label>
                        <input type="text" name="d[ifsc]" class="form-control" maxlength="20">
                    </div>
                </div>
                <div class="row">
                    <div class="col-6 form-group">
                        <label>ESI No</label>
                        <input type="text" name="d[esi_no]" class="form-control" maxlength="40">
                    </div>
                    <div class="col-6 form-group">
                        <label>Branch (footer address)</label>
                        <select name="d[branch_id]" class="form-control">
                            <option value="">Head office</option>
                            @foreach ($branchList as $b)<option value="{{ $b->id }}">{{ $b->branch_name }}</option>@endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="lg-card">
                <h5>Months</h5>
                <div class="row">
                    <div class="col-6 form-group">
                        <label>From *</label>
                        <input type="month" name="d[from]" id="gen-from" class="form-control" value="{{ $lastMonth }}">
                    </div>
                    <div class="col-6 form-group">
                        <label>To (for several months)</label>
                        <input type="month" name="d[to]" id="gen-to" class="form-control" value="{{ $lastMonth }}">
                    </div>
                </div>
                <div class="lg-hint mb-1">One payslip per month, up to {{ \App\Services\Payroll\ManualPayslip::MAX_MONTHS }}. Each month is its pay cycle (21st to 20th). Days of loss of pay, each month:</div>
                <table class="w-100 lg-months" id="gen-months"></table>
            </div>

            <div class="lg-card">
                <h5>Salary</h5>
                <div class="row">
                    <div class="col-5 form-group">
                        <label>Annual CTC (₹) *</label>
                        <input type="number" name="d[ctc]" class="form-control" min="0" step="1">
                    </div>
                    <div class="col-3 form-group">
                        <label>Basic %</label>
                        <input type="number" name="d[basic_pct]" class="form-control" min="1" max="100" step="0.01" placeholder="{{ rtrim(rtrim(number_format($basicDefault, 2), '0'), '.') }}">
                    </div>
                    <div class="col-4 form-group">
                        <label>TDS a month (₹)</label>
                        <input type="number" name="d[tds]" class="form-control" min="0" step="1" placeholder="0">
                    </div>
                </div>
                <div class="lg-hint">Basic, HRA, Conveyance, LTA, CEA, Special, PF, ESI, PT and the loss of pay are payroll's own calculation for each month.</div>
            </div>

            <div class="lg-card">
                <h5>Pay elements (every month)</h5>
                <div id="gen-extras"></div>
                <button type="button" id="gen-add-extra" class="btn btn-secondary rounded f-13 px-3"><i class="fa fa-plus mr-1"></i>Add a pay element</button>
                <div class="lg-hint mt-1">Overtime, bonus, arrears, an advance recovered... pick one or type your own.</div>
            </div>

            <div class="lg-card">
                <h5>Payslips</h5>
                <table class="w-100 lg-pay" id="gen-summary"><tr><td class="lg-hint">Type the name and CTC.</td></tr></table>
            </div>

            <div class="d-flex mb-4">
                <button type="button" id="gen-print" class="btn btn-secondary rounded f-14 p-2 mr-2 flex-fill"><i class="fa fa-print mr-1"></i>Print</button>
                <button type="button" id="gen-download" class="btn btn-primary rounded f-14 p-2 flex-fill"><i class="fa fa-download mr-1"></i>Download PDF</button>
            </div>

            <datalist id="gen-designations">@foreach ($designations as $x)<option value="{{ $x }}">@endforeach</datalist>
            <datalist id="gen-departments">@foreach ($departments as $x)<option value="{{ $x }}">@endforeach</datalist>
            <datalist id="gen-elements">@foreach ($payElements as $x)<option value="{{ $x->name }}" data-kind="{{ $x->kind }}">@endforeach</datalist>
        </form>

        <div class="lg-preview">
            <iframe id="gen-preview" title="Payslip preview"></iframe>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const form = $('#gen-form');
        const kinds = @json($payElements->pluck('kind', 'name'));
        let timer = null, pending = null, extraIndex = 0, problems = ['Type the name and CTC.'];
        const esc = function (v) { return $('<div>').text(v == null ? '' : String(v)).html(); };

        // A row of loss-of-pay days for every month of the range, keeping what was typed.
        function months() {
            const from = $('#gen-from').val(), to = $('#gen-to').val() || from;
            const box = $('#gen-months'), kept = {};
            box.find('input').each(function () { kept[$(this).data('month')] = $(this).val(); });
            box.empty();
            if (!from) { return; }
            let [y, m] = from.split('-').map(Number);
            let [ty, tm] = (to < from ? from : to).split('-').map(Number);
            for (let n = 0; n < {{ \App\Services\Payroll\ManualPayslip::MAX_MONTHS }} && (y < ty || (y === ty && m <= tm)); n++) {
                const key = y + '-' + String(m).padStart(2, '0');
                const label = new Date(y, m - 1, 1).toLocaleString('en-IN', {month: 'long', year: 'numeric'});
                box.append($('<tr>').append($('<td>').text(label),
                    $('<td class="text-right">').append($('<input type="number" min="0" step="0.5" class="form-control d-inline-block">')
                        .attr('name', 'd[lop][' + key + ']').attr('data-month', key).val(kept[key] || 0)),
                    $('<td class="lg-hint">').text('LOP days')));
                if (++m > 12) { m = 1; y++; }
            }
        }

        function addExtra(kind, label, amount) {
            const i = extraIndex++;
            const row = $('<div class="lg-extra">').append(
                $('<select class="form-control">').attr('name', 'd[extras][' + i + '][kind]')
                    .append('<option value="earning">Earning</option><option value="deduction">Deduction</option>').val(kind || 'earning'),
                $('<input type="text" class="form-control" list="gen-elements" placeholder="Name">').attr('name', 'd[extras][' + i + '][label]').val(label || ''),
                $('<input type="number" class="form-control lg-amt" min="0" step="1" placeholder="₹">').attr('name', 'd[extras][' + i + '][amount]').val(amount || ''),
                $('<span class="lg-del" title="Remove"><i class="fa fa-times"></i></span>')
            );
            $('#gen-extras').append(row);
        }

        function refresh() {
            if (pending) { pending.abort(); }
            pending = $.ajax({url: "{{ route('offer-letters.generate.preview') }}", type: 'POST', data: form.serialize()})
                .done(function (r) {
                    if (r.status !== 'success') { return; }
                    const frame = document.getElementById('gen-preview');
                    const y = frame.contentWindow ? frame.contentWindow.scrollY : 0;
                    frame.onload = function () { try { frame.contentWindow.scrollTo(0, y); } catch (e) {} };
                    frame.srcdoc = r.html;
                    problems = r.problems || [];

                    const t = $('#gen-summary').empty();
                    if (problems.length) {
                        t.append($('<tr>').append($('<td class="lg-hint">').text(problems.join(' '))));
                    } else {
                        t.append('<tr class="lg-hint"><td>Month</td><td>Days / paid</td><td>Gross</td><td>Net pay</td></tr>');
                        (r.summary || []).forEach(function (s) {
                            t.append($('<tr>').append($('<td>').text(s.month), $('<td>').text(s.days), $('<td>').text('₹' + s.gross), $('<td>').text('₹' + s.net)));
                        });
                    }
                });
        }

        function soon() { clearTimeout(timer); timer = setTimeout(refresh, 350); }
        form.on('input change', 'input, select, textarea', soon);
        $('#gen-from, #gen-to').on('change', function () {
            if ($('#gen-to').val() < $('#gen-from').val()) { $('#gen-to').val($('#gen-from').val()); }
            months();
        });
        $('#gen-add-extra').on('click', function () { addExtra(); });
        $('#gen-extras').on('click', '.lg-del', function () { $(this).closest('.lg-extra').remove(); soon(); });
        // A known pay element sets its own kind (earning / deduction).
        $('#gen-extras').on('change', 'input[list]', function () {
            const kind = kinds[$(this).val()];
            if (kind) { $(this).siblings('select').val(kind); }
        });

        $('#gen-prefill').on('change', function () {
            const id = $(this).val();
            if (!id) { return; }
            $.easyAjax({
                url: "{{ route('offer-letters.generate.prefill', ':id') }}".replace(':id', id) + '?type=payslip',
                type: 'GET', blockUI: true,
                success: function (r) {
                    if (r.status !== 'success') { return; }
                    $('#gen-clear').removeClass('d-none');
                    $.each(r.details, function (key, value) {
                        const field = form.find('[name="d[' + key + ']"]');
                        if (field.length && value !== null && value !== undefined && String(value).trim() !== '') { field.val(value); }
                    });
                    refresh();
                }
            });
        });

        $('#gen-clear').on('click', function () {
            form[0].reset();
            $('#gen-extras').empty();
            $('#gen-prefill').val('').selectpicker('refresh');
            $(this).addClass('d-none');
            months();
            refresh();
        });

        // The PDF: the form's fields posted to a new tab (print) or downloaded. Nothing is saved.
        function pdf(inline) {
            if (problems.length) {
                Swal.fire({icon: 'warning', title: 'Not yet', text: problems.join(' ')});
                return;
            }
            const post = $('<form method="POST" style="display:none"></form>').attr('action', "{{ route('offer-letters.generate.payslip') }}");
            if (inline) { post.attr('target', '_blank'); }
            form.serializeArray().forEach(function (f) { $('<input type="hidden">').attr('name', f.name).val(f.value).appendTo(post); });
            if (inline) { $('<input type="hidden" name="inline" value="1">').appendTo(post); }
            post.appendTo('body');
            post[0].submit();
            post.remove();
        }
        $('#gen-print').on('click', function () { pdf(true); });
        $('#gen-download').on('click', function () { pdf(false); });

        months();
        refresh();
    })();
</script>
@endpush
