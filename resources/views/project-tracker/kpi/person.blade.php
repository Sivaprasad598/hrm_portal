{{-- Work -> Employee KPIs -> one person (TrackerKpiController::show), in the side panel. --}}
<div class="kpi-wrap p-3 p-lg-4">
    @include('project-tracker.kpi.detail', ['panel' => request()->ajax(), 'monthUrl' => fn ($m) => route('project-tracker.kpis.show', [$person->id, 'month' => $m->format('Y-m')])])
</div>
