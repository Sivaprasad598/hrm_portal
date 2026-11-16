@extends('layouts.app')

@section('content')
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
            <div class="mb-2">
                <a href="{{ route('employee-exit.index') }}" class="f-13 text-dark-grey"><i class="fa fa-arrow-left mr-1"></i>Exited Employees</a>
                <span class="f-15 font-weight-bold ml-3">{{ $employee->name }}</span>
                <span class="f-13 text-dark-grey ml-2">Relieving letter dated {{ \Carbon\Carbon::parse($letter->letter_date)->format(company()->date_format) }}</span>
            </div>
            <div class="mb-2">
                <a href="{{ route('relieving-letters.pdf', [$letter->id, 'inline' => 1]) }}" target="_blank" class="btn btn-secondary rounded f-14 p-2 mr-2">
                    <i class="fa fa-print mr-1"></i>Print
                </a>
                <a href="{{ route('relieving-letters.pdf', $letter->id) }}" class="btn btn-primary rounded f-14 p-2">
                    <i class="fa fa-download mr-1"></i>Download PDF
                </a>
            </div>
        </div>

        <div class="f-12 text-dark-grey mb-2">
            Downloading or printing keeps a copy in the employee's Documents and ticks "Relieving letter issued" on their exit.
        </div>

        <iframe src="{{ route('relieving-letters.view', $letter->id) }}" class="w-100 border rounded"
                style="height: calc(100vh - 230px); min-height: 600px; background: #e9ecef;" title="Relieving letter"></iframe>
    </div>
@endsection
