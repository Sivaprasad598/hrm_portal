@extends('layouts.app')

@section('content')
    <ul class="nav nav-tabs mb-3" id="terminationTabs" role="tablist">
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
