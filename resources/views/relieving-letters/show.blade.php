@extends('layouts.app')

{{-- One relieving or experience letter; the two issued together switch between each other. --}}
@section('content')
    @php
        $kindOf = fn ($l) => \App\Services\Employee\RelievingLetter::kind($l->kind ?? null);
        $mine = $kindOf($letter);
        $both = $pair ? [$mine => $letter, $kindOf($pair) => $pair] : [$mine => $letter];
    @endphp
    <div class="content-wrapper" data-swap-links>
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
            <div class="mb-2 d-flex align-items-center flex-wrap">
                @if (($letter->source ?? '') === 'manual')
                    <a href="{{ route('offer-letters.index') }}" class="f-13 text-dark-grey mr-3"><i class="fa fa-arrow-left mr-1"></i>Issued Letters</a>
                @else
                    <a href="{{ route('employee-exit.index') }}" class="f-13 text-dark-grey mr-3"><i class="fa fa-arrow-left mr-1"></i>Exited Employees</a>
                @endif
                <span class="f-15 font-weight-bold">{{ $employee->name }}</span>
                <span class="f-13 text-dark-grey ml-2">{{ $label }} dated {{ \Carbon\Carbon::parse($letter->letter_date)->format(company()->date_format) }}</span>
                @if (($letter->source ?? '') === 'manual')<span class="badge badge-light ml-2">From the form</span>@endif
            </div>
            <div class="mb-2">
                <a href="{{ route('offer-letters.generate', ['type' => $mine, 'duplicate' => $letter->id]) }}" class="btn btn-secondary rounded f-14 p-2 mr-2">
                    <i class="fa fa-copy mr-1"></i>Duplicate
                </a>
                <a href="{{ route('relieving-letters.pdf', [$letter->id, 'inline' => 1]) }}" target="_blank" class="btn btn-secondary rounded f-14 p-2 mr-2">
                    <i class="fa fa-print mr-1"></i>Print
                </a>
                <a href="{{ route('relieving-letters.pdf', $letter->id) }}" class="btn btn-primary rounded f-14 p-2">
                    <i class="fa fa-download mr-1"></i>Download PDF
                </a>
            </div>
        </div>

        @if ($pair)
            {{-- the two letters issued together --}}
            <div class="btn-group mb-3" role="group">
                @foreach (\App\Services\Employee\RelievingLetter::KINDS as $kind => $name)
                    @isset($both[$kind])
                        <a href="{{ route('relieving-letters.show', $both[$kind]->id) }}"
                           class="btn f-14 {{ $kind === $mine ? 'btn-primary' : 'btn-secondary' }}">{{ $name }}</a>
                    @endisset
                @endforeach
            </div>
        @endif

        <div class="f-12 text-dark-grey mb-2">
            @if ($letter->user_id)
                Downloading or printing keeps a copy in the employee's Documents and ticks "{{ $label }} issued" on their exit.
            @else
                Made from the form for someone not in the system; it prints the details it was issued with.
            @endif
        </div>

        <iframe src="{{ route('relieving-letters.view', $letter->id) }}" class="w-100 border rounded"
                style="height: calc(100vh - 260px); min-height: 600px; background: #e9ecef;" title="{{ $label }}"></iframe>
    </div>
@endsection
