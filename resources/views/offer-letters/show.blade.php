@extends('layouts.app')

@section('content')
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
            <div class="mb-2">
                <a href="{{ route('offer-letters.index') }}" class="f-13 text-dark-grey"><i class="fa fa-arrow-left mr-1"></i>Offer Letters</a>
                <span class="f-15 font-weight-bold ml-3">{{ $employee->name }}</span>
                <span class="f-13 text-dark-grey ml-2">Reference {{ $letter->reference_no }}</span>
            </div>
            <div class="mb-2">
                <a href="{{ route('offer-letters.pdf', [$letter->id, 'inline' => 1]) }}" target="_blank" class="btn btn-secondary rounded f-14 p-2 mr-2">
                    <i class="fa fa-print mr-1"></i>Print
                </a>
                <a href="{{ route('offer-letters.pdf', $letter->id) }}" class="btn btn-primary rounded f-14 p-2">
                    <i class="fa fa-download mr-1"></i>Download PDF
                </a>
            </div>
        </div>

        @if ($problems)
            <div class="alert alert-warning f-13">
                The employee's record has changed since this letter was issued and now needs:
                <ul class="mb-0 mt-1">@foreach ($problems as $p)<li>{{ $p }}</li>@endforeach</ul>
                <a href="{{ route('employees.edit', $employee->id) }}">Edit the employee</a> - the letter shows their record as it is now.
            </div>
        @endif

        <div class="f-12 text-dark-grey mb-2">
            The letter shows the employee's record as it is now, with this reference and date. Downloading or printing keeps a copy
            in the employee's Documents (Offer Letter) and ticks it on the joining checklist.
        </div>

        <iframe src="{{ route('offer-letters.view', $letter->id) }}" class="w-100 border rounded"
                style="height: calc(100vh - 230px); min-height: 600px; background: #e9ecef;" title="Offer letter"></iframe>
    </div>
@endsection
