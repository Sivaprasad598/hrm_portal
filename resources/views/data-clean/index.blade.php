@extends('layouts.app')

@push('styles')
<style>
    .dc-group { border: 1px solid #e8eef3; border-radius: 6px; margin-bottom: 12px; }
    .dc-group-head { display: flex; align-items: flex-start; padding: 12px 14px; }
    .dc-group-head .dc-toggle { margin-left: auto; white-space: nowrap; }
    .dc-tables { border-top: 1px solid #e8eef3; padding: 8px 14px 4px 40px; }
    .dc-item { display: flex; align-items: center; padding: 4px 0; margin: 0; cursor: pointer; font-size: 13px; }
    .dc-item input { margin-right: 10px; }
    .dc-item .dc-count { margin-left: auto; color: #99a5b5; font-size: 12px; }
    .dc-item.dc-special { font-weight: 600; }
    .dc-item.is-empty { color: #99a5b5; }
    .dc-preview-list { max-height: 260px; overflow-y: auto; text-align: left; font-size: 13px; border: 1px solid #e8eef3; border-radius: 4px; padding: 6px 10px; }
    .dc-preview-list div { display: flex; justify-content: space-between; padding: 1px 0; }
</style>
@endpush

@section('content')

    <div class="w-100 d-flex">

        @include('sections.setting-sidebar')

        <x-setting-card>
            <x-slot name="header">
                <div class="s-b-n-header" id="tabs">
                    <h2 class="mb-0 p-20 f-21 font-weight-normal border-bottom-grey">Data Clean</h2>
                </div>
            </x-slot>

            <div class="col-lg-12 col-md-12 w-100 p-4">

                <div class="alert alert-danger f-13">
                    <strong>This permanently removes data.</strong>
                    Only the tables you tick are emptied; nothing else is added. A full database backup is taken first
                    (in <code>storage/app/backups</code>); if it fails, nothing is removed.
                </div>

                <div class="alert alert-info f-13">
                    <strong>Always kept:</strong> the Super Admin login
                    ({{ $keptUsers->map(fn ($u) => $u->name . ' - ' . $u->email)->implode(', ') }}),
                    company and module settings, <strong>payroll settings</strong> (Payslip Calculation), leave types and the leave policy,
                    notice periods, holidays, <strong>General Shift</strong> and Day Off, biometric devices and connection settings,
                    and the Super Admin, Employee and Client roles.
                </div>

                <form id="data-clean-form">
                    @csrf
                    @foreach ($groups as $key => [$label, $description])
                        @php $groupItems = $items[$key]; @endphp
                        <div class="dc-group" data-group="{{ $key }}">
                            <div class="dc-group-head">
                                <input type="checkbox" class="dc-group-box mr-3 mt-1" title="Tick every table in this group">
                                <div>
                                    <span class="f-15 font-weight-bold text-dark">{{ $label }}</span>
                                    <span class="badge badge-light ml-2 f-12">{{ number_format(collect($groupItems)->sum(fn ($i) => $counts[$i] ?? 0)) }} rows</span>
                                    <span class="badge badge-light ml-1 f-12 dc-picked d-none"></span>
                                    <span class="d-block f-12 text-dark-grey mt-1">{{ $description }}</span>
                                </div>
                                <a href="javascript:;" class="dc-toggle f-13 ml-3">{{ count($groupItems) }} {{ count($groupItems) === 1 ? 'item' : 'items' }} <i class="fa fa-chevron-down ml-1"></i></a>
                            </div>
                            <div class="dc-tables d-none">
                                @foreach ($groupItems as $item)
                                    @php $isSpecial = isset($special[$item]); $n = $counts[$item] ?? 0; @endphp
                                    <label class="dc-item {{ $isSpecial ? 'dc-special' : '' }} {{ $n ? '' : 'is-empty' }}">
                                        <input type="checkbox" name="items[]" value="{{ $item }}">
                                        <span>
                                            {{ $isSpecial ? $special[$item][0] : ucfirst(str_replace('_', ' ', $item)) }}
                                            @if ($isSpecial)
                                                <span class="d-block f-11 font-weight-normal {{ $item === '@users' ? 'text-red' : 'text-dark-grey' }}">{{ $special[$item][1] }}</span>
                                            @else
                                                <code class="f-11 ml-1">{{ $item }}</code>
                                            @endif
                                        </span>
                                        <span class="dc-count">{{ number_format($n) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div class="mt-2">
                        <a href="javascript:;" id="expand-all" class="f-13 mr-3">Show all tables</a>
                        <a href="javascript:;" id="tick-all" class="f-13 mr-3">Tick everything (full fresh start)</a>
                        <a href="javascript:;" id="tick-none" class="f-13">Untick all</a>
                    </div>

                    <div class="row mt-4">
                        <div class="col-lg-6">
                            <x-forms.text fieldId="confirm" fieldName="confirm" :fieldLabel="'Type ' . $phrase . ' to confirm'" fieldPlaceholder="{{ $phrase }}" fieldRequired="true" />
                        </div>
                    </div>

                    <div class="w-100 border-top-grey mt-3">
                        <x-setting-form-actions>
                            <x-forms.button-primary id="run-data-clean" class="btn-danger" icon="trash">
                                Review and clean
                            </x-forms.button-primary>
                        </x-setting-form-actions>
                    </div>
                </form>
            </div>
        </x-setting-card>
    </div>

@endsection

@push('scripts')
<script>
    (function () {
        var labels = @json(collect($special)->map(fn ($s) => $s[0]));
        var form = $('#data-clean-form');

        function name(item) { return labels[item] || item.replace(/_/g, ' '); }
        function esc(s) { return $('<div>').text(s).html(); }
        function picked() { return $('input[name="items[]"]:checked').map(function () { return this.value; }).get(); }

        // The group box shows all / some / none of its tables.
        function refresh() {
            $('.dc-group').each(function () {
                var boxes = $(this).find('input[name="items[]"]'), on = boxes.filter(':checked').length;
                $(this).find('.dc-group-box').prop('checked', on === boxes.length && on > 0).prop('indeterminate', on > 0 && on < boxes.length);
                $(this).find('.dc-picked').toggleClass('d-none', !on).text(on + ' of ' + boxes.length + ' ticked');
            });
        }

        form.on('change', '.dc-group-box', function () {
            var group = $(this).closest('.dc-group');
            group.find('input[name="items[]"]').prop('checked', this.checked);
            if (this.checked) { group.find('.dc-tables').removeClass('d-none'); }
            refresh();
        });
        form.on('change', 'input[name="items[]"]', refresh);
        form.on('click', '.dc-toggle', function () { $(this).closest('.dc-group').find('.dc-tables').toggleClass('d-none'); });
        $('#expand-all').on('click', function () { $('.dc-tables').removeClass('d-none'); });
        $('#tick-all').on('click', function () { $('input[name="items[]"]').prop('checked', true); refresh(); });
        $('#tick-none').on('click', function () { $('input[name="items[]"]').prop('checked', false); refresh(); });

        $('#run-data-clean').on('click', function () {
            var items = picked();
            if (!items.length) { Swal.fire({ icon: 'warning', text: 'Tick at least one table.' }); return; }
            if ($('#confirm').val().trim() !== @json($phrase)) { Swal.fire({ icon: 'warning', text: 'Type ' + @json($phrase) + ' exactly to confirm.' }); return; }

            $.easyAjax({
                url: "{{ route('data-clean.preview') }}",
                type: 'POST',
                container: '#data-clean-form',
                blockUI: true,
                data: { _token: '{{ csrf_token() }}', items: items },
                success: function (r) {
                    if (r.status !== 'success') { return; }

                    var total = 0, list = '', linkedTotal = 0, linked = '';
                    $.each(r.items, function (item, n) { total += n; list += '<div><span>' + esc(name(item)) + '</span><span>' + n.toLocaleString() + '</span></div>'; });
                    $.each(r.linked, function (table, n) { linkedTotal += n; linked += '<div><span>' + esc(table.replace(/_/g, ' ')) + '</span><span>' + n.toLocaleString() + '</span></div>'; });

                    var html = '<p class="f-13 text-left mb-2">Only these will be emptied (' + total.toLocaleString() + ' rows):</p><div class="dc-preview-list">' + list + '</div>';
                    if (linked) {
                        html += '<p class="f-13 text-left text-red mt-3 mb-2"><strong>Employee accounts are ticked.</strong> These rows belong to the people being removed and go with them ('
                            + linkedTotal.toLocaleString() + ' rows), even though their tables are not ticked:</p><div class="dc-preview-list">' + linked + '</div>';
                    }
                    html += '<p class="f-12 text-dark-grey text-left mt-3 mb-0">Rows elsewhere that point at removed rows are cleared as their database links require. A full backup is taken first.</p>';

                    Swal.fire({
                        icon: 'warning',
                        title: 'Remove this data?',
                        html: html,
                        width: 640,
                        showCancelButton: true,
                        confirmButtonText: 'Back up and clean',
                        confirmButtonColor: '#d33'
                    }).then(function (answer) {
                        if (!answer.isConfirmed) { return; }
                        $.easyAjax({
                            url: "{{ route('data-clean.run') }}",
                            type: 'POST',
                            container: '#data-clean-form',
                            blockUI: true,
                            data: { _token: '{{ csrf_token() }}', items: items, confirm: $('#confirm').val() },
                            success: function (response) {
                                if (response.status === 'success') {
                                    Swal.fire({ icon: 'success', title: 'Done', text: response.message }).then(function () { window.location.reload(); });
                                }
                            }
                        });
                    });
                }
            });
        });
    })();
</script>
@endpush
