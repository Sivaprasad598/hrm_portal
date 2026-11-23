{{-- Employee page -> KPI tab, and My Space -> My KPI (EmployeeController::show ?tab=kpi). --}}
<div class="kpi-wrap pt-3">
    @include('project-tracker.kpi.detail', ['panel' => false, 'monthUrl' => fn ($m) => route('employees.show', $person->id) . '?tab=kpi&month=' . $m->format('Y-m')])
</div>
