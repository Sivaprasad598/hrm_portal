@extends('layouts.app')

{{-- Finance -> Invoices -> New / Edit draft (InvoiceController::create / edit): the form on the left,
     the invoice drawn live on the right (like the Letter Generator). Every printed word can be
     changed for this invoice under "Wording". Save as a draft, or issue (numbered then). --}}
@push('styles')
@include('letter-generator.partials.styles')
@endpush

@section('content')
@php
    $f = $formData;
    $fmt = company()->date_format;
    $date = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format($fmt) : '';
    $lines = $f['lines'] ?: [['name' => '', 'description' => '', 'hsn' => '', 'unit' => '', 'qty' => 1, 'rate' => '', 'gst' => 18]];
    $num = fn ($v) => $v === '' || $v === null ? '' : rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
@endphp
<div class="content-wrapper" data-swap-links>
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3 iv-top">
        <div class="f-14 text-dark-grey">
            @if ($invoice)Editing a draft
            @elseif (!empty($duplicateOf))A copy of <b>{{ $duplicateOf }}</b> — new dates, numbered when issued
            @else A new invoice — numbered when issued
            @endif
        </div>
        <div>
            <a href="{{ route('invoices.index') }}" class="btn btn-secondary mr-2"><i class="fa fa-list mr-1"></i>All invoices</a>
            <a href="{{ route('invoices.settings') }}" class="btn btn-secondary openRightModal"><i class="fa fa-cog mr-1"></i>Invoice settings</a>
        </div>
    </div>

    @if (!$settings->gstin)
        <div class="iv-alert"><i class="fa fa-exclamation-triangle mr-1"></i>The company's GSTIN is not set, so it prints as "Not set".
            <a href="{{ route('invoices.settings') }}" class="openRightModal">Add it in Invoice settings</a>.</div>
    @endif

    <div class="lg-wrap">
        <form id="iv-form" class="lg-form" onsubmit="return false;">
            @csrf
            @if ($invoice)<input type="hidden" name="_method" value="PUT">@endif

            <div class="lg-card">
                <h5>Invoice</h5>
                <div class="row">
                    <div class="col-12 form-group">
                        <label>Type</label>
                        <div class="iv-seg">
                            @foreach (\AppModules\Finance\Services\GstInvoice::TITLES as $t)
                                <label class="{{ $f['title'] === $t ? 'active' : '' }}"><input type="radio" name="title" value="{{ $t }}" @checked($f['title'] === $t)>{{ $t }}</label>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-6 form-group">
                        <label for="iv_issue">Invoice date</label>
                        <input type="text" class="form-control" id="iv_issue" name="issue_date" value="{{ $date($f['issue_date']) }}" autocomplete="off">
                    </div>
                    <div class="col-6 form-group">
                        <label for="iv_due">Due date</label>
                        <input type="text" class="form-control" id="iv_due" name="due_date" value="{{ $date($f['due_date']) }}" autocomplete="off">
                    </div>
                    <div class="col-12 form-group">
                        <label for="iv_project">Project (optional)</label>
                        <select class="form-control select-picker" id="iv_project" name="project_id" data-live-search="true" data-size="8">
                            <option value="">None</option>
                            @foreach ($projects as $p)<option value="{{ $p->id }}" @selected((int) $f['project_id'] === (int) $p->id)>{{ $p->project_name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-12 form-group">
                        <label for="iv_po">PO / reference (optional)</label>
                        <input type="text" class="form-control" id="iv_po" name="po" value="{{ $f['po'] }}" maxlength="80" placeholder="e.g. PO-4521 dated 02-11-2026">
                    </div>
                </div>
            </div>

            <div class="lg-card">
                <h5>Bill to</h5>
                <div class="form-group">
                    <label for="iv_client">Client</label>
                    <select class="form-control select-picker" id="iv_client" name="client_id" data-live-search="true" data-size="8" title="Choose a client, or type the details below">
                        <option value="">Not a saved client</option>
                        @foreach ($clients as $cl)
                            <option value="{{ $cl->id }}" data-subtext="{{ $cl->gst_number }}" @selected((int) $f['client_id'] === (int) $cl->id)>{{ $cl->company_name ?: $cl->name }}</option>
                        @endforeach
                    </select>
                    @if (Route::has('clients.create'))<small class="lg-hint">Not there? <a href="{{ route('clients.create') }}" target="_blank">Add a client</a>, then pick them here.</small>@endif
                </div>
                <div class="form-group"><label for="iv_cname">Name</label><input type="text" class="form-control" id="iv_cname" name="client_name" value="{{ $f['client_name'] }}" maxlength="190"></div>
                <div class="form-group"><label for="iv_caddr">Address</label><textarea class="form-control" id="iv_caddr" name="client_address" rows="3">{{ $f['client_address'] }}</textarea></div>
                <div class="row">
                    <div class="col-7 form-group">
                        <label for="iv_gstin">GSTIN</label>
                        <input type="text" class="form-control text-uppercase" id="iv_gstin" name="client_gstin" value="{{ $f['client_gstin'] }}" maxlength="15" placeholder="Blank if unregistered">
                    </div>
                    <div class="col-5 form-group">
                        <label for="iv_email">Email</label>
                        <input type="email" class="form-control" id="iv_email" name="client_email" value="{{ $f['client_email'] }}">
                    </div>
                </div>
                <div class="form-group mb-1">
                    <label for="iv_pos">Place of supply</label>
                    <select class="form-control select-picker" id="iv_pos" name="place_of_supply" data-live-search="true" data-size="8">
                        @foreach ($states as $code => $name)<option value="{{ $code }}" data-subtext="{{ $code }}" @selected((string) $f['place_of_supply'] === (string) $code)>{{ $name }}</option>@endforeach
                    </select>
                    <small class="lg-hint" id="iv-taxkind"></small>
                </div>
                <label class="lg-check f-13 mt-2 d-inline-block"><input type="checkbox" name="reverse_charge" value="1" @checked($f['reverse_charge'])> Tax payable on reverse charge</label>
            </div>

            <div class="lg-card">
                <h5>Lines</h5>
                <div id="iv-lines">
                    @foreach ($lines as $i => $l)
                        <div class="iv-line">
                            <div class="iv-line-top">
                                <input type="text" class="form-control iv-name" data-f="name" value="{{ $l['name'] }}" placeholder="Item / service" maxlength="190">
                                <button type="button" class="iv-del" title="Remove the line"><i class="fa fa-times"></i></button>
                            </div>
                            <textarea class="form-control iv-desc" data-f="description" rows="1" placeholder="Details (optional)">{{ $l['description'] }}</textarea>
                            <div class="iv-line-grid">
                                <div><label>HSN/SAC</label><input type="text" class="form-control" data-f="hsn" value="{{ $l['hsn'] }}" maxlength="8" placeholder="998313"></div>
                                <div><label>Qty</label><input type="number" step="0.001" min="0" class="form-control" data-f="qty" value="{{ $num($l['qty']) }}"></div>
                                <div><label>Unit</label><input type="text" class="form-control" data-f="unit" value="{{ $l['unit'] }}" maxlength="20" placeholder="Nos / Hrs"></div>
                                <div><label>Rate (₹)</label><input type="number" step="0.01" min="0" class="form-control" data-f="rate" value="{{ $num($l['rate']) }}"></div>
                                <div><label>GST</label>
                                    <select class="form-control" data-f="gst">
                                        @foreach (\AppModules\Finance\Services\GstInvoice::RATES as $r)<option value="{{ $r }}" @selected((float) $l['gst'] === (float) $r)>{{ $r }}%</option>@endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <a href="javascript:;" id="iv-add" class="f-13"><i class="fa fa-plus mr-1"></i>Add a line</a>
                <p class="lg-hint mb-0 mt-2">Common SAC codes: 998313 IT consulting / software, 998331 mapping &amp; surveying, 998346 engineering drawing, 998399 other technical services.</p>
            </div>

            <div class="lg-card">
                <h5 class="iv-toggle" data-target="#iv-wording"><i class="fa fa-pen-nib mr-1"></i>Wording <span class="lg-hint text-transform-none">— what prints on this invoice</span><i class="fa fa-chevron-down float-right"></i></h5>
                <div id="iv-wording">
                    <div class="form-group"><label for="iv_intro">Line above the table (optional)</label>
                        <textarea class="form-control" id="iv_intro" name="intro" rows="2" placeholder="e.g. Being charges for GIS digitisation of Vizag Port utilities, Phase 1">{{ $f['intro'] }}</textarea></div>
                    <div class="form-group"><label for="iv_bank">Bank details</label><textarea class="form-control" id="iv_bank" name="bank" rows="4">{{ $f['bank'] }}</textarea></div>
                    <div class="form-group"><label for="iv_terms">Terms</label><textarea class="form-control" id="iv_terms" name="terms" rows="3">{{ $f['terms'] }}</textarea></div>
                    <div class="form-group"><label for="iv_notes">Notes</label><textarea class="form-control" id="iv_notes" name="notes" rows="2">{{ $f['notes'] }}</textarea></div>
                    <div class="form-group"><label for="iv_sign">Signatory</label><input type="text" class="form-control" id="iv_sign" name="signatory" value="{{ $f['signatory'] }}" maxlength="120" placeholder="Name of the authorised signatory"></div>
                    <label class="lg-check f-13"><input type="checkbox" name="show_stamp" value="1" @checked($f['show_stamp'])> Print the company's sign and stamp</label>
                </div>
            </div>

            <div class="iv-actions">
                <button type="button" class="btn btn-secondary" id="iv-draft"><i class="fa fa-save mr-1"></i>Save draft</button>
                <button type="button" class="btn btn-primary" id="iv-issue"><i class="fa fa-check mr-1"></i>Issue invoice</button>
            </div>
            <p class="lg-hint mt-2">Issuing gives it the next number for its financial year. After that it is not edited: cancel it, or duplicate it.</p>
        </form>

        <div class="lg-preview">
            <iframe id="iv-preview" title="Invoice preview"></iframe>
        </div>
    </div>
</div>

<style>
    .lg-form { flex-basis: 470px; max-width: 470px; }
    .iv-alert { background: #fffaeb; color: #93370d; border-radius: 8px; padding: 9px 14px; font-size: 13px; margin-bottom: 14px; }
    .iv-seg { display: flex; flex-wrap: wrap; gap: 4px; background: #f2f4f7; padding: 3px; border-radius: 8px; }
    .iv-seg label { flex: 1 1 auto; margin: 0; padding: 6px 10px; border-radius: 6px; font-size: 13px; text-align: center; cursor: pointer; color: #475467; white-space: nowrap; }
    .iv-seg label input { display: none; }
    .iv-seg label.active { background: #fff; color: var(--epic-blue, #cb6b4f); font-weight: 600; box-shadow: 0 1px 3px rgba(16, 24, 40, .12); }
    .iv-line { border: 1px solid #eaecf0; border-radius: 8px; padding: 10px; margin-bottom: 10px; background: #fcfcfd; }
    .iv-line-top { display: flex; gap: 6px; }
    .iv-line-top .iv-name { font-weight: 600; }
    .iv-desc { margin: 6px 0; font-size: 12.5px !important; resize: vertical; }
    .iv-line-grid { display: grid; grid-template-columns: 1.3fr .8fr .8fr 1.1fr .9fr; gap: 6px; }
    .iv-line-grid label { font-size: 11px !important; margin-bottom: 2px !important; }
    .iv-line-grid .form-control { padding: 4px 7px; font-size: 13px !important; height: 34px; }
    .iv-del { flex: 0 0 34px; border: 1px solid #eaecf0; background: #fff; border-radius: 6px; color: #98a2b3; }
    .iv-del:hover { color: #d92d20; }
    .iv-toggle { cursor: pointer; }
    .text-transform-none { text-transform: none; letter-spacing: 0; font-weight: 400; }
    .iv-actions { display: flex; gap: 8px; position: sticky; bottom: 0; background: var(--epic-page, #f6f7f9); padding: 10px 0; }
    .iv-actions .btn { flex: 1; height: 40px; }
    @media (max-width: 575px) { .iv-line-grid { grid-template-columns: 1fr 1fr 1fr; } }
</style>
@endsection

@push('scripts')
<script>
    (function () {
        var form = $('#iv-form');
        var ourState = @json($settings->state_code);
        var states = @json($states);
        var timer = null, pending = null;

        // lines are sent as lines[i][field]
        function renumber() {
            $('#iv-lines .iv-line').each(function (i) {
                $(this).find('[data-f]').each(function () { this.name = 'lines[' + i + '][' + $(this).data('f') + ']'; });
            });
        }

        function taxKind() {
            var pos = $('#iv_pos').val();
            $('#iv-taxkind').text(!pos ? '' : (pos === ourState ? 'Same state as ours: CGST + SGST' : 'Another state: IGST'));
        }

        function refresh() {
            renumber();
            taxKind();
            if (pending) { pending.abort(); }
            pending = $.ajax({url: "{{ route('invoices.preview') }}", type: 'POST', data: form.serialize().replace(/(^|&)_method=PUT/, '')})
                .done(function (html) {
                    var frame = document.getElementById('iv-preview');
                    var y = frame.contentWindow ? frame.contentWindow.scrollY : 0;
                    frame.onload = function () { try { frame.contentWindow.scrollTo(0, y); } catch (e) {} };
                    frame.srcdoc = html;
                });
        }
        function later() { clearTimeout(timer); timer = setTimeout(refresh, 350); }
        form.on('input change', 'input, textarea, select', later);

        // the type
        form.on('change', 'input[name=title]', function () {
            $('.iv-seg label').removeClass('active');
            $(this).closest('label').addClass('active');
        });

        // lines
        var blank = $('#iv-lines .iv-line').first().clone();
        blank.find('input, textarea').val('');
        blank.find('[data-f=qty]').val(1);
        blank.find('[data-f=gst]').val('18');
        $('#iv-add').on('click', function () {
            var row = blank.clone();
            $('#iv-lines').append(row);
            row.find('.iv-name').trigger('focus');
            later();
        });
        $('#iv-lines').on('click', '.iv-del', function () {
            if ($('#iv-lines .iv-line').length > 1) { $(this).closest('.iv-line').remove(); }
            else { $(this).closest('.iv-line').find('input, textarea').val(''); }
            later();
        });

        // the client: fill the bill-to from their record
        $('#iv_client').on('change', function () {
            if (!this.value) { return; }
            $.get("{{ route('invoices.client', '__id__') }}".replace('__id__', this.value), function (c) {
                $('#iv_cname').val(c.client_name || '');
                $('#iv_caddr').val(c.client_address || '');
                $('#iv_gstin').val(c.client_gstin || '');
                $('#iv_email').val(c.client_email || '');
                if (c.place_of_supply) { $('#iv_pos').selectpicker('val', c.place_of_supply); }
                refresh();
            });
        });

        // a GSTIN gives the state
        $('#iv_gstin').on('input', function () {
            var v = this.value.toUpperCase().trim();
            this.value = v;
            if (/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/.test(v) && states[v.substr(0, 2)]) {
                $('#iv_pos').selectpicker('val', v.substr(0, 2));
            }
        });

        // wording: folded away until wanted
        $('.iv-toggle').on('click', function () { $($(this).data('target')).slideToggle(150); });
        @if (!$f['intro'] && !$f['notes'])$('#iv-wording').hide();@endif

        datepicker('#iv_issue', {position: 'bl', onSelect: later, ...datepickerConfig});
        datepicker('#iv_due', {position: 'bl', onSelect: later, ...datepickerConfig});

        function save(action) {
            renumber();
            $.easyAjax({
                url: "{{ $invoice ? route('invoices.update', $invoice->id) : route('invoices.store') }}",
                type: 'POST', container: '#iv-form', blockUI: true, disableButton: true, buttonSelector: action === 'issue' ? '#iv-issue' : '#iv-draft',
                data: form.serialize() + '&action=' + action,
                success: function (r) {
                    if (r.status === 'success' && r.redirectUrl) { window.PageSwap ? PageSwap.open(r.redirectUrl) : (location.href = r.redirectUrl); }
                }
            });
        }
        $('#iv-draft').on('click', function () { save('draft'); });
        $('#iv-issue').on('click', function () {
            Swal.fire({
                title: 'Issue this invoice?', text: 'It gets the next number for its financial year and cannot be edited after that.',
                icon: 'question', showCancelButton: true, confirmButtonText: 'Issue', cancelButtonText: 'Not yet', reverseButtons: true,
                customClass: {confirmButton: 'btn btn-primary mr-3', cancelButton: 'btn btn-secondary'}, buttonsStyling: false
            }).then(function (res) { if (res.isConfirmed) { save('issue'); } });
        });

        refresh();
    })();
</script>
@endpush
