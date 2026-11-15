@extends('layouts.app')

@section('content')
    {{-- Standard layout (as Run Payroll): header card, stat cards, the table in a card --}}
    <div class="content-wrapper arw-dash">
        @include('payroll-attendance.show', ['asPage' => true])
    </div>
@endsection
