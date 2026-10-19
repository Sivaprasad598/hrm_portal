<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Models\User;
use App\Services\Employment\EmploymentPeriods;
use App\Services\Employment\Rejoin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Rejoin: a former employee comes back as the same employee. The details of
 * the new employment are collected on their own form, saved as a draft, and
 * confirmed (App\Services\Employment\Rejoin).
 */
class EmployeeRejoinController extends AccountBaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Rejoin Employee';

        $this->middleware(function ($request, $next) {
            abort_403(!in_array(user()->permission('edit_employees'), ['all'], true));

            return $next($request);
        });
    }

    public function create(int $id, Rejoin $rejoins, EmploymentPeriods $periods)
    {
        $this->employee = User::withoutGlobalScopes()->with('employeeDetail')->findOrFail($id);
        $this->check = $rejoins->check($id);
        $this->draft = $rejoins->draftFor($id);
        $this->periods = $periods->all($id);
        $this->rejoinHistory = $rejoins->history($id);

        $this->designations = DB::table('designations')->orderBy('name')->get(['id', 'name']);
        $this->teams = DB::table('teams')->orderBy('team_name')->get(['id', 'team_name']);
        $this->branches = \App\Models\Branch::all();
        $this->managers = DB::table('users')->where('status', 'active')->where('id', '<>', $id)->orderBy('name')->get(['id', 'name']);
        $this->roles = DB::table('roles')->whereNotIn('name', ['client'])->orderBy('id')->get(['id', 'name', 'display_name']);
        $this->shifts = DB::table('employee_shifts')->where('shift_name', '<>', 'Day Off')->orderBy('office_start_time')->get();
        $this->deviceCodes = DB::table('iapp_employees')->orderBy('emp_code')->get(['emp_code', 'employee_name', 'user_id']);
        $this->probationMonths = (int) (DB::table('probation_period_settings')->where('company_id', company()->id)
            ->orderByDesc('id')->value('months') ?? 0);
        $this->currentRoleId = DB::table('role_user as ru')->join('roles as r', 'r.id', '=', 'ru.role_id')
            ->where('ru.user_id', $id)->where('r.name', '<>', 'employee')->value('r.id')
            ?? DB::table('roles')->where('name', 'employee')->value('id');
        $this->currentShiftId = DB::table('employee_shift_assignments')->where('user_id', $id)
            ->orderByDesc('effective_from')->value('employee_shift_id');

        return view('employees.rejoin', $this->data);
    }

    /** Save the form as a draft, or save and confirm. */
    public function store(Request $request, int $id, Rejoin $rejoins)
    {
        $format = company()->date_format;

        $request->validate([
            'rejoin_date' => 'required|date_format:"' . $format . '"',
            'employee_code' => ['required', 'max:100',
                Rule::unique('employee_details', 'employee_id')->where('company_id', company()->id)->ignore($id, 'user_id')],
            'employee_card_id' => ['required', 'regex:/^[A-Za-z0-9_-]{1,50}$/'],
            'iapp_emp_code' => 'nullable|max:100',
            'designation_id' => 'required|integer|exists:designations,id',
            'department_id' => 'required|integer|exists:teams,id',
            'branch_id' => 'nullable|integer',
            'reporting_to' => 'nullable|integer|exists:users,id',
            'role_id' => 'nullable|integer|exists:roles,id',
            'employment_type' => 'nullable|in:full_time,part_time,on_contract,internship,trainee',
            'employee_ctc' => 'nullable|numeric|min:0',
            'basic_for_payroll' => 'nullable|numeric|min:0|max:100',
            'employee_shift_id' => 'nullable|integer|exists:employee_shifts,id',
            'probation_applies' => 'nullable|boolean',
            'probation_end_date' => 'nullable|required_if:probation_applies,1|date_format:"' . $format . '"',
            'skip_probation_reason' => 'nullable|required_unless:probation_applies,1|max:500',
            'rejoin_reason' => 'nullable|max:500',
            'notes' => 'nullable|max:2000',
            'rehire_override_reason' => 'nullable|max:500',
        ], [
            'probation_end_date.required_if' => 'Give the probation end date, or switch probation off.',
            'skip_probation_reason.required_unless' => 'Say why probation is skipped.',
            'employee_code.unique' => 'That Employee ID is already used by someone else.',
        ]);

        // Another employee already on this card / device code?
        $cardTaken = DB::table('employee_details')->where('employee_card_id', $request->employee_card_id)
            ->where('user_id', '<>', $id)->exists();

        if ($cardTaken) {
            return Reply::error('That Employee Card ID is already used by someone else.');
        }

        $data = $request->only(['rejoin_reason', 'notes', 'employee_code', 'employee_card_id', 'iapp_emp_code', 'designation_id',
            'department_id', 'branch_id', 'reporting_to', 'role_id', 'employment_type', 'employee_ctc', 'basic_for_payroll',
            'employee_shift_id', 'probation_months', 'skip_probation_reason', 'rehire_override_reason']);
        $data['rejoin_date'] = companyToYmd($request->rejoin_date);
        $data['probation_applies'] = $request->boolean('probation_applies');
        $data['probation_end_date'] = $request->filled('probation_end_date') ? companyToYmd($request->probation_end_date) : null;

        if (!$request->boolean('confirm')) {
            $draftId = $rejoins->saveDraft($id, $data, user()->id);

            return Reply::successWithData('Rejoin saved as a draft. Confirm it when everything is in place.', ['id' => $draftId]);
        }

        $check = $rejoins->check($id);

        if ($check['blockers']) {
            return Reply::error(implode(' ', $check['blockers']));
        }

        $draftId = $rejoins->saveDraft($id, $data, user()->id);

        try {
            $rejoins->confirm($draftId, user()->id);
        } catch (\RuntimeException $e) {
            return Reply::error($e->getMessage());
        }

        return Reply::redirect(route('employees.show', $id),
            'Rejoin confirmed. Collect the joining documents for the new employment on Edit Employee.');
    }

    public function cancel(int $rejoinId, Rejoin $rejoins)
    {
        $userId = DB::table('employee_rejoins')->where('id', $rejoinId)->value('user_id');
        $rejoins->cancel($rejoinId, user()->id);

        return Reply::redirect(route('employees.rejoin.create', $userId), 'Rejoin draft cancelled.');
    }

    /** Eligible for rehire, decided on the employment that ended. */
    public function eligibility(Request $request, int $periodId)
    {
        $request->validate([
            'eligible_for_rehire' => 'required|boolean',
            'not_eligible_reason' => 'nullable|required_if:eligible_for_rehire,0|max:500',
        ], ['not_eligible_reason.required_if' => 'Give the reason they should not be rehired.']);

        DB::table('employment_periods')->where('id', $periodId)->update([
            'eligible_for_rehire' => $request->boolean('eligible_for_rehire'),
            'not_eligible_reason' => $request->boolean('eligible_for_rehire') ? null : $request->not_eligible_reason,
            'updated_at' => now(),
        ]);

        return Reply::success('Rehire eligibility saved.');
    }
}
