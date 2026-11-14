@extends('layouts.app')

{{-- standard page: "Filters" at the top right of the tab row shows the filter card (inside the frame) --}}
@push('page-tab-actions')
    <button type="button" class="btn-secondary rounded f-14 pb-filter-btn" id="pbFilterBtn"
            style="height:38px;border-radius:8px;padding:0 14px;display:inline-flex;align-items:center;">
        <i class="fa fa-filter mr-1"></i> Filters
    </button>
@endpush

@section('content')
    {{-- same grey page area as the Divisions page (no white card around the frame) --}}
    <div class="content-wrapper">
        <iframe
            id="probationRequestsFrame"
            src="{{ route('legacy.page', 'probation_period_request') }}"
            class="w-100 d-block"
            style="min-height: calc(100vh - 200px); border: 0; background: transparent;"
            title="@lang('app.menu.probationPeriodRequests')">
        </iframe>
    </div>

    <script>
        (function () {
            var frame = document.getElementById('probationRequestsFrame');
            var btn = document.getElementById('pbFilterBtn');
            function syncBtn() {
                try { btn.classList.toggle('is-open', frame.contentDocument.body.classList.contains('filters-open')); } catch (e) {}
            }
            btn.addEventListener('click', function () {
                try { frame.contentDocument.body.classList.toggle('filters-open'); } catch (e) {}
                syncBtn();
            });
            frame.addEventListener('load', syncBtn);
        })();
    </script>
    <style>
        html body.epic .pb-filter-btn.is-open { background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 10%, #fff) !important; border-color: var(--epic-blue, #cb6b4f) !important; color: var(--epic-blue, #cb6b4f) !important; }
        html body.epic .pb-filter-btn.is-open i { color: var(--epic-blue, #cb6b4f) !important; }
    </style>
@endsection
