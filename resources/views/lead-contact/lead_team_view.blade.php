{{--
    Lead Team page: the lead page (lead-contact.show) with only its Lead Team tab — Lead Team, Team Chat and
    Lead Team Meetings, Manage team and Create Meeting — and none of the other tabs.
    LeadContactController::teamView; /lead_team_view.php?id=<lead> opens it.
--}}
@extends('layouts.app')

@push('datatable-styles')
    @include('sections.datatable_css')
@endpush

@section('filter-section')
    <!-- PROJECT HEADER START -->
    <div class="d-flex filter-box project-header bg-white">
        <div class="project-menu d-lg-flex" id="mob-client-detail">
            <x-tab :href="route('lead-contact.team-only', $leadContact->id)" ajax="false" text="Lead Team" class="team" />
        </div>
        {{-- Back to the Lead Contacts list, at the right end of the tab bar --}}
        <a href="{{ route('lead-contact.index') }}"
           class="btn btn-outline-secondary btn-sm align-self-center ml-auto mr-3 text-nowrap"
           title="Back to Lead Contacts">&larr; Back</a>
    </div>
    <!-- PROJECT HEADER END -->
@endsection

@section('content')
    <div class="content-wrapper border-top-0 client-detail-wrapper">
        {{-- Commented out on this page: Manage team, both Create Meeting buttons (Lead Team and Lead Team Meetings),
             the tick boxes with "3 of 3 selected", and Edit / Meeting Result / Delete on the Lead Team Meetings cards --}}
        @include('lead-contact.ajax.team', ['hideManageTeam' => true, 'hideMeetingActions' => true, 'hideCreateMeeting' => true,
            'hideAttendeeSelect' => true])
    </div>
@endsection

@push('scripts')
    <script>
        $('.project-menu .team').addClass('active');
    </script>
@endpush
