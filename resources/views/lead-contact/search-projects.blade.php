@extends('layouts.app')

{{--
    Lead Contacts → Search Projects: a page of its own for the project search (Location + Research project; matching
    projects, newest first), under the "Search Projects | Lead Contacts | Meetings" tabs. It is the finder
    (lead-generation-bot.finder) with ?mode=projects: only the project search, without the Lead Finder's sidebar and
    other sections; in an iframe; administrators only.
--}}

@section('content')
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3" style="gap: 8px;">
            <a href="{{ route('lead-contact.index') }}" class="btn btn-outline-secondary" id="backToLeadContacts"><i class="fa fa-arrow-left mr-1"></i>Back</a>
            @include('customers.partials.contact-section-tabs', [
                'active' => 'projects',
                'projectsUrl' => route('lead-contact.search-projects'),
                'listUrl' => route('lead-contact.index'),
                'listLabel' => __('modules.leadContact.leadContacts'),
                'meetingsUrl' => route('customers.index', ['from' => 'lead-contact']) . '#meetings',
                'margin' => 'mb-0',
            ])
        </div>
        <div id="leadProjectsWrap" class="rounded bg-white">
            <iframe src="{{ route('lead-generation-bot.finder', ['mode' => 'projects']) }}" id="leadProjectsFrame" title="Search Projects" allow="clipboard-write"></iframe>
        </div>
    </div>
@endsection

@push('scripts')
    <style>
        #leadProjectsWrap { height: calc(100vh - 190px); min-height: 560px; overflow: hidden; }
        #leadProjectsFrame { display: block; width: 100%; height: 100%; border: 0; background: #f4f7fb; }
    </style>
@endpush
