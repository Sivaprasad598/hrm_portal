@extends('layouts.app')

@section('content')
    <div class="content-wrapper" data-swap-links>
        <div class="bg-white rounded b-shadow-4">
            @include($view)
        </div>
    </div>
@endsection
