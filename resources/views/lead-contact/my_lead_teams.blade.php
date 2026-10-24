{{--
    My Lead Teams: the leads the signed-in employee is assigned to (Lead Owner or team member), with the project,
    the Lead Owner and their role. Each opens that lead's Lead Team page. LeadContactController::myTeams;
    /lead_team_view.php without an id opens it. $assignedLeads
--}}
@extends('layouts.app')

@section('content')
<div class="content-wrapper">
    <div class="card bg-white border-0 b-shadow-4">
        <div class="card-header bg-white border-0 p-20">
            <h4 class="f-18 f-w-500 mb-1">My Lead Teams</h4>
            <p class="text-lightest f-13 mb-0">The leads and projects you are assigned to. Open one to see its team, Team Chat and meetings.</p>
        </div>

        @if ($assignedLeads->isEmpty())
            <div class="text-center py-5 px-3">
                <i class="fa fa-users fa-3x text-lightest mb-3"></i>
                <h5 class="f-16 text-dark-grey mb-1">No leads or projects assigned to you yet.</h5>
                <p class="text-lightest f-13 mb-0">When a lead owner adds you to a lead's team, it will appear here.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Lead</th>
                            <th>Project</th>
                            <th>Lead Owner</th>
                            <th>My role</th>
                            <th class="text-center">Team</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($assignedLeads as $row)
                            <tr>
                                <td class="f-14">
                                    <a href="{{ $row['url'] }}" class="text-dark-grey font-weight-bold">{{ $row['lead'] }}</a>
                                    @if ($row['company'] && $row['company'] !== $row['lead'])
                                        <div class="text-lightest f-12">{{ $row['company'] }}</div>
                                    @endif
                                </td>
                                <td class="f-14">{{ $row['project'] ?: '--' }}</td>
                                <td class="f-14">{{ $row['owner'] ?: '--' }}</td>
                                <td>
                                    <span class="badge {{ $row['role'] === 'Lead Owner' ? 'badge-success' : 'badge-light border' }}">{{ $row['role'] }}</span>
                                </td>
                                <td class="text-center f-14">{{ $row['team_size'] }}</td>
                                <td class="text-right">
                                    <a href="{{ $row['url'] }}" class="btn btn-primary btn-sm"><i class="fa fa-users mr-1"></i> Open Lead Team</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
