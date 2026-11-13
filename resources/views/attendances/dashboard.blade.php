{{-- Attendance > Dashboard tab (AttendanceController::dashboard): the attendance
     dashboard, which used to open in a pop-up, as a page of its own. It scrolls
     inside its frame: its employee table can run to thousands of rows. --}}
@extends('layouts.app')

@section('content')
    {{-- On the grey page like the Attendance Register: no white card around the frame --}}
    <div class="content-wrapper">
        <iframe data-src="{{ $dashboardUrl }}" id="attendance-dashboard-frame" title="Attendance Dashboard"
                style="width: 100%; height: calc(100vh - 150px); min-height: 520px; border: 0; display: block; background: transparent;"></iframe>
    </div>
@endsection

@push('scripts')
    <script>
        // Loaded once the page is up, so the page's own loader does not wait on the dashboard
        $(window).on('load', function () {
            var frame = document.getElementById('attendance-dashboard-frame');
            frame.src = frame.getAttribute('data-src');
        });
    </script>
@endpush
