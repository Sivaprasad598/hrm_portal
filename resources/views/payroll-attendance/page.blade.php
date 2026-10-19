@extends('layouts.app')

@section('content')
    <div class="content-wrapper">
        <div class="bg-white rounded b-shadow-4 p-4">
            @include('payroll-attendance.show')
        </div>
    </div>
@endsection
