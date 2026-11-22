{{-- "Register as Employee": after the offer is accepted, opens Add Employee filled from the
     candidate and the offer (CandidateController::convert -> employees.create?candidate=).
     Shown to those who manage recruitment, for a candidate not yet an employee; when something
     stands in the way (e.g. the email is already a user's) it is shown disabled with the reason.
     $candidate; optional $regButtonClass, $regButtonLabel (own names: a page's $class / $label would leak in). --}}
@php
    $regClass = $regButtonClass ?? 'btn btn-sm btn-success';
    $regLabel = $regButtonLabel ?? 'Register as Employee';
@endphp
@if (\App\Services\Recruit\RecruitAccess::can('manage') && !$candidate->user_id)
    @if ($regProblem = \App\Services\Recruit\CandidateConversion::problem($candidate))
        @if ($candidate->applications()->where('stage', 'offer_accepted')->exists())
            <span class="d-inline-block" title="{{ $regProblem }}" data-toggle="tooltip">
                <button type="button" class="{{ $regClass }}" disabled><i class="fa fa-user-plus mr-1"></i>{{ $regLabel }}</button>
            </span>
        @endif
    @else
        <a href="{{ route('candidates.convert', $candidate->id) }}" class="{{ $regClass }} no-swap"
           title="Opens Add Employee with {{ $candidate->name }}'s details and the accepted offer filled in"><i class="fa fa-user-plus mr-1"></i>{{ $regLabel }}</a>
    @endif
@endif
