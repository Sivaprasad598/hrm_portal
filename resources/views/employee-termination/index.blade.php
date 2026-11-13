@extends('layouts.app')

@section('content')
<div class="content-wrapper tm-wrap">
    <ul class="nav nav-tabs tm-tabs mb-3" id="terminationTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="termination-dashboard-tab" data-toggle="tab" href="#terminationDashboardPane"
               role="tab" aria-controls="terminationDashboardPane" aria-selected="true">
                Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="termination-form-tab" data-toggle="tab" href="#terminationFormPane"
               role="tab" aria-controls="terminationFormPane" aria-selected="false">
                Employee Termination
            </a>
        </li>
    </ul>

    <div class="tab-content" id="terminationTabContent">
        <div class="tab-pane fade" id="terminationFormPane" role="tabpanel" aria-labelledby="termination-form-tab">
            <iframe
                id="employeeTerminationFrame"
                src="{{ route('legacy.page', 'termination') }}"
                style="display: block; width: 100%; border: 0;"
                title="Employee Termination">
            </iframe>
        </div>

        <div class="tab-pane fade show active" id="terminationDashboardPane" role="tabpanel" aria-labelledby="termination-dashboard-tab">
            <iframe
                id="employeeTerminationDashboardFrame"
                src="{{ route('legacy.page', 'termination_dashboard') }}"
                style="display: block; width: 100%; border: 0;"
                title="Employee Termination Dashboard">
            </iframe>
        </div>
    </div>
</div>

    <style>
        /* Termination: page padding like every other page, and the two views as underlined tabs */
        .tm-wrap .tm-tabs { border-bottom: 1px solid var(--epic-line, #e8e9e9); gap: 4px; }
        .tm-wrap .tm-tabs .nav-link { border: 0; border-bottom: 2px solid transparent; color: var(--epic-muted, #667085);
            font-size: 14px; font-weight: 500; padding: 10px 14px; background: transparent; }
        .tm-wrap .tm-tabs .nav-link:hover { color: var(--epic-text, #1d2939); }
        .tm-wrap .tm-tabs .nav-link.active { color: var(--epic-blue, #cb6b4f); border-bottom-color: var(--epic-blue, #cb6b4f); background: transparent; }
    </style>

    <script>
        (function () {
            var frames = [
                document.getElementById('employeeTerminationFrame'),
                document.getElementById('employeeTerminationDashboardFrame'),
            ];

            function resizeFrame(frame) {
                if (!frame) {
                    return;
                }

                var top = frame.getBoundingClientRect().top;
                frame.style.height = Math.max(window.innerHeight - top, 400) + 'px';
            }

            function resizeAll() {
                frames.forEach(resizeFrame);
            }

            window.addEventListener('resize', resizeAll);
            resizeAll();

            // A tab-pane that isn't active has zero height, so its iframe can't
            // size itself correctly until the tab is actually shown.
            $('a[data-toggle="tab"]').on('shown.bs.tab', resizeAll);
        })();
    </script>
@endsection
