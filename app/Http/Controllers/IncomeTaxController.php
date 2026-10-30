<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Services\Payroll\PayPeriod;
use App\Services\Payroll\TaxCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Income tax: what each employee owes for the year, and what to take monthly.
 *
 * Until now payroll deducted a figure somebody typed in. This works it out —
 * projected earnings for the year, the reliefs the chosen regime allows, the
 * slabs, rebate, surcharge and cess — and writes the monthly figure back on to
 * the employee so payroll picks it up as before.
 *
 * The rates themselves are editable here, and are flagged as needing review
 * until somebody confirms them against the current Finance Act.
 */
class IncomeTaxController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Income Tax';

        $this->middleware(function ($request, $next) {
            abort_403(!\App\Services\Access\PayrollAccess::can('settings'));

            return $next($request);
        });
    }

    public function index(Request $request, TaxCalculator $tax)
    {
        $year = $request->financial_year ?: TaxCalculator::financialYear();
        $this->year = $year;
        $this->years = $this->yearsOnRecord($year);
        $this->tab = in_array($request->tab, ['employees', 'rates']) ? $request->tab : 'employees';
        $this->cycle = PayPeriod::forMonth(now()->month, now()->year);

        $this->settings = DB::table('tax_settings')->where('financial_year', $year)
            ->orderBy('regime')->get()->keyBy('regime');
        $this->slabs = DB::table('tax_slabs')->where('financial_year', $year)
            ->orderBy('regime')->orderBy('sort_order')->get()->groupBy('regime');
        $this->needsReview = $this->settings->contains(fn ($s) => (bool) $s->needs_review);

        $this->rows = $this->employeeRows($year, $tax);
        $this->totals = [
            'employees' => count($this->rows),
            'annual_tax' => array_sum(array_column($this->rows, 'annual_tax')),
            'deducted' => array_sum(array_column($this->rows, 'already_deducted')),
            'outstanding' => array_sum(array_column($this->rows, 'outstanding')),
            'monthly' => array_sum(array_column($this->rows, 'monthly_tds')),
            'declared' => DB::table('tax_declarations')->where('financial_year', $year)->count(),
        ];

        return view('income-tax.index', $this->data);
    }

    /**
     * One employee's computation, with every step shown.
     */
    public function workings(Request $request, TaxCalculator $tax)
    {
        $request->validate(['user_id' => 'required|integer|exists:users,id']);

        $year = $request->financial_year ?: TaxCalculator::financialYear();
        $cycle = PayPeriod::forMonth(now()->month, now()->year);
        $result = $tax->forEmployee((int) $request->user_id, $cycle->start);
        $result['name'] = DB::table('users')->where('id', $request->user_id)->value('name');
        $result['declaration'] = $tax->declaration((int) $request->user_id, $year);

        return response()->json($result);
    }

    /**
     * Record what an employee says they have, and which regime they chose.
     */
    public function storeDeclaration(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'financial_year' => 'required|string|max:9',
            'regime' => 'required|in:new,old',
            'section_80c' => 'nullable|numeric|min:0|max:9999999',
            'section_80d' => 'nullable|numeric|min:0|max:9999999',
            'section_80ccd_1b' => 'nullable|numeric|min:0|max:9999999',
            'home_loan_interest' => 'nullable|numeric|min:0|max:9999999',
            'other_deductions' => 'nullable|numeric|min:0|max:9999999',
            'rent_paid_annual' => 'nullable|numeric|min:0|max:9999999',
            'previous_employer_income' => 'nullable|numeric|min:0|max:99999999',
            'previous_employer_tax' => 'nullable|numeric|min:0|max:9999999',
            'metro_city' => 'nullable|boolean',
            'proofs_received' => 'nullable|boolean',
            'note' => 'nullable|string|max:255',
        ]);

        $values = [
            'company_id' => DB::table('employee_details')->where('user_id', $request->user_id)->value('company_id'),
            'regime' => $request->regime,
            'section_80c' => (float) $request->section_80c,
            'section_80d' => (float) $request->section_80d,
            'section_80ccd_1b' => (float) $request->section_80ccd_1b,
            'home_loan_interest' => (float) $request->home_loan_interest,
            'other_deductions' => (float) $request->other_deductions,
            'rent_paid_annual' => (float) $request->rent_paid_annual,
            'metro_city' => $request->boolean('metro_city'),
            'previous_employer_income' => (float) $request->previous_employer_income,
            'previous_employer_tax' => (float) $request->previous_employer_tax,
            'proofs_received' => $request->boolean('proofs_received'),
            'note' => $request->note,
            'updated_by' => user()->id,
            'updated_at' => now(),
        ];

        DB::table('tax_declarations')->updateOrInsert(
            ['user_id' => $request->user_id, 'financial_year' => $request->financial_year],
            $values + ['created_at' => now()]
        );

        return Reply::success('Saved. The tax is worked out again from these figures.');
    }

    /**
     * Write the computed monthly figure on to the employees, so payroll deducts
     * it. Only what has actually changed is written.
     */
    public function apply(Request $request, TaxCalculator $tax)
    {
        $request->validate([
            'financial_year' => 'required|string|max:9',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer',
        ]);

        $rows = $this->employeeRows($request->financial_year, $tax, (array) $request->user_ids);
        $changed = 0;

        foreach ($rows as $row) {
            if (!$row['ok'] || abs($row['monthly_tds'] - $row['current_monthly']) < 0.5) {
                continue;
            }

            DB::table('users')->where('id', $row['user_id'])
                ->update(['monthly_tds' => $row['monthly_tds'] ?: null, 'updated_at' => now()]);
            $changed++;
        }

        return Reply::successWithData(
            $changed > 0
                ? sprintf('%d employee(s) now have the computed figure; payroll will deduct it.', $changed)
                : 'Every employee already has the computed figure.',
            ['changed' => $changed]
        );
    }

    /**
     * Change a rate, a threshold or an allowance.
     */
    public function saveRates(Request $request)
    {
        $request->validate([
            'financial_year' => 'required|string|max:9',
            'regime' => 'required|in:new,old',
            'standard_deduction' => 'required|numeric|min:0|max:9999999',
            'rebate_upto' => 'required|numeric|min:0|max:99999999',
            'rebate_max' => 'required|numeric|min:0|max:9999999',
            'cess_pct' => 'required|numeric|min:0|max:100',
            'slabs' => 'required|array|min:1',
            'slabs.*.upto' => 'nullable|numeric|min:0|max:999999999',
            'slabs.*.rate' => 'required|numeric|min:0|max:100',
        ]);

        DB::transaction(function () use ($request) {
            DB::table('tax_settings')
                ->where('financial_year', $request->financial_year)->where('regime', $request->regime)
                ->update([
                    'standard_deduction' => (float) $request->standard_deduction,
                    'rebate_upto' => (float) $request->rebate_upto,
                    'rebate_max' => (float) $request->rebate_max,
                    'cess_pct' => (float) $request->cess_pct,
                    // Edited by hand, so no longer the seeded guess.
                    'needs_review' => false,
                    'updated_at' => now(),
                ]);

            DB::table('tax_slabs')
                ->where('financial_year', $request->financial_year)->where('regime', $request->regime)->delete();

            $order = 0;

            foreach ($request->slabs as $slab) {
                DB::table('tax_slabs')->insert([
                    'financial_year' => $request->financial_year,
                    'regime' => $request->regime,
                    'upto' => ($slab['upto'] ?? null) !== null && $slab['upto'] !== '' ? (float) $slab['upto'] : null,
                    'rate' => (float) $slab['rate'],
                    'sort_order' => $order += 10,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return Reply::success('Rates saved for ' . $request->financial_year . ', ' . $request->regime . ' regime.');
    }

    /**
     * Form 16 Part B: the salary and the tax on it, as the employee files it.
     */
    public function formSixteen(Request $request, TaxCalculator $tax)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'financial_year' => 'nullable|string|max:9',
        ]);

        $year = $request->financial_year ?: TaxCalculator::financialYear();
        $userId = (int) $request->user_id;

        $employee = DB::table('users as u')
            ->leftJoin('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->leftJoin('designations as d', 'd.id', '=', 'ed.designation_id')
            ->where('u.id', $userId)
            ->first(['u.name', 'u.pan_number', 'u.employee_ctc', 'ed.employee_card_id',
                'ed.pf_account_number', 'd.name as designation']);

        $start = TaxCalculator::yearStart($year);
        $payslips = DB::table('payslips')
            ->where('user_id', $userId)->where('status', 'active')->whereNull('deleted_at')
            ->whereIn('approval_status', ['approved', 'paid'])
            ->whereDate('period_start', '>=', $start->toDateString())
            ->whereDate('period_start', '<=', $start->copy()->addYear()->subDay()->toDateString())
            ->orderBy('period_start')
            ->get();

        $computation = $tax->forEmployee($userId, PayPeriod::forMonth(now()->month, now()->year)->start);

        $this->employee = $employee;
        $this->company = DB::table('companies')->first();
        $this->year = $year;
        $this->payslips = $payslips;
        $this->computation = $computation;
        $this->declaration = $tax->declaration($userId, $year);
        $this->issuedOn = now();

        $html = view('income-tax.form-16', $this->data)->render();

        if ($request->boolean('html')) {
            return response($html);
        }

        return Pdf::loadHTML($html)->setPaper('a4')
            ->download('form-16-' . preg_replace('/[^A-Za-z0-9]+/', '-', (string) $employee->name) . '-' . $year . '.pdf');
    }

    /**
     * Every payable employee, with their computation.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function employeeRows(string $year, TaxCalculator $tax, array $only = []): array
    {
        $cycle = PayPeriod::forMonth(now()->month, now()->year);

        $employees = DB::table('users as u')
            ->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->whereNotIn('u.id', \App\Services\Access\SystemAccounts::ids())   // not the Super Admin login
            ->leftJoin('teams as t', 't.id', '=', 'ed.department_id')
            ->where('u.status', 'active')
            ->where('u.employee_ctc', '>', 0)
            ->when($only !== [], fn ($q) => $q->whereIn('u.id', $only))
            ->orderBy('u.name')
            ->get(['u.id', 'u.name', 'u.employee_ctc', 'u.monthly_tds', 'u.pan_number',
                'ed.employee_card_id', 't.team_name as department']);

        $declarations = DB::table('tax_declarations')->where('financial_year', $year)
            ->get()->keyBy('user_id');

        $rows = [];

        foreach ($employees as $employee) {
            $result = $tax->forEmployee((int) $employee->id, $cycle->start);
            $declaration = $declarations[$employee->id] ?? null;

            $rows[] = array_merge($result, [
                'user_id' => (int) $employee->id,
                'name' => $employee->name,
                'code' => $employee->employee_card_id,
                'department' => $employee->department,
                'pan' => $employee->pan_number,
                'ctc' => (float) $employee->employee_ctc,
                'current_monthly' => (float) ($employee->monthly_tds ?? 0),
                'has_declaration' => $declaration !== null,
                'proofs_received' => (bool) ($declaration->proofs_received ?? false),
            ]);
        }

        return $rows;
    }

    /** @return array<int, string> */
    protected function yearsOnRecord(string $current): array
    {
        $years = DB::table('tax_settings')->distinct()->orderByDesc('financial_year')
            ->pluck('financial_year')->all();

        if (!in_array($current, $years, true)) {
            array_unshift($years, $current);
        }

        return $years;
    }

}
