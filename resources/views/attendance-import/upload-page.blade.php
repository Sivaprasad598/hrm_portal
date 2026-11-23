{{-- An upload opened on its own (not in the side panel): the same panel, as a page. --}}
@extends('layouts.app')

@section('content')
    <div class="content-wrapper" data-swap-links>
        <p class="mb-2"><a href="{{ route('attendance-import.index') }}" class="f-13"><i class="fa fa-arrow-left mr-1"></i> Attendance Import</a></p>
        <div class="bg-white rounded b-shadow-4">
            @include('attendance-import.upload')
        </div>
    </div>
@endsection
