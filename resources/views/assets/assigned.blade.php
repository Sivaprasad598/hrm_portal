@extends('layouts.app')

@section('content')
@php $fmt = company()->date_format; @endphp
<div class="content-wrapper">

    <form method="GET" action="{{ route('assets.assigned') }}" class="bg-white rounded b-shadow-4 p-3 mb-3">
        <div class="row align-items-end">
            <div class="col-md-3">
                <label class="f-13 text-dark-grey mb-1">Employee</label>
                <select name="user_id" class="form-control select-picker" data-live-search="true">
                    <option value="">All</option>
                    @foreach ($employees as $e)
                        <option value="{{ $e->id }}" @selected(request('user_id') == $e->id)>{{ $e->name }}{{ $e->employee_id ? ' (' . $e->employee_id . ')' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="f-13 text-dark-grey mb-1">Type</label>
                <select name="type_id" class="form-control select-picker">
                    <option value="">All</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->id }}" @selected(request('type_id') == $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="f-13 text-dark-grey mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control f-14" placeholder="Asset, code, serial, employee">
            </div>
            <div class="col-md-2">
                <div class="custom-control custom-checkbox mb-2">
                    <input type="checkbox" class="custom-control-input" id="overdue" name="overdue" value="1" @checked(request('overdue'))>
                    <label class="custom-control-label f-14" for="overdue">Overdue only</label>
                </div>
            </div>
            <div class="col-md-2 text-right">
                <button type="submit" class="btn btn-primary rounded f-14"><i class="fa fa-filter mr-1"></i>Filter</button>
                <a href="{{ route('assets.assigned') }}" class="btn btn-secondary rounded f-14">Clear</a>
            </div>
        </div>
    </form>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <span class="f-15 font-weight-600 text-dark">{{ $assignments->total() }} asset(s) with employees</span>
        <a href="{{ route('assets.assign-form') }}" class="btn btn-primary rounded f-14 openRightModal"><i class="fa fa-user-plus mr-1"></i>Assign Asset</a>
    </div>

    <div class="bg-white rounded b-shadow-4 table-responsive">
        <table class="table table-hover mb-0 f-14">
            <thead class="bg-light">
                <tr>
                    <th>Employee</th>
                    <th>Asset</th>
                    <th>Type</th>
                    <th>Assigned On</th>
                    <th>Expected Return</th>
                    <th>Condition Out</th>
                    <th class="text-right pr-4">Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($assignments as $a)
                <tr>
                    <td>
                        <a href="{{ route('employees.show', $a->user_id) }}?tab=assets" class="text-darkest-grey font-weight-500">{{ $a->user?->name }}</a>
                        <br><span class="f-11 text-dark-grey">{{ $a->user?->employeeDetail?->employee_id }}</span>
                        @if ($a->user && $a->user->status !== 'active')
                            <span class="badge badge-danger">Left</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('assets.show', $a->asset_id) }}" class="openRightModal text-darkest-grey">{{ $a->asset?->name }}</a>
                        <br><span class="f-11 text-dark-grey">{{ $a->asset?->asset_code }}{{ $a->asset?->serial_number ? ' · SN ' . $a->asset->serial_number : '' }}</span>
                    </td>
                    <td>{{ $a->asset?->type?->name ?? '--' }}</td>
                    <td>{{ $a->assigned_on->format($fmt) }}</td>
                    <td>
                        @if ($a->expected_return_on)
                            <span class="{{ $a->isOverdue() ? 'text-danger font-weight-600' : '' }}">{{ $a->expected_return_on->format($fmt) }}</span>
                            @if ($a->isOverdue()) <span class="badge badge-danger">Overdue</span> @endif
                        @else
                            --
                        @endif
                    </td>
                    <td>{{ \App\Models\Asset::CONDITIONS[$a->condition_out] ?? ($a->condition_out ?: '--') }}</td>
                    <td class="text-right pr-4">
                        <a href="{{ route('assets.handover', ['assignment' => $a->id]) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Print handover acknowledgement"><i class="fa fa-print"></i></a>
                        <a href="{{ route('assets.return-form', $a->id) }}" class="btn btn-sm btn-outline-primary openRightModal"><i class="fa fa-undo mr-1"></i>Take Return</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-cards.no-record icon="laptop" message="No asset is with an employee." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $assignments->links() }}</div>
</div>
@endsection
