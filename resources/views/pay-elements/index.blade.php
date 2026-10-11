@extends('layouts.app')

@push('styles')
    <style>
        .element-table th, .element-table td { white-space: nowrap; }
        .element-table thead th { background: #f8f9fa; }
        .assign-rows { max-height: 46vh; overflow-y: auto; }
        .assign-rows input.amount { width: 120px; }
        .bootstrap-select .dropdown-menu { z-index: 1060; }
        .kind-credit { color: #1d9e6f; }
        .kind-debit { color: #d0342c; }
    </style>
@endpush

@section('content')
    @php
        $money = fn ($v) => '₹' . number_format((float) $v, 2);
        $short = function ($v) {
            $v = (float) $v;

            return $v >= 100000 ? '₹' . number_format($v / 100000, 2) . 'L' : '₹' . number_format($v);
        };
        // What an employee sees on the payslip, said the way the office says it.
        $kindWord = fn ($kind) => $kind === 'earning' ? 'Credit' : 'Debit';
        $activeTypes = array_filter($types, fn ($t) => (bool) $t->is_active);
    @endphp

    <div class="content-wrapper arw-dash">

        {{-- Which period is being worked on --}}
        <div class="card bg-white border-0 b-shadow-4 mb-3">
            <div class="card-body p-3">
                <form method="GET" action="{{ route('pay-elements.index') }}">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <div class="row align-items-end">
                        <div class="col-md-3 mb-2">
                            <label class="f-12 text-dark-grey">@lang('app.month')</label>
                            <select name="month" class="form-control f-14">
                                @foreach (range(1, 12) as $m)
                                    <option value="{{ $m }}" @selected($m === $month)>
                                        {{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="f-12 text-dark-grey">@lang('app.year')</label>
                            <select name="year" class="form-control f-14">
                                @foreach (range(now()->year - 3, now()->year + 1) as $y)
                                    <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <button type="submit" class="btn btn-primary f-14 w-100">@lang('app.apply')</button>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="{{ route('payroll-run.index', ['month' => $month, 'year' => $year]) }}"
                               class="btn btn-outline-secondary f-14 w-100">
                                <i class="fa fa-calculator mr-1"></i> Payroll
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Where the month stands --}}
        <div class="row mb-3">
            @php
                $tiles = [
                    ['Credits', $short($totals['earnings']), 'kind-credit', 'paid on top of salary'],
                    ['Debits', $short($totals['deductions']), 'kind-debit', 'taken off the salary'],
                    ['Employees affected', number_format($totals['people']), 'text-dark-grey',
                        count($lines) . ' line(s) in ' . $monthName],
                    ['Advances outstanding', $short($totals['outstanding']), 'text-yellow',
                        'still to be recovered'],
                ];
            @endphp
            @foreach ($tiles as [$label, $value, $colour, $sub])
                <div class="col-md-3 col-6 mb-2">
                    <div class="card bg-white border-0 b-shadow-4 h-100">
                        <div class="card-body p-3">
                            <p class="mb-1 f-12 text-lightest text-uppercase">{{ $label }}</p>
                            <h4 class="mb-0 f-21 {{ $colour }}">{{ $value }}</h4>
                            <p class="mb-0 f-11 text-lightest">{{ $sub }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <ul class="nav nav-tabs border-0 mb-0" role="tablist">
            @foreach ([
                'period' => $monthName . ' amounts',
                'rules' => 'Standing rules',
                'groups' => 'Groups',
                'types' => 'Element types',
                'arrangements' => 'Advances & loans',
            ] as $key => $label)
                <li class="nav-item">
                    <a class="nav-link f-14 {{ $tab === $key ? 'active' : '' }}" data-toggle="tab"
                       href="#tab-{{ $key }}">{{ $label }}</a>
                </li>
            @endforeach
        </ul>

        <div class="tab-content">

            {{-- ------------------------------------------------ this period --}}
            <div class="tab-pane fade {{ $tab === 'period' ? 'show active' : '' }}" id="tab-period">

                <div class="card bg-white border-0 b-shadow-4 mb-3">
                    <div class="card-header bg-white border-bottom p-3">
                        <h6 class="mb-0 f-14">Give an element for {{ $monthName }}</h6>
                        <p class="mb-0 f-12 text-lightest">
                            {{ $cycle->start->format('d M Y') }} to {{ $cycle->end->format('d M Y') }}
                            &middot; pick who it is for, then an amount each. It shows on the payroll
                            estimate straight away and on the payslip once that is issued.
                        </p>
                    </div>
                    <div class="card-body p-3">
                        @if ($activeTypes === [])
                            <p class="f-13 text-lightest mb-0">
                                No element types yet. Add one on the <strong>Element types</strong> tab first.
                            </p>
                        @else
                            <div class="row align-items-end">
                                <div class="col-md-3 mb-2">
                                    <label class="f-12 text-dark-grey">Element</label>
                                    <select id="assign-type" class="form-control f-14">
                                        @foreach ($activeTypes as $type)
                                            <option value="{{ $type->id }}" data-kind="{{ $type->kind }}"
                                                    data-default="{{ $type->default_amount }}">
                                                {{ $type->name }} ({{ $kindWord($type->kind) }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-5 mb-2">
                                    <label class="f-12 text-dark-grey">@lang('app.employee')</label>
                                    <select id="assign-employees" class="form-control f-14" multiple
                                            data-live-search="true" title="Nobody yet">
                                        @foreach ($employees as $employee)
                                            <option value="{{ $employee->id }}"
                                                    data-department="{{ $employee->department_id }}">
                                                {{ $employee->name }}{{ $employee->employee_card_id ? ' · ' . $employee->employee_card_id : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="f-12 text-dark-grey">Or everyone in a department</label>
                                    <select id="assign-department" class="form-control f-14">
                                        <option value="">Choose a department…</option>
                                        @foreach ($departments as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div id="assign-panel" class="d-none">
                                <hr class="my-2">
                                <div class="row align-items-end">
                                    <div class="col-md-3 mb-2">
                                        <label class="f-12 text-dark-grey">Same amount for everyone</label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" min="0" class="form-control f-14" id="fill-all"
                                                   placeholder="0.00">
                                            <div class="input-group-append">
                                                <button class="btn btn-outline-secondary f-13" type="button" id="fill-all-go">Fill</button>
                                            </div>
                                        </div>
                                        <span class="f-11 text-lightest">Each row can still be changed.</span>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="f-12 text-dark-grey">Note (optional, shows on hover)</label>
                                        <input type="text" class="form-control f-14" id="assign-note" maxlength="255"
                                               placeholder="e.g. 14 hours, approved by Ops">
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <button type="button" class="btn btn-primary f-14 w-100" id="assign-save">
                                            <i class="fa fa-check mr-1"></i> Save <span id="assign-count"></span>
                                        </button>
                                    </div>
                                </div>

                                <div class="assign-rows border rounded">
                                    <table class="table table-sm mb-0 f-13">
                                        <tbody id="assign-rows"></tbody>
                                    </table>
                                </div>
                                <p class="f-11 text-lightest mb-0 mt-1">
                                    A row left blank or at nil is skipped. Rows total
                                    <strong id="assign-total">₹0.00</strong>.
                                </p>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card bg-white border-0 b-shadow-4 mb-3">
                    <div class="card-header bg-white border-bottom p-3">
                        <h6 class="mb-0 f-14">What is already on {{ $monthName }}</h6>
                        <p class="mb-0 f-12 text-lightest">
                            Credits {{ $money($totals['earnings']) }} &middot;
                            debits {{ $money($totals['deductions']) }} &middot;
                            net effect {{ $money($totals['earnings'] - $totals['deductions']) }}
                        </p>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 f-13 element-table">
                            <thead>
                                <tr>
                                    <th>@lang('app.employee')</th>
                                    <th>Element</th>
                                    <th>Type</th>
                                    <th class="text-right">Amount</th>
                                    <th>Where it came from</th>
                                    <th>Payslip</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($lines as $line)
                                    <tr>
                                        <td>
                                            {{ $line->name }}
                                            <span class="f-11 text-lightest d-block">{{ $line->employee_card_id ?: 'no code' }}</span>
                                        </td>
                                        <td>{{ $line->label }}</td>
                                        <td class="{{ $line->kind === 'earning' ? 'kind-credit' : 'kind-debit' }}">
                                            {{ $kindWord($line->kind) }}
                                        </td>
                                        <td class="text-right {{ $line->kind === 'earning' ? 'kind-credit' : 'kind-debit' }}">
                                            {{ $line->kind === 'earning' ? '+' : '−' }}{{ number_format($line->amount, 2) }}
                                        </td>
                                        <td class="f-12 text-lightest">
                                            {{ $line->arrangement_id ? 'Standing arrangement' : 'Entered by hand' }}
                                            @if ($line->note)
                                                <i class="fa fa-info-circle ml-1" title="{{ $line->note }}"></i>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($line->payslip_id)
                                                <span class="badge badge-{{ $line->approval_status === 'paid' ? 'success' : 'light border' }}"
                                                      title="{{ $line->payslip_number }}">
                                                    {{ ucfirst($line->approval_status ?: 'issued') }}
                                                </span>
                                            @else
                                                <span class="f-12 text-lightest">not issued yet</span>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @unless ($line->payslip_id)
                                                <a href="#" class="remove-line text-lightest" data-id="{{ $line->id }}"
                                                   title="Remove this line">&times;</a>
                                            @endunless
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-lightest p-4 f-13">
                                            Nothing extra on {{ $monthName }} yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- -------------------------------------------------- the types --}}
            <div class="tab-pane fade {{ $tab === 'types' ? 'show active' : '' }}" id="tab-types">
                <div class="card bg-white border-0 b-shadow-4 mb-3">
                    <div class="card-header bg-white border-bottom p-3">
                        <h6 class="mb-0 f-14">Add an element type</h6>
                        <p class="mb-0 f-12 text-lightest">
                            A credit is paid on top of salary; a debit is taken off. The name is what the
                            employee reads on the payslip.
                        </p>
                    </div>
                    <div class="card-body p-3">
                        <div class="row align-items-end">
                            <div class="col-md-3 mb-2">
                                <label class="f-12 text-dark-grey">Name</label>
                                <input type="text" class="form-control f-14" id="type-name" maxlength="100"
                                       placeholder="e.g. Night shift allowance">
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="f-12 text-dark-grey">Credit or debit</label>
                                <select id="type-kind" class="form-control f-14">
                                    <option value="earning">Credit — paid on top</option>
                                    <option value="deduction">Debit — taken off</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="f-12 text-dark-grey">Usual amount (optional)</label>
                                <input type="number" step="0.01" min="0" class="form-control f-14" id="type-default"
                                       placeholder="0.00">
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="f-12 text-dark-grey">Note (optional)</label>
                                <input type="text" class="form-control f-14" id="type-note" maxlength="255">
                            </div>
                            <div class="col-md-2 mb-2">
                                <button type="button" class="btn btn-primary f-14 w-100" id="type-save">
                                    <i class="fa fa-plus mr-1"></i> Add
                                </button>
                            </div>
                            <div class="col-12">
                                <label class="f-12 text-lightest mb-0">
                                    <input type="checkbox" id="type-arrangement" value="1">
                                    Can be recovered over several months (an advance, a loan)
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 f-13 element-table">
                            <thead>
                                <tr>
                                    <th>Element</th>
                                    <th>Type</th>
                                    <th class="text-right">Usual amount</th>
                                    <th>Over several months</th>
                                    <th>Note</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($types as $type)
                                    <tr class="{{ $type->is_active ? '' : 'text-lightest' }}">
                                        <td>{{ $type->name }}</td>
                                        <td class="{{ $type->kind === 'earning' ? 'kind-credit' : 'kind-debit' }}">
                                            {{ $kindWord($type->kind) }}
                                        </td>
                                        <td class="text-right">
                                            {{ $type->default_amount !== null ? number_format($type->default_amount, 2) : '—' }}
                                        </td>
                                        <td>{{ $type->allows_arrangement ? 'Yes' : 'No' }}</td>
                                        <td class="f-12 text-lightest">{{ $type->note ?: '—' }}</td>
                                        <td>
                                            <span class="badge badge-{{ $type->is_active ? 'success' : 'light border' }}">
                                                {{ $type->is_active ? 'In use' : 'Switched off' }}
                                            </span>
                                        </td>
                                        <td class="text-right">
                                            <a href="#" class="toggle-type f-12" data-id="{{ $type->id }}">
                                                {{ $type->is_active ? 'Switch off' : 'Switch on' }}
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-lightest p-4 f-13">No element types yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- ------------------------------------------- standing rules --}}
            <div class="tab-pane fade {{ $tab === 'rules' ? 'show active' : '' }}" id="tab-rules">
                <div class="card bg-white border-0 b-shadow-4 mb-3">
                    <div class="card-header bg-white border-bottom p-3">
                        <h6 class="mb-0 f-14">Give an element every period, automatically</h6>
                        <p class="mb-0 f-12 text-lightest">
                            "Everyone in Gis Department gets ₹1,000 a month." The rule adds one line per employee
                            per pay period, from the month it starts until it is stopped. Two rules reaching the
                            same person both apply, each as its own line, so the payslip always says where an
                            amount came from.
                        </p>
                    </div>
                    <div class="card-body p-3">
                        @if ($activeTypes === [])
                            <p class="f-13 text-lightest mb-0">Add an element type first.</p>
                        @else
                            <div class="row align-items-end">
                                <div class="col-md-2 mb-2">
                                    <label class="f-12 text-dark-grey">Element</label>
                                    <select id="rule-type" class="form-control f-14">
                                        @foreach ($activeTypes as $type)
                                            <option value="{{ $type->id }}" data-default="{{ $type->default_amount }}">
                                                {{ $type->name }} ({{ $kindWord($type->kind) }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label class="f-12 text-dark-grey">Who it is for</label>
                                    <select id="rule-scope" class="form-control f-14">
                                        <option value="everyone">Everyone</option>
                                        <option value="department">A department</option>
                                        <option value="designation">A designation</option>
                                        <option value="group">A group</option>
                                        <option value="employee">One employee</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-2" id="rule-target-wrap" style="display:none">
                                    <label class="f-12 text-dark-grey">Which one</label>
                                    <select id="rule-target" class="form-control f-14"></select>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label class="f-12 text-dark-grey">Amount each period</label>
                                    <input type="number" step="0.01" min="0.01" class="form-control f-14" id="rule-amount">
                                </div>
                                <div class="col-md-3 mb-2">
                                    <button type="button" class="btn btn-primary f-14 w-100" id="rule-save">
                                        <i class="fa fa-plus mr-1"></i> Start from {{ $monthName }}
                                    </button>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="f-12 text-dark-grey">Last period (optional)</label>
                                    <div class="d-flex">
                                        <select id="rule-end-month" class="form-control f-14 mr-1">
                                            <option value="">No end</option>
                                            @foreach (range(1, 12) as $m)
                                                <option value="{{ $m }}">{{ \Carbon\Carbon::create(null, $m, 1)->format('M') }}</option>
                                            @endforeach
                                        </select>
                                        <select id="rule-end-year" class="form-control f-14">
                                            @foreach (range(now()->year, now()->year + 2) as $y)
                                                <option value="{{ $y }}">{{ $y }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="f-12 text-dark-grey">Note (shows on the payslip line)</label>
                                    <input type="text" class="form-control f-14" id="rule-note" maxlength="255"
                                           placeholder="e.g. Site allowance agreed for the Gis team">
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 f-13 element-table">
                            <thead>
                                <tr>
                                    <th>Element</th>
                                    <th>Who it is for</th>
                                    <th class="text-right">Each period</th>
                                    <th>From</th>
                                    <th>Until</th>
                                    <th class="text-right">Paid out so far</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rules as $rule)
                                    <tr class="{{ $rule->status === 'active' ? '' : 'text-lightest' }}">
                                        <td class="{{ $rule->kind === 'earning' ? 'kind-credit' : 'kind-debit' }}">
                                            {{ $rule->element_name }}
                                        </td>
                                        <td>{{ $rule->target }}</td>
                                        <td class="text-right">{{ number_format($rule->amount, 2) }}</td>
                                        <td>{{ \Carbon\Carbon::parse($rule->starts_on)->format('d M Y') }}</td>
                                        <td>{{ $rule->ends_on ? \Carbon\Carbon::parse($rule->ends_on)->format('d M Y') : 'no end' }}</td>
                                        <td class="text-right">
                                            {{ number_format($rule->paid_out, 2) }}
                                            <span class="f-11 text-lightest d-block">{{ $rule->times }} line(s)</span>
                                        </td>
                                        <td>
                                            <span class="badge badge-{{ $rule->status === 'active' ? 'success' : 'light border' }}"
                                                  title="{{ $rule->note }}">
                                                {{ $rule->status === 'active' ? 'Running' : 'Stopped' }}
                                            </span>
                                        </td>
                                        <td class="text-right">
                                            @if ($rule->status === 'active')
                                                <a href="#" class="stop-rule f-12 text-red" data-id="{{ $rule->id }}">Stop</a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-lightest p-4 f-13">
                                            No standing rules yet. Anything added here happens every period on its own.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- ------------------------------------------------- the groups --}}
            <div class="tab-pane fade {{ $tab === 'groups' ? 'show active' : '' }}" id="tab-groups">
                <div class="card bg-white border-0 b-shadow-4 mb-3">
                    <div class="card-header bg-white border-bottom p-3">
                        <h6 class="mb-0 f-14">Groups</h6>
                        <p class="mb-0 f-12 text-lightest">
                            A named set of people a rule can be pointed at — the night shift, the site team,
                            whoever is on a particular allowance. Saving a group with the same name again replaces
                            its members.
                        </p>
                    </div>
                    <div class="card-body p-3">
                        <div class="row align-items-end">
                            <div class="col-md-3 mb-2">
                                <label class="f-12 text-dark-grey">Name</label>
                                <input type="text" class="form-control f-14" id="group-name" maxlength="100"
                                       placeholder="e.g. Night shift">
                            </div>
                            <div class="col-md-5 mb-2">
                                <label class="f-12 text-dark-grey">Members</label>
                                <select id="group-members" class="form-control f-14" multiple
                                        data-live-search="true" title="Nobody yet">
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}">
                                            {{ $employee->name }}{{ $employee->employee_card_id ? ' · ' . $employee->employee_card_id : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="f-12 text-dark-grey">Note (optional)</label>
                                <input type="text" class="form-control f-14" id="group-note" maxlength="255">
                            </div>
                            <div class="col-md-2 mb-2">
                                <button type="button" class="btn btn-primary f-14 w-100" id="group-save">
                                    <i class="fa fa-save mr-1"></i> Save group
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 f-13 element-table">
                            <thead>
                                <tr>
                                    <th>Group</th>
                                    <th>Members</th>
                                    <th>Note</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($groups as $group)
                                    <tr>
                                        <td>{{ $group->name }}</td>
                                        <td class="f-12">
                                            {{ count($group->members) }} —
                                            {{ \Illuminate\Support\Str::limit(collect($group->members)->pluck('name')->implode(', '), 90) }}
                                        </td>
                                        <td class="f-12 text-lightest">{{ $group->note ?: '—' }}</td>
                                        <td class="text-right">
                                            <a href="#" class="edit-group f-12 mr-2" data-id="{{ $group->id }}"
                                               data-name="{{ $group->name }}" data-note="{{ $group->note }}"
                                               data-members="{{ collect($group->members)->pluck('id')->implode(',') }}">Edit</a>
                                            <a href="#" class="delete-group f-12 text-red" data-id="{{ $group->id }}">Remove</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-lightest p-4 f-13">
                                            No groups yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- ------------------------------------------- standing amounts --}}
            <div class="tab-pane fade {{ $tab === 'arrangements' ? 'show active' : '' }}" id="tab-arrangements">
                <div class="card bg-white border-0 b-shadow-4 mb-3">
                    <div class="card-header bg-white border-bottom p-3">
                        <h6 class="mb-0 f-14">Recover something over several months</h6>
                        <p class="mb-0 f-12 text-lightest">
                            ₹30,000 advanced, ₹5,000 a month: one instalment is taken each pay period, the last
                            one trimmed to whatever is left, and it stops of its own accord. Leave the total
                            blank for something that runs every month until it is stopped.
                        </p>
                    </div>
                    <div class="card-body p-3">
                        <div class="row align-items-end">
                            <div class="col-md-3 mb-2">
                                <label class="f-12 text-dark-grey">@lang('app.employee')</label>
                                <select id="arr-employee" class="form-control f-14" data-live-search="true">
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}">
                                            {{ $employee->name }}{{ $employee->employee_card_id ? ' · ' . $employee->employee_card_id : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="f-12 text-dark-grey">Element</label>
                                <select id="arr-type" class="form-control f-14">
                                    @foreach ($activeTypes as $type)
                                        <option value="{{ $type->id }}" @selected($type->allows_arrangement)>
                                            {{ $type->name }} ({{ $kindWord($type->kind) }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="f-12 text-dark-grey">Total (blank = no end)</label>
                                <input type="number" step="0.01" min="0" class="form-control f-14" id="arr-total"
                                       placeholder="30000">
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="f-12 text-dark-grey">Each month</label>
                                <input type="number" step="0.01" min="0.01" class="form-control f-14" id="arr-instalment"
                                       placeholder="5000">
                            </div>
                            <div class="col-md-3 mb-2">
                                <button type="button" class="btn btn-primary f-14 w-100" id="arr-save">
                                    <i class="fa fa-plus mr-1"></i> Start from {{ $monthName }}
                                </button>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="f-12 text-dark-grey">Note (optional)</label>
                                <input type="text" class="form-control f-14" id="arr-note" maxlength="255"
                                       placeholder="e.g. advance paid on 2 October by bank transfer">
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 f-13 element-table">
                            <thead>
                                <tr>
                                    <th>@lang('app.employee')</th>
                                    <th>Element</th>
                                    <th class="text-right">Total</th>
                                    <th class="text-right">Each month</th>
                                    <th class="text-right">Taken so far</th>
                                    <th class="text-right">Left</th>
                                    <th>Started</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($arrangements as $arrangement)
                                    @php
                                        $openEnded = $arrangement->total_amount === null;
                                        $left = $openEnded ? null : max(0, (float) $arrangement->total_amount - (float) $arrangement->recovered);
                                    @endphp
                                    <tr class="{{ $arrangement->status === 'active' ? '' : 'text-lightest' }}">
                                        <td>{{ $arrangement->name }}</td>
                                        <td class="{{ $arrangement->kind === 'earning' ? 'kind-credit' : 'kind-debit' }}">
                                            {{ $arrangement->label }}
                                        </td>
                                        <td class="text-right">{{ $openEnded ? 'no end' : number_format($arrangement->total_amount, 2) }}</td>
                                        <td class="text-right">{{ number_format($arrangement->instalment, 2) }}</td>
                                        <td class="text-right">{{ number_format($arrangement->recovered, 2) }}</td>
                                        <td class="text-right">{{ $left === null ? '—' : number_format($left, 2) }}</td>
                                        <td>{{ \Carbon\Carbon::parse($arrangement->starts_on)->format('d M Y') }}</td>
                                        <td>
                                            <span class="badge badge-{{ ['active' => 'success', 'completed' => 'light border', 'cancelled' => 'danger'][$arrangement->status] ?? 'light' }}"
                                                  title="{{ $arrangement->note }}">
                                                {{ ['active' => 'Running', 'completed' => 'Repaid', 'cancelled' => 'Stopped'][$arrangement->status] ?? $arrangement->status }}
                                            </span>
                                        </td>
                                        <td class="text-right">
                                            @if ($arrangement->status === 'active')
                                                <a href="#" class="cancel-arrangement f-12 text-red"
                                                   data-id="{{ $arrangement->id }}">Stop</a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-lightest p-4 f-13">
                                            Nothing is being recovered at the moment.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            var csrf = '{{ csrf_token() }}';
            var month = {{ $month }};
            var year = {{ $year }};
            var employees = @json($employees);

            $('#assign-employees').selectpicker({
                liveSearch: true,
                actionsBox: true,
                selectedTextFormat: 'count > 2',
                countSelectedText: '{0} employees',
                noneSelectedText: 'Nobody yet',
                container: 'body',
                width: '100%'
            });
            $('#arr-employee').selectpicker({ liveSearch: true, container: 'body', width: '100%' });

            function money(value) {
                return '₹' + (value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function post(url, data, done) {
                $.post(url, $.extend({ _token: csrf }, data))
                    .done(function (response) {
                        if (response && response.status === 'fail') {
                            Swal.fire({ icon: 'error', title: response.message });

                            return;
                        }

                        done ? done(response) : window.location.reload();
                    })
                    .fail(function (xhr) {
                        var body = xhr.responseJSON || {};
                        var first = body.errors ? body.errors[Object.keys(body.errors)[0]][0] : null;

                        Swal.fire({ icon: 'error', title: first || body.message || 'That did not work' });
                    });
            }

            // ---- a row per chosen employee, each with its own amount --------
            function drawRows() {
                var chosen = $('#assign-employees').val() || [];
                var fallback = $('#assign-type option:selected').data('default');
                var existing = {};

                $('#assign-rows tr').each(function () {
                    existing[$(this).data('user')] = $(this).find('input.amount').val();
                });

                var html = chosen.map(function (id) {
                    var person = employees.filter(function (e) { return String(e.id) === String(id); })[0] || {};
                    var value = existing[id] !== undefined ? existing[id] : (fallback || '');

                    return '<tr data-user="' + id + '">' +
                        '<td>' + $('<div>').text(person.name || '').html() +
                        '<span class="f-11 text-lightest d-block">' + (person.employee_card_id || 'no code') + '</span></td>' +
                        '<td class="text-right"><input type="number" step="0.01" min="0" class="form-control form-control-sm amount d-inline-block" value="' + value + '" placeholder="0.00"></td>' +
                        '<td class="text-right" style="width:1%"><a href="#" class="drop-row text-lightest" title="Take off this list">&times;</a></td>' +
                        '</tr>';
                }).join('');

                $('#assign-rows').html(html);
                $('#assign-panel').toggleClass('d-none', chosen.length === 0);
                retotal();
            }

            function retotal() {
                var total = 0;
                var counted = 0;

                $('#assign-rows input.amount').each(function () {
                    var value = parseFloat($(this).val());

                    if (value > 0) { total += value; counted++; }
                });

                $('#assign-total').text(money(total));
                $('#assign-count').text(counted ? '(' + counted + ')' : '');
            }

            $('#assign-employees').on('changed.bs.select', drawRows);
            $('#assign-type').on('change', function () {
                // A new element may carry a different usual amount; only blank
                // rows take it, so anything typed in is left alone.
                var fallback = $('#assign-type option:selected').data('default');

                if (fallback) {
                    $('#assign-rows input.amount').each(function () {
                        if (!$(this).val()) { $(this).val(fallback); }
                    });
                }

                retotal();
            });
            $(document).on('input', '#assign-rows input.amount', retotal);
            $(document).on('click', '.drop-row', function (event) {
                event.preventDefault();
                var id = String($(this).closest('tr').data('user'));
                var keep = ($('#assign-employees').val() || []).filter(function (v) { return String(v) !== id; });

                $('#assign-employees').selectpicker('val', keep);
                drawRows();
            });

            $('#assign-department').on('change', function () {
                var department = $(this).val();

                if (!department) { return; }

                var inDepartment = employees
                    .filter(function (e) { return String(e.department_id) === String(department); })
                    .map(function (e) { return String(e.id); });

                if (!inDepartment.length) {
                    Swal.fire({ icon: 'info', title: 'Nobody active in that department' });

                    return;
                }

                var merged = ($('#assign-employees').val() || []).concat(inDepartment)
                    .filter(function (v, i, all) { return all.indexOf(v) === i; });

                $('#assign-employees').selectpicker('val', merged);
                drawRows();
            });

            $('#fill-all-go').on('click', function () {
                var amount = $('#fill-all').val();

                $('#assign-rows input.amount').val(amount);
                retotal();
            });

            $('#assign-save').on('click', function () {
                var amounts = {};
                var counted = 0;

                $('#assign-rows tr').each(function () {
                    var value = parseFloat($(this).find('input.amount').val());

                    if (value > 0) { amounts[$(this).data('user')] = value; counted++; }
                });

                if (!counted) {
                    Swal.fire({ icon: 'info', title: 'Put an amount against at least one person' });

                    return;
                }

                var button = $(this).prop('disabled', true);

                post('{{ route('pay-elements.assign') }}', {
                    element_type_id: $('#assign-type').val(), month: month, year: year,
                    amounts: amounts, note: $('#assign-note').val()
                }, function (response) {
                    var skipped = (response && response.skipped) || [];

                    Swal.fire({
                        icon: skipped.length ? 'warning' : 'success',
                        title: response.message,
                        html: skipped.length ? '<p class="f-13">Left out: ' + skipped.join('; ') + '</p>' : ''
                    }).then(function () { window.location.reload(); });
                });

                setTimeout(function () { button.prop('disabled', false); }, 4000);
            });

            // ---- the period's existing lines --------------------------------
            $(document).on('click', '.remove-line', function (event) {
                event.preventDefault();
                var id = $(this).data('id');

                Swal.fire({
                    title: 'Remove this line?',
                    text: 'If it came from an advance, the amount goes back on to the balance owed.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Remove',
                    cancelButtonText: '@lang('app.cancel')'
                }).then(function (result) {
                    if (result.isConfirmed) { post('{{ route('pay-elements.remove') }}', { id: id }); }
                });
            });

            // ---- the catalogue ---------------------------------------------
            $('#type-save').on('click', function () {
                var name = ($('#type-name').val() || '').trim();

                if (!name) {
                    Swal.fire({ icon: 'info', title: 'Give the element a name' });

                    return;
                }

                post('{{ route('pay-elements.type.store') }}', {
                    name: name, kind: $('#type-kind').val(),
                    allows_arrangement: $('#type-arrangement').is(':checked') ? 1 : 0,
                    default_amount: $('#type-default').val() || null,
                    note: $('#type-note').val()
                });
            });

            $(document).on('click', '.toggle-type', function (event) {
                event.preventDefault();

                post('{{ route('pay-elements.type.toggle') }}', { id: $(this).data('id') });
            });

            // ---- standing rules --------------------------------------------
            var ruleTargets = {
                department: @json($departments),
                designation: @json($designations),
                group: @json(collect($groups)->pluck('name', 'id')),
                employee: @json($employees->pluck('name', 'id'))
            };

            $('#group-members').selectpicker({
                liveSearch: true, actionsBox: true, selectedTextFormat: 'count > 2',
                countSelectedText: '{0} employees', noneSelectedText: 'Nobody yet',
                container: 'body', width: '100%'
            });

            $('#rule-scope').on('change', function () {
                var scope = $(this).val();
                var options = ruleTargets[scope];

                $('#rule-target-wrap').toggle(scope !== 'everyone');

                if (!options) { return; }

                var html = Object.keys(options).map(function (id) {
                    return '<option value="' + id + '">' + $('<div>').text(options[id]).html() + '</option>';
                }).join('');

                $('#rule-target').html(html || '<option value="">Nothing to choose</option>');
            }).trigger('change');

            $('#rule-type').on('change', function () {
                var fallback = $('#rule-type option:selected').data('default');

                if (fallback && !$('#rule-amount').val()) { $('#rule-amount').val(fallback); }
            });

            $('#rule-save').on('click', function () {
                var amount = parseFloat($('#rule-amount').val());
                var scope = $('#rule-scope').val();

                if (!amount || amount <= 0) {
                    Swal.fire({ icon: 'info', title: 'Enter the amount for each period' });

                    return;
                }

                if (scope !== 'everyone' && !$('#rule-target').val()) {
                    Swal.fire({ icon: 'info', title: 'Choose who the rule is for' });

                    return;
                }

                post('{{ route('pay-elements.rule.store') }}', {
                    element_type_id: $('#rule-type').val(), scope: scope,
                    scope_id: scope === 'everyone' ? null : $('#rule-target').val(),
                    amount: amount, month: month, year: year,
                    end_month: $('#rule-end-month').val() || null,
                    end_year: $('#rule-end-month').val() ? $('#rule-end-year').val() : null,
                    note: $('#rule-note').val()
                });
            });

            $(document).on('click', '.stop-rule', function (event) {
                event.preventDefault();
                var id = $(this).data('id');

                Swal.fire({
                    title: 'Stop this rule?',
                    text: 'Lines it has already added stay where they are. Nothing further will be added.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Stop it',
                    cancelButtonText: '@lang('app.cancel')'
                }).then(function (result) {
                    if (result.isConfirmed) { post('{{ route('pay-elements.rule.stop') }}', { id: id }); }
                });
            });

            // ---- groups ----------------------------------------------------
            $('#group-save').on('click', function () {
                var name = ($('#group-name').val() || '').trim();

                if (!name) {
                    Swal.fire({ icon: 'info', title: 'Give the group a name' });

                    return;
                }

                post('{{ route('pay-elements.group.store') }}', {
                    name: name, note: $('#group-note').val(),
                    members: $('#group-members').val() || []
                });
            });

            $(document).on('click', '.edit-group', function (event) {
                event.preventDefault();
                var row = $(this);

                $('#group-name').val(row.data('name'));
                $('#group-note').val(row.data('note'));
                $('#group-members').selectpicker('val', String(row.data('members') || '').split(',').filter(Boolean));
                $('html, body').animate({ scrollTop: $('#group-name').offset().top - 120 }, 200);
            });

            $(document).on('click', '.delete-group', function (event) {
                event.preventDefault();
                var id = $(this).data('id');

                Swal.fire({
                    title: 'Remove this group?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Remove',
                    cancelButtonText: '@lang('app.cancel')'
                }).then(function (result) {
                    if (result.isConfirmed) { post('{{ route('pay-elements.group.delete') }}', { id: id }); }
                });
            });

            // ---- advances and loans ----------------------------------------
            $('#arr-save').on('click', function () {
                var instalment = parseFloat($('#arr-instalment').val());

                if (!instalment || instalment <= 0) {
                    Swal.fire({ icon: 'info', title: 'Enter how much is taken each month' });

                    return;
                }

                post('{{ route('pay-elements.arrangement.store') }}', {
                    user_id: $('#arr-employee').val(), element_type_id: $('#arr-type').val(),
                    total_amount: $('#arr-total').val() || null, instalment: instalment,
                    month: month, year: year, note: $('#arr-note').val()
                });
            });

            $(document).on('click', '.cancel-arrangement', function (event) {
                event.preventDefault();
                var id = $(this).data('id');

                Swal.fire({
                    title: 'Stop taking this?',
                    text: 'Instalments already taken stay as they are. Nothing further will be taken.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Stop it',
                    cancelButtonText: '@lang('app.cancel')'
                }).then(function (result) {
                    if (result.isConfirmed) { post('{{ route('pay-elements.arrangement.cancel') }}', { id: id }); }
                });
            });

            // Keep the chosen tab in the address bar, so a reload comes back here.
            $('a[data-toggle="tab"]').on('shown.bs.tab', function (event) {
                var tab = $(event.target).attr('href').replace('#tab-', '');
                var url = new URL(window.location.href);

                url.searchParams.set('tab', tab);
                window.history.replaceState({}, '', url);
            });
        });
    </script>
@endpush
