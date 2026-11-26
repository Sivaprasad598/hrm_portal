{{-- PF / ESI / PT for this employee, and how the salary is paid (App\Services\Payroll\Statutory).
     Off: not deducted at all, employee or employer side, and not printed on the payslip. --}}
@php
    $sd = $detail ?? null;
    $on = fn ($column) => $sd === null || !isset($sd->{$column}) || (bool) $sd->{$column};
@endphp
@if (\App\Support\SchemaCache::hasColumn('employee_details', 'pf_applicable'))
    <div class="col-lg-3 col-md-6">
        <div class="form-group my-3">
            <label class="f-14 text-dark-grey mb-12 w-100" data-toggle="tooltip"
                   title="Switch off for staff paid by hand / housekeeping: not deducted (either side) and not printed on the payslip">Statutory deductions</label>
            <div class="d-flex flex-wrap align-items-center" style="min-height:35px; gap: 14px">
                @foreach (['pf_applicable' => 'PF', 'esi_applicable' => 'ESI', 'pt_applicable' => 'PT'] as $column => $label)
                    <input type="hidden" name="{{ $column }}" value="0">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="{{ $column }}" name="{{ $column }}" value="1" @checked($on($column))>
                        <label class="custom-control-label f-14" for="{{ $column }}">{{ $label }}</label>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <x-forms.select fieldId="salary_paid_by" fieldLabel="Salary paid by" fieldName="salary_paid_by">
            @foreach (\App\Services\Payroll\Statutory::PAID_BY as $value => $text)
                <option value="{{ $value }}" @selected(($sd->salary_paid_by ?? 'bank') === $value)>{{ $text }}</option>
            @endforeach
        </x-forms.select>
    </div>
@endif
