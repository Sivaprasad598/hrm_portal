{{-- Shown in place of the review once the import has run. w-100: it sits inside a flex row,
     where a child is only as wide as its content unless told otherwise. --}}
<div class="w-100" id="import-done">
    <div class="bg-white rounded border">
        <div class="d-flex align-items-center p-20 border-bottom-grey">
            <i class="fa fa-check-circle text-success f-21 mr-2"></i>
            <h4 class="mb-0 f-21 font-weight-normal">Import finished</h4>
        </div>

        <div class="p-20">
            <div class="row">
                @foreach ([
                    ['Employees added', $result['created'], 'text-success', 'fa-user-plus'],
                    ['Updated', $result['updated'], 'text-primary', 'fa-user-edit'],
                    ['Not imported', $result['skipped'], $result['skipped'] ? 'text-red' : 'text-lightest', 'fa-user-times'],
                    ['Departments created', $result['teams'], 'text-dark-grey', 'fa-sitemap'],
                    ['Designations created', $result['designations'], 'text-dark-grey', 'fa-id-badge'],
                    ['Branches created', $result['branches'], 'text-dark-grey', 'fa-map-marker-alt'],
                ] as [$label, $value, $class, $icon])
                    <div class="col-xl-2 col-lg-4 col-md-4 col-6 mb-3">
                        <div class="border rounded p-3 h-100">
                            <div class="f-12 text-lightest"><i class="fa {{ $icon }} mr-1"></i>{{ $label }}</div>
                            <div class="f-21 font-weight-bold {{ $class }}">{{ number_format((int) $value) }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($result['skipped'])
                <div class="alert alert-warning f-13 mb-3">
                    {{ $result['skipped'] }} row(s) were not imported (for example an EMP ID used twice). They are listed, with the reason,
                    in the file below; fix them in the sheet and import it again - employees already added are updated, not duplicated.
                </div>
            @endif

            <h6 class="f-15 mb-2">Done for every employee</h6>
            <ul class="f-14 text-dark-grey mb-3 pl-3">
                <li>Access role from the designation (Settings → Designations), on top of the Employee role.</li>
                <li>General Shift from the joining date (or the current salary period for earlier joiners).</li>
                <li>Leave balances worked out from each joining date. No email was sent to anyone.</li>
            </ul>

            <h6 class="f-15 mb-2">Next</h6>
            <ol class="f-14 text-dark-grey mb-0 pl-3">
                <li><a href="{{ route('biometric.employees') }}">Biometric → Employee Mapping</a>: <strong>Upload device employee list</strong>
                    (the biometric Employee report), then <strong>Sync all employees</strong>.</li>
                <li>Fix what is listed in the file below under Employees → Edit (missing emails, account numbers Excel rounded, invalid PAN / Aadhaar…).</li>
            </ol>
        </div>

        <div class="d-flex flex-wrap align-items-center p-20 border-top-grey">
            <x-forms.link-primary :link="route('employees.index')" icon="users" class="mr-3 mb-2 mb-md-0">Go to Employees</x-forms.link-primary>
            <x-forms.link-secondary :link="route('biometric.employees')" icon="fingerprint" class="mr-3 mb-2 mb-md-0">Employee Mapping</x-forms.link-secondary>
            <x-forms.link-secondary :link="route('employees.import.report')" icon="download">Download what still needs fixing</x-forms.link-secondary>
        </div>
    </div>
</div>
