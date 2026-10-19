{{-- Monthly salary beside the CTC (CTC ÷ 12, saved with it), and the month
     broken down by the payroll formula. $monthlySalary: the saved figure (Edit). --}}
<div class="{{ $colClass ?? 'col-lg-3 col-md-6' }}">
    <x-forms.label class="my-3" fieldId="monthly_salary" fieldLabel="Monthly Salary (CTC ÷ 12)"></x-forms.label>
    <x-forms.input-group>
        <x-slot name="prepend">
            <span class="input-group-text f-14 bg-white-shade">{{ company()->currency->currency_symbol }}</span>
        </x-slot>
        <input type="text" class="form-control height-35 f-14 bg-light" name="monthly_salary" id="monthly_salary"
               value="{{ $monthlySalary ?? '' }}" readonly tabindex="-1">
    </x-forms.input-group>
    <a href="javascript:;" id="salary-breakdown-toggle" class="f-12 d-none">
        <i class="fa fa-list-ul mr-1"></i><span>Show monthly breakdown</span>
    </a>
</div>
<div class="col-12 d-none salary-breakdown" id="salary-breakdown"></div>
