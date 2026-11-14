@extends('layouts.app')

@section('content')
@push('page-tab-actions')
    <button type="button" class="btn-primary rounded f-14" onclick="window.openTerminationForm()"
            style="height:38px;border-radius:8px;padding:0 14px;display:inline-flex;align-items:center;">
        <i class="fa fa-plus mr-1"></i> Add Employee Termination
    </button>
    {{-- shows / hides the list's filter card (inside the frame) --}}
    <button type="button" class="btn-secondary rounded f-14 tm-filter-btn" id="tmFilterBtn"
            style="height:38px;border-radius:8px;padding:0 14px;display:inline-flex;align-items:center;">
        <i class="fa fa-filter mr-1"></i> Filters
    </button>
@endpush
<div class="content-wrapper tm-wrap">
    {{-- The terminations list; "+ Add Employee Termination" opens the form in a pop-up --}}
    <iframe
        id="employeeTerminationDashboardFrame"
        src="{{ route('legacy.page', 'termination_dashboard') }}"
        style="display: block; width: 100%; border: 0; background: transparent;"
        title="Employee Terminations">
    </iframe>
</div>

<div class="modal fade" id="terminationModal" tabindex="-1" role="dialog" aria-labelledby="terminationModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="terminationModalTitle">Employee Termination</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="employeeTerminationFrame" data-src="{{ route('legacy.page', 'termination') }}"
                        style="display: block; width: 100%; height: 72vh; border: 0;" title="Employee Termination"></iframe>
            </div>
        </div>
    </div>
</div>

    <script>
        (function () {
            var list = document.getElementById('employeeTerminationDashboardFrame');
            var form = document.getElementById('employeeTerminationFrame');

            function resizeList() {
                var top = list.getBoundingClientRect().top;
                list.style.height = Math.max(window.innerHeight - top, 400) + 'px';
            }
            window.addEventListener('resize', resizeList);

            // Filters: toggles the filter card in the list frame; the button shows when it is open
            var fbtn = document.getElementById('tmFilterBtn');
            function syncBtn() {
                try { fbtn.classList.toggle('is-open', list.contentDocument.body.classList.contains('filters-open')); } catch (e) {}
            }
            fbtn.addEventListener('click', function () {
                try { list.contentDocument.body.classList.toggle('filters-open'); } catch (e) {}
                syncBtn();
            });
            list.addEventListener('load', syncBtn);
            resizeList();

            // opened from the list's "+ Add Employee Termination"; a fresh form each time
            window.openTerminationForm = function () {
                form.src = form.getAttribute('data-src');
                $('#terminationModal').modal('show');
            };

            // after a save / undo / delete the form reloads with ?added= / ?undone= / ?deleted=:
            // close the pop-up and refresh the list
            form.addEventListener('load', function () {
                try {
                    if (/[?&](added|undone|deleted)=1/.test(form.contentWindow.location.search)) {
                        $('#terminationModal').modal('hide');
                        list.contentWindow.location.reload();
                        if (window.Swal) {
                            Swal.fire({ icon: 'success', title: 'Done', text: 'Termination saved.', timer: 2000, showConfirmButton: false });
                        }
                    }
                } catch (e) {}
            });
        })();
    </script>
    <style>
        html body.epic .tm-filter-btn.is-open { background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 10%, #fff) !important; border-color: var(--epic-blue, #cb6b4f) !important; color: var(--epic-blue, #cb6b4f) !important; }
        html body.epic .tm-filter-btn.is-open i { color: var(--epic-blue, #cb6b4f) !important; }
    </style>
@endsection
