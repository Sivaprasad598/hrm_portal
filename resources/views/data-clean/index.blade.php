@extends('layouts.app')

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
                    <strong>This permanently removes data, for a fresh test.</strong>
                    A full database backup is taken first (in <code>storage/app/backups</code>); if it fails, nothing is removed.
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
                    <div class="row">
                        @foreach ($groups as $key => [$label, $description])
                            <div class="col-lg-6 mb-3">
                                <label class="d-flex border rounded p-3 h-100 mb-0 cursor-pointer data-clean-group" data-group="{{ $key }}">
                                    <input type="checkbox" name="groups[]" value="{{ $key }}" class="mr-3 mt-1">
                                    <span>
                                        <span class="f-15 font-weight-bold text-dark">{{ $label }}</span>
                                        <span class="badge badge-light ml-2 f-12">{{ number_format($counts[$key] ?? 0) }} rows</span>
                                        <span class="d-block f-12 text-dark-grey mt-1">{{ $description }}</span>
                                        <span class="d-none f-11 text-red mt-1 implied-note">Included: removing employees removes this too.</span>
                                    </span>
                                </label>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-2">
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
                                Back up and clean
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
        var implied = @json($implied);

        function refresh() {
            var employees = $('input[value="employees"]').is(':checked');
            implied.forEach(function (g) {
                var box = $('input[value="' + g + '"]');
                box.prop('disabled', employees);
                if (employees) { box.prop('checked', true); }
                box.closest('label').find('.implied-note').toggleClass('d-none', !employees);
            });
        }

        $('#data-clean-form').on('change', 'input[type=checkbox]', refresh);
        $('#tick-all').on('click', function () { $('input[name="groups[]"]').prop('checked', true); refresh(); });
        $('#tick-none').on('click', function () { $('input[name="groups[]"]').prop('checked', false).prop('disabled', false); refresh(); });

        $('#run-data-clean').on('click', function () {
            var groups = $('input[name="groups[]"]:checked').map(function () { return this.value; }).get();
            if (!groups.length) { Swal.fire({ icon: 'warning', text: 'Tick at least one group.' }); return; }
            if ($('#confirm').val().trim() !== @json($phrase)) { Swal.fire({ icon: 'warning', text: 'Type ' + @json($phrase) + ' exactly to confirm.' }); return; }

            Swal.fire({
                icon: 'warning',
                title: 'Remove this data?',
                text: 'A backup is taken first, then the ticked data is removed for good. This can take a few minutes.',
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
                    data: { _token: '{{ csrf_token() }}', groups: groups, confirm: $('#confirm').val() },
                    success: function (response) {
                        if (response.status === 'success') {
                            Swal.fire({ icon: 'success', title: 'Done', text: response.message }).then(function () { window.location.reload(); });
                        }
                    }
                });
            });
        });
    })();
</script>
@endpush
