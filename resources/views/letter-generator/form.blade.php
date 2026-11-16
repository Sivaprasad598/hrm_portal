@extends('layouts.app')

@push('styles')
@include('letter-generator.partials.styles')
@endpush

@section('content')
@php
    $d = $details;
    $v = fn ($key, $default = '') => old('d.' . $key, $d[$key] ?? $default);
    $noticeDefault = optional($noticeOptions->first(), fn ($n) => $settings['notice_unit'] === 'months'
        ? $n->months . ' ' . ((int) $n->months === 1 ? 'month' : 'months') : ($n->months * 30) . ' days') ?? '60 days';
@endphp
<div class="content-wrapper">
    @include('letter-generator.partials.header')

    <div class="lg-wrap">
        <form id="gen-form" class="lg-form" onsubmit="return false;">
            @csrf
            <input type="hidden" name="type" value="{{ $type }}">
            <input type="hidden" name="user_id" id="gen-user" value="">

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
                <div class="lg-hint mt-1">Their record fills the form; your changes are for this letter only and do not change the employee.</div>
            </div>

            <div class="lg-card">
                <h5>{{ $type === 'offer' ? 'Candidate' : 'Employee' }}</h5>
                <div class="row">
                    <div class="col-4 form-group">
                        <label>Title</label>
                        <select name="d[salutation]" class="form-control">
                            @foreach (['Mr.', 'Ms.', 'Mrs.', 'Dr.'] as $s)<option @selected($v('salutation', 'Mr.') === $s)>{{ $s }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-8 form-group">
                        <label>Name *</label>
                        <input type="text" name="d[name]" class="form-control" value="{{ $v('name') }}" maxlength="150">
                    </div>
                </div>
                @if ($type === 'relieving')
                    <div class="form-group">
                        <label>Gender (for his / her)</label>
                        <select name="d[gender]" class="form-control">
                            <option value="">From the title</option>
                            <option value="male" @selected($v('gender') === 'male')>Male</option>
                            <option value="female" @selected($v('gender') === 'female')>Female</option>
                        </select>
                    </div>
                @endif
                <div class="form-group">
                    <label>Address * <span class="lg-hint">(one line each)</span></label>
                    <textarea name="d[address]" class="form-control" rows="3">{{ $v('address') }}</textarea>
                </div>
            </div>

            @if ($type === 'offer')
                <div class="lg-card">
                    <h5>Position</h5>
                    <div class="form-group">
                        <label>Designation *</label>
                        @include('letter-generator.partials.pick', ['name' => 'd[designation]', 'options' => $designations, 'value' => $v('designation'), 'title' => 'Search designation'])
                    </div>
                    <div class="row">
                        <div class="col-6 form-group">
                            <label>Based in (location) *</label>
                            @include('letter-generator.partials.pick', ['name' => 'd[location]', 'options' => $branches, 'value' => $v('location'), 'title' => 'Search location'])
                        </div>
                        <div class="col-6 form-group">
                            <label>Joining date *</label>
                            <input type="date" name="d[joining_date]" class="form-control" value="{{ $v('joining_date') }}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Probation *</label>
                        <input type="text" name="d[probation]" class="form-control" list="gen-probation" value="{{ $v('probation', '1 month') }}">
                    </div>
                    <div class="lg-sub">Notice period</div>
                    <div class="row">
                        <div class="col-6 form-group">
                            <label>During probation *</label>
                            <input type="text" name="d[notice_probation]" class="form-control" list="gen-days" value="{{ $v('notice_probation', $settings['notice_probation_days'] . ' days') }}">
                        </div>
                        <div class="col-6 form-group">
                            <label>After confirmation *</label>
                            <input type="text" name="d[notice_confirmed]" class="form-control" list="gen-notice" value="{{ $v('notice_confirmed', $noticeDefault) }}">
                        </div>
                    </div>
                </div>

                <div class="lg-card">
                    <h5>Salary</h5>
                    <div class="row">
                        <div class="col-7 form-group">
                            <label>Annual CTC (₹) *</label>
                            <input type="number" name="d[ctc]" class="form-control" min="0" step="1" value="{{ $v('ctc') }}">
                        </div>
                        <div class="col-5 form-group">
                            <label>Basic %</label>
                            <input type="number" name="d[basic_pct]" class="form-control" min="1" max="100" step="0.01" value="{{ $v('basic_pct') }}" placeholder="{{ rtrim(rtrim(number_format($basicDefault, 2), '0'), '.') }} (default)">
                        </div>
                    </div>
                    <div id="gen-words" class="lg-hint mb-2"></div>
                    <table class="w-100 lg-pay" id="gen-pay"><tr><td class="lg-hint">Type the CTC to see the monthly break-up (payroll's own calculation).</td></tr></table>
                </div>

                <div class="lg-card">
                    <h5>Letter</h5>
                    <div class="row">
                        <div class="col-6 form-group">
                            <label>Reference No. *</label>
                            <input type="text" name="reference" class="form-control" value="{{ $suggested }}">
                            <div class="lg-hint">Next in the series; change it if needed.</div>
                        </div>
                        <div class="col-6 form-group">
                            <label>Letter date</label>
                            <input type="date" name="letter_date" class="form-control" value="{{ now()->toDateString() }}">
                        </div>
                    </div>
                    <label class="mb-1 lg-check"><input type="checkbox" id="gen-own-wording" class="mr-1" @checked(!empty($d['paragraphs']))> Change the wording for this letter only</label>
                    <textarea name="d[paragraphs]" id="gen-wording" class="form-control mt-1 {{ empty($d['paragraphs']) ? 'd-none' : '' }}" rows="12" @disabled(empty($d['paragraphs']))>{{ $d['paragraphs'] ?? $settings['paragraphs'] }}</textarea>
                </div>
            @else
                <div class="lg-card">
                    <h5>Employment</h5>
                    <div class="row">
                        <div class="col-6 form-group">
                            <label>Joining date *</label>
                            <input type="date" name="d[joining_date]" class="form-control" value="{{ $v('joining_date') }}">
                        </div>
                        <div class="col-6 form-group">
                            <label>Relieving date *</label>
                            <input type="date" name="d[relieving_date]" class="form-control" value="{{ $v('relieving_date') }}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Designation at the time of relieving *</label>
                        @include('letter-generator.partials.pick', ['name' => 'd[designation]', 'options' => $designations, 'value' => $v('designation'), 'title' => 'Search designation'])
                    </div>
                    <div class="form-group">
                        <label>Department *</label>
                        @include('letter-generator.partials.pick', ['name' => 'd[department]', 'options' => $departments, 'value' => $v('department'), 'title' => 'Search department'])
                    </div>
                    <div class="form-group">
                        <label>Employment tenure</label>
                        <input type="text" name="d[tenure]" class="form-control" value="{{ $v('tenure') }}" id="gen-tenure" placeholder="Worked out from the dates">
                        <div class="lg-hint">Leave empty to use the dates: <span id="gen-tenure-auto">—</span></div>
                    </div>
                </div>

                <div class="lg-card">
                    <h5>Letter</h5>
                    <div class="form-group">
                        <label>Letter date</label>
                        <input type="date" name="letter_date" class="form-control" value="{{ now()->toDateString() }}">
                    </div>
                    <label class="mb-1 lg-check"><input type="checkbox" id="gen-own-wording" class="mr-1" @checked(!empty($d['paragraphs']))> Change the wording for this letter only</label>
                    <textarea name="d[paragraphs]" id="gen-wording" class="form-control mt-1 {{ empty($d['paragraphs']) ? 'd-none' : '' }}" rows="12" @disabled(empty($d['paragraphs']))>{{ $d['paragraphs'] ?? $settings['relieving_paragraphs'] }}</textarea>
                    <div class="lg-hint mt-1">For someone who left before the system; employees who left through it get theirs from Exited Employees (after clearances).</div>
                </div>
            @endif

            <x-forms.button-primary id="gen-save" icon="file-signature" class="w-100 mb-4">
                Generate {{ $type === 'offer' ? 'Offer' : 'Relieving' }} Letter
            </x-forms.button-primary>

            <datalist id="gen-designations">@foreach ($designations as $x)<option value="{{ $x }}">@endforeach</datalist>
            <datalist id="gen-branches">@foreach ($branches as $x)<option value="{{ $x }}">@endforeach</datalist>
            <datalist id="gen-departments">@foreach ($departments as $x)<option value="{{ $x }}">@endforeach</datalist>
            <datalist id="gen-probation">@foreach (['No probation', '1 month', '2 months', '3 months', '6 months', '45 days'] as $x)<option value="{{ $x }}">@endforeach</datalist>
            <datalist id="gen-days">@foreach (['7 days', '15 days', '30 days'] as $x)<option value="{{ $x }}">@endforeach</datalist>
            <datalist id="gen-notice">
                @foreach ($noticeOptions as $n)
                    <option value="{{ $settings['notice_unit'] === 'months' ? $n->months . ' ' . ((int) $n->months === 1 ? 'month' : 'months') : ($n->months * 30) . ' days' }}">{{ $n->name }}</option>
                @endforeach
            </datalist>
        </form>

        <div class="lg-preview">
            <iframe id="gen-preview" title="Letter preview"></iframe>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const type = @json($type);
        const form = $('#gen-form');
        let timer = null, pending = null;

        function refresh() {
            if (pending) { pending.abort(); }
            pending = $.ajax({url: "{{ route('offer-letters.generate.preview') }}", type: 'POST', data: form.serialize()})
                .done(function (r) {
                    if (r.status !== 'success') { return; }
                    // Keep the reader's place in the letter while it redraws.
                    const frame = document.getElementById('gen-preview');
                    const y = frame.contentWindow ? frame.contentWindow.scrollY : 0;
                    frame.onload = function () { try { frame.contentWindow.scrollTo(0, y); } catch (e) {} };
                    frame.srcdoc = r.html;

                    if (type === 'offer') {
                        const t = $('#gen-pay').empty();
                        if (r.pay) {
                            r.pay.rows.forEach(function (row) { t.append($('<tr>').append($('<td>').text(row[0]), $('<td>').text('₹' + row[1]))); });
                            t.append($('<tr class="t">').append($('<td>').text('Gross a month'), $('<td>').text('₹' + r.pay.gross)));
                            t.append($('<tr>').append($('<td>').text('Deductions (PF, ESI, PT)'), $('<td>').text('₹' + r.pay.deductions)));
                            t.append($('<tr class="t">').append($('<td>').text('Net in hand a month'), $('<td>').text('₹' + r.pay.net)));
                            $('#gen-words').text(r.pay.words);
                        } else {
                            t.append('<tr><td class="lg-hint">Type the CTC to see the monthly break-up (payroll\'s own calculation).</td></tr>');
                            $('#gen-words').text('');
                        }
                    } else {
                        $('#gen-tenure-auto').text(r.tenure || '—');
                    }
                });
        }

        // A searchable drop-down takes a value it does not list (an employee's own) as a new option.
        function setField(field, value) {
            if (field.is('select.gen-pick')) {
                if (!field.find('option').filter(function () { return this.value === String(value); }).length) {
                    field.append($('<option>').val(value).text(value));
                }
                field.val(String(value)).selectpicker('refresh');
                return;
            }
            field.val(value);
        }

        function soon() { clearTimeout(timer); timer = setTimeout(refresh, 350); }
        form.on('input change', 'input, select, textarea', soon);

        $('#gen-own-wording').on('change', function () {
            $('#gen-wording').toggleClass('d-none', !this.checked).prop('disabled', !this.checked);
            soon();
        });

        // Fill from an employee: everything stays editable.
        $('#gen-prefill').on('change', function () {
            const id = $(this).val();
            if (!id) { return; }
            $.easyAjax({
                url: "{{ route('offer-letters.generate.prefill', ':id') }}".replace(':id', id) + '?type=' + type,
                type: 'GET', blockUI: true,
                success: function (r) {
                    if (r.status !== 'success') { return; }
                    $('#gen-user').val(id);
                    $('#gen-clear').removeClass('d-none');
                    // What the record has; a field it leaves empty keeps what the form had (e.g. the default notice).
                    $.each(r.details, function (key, value) {
                        const field = form.find('[name="d[' + key + ']"]');
                        if (field.length && value !== null && value !== undefined && String(value).trim() !== '') { setField(field, value); }
                    });
                    refresh();
                }
            });
        });

        // Clear: no employee, and the form back as it opened (the defaults).
        $('#gen-clear').on('click', function () {
            form[0].reset();
            form.find('select.gen-pick').selectpicker('refresh');
            $('#gen-user').val('');
            $('#gen-prefill').val('').selectpicker('refresh');
            $('#gen-own-wording').trigger('change');
            $(this).addClass('d-none');
            refresh();
        });

        $('#gen-save').on('click', function () {
            const button = $(this).prop('disabled', true);
            $.ajax({url: "{{ route('offer-letters.generate.store') }}", type: 'POST', data: form.serialize()})
                .done(function (r) {
                    if (r.status === 'success' && r.redirect_url) { window.location.href = r.redirect_url; return; }
                    Swal.fire({icon: 'warning', title: 'Not yet', text: r.message});
                })
                .fail(function (xhr) { Swal.fire({icon: 'error', title: (xhr.responseJSON && xhr.responseJSON.message) || 'Could not generate the letter.'}); })
                .always(function () { button.prop('disabled', false); });
        });

        refresh();
    })();
</script>
@endpush
