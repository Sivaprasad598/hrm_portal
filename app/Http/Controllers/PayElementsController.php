<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Services\Payroll\PayComponents;
use App\Services\Payroll\PayPeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pay that sits outside the standard salary: overtime, a bonus, arrears, a
 * salary advance, a loan being recovered.
 *
 * Three things on one screen. The list of element types the company uses, each
 * a credit or a debit. The amounts given to employees for a particular month,
 * which is what reaches the payslip. And standing arrangements, where one sum
 * is recovered over several months.
 *
 * Nothing here works out pay. Whatever is entered is picked up by the payroll
 * screen and by the payslip itself through the same service, so a figure shows
 * in the estimate before the payslip exists and on the payslip afterwards.
 */
class PayElementsController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Pay Elements';

        $this->middleware(function ($request, $next) {
            // Whole-company salary figures. This was the administrator role
            // itself, which meant payroll could not be handed to anybody
            // without handing them everything else with it. manage_payroll is
            // the permission for the job; the administrator passes regardless.
            abort_403(!\App\Services\Access\PayrollAccess::can('settings'));

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $month = (int) ($request->month ?: now()->month);
        $year = (int) ($request->year ?: now()->year);
        $cycle = PayPeriod::forMonth(max(1, min(12, $month)), $year);

        $this->month = $cycle->month;
        $this->year = $cycle->year;
        $this->cycle = $cycle;
        $this->monthName = $cycle->describe();
        $this->tab = in_array($request->tab, ['types', 'period', 'arrangements', 'rules', 'groups']) ? $request->tab : 'period';

        $this->types = $this->types(false);
        $this->employees = DB::table('users as u')
            ->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->whereNotIn('u.id', \App\Services\Access\SystemAccounts::ids())   // not the Super Admin login
            ->leftJoin('teams as t', 't.id', '=', 'ed.department_id')
            ->where('u.status', 'active')
            ->orderBy('u.name')
            ->get(['u.id', 'u.name', 'ed.employee_card_id', 'ed.department_id', 't.team_name as department']);
        $this->departments = DB::table('teams')->orderBy('team_name')->pluck('team_name', 'id');

        $this->lines = $this->periodLines($cycle);
        $this->arrangements = $this->arrangements();
        $this->rules = $this->rules();
        $this->groups = $this->groups();
        $this->designations = DB::table('designations')->orderBy('name')->pluck('name', 'id');

        $this->totals = [
            'earnings' => array_sum(array_map(fn ($l) => $l->kind === 'earning' ? (float) $l->amount : 0, $this->lines)),
            'deductions' => array_sum(array_map(fn ($l) => $l->kind === 'deduction' ? (float) $l->amount : 0, $this->lines)),
            'people' => count(array_unique(array_map(fn ($l) => $l->user_id, $this->lines))),
            'outstanding' => array_sum(array_map(
                fn ($a) => $a->total_amount === null ? 0 : max(0, (float) $a->total_amount - (float) $a->recovered),
                array_filter($this->arrangements, fn ($a) => $a->status === 'active')
            )),
        ];

        return view('pay-elements.index', $this->data);
    }

    /**
     * Add an element type, or bring back one that was switched off.
     */
    public function storeType(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'kind' => 'required|in:earning,deduction',
            'allows_arrangement' => 'nullable|boolean',
            'default_amount' => 'nullable|numeric|min:0|max:9999999',
            'note' => 'nullable|string|max:255',
        ]);

        $company = DB::table('employee_details')->where('user_id', user()->id)->value('company_id');
        $name = trim($request->name);

        $existing = DB::table('pay_element_types')->where('company_id', $company)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

        if ($existing) {
            // The same name again is taken as bringing it back, not an error.
            DB::table('pay_element_types')->where('id', $existing->id)->update([
                'is_active' => true, 'kind' => $request->kind,
                'allows_arrangement' => $request->boolean('allows_arrangement'),
                'default_amount' => $request->default_amount !== null ? (float) $request->default_amount : null,
                'note' => $request->note, 'updated_at' => now(),
            ]);

            return Reply::success($name . ' is on the list.');
        }

        DB::table('pay_element_types')->insert([
            'company_id' => $company,
            'name' => $name,
            'kind' => $request->kind,
            'allows_arrangement' => $request->boolean('allows_arrangement'),
            'default_amount' => $request->default_amount !== null ? (float) $request->default_amount : null,
            'is_active' => true,
            'note' => $request->note,
            'sort_order' => (int) DB::table('pay_element_types')->max('sort_order') + 10,
            'created_by' => user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Reply::success($name . ' added.');
    }

    /**
     * Switch a type off or on.
     *
     * Nothing is deleted: amounts already given out keep their own label, and a
     * type switched off simply stops being offered.
     */
    public function toggleType(Request $request)
    {
        $request->validate(['id' => 'required|integer|exists:pay_element_types,id']);

        $type = DB::table('pay_element_types')->find($request->id);

        DB::table('pay_element_types')->where('id', $type->id)
            ->update(['is_active' => !$type->is_active, 'updated_at' => now()]);

        return Reply::success($type->name . ($type->is_active ? ' switched off.' : ' switched on.'));
    }

    /**
     * Give an element to one employee or to many, for one period.
     *
     * The screen sends an amount per employee, so a bonus can differ from one
     * person to the next while still being entered once.
     */
    public function storeElements(Request $request, PayComponents $components)
    {
        $request->validate([
            'element_type_id' => 'required|integer|exists:pay_element_types,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'amounts' => 'required|array|min:1',
            'amounts.*' => 'nullable|numeric|min:0|max:9999999',
            'note' => 'nullable|string|max:255',
        ]);

        $type = DB::table('pay_element_types')->find($request->element_type_id);
        $cycle = PayPeriod::forMonth((int) $request->month, (int) $request->year);

        $result = $components->addMany(
            array_filter($request->amounts, fn ($amount) => (float) $amount > 0),
            $cycle, $type->kind, $type->name, $request->note, (int) $type->id
        );

        if ($result['added'] === 0) {
            return Reply::error($result['skipped'] === []
                ? 'Nothing to add — every amount was blank or nil.'
                : 'Nobody could be given it: ' . implode('; ', $result['skipped']));
        }

        return array_merge(
            Reply::success(sprintf('%s added for %d employee(s) in %s.', $type->name, $result['added'], $cycle->describe())),
            ['added' => $result['added'], 'skipped' => $result['skipped']]
        );
    }

    /**
     * Take one amount back off, as long as its payslip is still unissued.
     */
    public function removeElement(Request $request, PayComponents $components)
    {
        $request->validate(['id' => 'required|integer']);

        return $components->remove((int) $request->id)
            ? Reply::success('Removed.')
            : Reply::error('That line belongs to a payslip already issued; revoke the payslip first.');
    }

    /**
     * Start a standing arrangement — so much advanced, so much a month.
     *
     * Leaving the total blank makes it open ended: it is taken every month
     * until somebody stops it.
     */
    public function storeArrangement(Request $request, PayComponents $components)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'element_type_id' => 'required|integer|exists:pay_element_types,id',
            'total_amount' => 'nullable|numeric|min:0|max:99999999',
            'instalment' => 'required|numeric|min:0.01|max:9999999',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'note' => 'nullable|string|max:255',
        ]);

        $type = DB::table('pay_element_types')->find($request->element_type_id);
        $cycle = PayPeriod::forMonth((int) $request->month, (int) $request->year);
        $total = $request->total_amount !== null && $request->total_amount !== '' ? (float) $request->total_amount : null;

        if ($total !== null && $total > 0 && (float) $request->instalment > $total) {
            return Reply::error('The monthly amount is more than the total. Set the total higher, or leave it blank for an open-ended arrangement.');
        }

        $components->openArrangement((int) $request->user_id, $type->kind, $type->name, $total,
            (float) $request->instalment, $cycle->start, $request->note, (int) $type->id);

        $name = DB::table('users')->where('id', $request->user_id)->value('name');

        return Reply::success(sprintf('%s for %s starts in %s.', $type->name, $name, $cycle->describe()));
    }

    /**
     * Stop an arrangement taking any more.
     */
    public function cancelArrangement(Request $request, PayComponents $components)
    {
        $request->validate(['id' => 'required|integer|exists:payroll_arrangements,id']);

        return $components->cancelArrangement((int) $request->id)
            ? Reply::success('Stopped. Nothing more will be taken.')
            : Reply::error('That arrangement is already stopped.');
    }

    /**
     * Start a standing instruction: everyone, a department, a designation, a
     * group or one person gets this element every period.
     */
    public function storeRule(Request $request, PayComponents $components)
    {
        $request->validate([
            'element_type_id' => 'required|integer|exists:pay_element_types,id',
            'scope' => 'required|in:everyone,department,designation,group,employee',
            'scope_id' => 'nullable|integer',
            'amount' => 'required|numeric|min:0.01|max:9999999',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'end_month' => 'nullable|integer|min:1|max:12',
            'end_year' => 'nullable|integer|min:2000|max:2100',
            'note' => 'nullable|string|max:255',
        ]);

        if ($request->scope !== 'everyone' && !$request->filled('scope_id')) {
            return Reply::error('Choose who the rule is for.');
        }

        $start = PayPeriod::forMonth((int) $request->month, (int) $request->year);
        $end = null;

        if ($request->filled('end_month') && $request->filled('end_year')) {
            $end = PayPeriod::forMonth((int) $request->end_month, (int) $request->end_year);

            if ($end->start->lt($start->start)) {
                return Reply::error('The last period cannot come before the first.');
            }
        }

        $type = DB::table('pay_element_types')->find($request->element_type_id);

        DB::table('pay_element_rules')->insert([
            'company_id' => DB::table('employee_details')->where('user_id', user()->id)->value('company_id'),
            'element_type_id' => $type->id,
            'scope' => $request->scope,
            'scope_id' => $request->scope === 'everyone' ? null : (int) $request->scope_id,
            'amount' => (float) $request->amount,
            'starts_on' => $start->start->toDateString(),
            'ends_on' => $end?->start->toDateString(),
            'status' => 'active',
            'note' => $request->note,
            'created_by' => user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Reply::success($type->name . ' will be applied from ' . $start->describe()
            . ($end ? ' to ' . $end->describe() : ', until it is stopped') . '.');
    }

    /**
     * Stop a rule. Lines it has already produced stay where they are; they can
     * be removed one at a time if that is wanted.
     */
    public function stopRule(Request $request)
    {
        $request->validate(['id' => 'required|integer|exists:pay_element_rules,id']);

        $stopped = DB::table('pay_element_rules')->where('id', $request->id)
            ->where('status', 'active')->update(['status' => 'stopped', 'updated_at' => now()]);

        return $stopped
            ? Reply::success('Stopped. Nothing further will be added from it.')
            : Reply::error('That rule is already stopped.');
    }

    /**
     * Name a group so a rule can be pointed at it.
     */
    public function storeGroup(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'note' => 'nullable|string|max:255',
            'members' => 'nullable|array',
            'members.*' => 'integer|exists:users,id',
        ]);

        $company = DB::table('employee_details')->where('user_id', user()->id)->value('company_id');
        $name = trim($request->name);

        $existing = DB::table('pay_element_groups')->where('company_id', $company)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

        $groupId = $existing->id ?? DB::table('pay_element_groups')->insertGetId([
            'company_id' => $company,
            'name' => $name,
            'note' => $request->note,
            'created_by' => user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // The members sent are the members wanted: anybody left off comes out.
        $wanted = array_map('intval', (array) $request->members);
        DB::table('pay_element_group_members')->where('group_id', $groupId)
            ->whereNotIn('user_id', $wanted ?: [0])->delete();

        $held = DB::table('pay_element_group_members')->where('group_id', $groupId)->pluck('user_id')->all();

        foreach (array_diff($wanted, $held) as $userId) {
            DB::table('pay_element_group_members')->insert([
                'group_id' => $groupId, 'user_id' => $userId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return Reply::success(sprintf('%s has %d member(s).', $name, count($wanted)));
    }

    /**
     * Remove a group, and any rule pointed at it.
     */
    public function destroyGroup(Request $request)
    {
        $request->validate(['id' => 'required|integer|exists:pay_element_groups,id']);

        $inUse = DB::table('pay_element_rules')->where('scope', 'group')
            ->where('scope_id', $request->id)->where('status', 'active')->count();

        if ($inUse > 0) {
            return Reply::error('A rule is still pointed at that group. Stop the rule first.');
        }

        $name = DB::table('pay_element_groups')->where('id', $request->id)->value('name');
        DB::table('pay_element_group_members')->where('group_id', $request->id)->delete();
        DB::table('pay_element_groups')->where('id', $request->id)->delete();

        return Reply::success($name . ' removed.');
    }

    /** The standing rules, running ones first. */
    protected function rules(): array
    {
        $rules = DB::table('pay_element_rules as r')
            ->join('pay_element_types as t', 't.id', '=', 'r.element_type_id')
            ->orderByRaw("FIELD(r.status, 'active', 'stopped')")
            ->orderByDesc('r.starts_on')
            ->get(['r.id', 'r.scope', 'r.scope_id', 'r.amount', 'r.starts_on', 'r.ends_on',
                'r.status', 'r.note', 't.name as element_name', 't.kind'])
            ->all();

        $departments = DB::table('teams')->pluck('team_name', 'id');
        $designations = DB::table('designations')->pluck('name', 'id');
        $groups = DB::table('pay_element_groups')->pluck('name', 'id');
        $people = DB::table('users')->pluck('name', 'id');

        foreach ($rules as $rule) {
            $rule->target = match ($rule->scope) {
                'everyone' => 'Everyone',
                'department' => 'Department: ' . ($departments[$rule->scope_id] ?? 'gone'),
                'designation' => 'Designation: ' . ($designations[$rule->scope_id] ?? 'gone'),
                'group' => 'Group: ' . ($groups[$rule->scope_id] ?? 'gone'),
                'employee' => $people[$rule->scope_id] ?? 'gone',
                default => $rule->scope,
            };
            $rule->paid_out = (float) DB::table('payslip_components')->where('rule_id', $rule->id)->sum('amount');
            $rule->times = DB::table('pay_element_rule_applications')->where('rule_id', $rule->id)->count();
        }

        return $rules;
    }

    /** The groups, with who is in them. */
    protected function groups(): array
    {
        $groups = DB::table('pay_element_groups')->orderBy('name')->get()->all();

        foreach ($groups as $group) {
            $group->members = DB::table('pay_element_group_members as m')
                ->join('users as u', 'u.id', '=', 'm.user_id')
                ->where('m.group_id', $group->id)
                ->orderBy('u.name')
                ->get(['u.id', 'u.name'])->all();
        }

        return $groups;
    }

    /** The element types, newest additions last. */
    protected function types(bool $activeOnly = true): array
    {
        return DB::table('pay_element_types')
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderBy('kind')->orderBy('sort_order')->orderBy('name')
            ->get()->all();
    }

    /** Every amount given out for this period, with who it belongs to. */
    protected function periodLines(PayPeriod $cycle): array
    {
        return DB::table('payslip_components as c')
            ->join('users as u', 'u.id', '=', 'c.user_id')
            ->leftJoin('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->leftJoin('payslips as p', 'p.id', '=', 'c.payslip_id')
            ->whereDate('c.period_start', $cycle->start->toDateString())
            ->orderBy('u.name')->orderBy('c.id')
            ->get(['c.id', 'c.user_id', 'c.kind', 'c.label', 'c.amount', 'c.note', 'c.payslip_id',
                'c.arrangement_id', 'u.name', 'ed.employee_card_id',
                'p.payslip_number', 'p.approval_status'])
            ->all();
    }

    /** Standing arrangements, the ones still running first. */
    protected function arrangements(): array
    {
        return DB::table('payroll_arrangements as a')
            ->join('users as u', 'u.id', '=', 'a.user_id')
            ->orderByRaw("FIELD(a.status, 'active', 'completed', 'cancelled')")
            ->orderBy('u.name')
            ->get(['a.id', 'a.user_id', 'a.kind', 'a.label', 'a.total_amount', 'a.instalment',
                'a.recovered', 'a.starts_on', 'a.status', 'a.note', 'u.name'])
            ->all();
    }

}
