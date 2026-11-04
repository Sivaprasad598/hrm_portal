@extends('layouts.app')

{{--
    Leads → Lead Generation Bot: the Target Company Finder (lead-generation-bot.target-company-finder), administrators only.
    It is a complete page of its own, so it is shown in an iframe that fills the content area; its styles stay separate
    from the portal's. Searches run through TargetCompanyFinderController (OpenAI with web search).
--}}

@section('content')
    <div class="content-wrapper p-0" id="leadFinderWrap">
        <iframe src="{{ route('lead-generation-bot.finder') }}" id="leadFinderFrame" title="Target Company Finder"
                allow="clipboard-write"></iframe>
    </div>
@endsection

@push('scripts')
    <style>
        #leadFinderWrap { height: calc(100vh - 64px); }
        #leadFinderFrame { display: block; width: 100%; height: 100%; border: 0; background: #f4f7fb; }
    </style>
@endpush
