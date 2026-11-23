@extends('layouts.app')

{{-- A page of a module marked Coming soon (AccountBaseController::comingSoon). --}}
@section('content')
<div class="content-wrapper" data-swap-links>
    @include('coming-soon.panel')
</div>
@endsection
