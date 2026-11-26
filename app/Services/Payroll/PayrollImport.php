<?php

namespace App\Services\Payroll;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * HR generating a pay period's payslips from a spreadsheet (Payroll -> Payroll Import).
 *
 * Two formats, told apart by their headings:
 *
 *  SIMPLE   Employee Code | Name | Annual CTC | TDS | Other Earnings | Other Deductions | Remarks
 *           The salary is worked out exactly as Payroll Run does (SalaryCalculator): a blank
 *           CTC or TDS is the one on record (with salary revisions); Monthly Gross may stand
 *           in for Annual CTC. Pay elements already entered for the period are included,
 *           as on Payroll Run.
 *
 *  DETAILED (totals included) Basic, HRA, Conveyance, LTA, CEA, Special Allowance,
 *           Other Earnings, PF, ESI, PT, TDS, Attendance Deduction, Other Deductions,
 *           Employer PF, Employer ESI, Working Days, Days Paid, and Gross / Net Pay to check
 *           against. The figures are the payslip; they must add up (earnings to Gross, Gross
 *           less deductions to Net Pay, within a rupee).
 *
 * The one condition (Payroll Settings -> "Use biometric attendance for payroll"):
 *  - on:  the days paid come from ERP attendance (biometric and uploaded), so loss of pay
 *         is deducted; somebody with no attendance at all is not paid (as Payroll Run).
 *  - off: no attendance deductions: the full month is paid; a detailed line with an
 *         attendance deduction is refused.
 * PF, ESI, PT and TDS are statutory and apply either way.
 *
 * As Payroll Run: only periods that are over, nobody who already has a payslip for the
 * period, nobody settled before it, never the Super Admin login. Nothing is written until
 * the preview has been read and confirmed; the checks run again then.
 */
class PayrollImport
{

    public const SIMPLE = ['Employee Code', 'Name', 'Annual CTC', 'TDS', 'Other Earnings', 'Other Deductions', 'Remarks'];

    public const DETAILED = ['Employee Code', 'Name', 'Working Days', 'Days Paid', 'Basic', 'HRA', 'Conveyance', 'LTA', 'CEA',
        'Special Allowance', 'Other Earnings', 'Gross', 'PF', 'ESI', 'PT', 'TDS', 'Attendance Deduction', 'Other Deductions',
        'Employer PF', 'Employer ESI', 'Net Pay', 'Remarks'];

    /** heading (letters only, lower case) => field */
    protected const FIELDS = [
        'code' => ['employeecode', 'empcode', 'code', 'employeeid', 'empid', 'employeeno', 'empno'],
        'ctc' => ['annualctc', 'ctc', 'annualsalary', 'yearlyctc', 'ctcannual'],
        'monthly' => ['monthlygross', 'monthlysalary', 'monthlyctc', 'grossmonthly'],
        'tds' => ['tds', 'incometax', 'tax'],
        // (a "Bonus", "Arrears", "Loan Recovery"... column is its own line: see payElementColumns())
        'other_earnings' => ['otherearnings', 'otherearning', 'additions'],
        'other_deductions' => ['otherdeductions', 'otherdeduction'],
        'remarks' => ['remarks', 'remark', 'note', 'notes'],
        'working_days' => ['workingdays', 'totaldays', 'days', 'daysinmonth'],
        'days_paid' => ['dayspaid', 'dayspresent', 'paiddays', 'payabledays'],
        'basic' => ['basic', 'basicpay', 'basicsalary'],
        'hra' => ['hra', 'houserent', 'houserentallowance'],
        'ca' => ['conveyance', 'ca', 'conveyanceallowance'],
        'lta' => ['lta', 'leavetravelallowance'],
        'cea' => ['cea', 'childreneducationallowance', 'childrenseducation'],
        'special' => ['specialallowance', 'special', 'others', 'otherallowance'],
        'gross' => ['gross', 'grosssalary', 'grosspay', 'totalearnings'],
        'pf' => ['pf', 'employeepf', 'pfemployee', 'providentfund'],
        'esi' => ['esi', 'employeeesi', 'esiemployee'],
        'pt' => ['pt', 'professionaltax', 'proftax'],
        'lop' => ['attendancededuction', 'lop', 'lopamount', 'lossofpay', 'lopdeduction'],
        'employer_pf' => ['employerpf', 'pfemployer'],
        'employer_esi' => ['employeresi', 'esiemployer'],
        'net' => ['netpay', 'net', 'netsalary', 'takehome', 'netpayable'],
    ];

    /** The extra columns the samples show (before Remarks). */
    /** The samples' columns (three earnings, three deductions); more lines are on their Earnings & Deductions tab. */
    public const SAMPLE_EXTRAS = [
        ['Bonus', 'earning'], ['Incentive', 'earning'], ['Night Allowance', 'earning'],
        ['Salary Advance', 'deduction'], ['Canteen', 'deduction'], ['Uniform', 'deduction'],
    ];

    /** The headings of a format's sheet. */
    public static function headings(string $format): array
    {
        return $format === 'detailed' ? self::DETAILED : self::SIMPLE;
    }

    protected const DETAILED_MARKERS = ['basic', 'hra', 'pf', 'net'];

    protected SalaryCalculator $calculator;

    public function __construct()
    {
        $this->calculator = new SalaryCalculator();
    }

    // ---------------------------------------------------------------- periods

    /** The financial year starts with this payroll month (April). */
    public const FY_FIRST_MONTH = 4;

    /**
     * The periods that can be imported, the latest first: the pay cycles of this financial year
     * that are over (with cycles from the 21st, April 2026 = 21 Mar - 20 Apr 2026, up to the last
     * one ended). Always whole cycles, never dates of one's own: a payslip is for one pay period.
     * "This year" is the financial year of the last period that is over, so in early April the
     * whole of the year just closed is still at hand.
     *
     * @return array<string, PayPeriod>
     */
    public function periods(): array
    {
        $tz = DB::table('companies')->value('timezone') ?: 'Asia/Kolkata';
        $cycle = PayPeriod::containing(Carbon::now($tz))->previous();
        $fyStart = $this->fyStartYear($cycle);
        $out = [];

        while ($cycle->year * 100 + $cycle->month >= $fyStart * 100 + self::FY_FIRST_MONTH) {
            $out[$this->key($cycle)] = $cycle;
            $cycle = $cycle->previous();
        }

        return $out;
    }

    /** The year the financial year of a pay period starts in. */
    protected function fyStartYear(PayPeriod $cycle): int
    {
        return $cycle->month >= self::FY_FIRST_MONTH ? $cycle->year : $cycle->year - 1;
    }

    /** "FY 2026-27": the financial year the periods belong to. */
    public function fyLabel(): string
    {
        $periods = $this->periods();
        $start = $this->fyStartYear(reset($periods));

        return 'FY ' . $start . '-' . substr((string) ($start + 1), -2);
    }

    public function key(PayPeriod $cycle): string
    {
        return sprintf('%04d-%02d', $cycle->year, $cycle->month);
    }

    public function period(?string $key): PayPeriod
    {
        $periods = $this->periods();

        return $periods[$key] ?? reset($periods);
    }

    /** Payroll Settings -> "Use biometric attendance for payroll": deduct for attendance or not. */
    public function useAttendance(): bool
    {
        return AttendanceGate::useBiometric(DB::table('companies')->value('id'));
    }

    // -------------------------------------------------------------- employees

    /**
     * Everyone who can be paid for the period, keyed by employee code (lower case).
     */
    public function employees(PayPeriod $cycle)
    {
        return DB::table('users as u')
            ->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->leftJoin('designations as d', 'd.id', '=', 'ed.designation_id')
            ->whereNotIn('u.id', \App\Services\Access\SystemAccounts::hidden())
            ->whereNotNull('ed.employee_card_id')->where('ed.employee_card_id', '!=', '')
            ->where(fn ($q) => $q->whereNull('ed.joining_date')->orWhereDate('ed.joining_date', '<=', $cycle->end->toDateString()))
            ->where(fn ($q) => $q->where('u.status', 'active')->orWhereDate('ed.last_date', '>=', $cycle->start->toDateString()))
            ->orderBy('u.name')
            ->get(['u.id', 'u.name', 'u.pan_number', 'u.employee_ctc', 'u.basic_for_payroll', 'u.monthly_tds',
                'ed.employee_card_id', 'ed.company_id', 'ed.attendance_exempt', 'd.name as designation'])
            ->keyBy(fn ($e) => mb_strtolower(trim((string) $e->employee_card_id)));
    }

    /** Employee codes that already have a payslip standing for the period. */
    public function issued(PayPeriod $cycle): array
    {
        return DB::table('payslips')->where('period', $cycle->label)->where('status', 'active')->whereNull('deleted_at')
            ->pluck('emp_id')->map(fn ($c) => mb_strtolower(trim((string) $c)))->flip()->all();
    }

    /** Why somebody cannot be paid for the period by their settlement, or null (as PayslipWriter). */
    protected function settledReason(int $userId, PayPeriod $cycle): ?string
    {
        $settled = DB::table('settlements')->where('user_id', $userId)->where('status', 'paid')
            ->orderByDesc('last_working_day')->value('last_working_day');

        if (!$settled) {
            return null;
        }

        $rejoinedOn = DB::table('employment_periods')->where('user_id', $userId)
            ->where('joined_on', '>', substr((string) $settled, 0, 10))->min('joined_on');

        if ($cycle->start->gt(Carbon::parse($settled)) && (!$rejoinedOn || $cycle->end->lt(Carbon::parse($rejoinedOn)))) {
            return 'settled on ' . Carbon::parse($settled)->format('d M Y') . ', so this period is after they left';
        }

        return null;
    }

    // -------------------------------------------------------------- the maths

    /**
     * The pay for the period as Payroll Run works it out, with a CTC / TDS from the sheet
     * where one is given. Attendance off: the full month.
     *
     * @param  array<int, object> $employees  by user id
     * @param  array<int, array{ctc?: ?float, tds?: ?float}> $overrides
     * @return array<int, array{pay: array, count: array, ctc: float}>
     */
    public function calculate(array $employees, PayPeriod $cycle, bool $useAttendance, array $overrides = []): array
    {
        $days = new AttendanceDays();
        $ids = array_map('intval', array_keys($employees));

        if ($useAttendance && $ids) {
            $days->preload($ids, $cycle->start, $cycle->end);
        }

        $out = [];

        foreach ($employees as $id => $e) {
            [$ctc, $basicPct] = SalaryRevisions::payFor((int) $id, $cycle, (float) $e->employee_ctc,
                $e->basic_for_payroll !== null ? (float) $e->basic_for_payroll : null);
            $ctc = $overrides[$id]['ctc'] ?? $ctc;
            $tds = $overrides[$id]['tds'] ?? (float) ($e->monthly_tds ?? 0);

            $count = $useAttendance
                ? $days->forRange((int) $id, $cycle->start, $cycle->end)
                : ['working_days' => $cycle->days(), 'days_present' => (float) $cycle->days(), 'basis' => 'full month'];

            $out[(int) $id] = [
                'pay' => $this->calculator->forEmployee((float) $ctc, $basicPct, (int) $count['working_days'], (float) $count['days_present'], (float) $tds,
                    PayrollSwitches::ownValue('prorate_statutory', (int) $id, $cycle->start->toDateString()), Statutory::flags((int) $id)),
                'count' => $count,
                'ctc' => (float) $ctc,
            ];
        }

        return $out;
    }

    // ----------------------------------------------------------------- sheets

    /**
     * The sheet to download for the period, every employee with a salary, what is known filled in:
     * simple - the CTC and TDS on record; detailed - the payslip as the system works it out now.
     */
    public function sheet(PayPeriod $cycle, string $format, ?int $limit = null): array
    {
        $employees = $this->employees($cycle)->filter(fn ($e) => (float) $e->employee_ctc > 0);
        $issued = $this->issued($cycle);
        $employees = $employees->reject(fn ($e, $code) => isset($issued[$code]));

        if ($limit) {
            $employees = $employees->take($limit);
        }

        $byId = $employees->keyBy('id')->all();

        if ($format === 'simple') {
            return $employees->map(fn ($e) => [
                'Employee Code' => (string) $e->employee_card_id, 'Name' => $e->name,
                'Annual CTC' => round((float) $e->employee_ctc, 2), 'TDS' => round((float) ($e->monthly_tds ?? 0), 2),
                'Other Earnings' => null, 'Other Deductions' => null, 'Remarks' => null,
            ])->values()->all();
        }

        $calc = $this->calculate($byId, $cycle, $this->useAttendance());
        $components = new PayComponents();
        PayComponents::preload(array_map('intval', array_keys($byId)), $cycle->start);
        $rows = [];

        foreach ($employees as $e) {
            $p = $calc[(int) $e->id]['pay'];
            // pay elements entered for the period go on the payslip too (see preview), so Net Pay includes them
            $onRecord = $components->forPeriod((int) $e->id, $cycle->start);
            $p['final_net'] += (float) $onRecord['earning_total'] - (float) $onRecord['deduction_total'];
            $rows[] = [
                'Employee Code' => (string) $e->employee_card_id, 'Name' => $e->name,
                'Working Days' => $p['working_days'], 'Days Paid' => round($p['days_present'], 2),
                'Basic' => round($p['basic'], 2), 'HRA' => round($p['hra'], 2), 'Conveyance' => round($p['ca'], 2),
                'LTA' => round($p['lta'], 2), 'CEA' => round($p['cea'], 2), 'Special Allowance' => round($p['other'], 2),
                'Other Earnings' => 0, 'Gross' => round($p['gross'], 2),
                'PF' => round($p['pf_employee'], 2), 'ESI' => round($p['esi_employee'], 2), 'PT' => round($p['pt'], 2),
                'TDS' => round($p['tds'], 2), 'Attendance Deduction' => round($p['lop_amount'], 2), 'Other Deductions' => 0,
                'Employer PF' => round($p['pf_employer'], 2), 'Employer ESI' => round($p['esi_employer'], 2),
                'Net Pay' => round($p['final_net'], 2), 'Remarks' => null,
            ];
        }

        return $rows;
    }

    /**
     * A small sample of a format: three employees, a few optional columns used, and their
     * Earnings & Deductions tab.
     *
     * @return array{main: array, items: array}
     */
    public function sample(PayPeriod $cycle, string $format): array
    {
        // three people who can be paid now (with attendance on: some attendance in the period), so
        // the sample goes through as it is
        $useAttendance = $this->useAttendance();
        $gate = new AttendanceGate();
        $issued = $this->issued($cycle);
        $codes = [];

        foreach ($this->employees($cycle) as $code => $e) {
            if ((float) $e->employee_ctc <= 0 || isset($issued[$code]) || $this->settledReason((int) $e->id, $cycle)) {
                continue;
            }

            if ($useAttendance && ($b = $gate->blocker((int) $e->id, $cycle, (int) $e->company_id ?: null)) && !$b[1]) {
                continue;
            }

            $codes[$code] = true;

            if (count($codes) === 3) {
                break;
            }
        }

        $rows = array_values(array_filter($this->sheet($cycle, $format),
            fn ($r) => isset($codes[mb_strtolower(trim((string) $r['Employee Code']))])));

        // Several kinds of earnings and deductions per employee, both ways:
        //  - a column on the Payroll tab (Bonus, Deduction: Canteen), and
        //  - one line per item on the Earnings & Deductions tab (the rest).
        // Nothing already entered for the period under the same name is repeated (it would be paid twice).
        $columns = $this->sampleColumns();
        $byCode = $this->employees($cycle);
        // every employee: several earnings and several deductions, in columns and on the tab
        $inColumns = [
            0 => ['Bonus' => 2000, 'Incentive' => 1000, 'Night Allowance' => 800, 'Salary Advance' => 1500, 'Canteen' => 350, 'Uniform' => 500],
            1 => ['Bonus' => 1500, 'Incentive' => 500, 'Night Allowance' => 400, 'Salary Advance' => 1000, 'Canteen' => 300, 'Uniform' => 250],
            2 => ['Bonus' => 1000, 'Incentive' => 750, 'Night Allowance' => 300, 'Salary Advance' => 500, 'Canteen' => 200, 'Uniform' => 150],
        ];
        $onTab = [
            0 => [['earning', 'Arrears', 1200], ['earning', 'Travel Allowance', 600], ['deduction', 'Fine', 100], ['deduction', 'Mess', 250]],
            1 => [['earning', 'Shift Allowance', 700], ['earning', 'Medical Allowance', 500], ['deduction', 'Fine', 50], ['deduction', 'Mess', 200]],
            2 => [['earning', 'Arrears', 900], ['earning', 'Travel Allowance', 400], ['deduction', 'Fine', 75], ['deduction', 'Mess', 150]],
        ];
        $elements = $this->payElements();
        $items = [];

        foreach ($rows as $i => &$r) {
            $r += array_fill_keys(array_keys($columns), null);
            $e = $byCode[mb_strtolower(trim((string) $r['Employee Code']))] ?? null;
            $entered = $e ? DB::table('payslip_components')->where('user_id', $e->id)->whereDate('period_start', $cycle->start->toDateString())
                ->whereNull('payslip_id')->pluck('label')->map(fn ($l) => mb_strtolower(trim($l)))->all() : [];
            $net = 0.0;
            $said = [];

            foreach ($columns as $heading => [$label, $kind]) {
                $amount = $inColumns[$i][$label] ?? null;

                if ($amount && !in_array(mb_strtolower($label), $entered, true)) {
                    $r[$heading] = $amount;
                    $net += $kind === 'earning' ? $amount : -$amount;
                    $said[] = ($kind === 'earning' ? '+' : '−') . $label . ' ' . number_format($amount);
                }
            }

            $tabCount = 0;

            foreach ($onTab[$i] ?? [] as [$kind, $label, $amount]) {
                $element = $elements[preg_replace('/[^a-z0-9]/', '', mb_strtolower($label))] ?? null;
                $label = $element && $element['kind'] === $kind ? $element['name'] : $label;

                if (in_array(mb_strtolower($label), $entered, true)) {
                    continue;
                }

                $items[] = ['Employee Code' => $r['Employee Code'], 'Name' => $i === 2 ? null : $r['Name'],
                    'Type' => $kind === 'earning' ? 'Earning' : 'Deduction', 'Item' => $label, 'Amount' => $amount,
                    'Remarks' => $tabCount === 0 ? 'Example: one line per item; Type says earning or deduction' : null];
                $net += $kind === 'earning' ? $amount : -$amount;
                $tabCount++;
            }

            $what = 'Example: ' . ($said ? implode(', ', $said) . ' in columns here' : 'nothing in columns here')
                . ($tabCount ? ' + ' . $tabCount . ' line(s) on the Earnings & Deductions tab' : '');

            if ($format === 'simple') {
                if ($i === 1) { $r['Annual CTC'] = null; $r['TDS'] = null; }
                if ($i === 2) { $r['Name'] = null; }
                $r['Remarks'] = $what . ($i === 1 ? '. Blank CTC / TDS = as on record' : '') . ($i === 2 ? '. Name is optional' : '');
            } elseif ($i === 2) {
                $r['Gross'] = null; $r['Net Pay'] = null;
                $r['Remarks'] = $what . '. Gross and Net Pay are optional (only checked when given)';
            } else {
                $r['Net Pay'] = round($r['Net Pay'] + $net, 2);
                $r['Remarks'] = $what . '. Net Pay includes all of them (' . ($net >= 0 ? '+' : '−') . number_format(abs($net)) . ')';
            }
        }
        unset($r);

        return ['main' => $rows, 'items' => $items];
    }

    /**
     * The extra columns of the samples, heading => [label, earning|deduction]: a pay element's own
     * name where the company has one, otherwise "Earning: ..." / "Deduction: ...".
     */
    public function sampleColumns(): array
    {
        $elements = $this->payElements();
        $out = [];

        // Every column says what it is ("Earning: Bonus", "Deduction: Salary Advance"); a pay element's
        // name after the prefix still links the line to that element.
        foreach (self::SAMPLE_EXTRAS as [$label, $kind]) {
            $element = $elements[preg_replace('/[^a-z0-9]/', '', mb_strtolower($label))] ?? null;
            $name = $element && $element['kind'] === $kind ? $element['name'] : $label;
            $out[($kind === 'earning' ? 'Earning: ' : 'Deduction: ') . $name] = [$name, $kind];
        }

        return $out;
    }

    /** A sample's headings: the format's, with the extra earning / deduction columns before Remarks. */
    public function sampleHeadings(string $format): array
    {
        $headings = self::headings($format);
        array_splice($headings, count($headings) - 1, 0, array_keys($this->sampleColumns()));

        return $headings;
    }

    // ---------------------------------------------------------------- preview

    /**
     * Read an uploaded sheet: every payslip it would generate, with totals. Nothing is written.
     *
     * @param  array $rows      the Payroll tab, headings first
     * @param  array $itemRows  the Earnings & Deductions tab, headings first (optional): one line per item
     */
    public function preview(array $rows, PayPeriod $cycle, array $itemRows = []): array
    {
        $useAttendance = $this->useAttendance();
        $result = ['period' => $this->key($cycle), 'label' => $cycle->describe(), 'attendance' => $useAttendance,
            'format' => null, 'entries' => [], 'problems' => [], 'warnings' => [],
            'totals' => ['payslips' => 0, 'gross' => 0.0, 'earnings' => 0.0, 'statutory' => 0.0, 'tds' => 0.0, 'lop' => 0.0,
                'other_deductions' => 0.0, 'deductions' => 0.0, 'net' => 0.0]];

        [$columns, $format, $extraColumns, $ignored] = $this->columns($rows[0] ?? []);
        $result['extra_columns'] = array_values(array_map(fn ($c) => ($c['kind'] === 'earning' ? '+ ' : '− ') . $c['label'], $extraColumns));
        $result['ignored_columns'] = $ignored;

        if (!$columns) {
            $result['error'] = 'The first row must have the headings: Employee Code, and either the simple columns (Annual CTC, TDS...) '
                . 'or the detailed ones (Basic, HRA, PF, Net Pay...). Download a sheet or a sample on this page.';

            return $result;
        }

        $result['format'] = $format;
        $employees = $this->employees($cycle);
        $issued = $this->issued($cycle);
        $seen = [];
        $lines = [];

        $problem = function (int $line, ?string $code, string $message) use (&$result) {
            $result['problems'][] = ['line' => $line, 'code' => $code, 'message' => $message];
        };

        // first pass: who, and the sheet's figures
        foreach (array_slice($rows, 1, null, true) as $index => $row) {
            $line = $index + 1;
            $cell = fn (string $key) => isset($columns[$key]) ? ($row[$columns[$key]] ?? null) : null;
            $code = trim((string) $cell('code'));

            if ($code === '' && collect($row)->filter(fn ($v) => trim((string) $v) !== '')->isEmpty()) {
                continue;
            }

            if ($code === '') {
                $problem($line, null, 'no employee code');
                continue;
            }

            $e = $employees[mb_strtolower($code)] ?? null;

            if (!$e) {
                $problem($line, $code, 'no employee with code ' . $code . ' for this period');
                continue;
            }

            if (isset($seen[(int) $e->id])) {
                $problem($line, $code, 'already on line ' . $seen[(int) $e->id]);
                continue;
            }

            if (isset($issued[mb_strtolower($code)])) {
                $problem($line, $code, $e->name . ' already has a payslip for ' . $cycle->label . ' (revoke it first to issue again)');
                continue;
            }

            if ($reason = $this->settledReason((int) $e->id, $cycle)) {
                $problem($line, $code, $e->name . ' was ' . $reason);
                continue;
            }

            $values = [];
            $bad = null;

            foreach (array_keys($columns) as $field) {
                if (in_array($field, ['code', 'remarks'], true)) {
                    continue;
                }

                $amount = $this->amount($cell($field));

                if ($amount === false) {
                    $bad = '"' . mb_substr(trim((string) $cell($field)), 0, 20) . '" in ' . $this->headingOf($field) . ' is not a number';
                    break;
                }

                if ($amount !== null && $amount < 0) {
                    $bad = $this->headingOf($field) . ' cannot be negative';
                    break;
                }

                $values[$field] = $amount;
            }

            // the extra earning / deduction columns, each its own line on the payslip
            $sheetLines = [];
            $remarks = trim((string) $cell('remarks')) ?: null;

            foreach ($extraColumns as $colIndex => $col) {
                if ($bad) {
                    break;
                }

                $amount = $this->amount($row[$colIndex] ?? null);

                if ($amount === false) {
                    $bad = '"' . mb_substr(trim((string) ($row[$colIndex] ?? '')), 0, 20) . '" in ' . $col['heading'] . ' is not a number';
                } elseif ($amount !== null && $amount < 0) {
                    $bad = $col['heading'] . ' cannot be negative';
                } elseif ($amount > 0) {
                    $sheetLines[] = ['kind' => $col['kind'], 'label' => $col['label'], 'amount' => $amount, 'type_id' => $col['type_id'], 'note' => $remarks];
                }
            }

            if ($bad) {
                $problem($line, $code, $bad);
                continue;
            }

            if (($values['other_earnings'] ?? 0) > 0) {
                $sheetLines[] = ['kind' => 'earning', 'label' => 'Other earnings', 'amount' => $values['other_earnings'], 'type_id' => null, 'note' => $remarks];
            }

            if (($values['other_deductions'] ?? 0) > 0) {
                $sheetLines[] = ['kind' => 'deduction', 'label' => 'Other deductions', 'amount' => $values['other_deductions'], 'type_id' => null, 'note' => $remarks];
            }

            // counted once it reads cleanly, so a corrected repeat of a bad line still goes through
            $seen[(int) $e->id] = $line;
            $lines[] = ['line' => $line, 'employee' => $e, 'values' => $values, 'sheet_lines' => $sheetLines, 'remarks' => $remarks];
        }

        // the Earnings & Deductions tab: one line per item, added to that employee's payslip
        $result['items_read'] = 0;

        if ($itemRows) {
            $itemColumns = $this->itemColumns($itemRows[0] ?? []);

            if (!$itemColumns) {
                $result['error'] = 'The Earnings & Deductions tab needs the headings Employee Code, Type, Item and Amount '
                    . '(download a sheet or a sample on this page).';

                return $result;
            }

            $result['items_read'] = $this->readItems($itemRows, $itemColumns, $lines, $employees, $result['problems']);
        }

        // second pass: the payslips
        $simple = $format === 'simple';
        $calc = [];

        if ($simple && $lines) {
            $overrides = [];
            foreach ($lines as $l) {
                $v = $l['values'];
                $overrides[(int) $l['employee']->id] = [
                    'ctc' => $v['ctc'] ?? (isset($v['monthly']) ? $v['monthly'] * 12 : null),
                    'tds' => $v['tds'] ?? null,
                ];
            }
            $calc = $this->calculate(collect($lines)->mapWithKeys(fn ($l) => [(int) $l['employee']->id => $l['employee']])->all(),
                $cycle, $useAttendance, $overrides);
        }

        $gate = new AttendanceGate();
        $components = new PayComponents();

        foreach ($lines as $l) {
            $e = $l['employee'];
            $v = $l['values'];
            $code = (string) $e->employee_card_id;
            $warnings = [];

            // Pay elements already entered for the period (overtime, advance instalments...) go on the
            // payslip in either format, as on Payroll Run: left aside they would be paid or recovered
            // a month late. The sheet's Other Earnings / Other Deductions come on top.
            $onRecord = $components->forPeriod((int) $e->id, $cycle->start);
            $pending = array_merge(
                array_map(fn ($x) => ['kind' => 'earning', 'label' => $x->label, 'amount' => (float) $x->amount], $onRecord['earnings']),
                array_map(fn ($x) => ['kind' => 'deduction', 'label' => $x->label, 'amount' => (float) $x->amount], $onRecord['deductions'])
            );
            $pendE = (float) $onRecord['earning_total'];
            $pendD = (float) $onRecord['deduction_total'];

            // the sheet's own earnings and deductions; one already entered for the period under the
            // same name would be paid (or recovered) twice
            $extra = $l['sheet_lines'];
            $twice = collect($extra)->first(fn ($x) => collect($pending)->contains(fn ($p) => $p['kind'] === $x['kind']
                && mb_strtolower(trim($p['label'])) === mb_strtolower(trim($x['label']))));

            if ($twice) {
                $already = collect($pending)->first(fn ($p) => mb_strtolower(trim($p['label'])) === mb_strtolower(trim($twice['label'])));
                $problem($l['line'], $code, sprintf('%s already has %s of %s entered for the period (Pay Elements): leave the %s column blank, or remove the entered line first',
                    $e->name, $twice['label'], $this->money((float) $already['amount']), $twice['label']));
                continue;
            }

            $otherE = array_sum(array_map(fn ($x) => $x['kind'] === 'earning' ? $x['amount'] : 0, $extra));
            $otherD = array_sum(array_map(fn ($x) => $x['kind'] === 'deduction' ? $x['amount'] : 0, $extra));

            // Attendance on, either format: as Payroll Run, nobody is paid on a month with no attendance
            // at all in the system (almost always missing data, not a month away); "Attendance exempt" passes.
            if ($useAttendance && ($blocked = $gate->blocker((int) $e->id, $cycle, (int) $e->company_id ?: null, $e->name))) {
                [$reason, $deviceLag] = $blocked;

                if (!$deviceLag) {
                    $problem($l['line'], $code, $reason);
                    continue;
                }

                $warnings[] = 'the biometric feed has not reached the end of the period yet; paid from the attendance in the system';
            }

            if ($simple) {
                $c = $calc[(int) $e->id];

                if ($c['ctc'] <= 0) {
                    $problem($l['line'], $code, $e->name . ' has no salary on record: give the Annual CTC in the sheet');
                    continue;
                }

                $pay = $c['pay'];
            } else {
                // the sheet is the payslip: it must add up
                $earn = ['basic', 'hra', 'ca', 'lta', 'cea', 'special'];
                $gross = array_sum(array_map(fn ($k) => (float) ($v[$k] ?? 0), $earn));

                if ($gross <= 0) {
                    $problem($l['line'], $code, 'no earnings: give Basic and the allowances');
                    continue;
                }

                if (isset($v['gross']) && abs($v['gross'] - $gross) > 1) {
                    $problem($l['line'], $code, sprintf('the earnings add up to %s but Gross says %s', $this->money($gross), $this->money($v['gross'])));
                    continue;
                }

                $lop = (float) ($v['lop'] ?? 0);

                if (!$useAttendance && $lop > 0) {
                    $problem($l['line'], $code, 'attendance deductions are off (Payroll Settings), but this line has an Attendance Deduction of ' . $this->money($lop));
                    continue;
                }

                $working = (int) round($v['working_days'] ?? $cycle->days());
                $paid = (float) ($v['days_paid'] ?? $working);

                if ($working < 1 || $paid > $working) {
                    $problem($l['line'], $code, 'Days Paid must be between 0 and Working Days');
                    continue;
                }

                $pfE = (float) ($v['pf'] ?? 0); $esiE = (float) ($v['esi'] ?? 0); $pt = (float) ($v['pt'] ?? 0); $tds = (float) ($v['tds'] ?? 0);
                $pfR = (float) ($v['employer_pf'] ?? 0); $esiR = (float) ($v['employer_esi'] ?? 0);
                $net =$gross + $otherE + $pendE - ($pfE + $esiE + $pt + $tds + $lop + $otherD + $pendD + $pfR + $esiR);

                if (isset($v['net']) && abs($v['net'] - $net) > 1) {
                    $problem($l['line'], $code, sprintf('Net Pay should be %s (earnings less deductions%s) but the sheet says %s', $this->money($net),
                        $pendE || $pendD ? sprintf(', with the pay elements entered for the period: +%s −%s', $this->money($pendE), $this->money($pendD)) : '',
                        $this->money($v['net'])));
                    continue;
                }

                if ($net < 0) {
                    $problem($l['line'], $code, 'the deductions are more than the earnings');
                    continue;
                }

                $beforeTax = $gross - $pfE - $esiE - $pt - $pfR - $esiR;
                $pay = [
                    'gross' => $gross, 'basic_pct' => $gross > 0 ? round((float) ($v['basic'] ?? 0) / $gross * 100, 2) : 0,
                    'basic' => (float) ($v['basic'] ?? 0), 'hra' => (float) ($v['hra'] ?? 0), 'ca' => (float) ($v['ca'] ?? 0),
                    'lta' => (float) ($v['lta'] ?? 0), 'cea' => (float) ($v['cea'] ?? 0), 'other' => (float) ($v['special'] ?? 0),
                    'pf_employee' => $pfE, 'esi_employee' => $esiE, 'pf_employer' => $pfR, 'esi_employer' => $esiR,
                    'pt' => $pt, 'tds' => $tds, 'lop_amount' => $lop, 'lop_days' => max(0.0, $working - $paid),
                    'working_days' => $working, 'days_present' => $paid,
                    'per_day' => $working > 0 ? $beforeTax / $working : 0, 'prorated' => (bool) ($this->calculator->settings()->prorate_statutory ?? 1),
                    'pf_employer_pct' => $this->calculator->settings()->pf_employer_pct ?? $this->calculator->settings()->pf_pct,
                    'final_net' => $net - $otherE - $pendE + $otherD + $pendD,
                ];
            }

            $earnLines = array_sum(array_map(fn ($x) => $x['kind'] === 'earning' ? $x['amount'] : 0, array_merge($pending, $extra)));
            $dedLines = array_sum(array_map(fn ($x) => $x['kind'] === 'deduction' ? $x['amount'] : 0, array_merge($pending, $extra)));
            $statutory = $pay['pf_employee'] + $pay['esi_employee'] + $pay['pf_employer'] + $pay['esi_employer'] + $pay['pt'];
            $net = $pay['final_net'] + $earnLines - $dedLines;

            if ($net < 0) {
                $problem($l['line'], $code, 'the deductions (' . $this->money($dedLines) . ' in other deductions) are more than the pay');
                continue;
            }

            $entry = [
                'line' => $l['line'], 'user_id' => (int) $e->id, 'code' => $code, 'name' => $e->name, 'designation' => $e->designation,
                'pay' => $pay, 'lines' => $extra, 'pending' => $pending, 'attach_pending' => true, 'warnings' => $warnings,
                'show' => [
                    'working' => (int) $pay['working_days'], 'paid' => round((float) $pay['days_present'], 2),
                    'gross' => round($pay['gross'], 2), 'other_earnings' => round($earnLines, 2),
                    'statutory' => round($statutory, 2), 'tds' => round($pay['tds'], 2), 'lop' => round($pay['lop_amount'], 2),
                    'other_deductions' => round($dedLines, 2), 'net' => round($net, 2),
                ],
            ];
            $result['entries'][] = $entry;

            $t = &$result['totals'];
            $t['payslips']++;
            $t['gross'] += $entry['show']['gross'];
            $t['earnings'] += $entry['show']['other_earnings'];
            $t['statutory'] += $entry['show']['statutory'];
            $t['tds'] += $entry['show']['tds'];
            $t['lop'] += $entry['show']['lop'];
            $t['other_deductions'] += $entry['show']['other_deductions'];
            $t['deductions'] += $entry['show']['statutory'] + $entry['show']['tds'] + $entry['show']['lop'] + $entry['show']['other_deductions'];
            $t['net'] += $entry['show']['net'];
            unset($t);

            foreach ($warnings as $w) {
                $result['warnings'][] = ['line' => $l['line'], 'code' => $code, 'message' => $e->name . ': ' . $w];
            }
        }

        foreach ($result['totals'] as $k => $v) {
            $result['totals'][$k] = is_float($v) ? round($v, 2) : $v;
        }

        return $result;
    }

    // ------------------------------------------------------------------ apply

    /**
     * Generate the payslips the preview showed, checking again that nobody has one by now.
     *
     * @return array{created: int, skipped: array<int, string>, net: float, payslip_ids: int[]}
     */
    public function apply(array $preview, PayPeriod $cycle, int $by, ?string $file = null): array
    {
        $writer = app(PayslipWriter::class);
        $employees = $this->employees($cycle)->keyBy('id');
        $issued = $this->issued($cycle);
        $created = 0;
        $net = 0.0;
        $ids = [];
        $skipped = [];

        foreach ($preview['entries'] ?? [] as $entry) {
            $e = $employees[$entry['user_id']] ?? null;

            if (!$e) {
                $skipped[] = $entry['name'] . ': no longer on record';
                continue;
            }

            if (isset($issued[mb_strtolower($entry['code'])])) {
                $skipped[] = $entry['name'] . ': a payslip was issued meanwhile';
                continue;
            }

            if ($reason = $this->settledReason((int) $e->id, $cycle)) {
                $skipped[] = $entry['name'] . ': ' . $reason;
                continue;
            }

            $values = $writer->rowFor($e, $cycle, $entry['pay'], $by);
            $ids[] = $writer->store($values, $cycle, (int) $e->id, (string) $e->employee_card_id, $entry['lines'], (bool) $entry['attach_pending'], $by);
            $issued[mb_strtolower($entry['code'])] = true;
            $created++;
            $net += (float) $entry['show']['net'];
        }

        if ($created > 0) {
            DB::table('payroll_imports')->insert([
                'company_id' => DB::table('companies')->value('id'),
                'period' => $this->key($cycle),
                'period_label' => $cycle->describe(),
                'file_name' => $file ? mb_substr($file, 0, 191) : null,
                'format' => $preview['format'] ?? null,
                'attendance' => !empty($preview['attendance']),
                'payslips' => $created,
                'total_net' => round($net, 2),
                'skipped' => count($skipped),
                'payslip_ids' => json_encode($ids),
                'created_by' => $by,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return ['created' => $created, 'skipped' => $skipped, 'net' => round($net, 2), 'payslip_ids' => $ids];
    }

    public function history(int $limit = 8)
    {
        return DB::table('payroll_imports as i')->leftJoin('users as u', 'u.id', '=', 'i.created_by')
            ->orderByDesc('i.id')->limit($limit)
            ->get(['i.*', 'u.name as by_name']);
    }

    // ---------------------------------------------------------------- reading

    /** The Earnings & Deductions tab's headings. */
    public const ITEM_HEADINGS = ['Employee Code', 'Name', 'Type', 'Item', 'Amount', 'Remarks'];

    /** Where the Earnings & Deductions tab's columns are, or null when a needed one is missing. */
    protected function itemColumns(array $headings): ?array
    {
        $names = [
            'code' => self::FIELDS['code'],
            'type' => ['type', 'kind', 'earningdeduction', 'earningordeduction', 'earningsdeductions'],
            'item' => ['item', 'itemname', 'description', 'component', 'element', 'payelement', 'paytype', 'head', 'particulars'],
            'amount' => ['amount', 'value', 'rs', 'inr', 'amountrs'],
            'remarks' => ['remarks', 'remark', 'note', 'notes'],
        ];
        $found = [];

        foreach ($headings as $index => $heading) {
            $key = preg_replace('/[^a-z]/', '', mb_strtolower((string) $heading));

            foreach ($names as $field => $options) {
                if (!isset($found[$field]) && in_array($key, $options, true)) {
                    $found[$field] = $index;
                }
            }
        }

        foreach (['code', 'type', 'item', 'amount'] as $needed) {
            if (!isset($found[$needed])) {
                return null;
            }
        }

        return $found;
    }

    /** Which tab of an uploaded workbook is the Earnings & Deductions one (not the first), or null. */
    public function itemsSheet(array $sheets): ?array
    {
        foreach (array_slice($sheets, 1) as $sheet) {
            if ($sheet && $this->itemColumns($sheet[0] ?? [])) {
                return $sheet;
            }
        }

        return null;
    }

    /** "Earning" / "Deduction" (or E, D, +, -, Addition, Recovery...) as earning | deduction | null. */
    protected function itemKind(string $type): ?string
    {
        $t = preg_replace('/[^a-z+\-−]/', '', mb_strtolower(trim($type)));

        return match (true) {
            in_array($t, ['earning', 'earnings', 'e', '+', 'addition', 'additions', 'allowance', 'credit', 'cr'], true) => 'earning',
            in_array($t, ['deduction', 'deductions', 'd', '-', '−', 'recovery', 'debit', 'dr'], true) => 'deduction',
            default => null,
        };
    }

    /**
     * Read the Earnings & Deductions tab into the Payroll tab's lines. An employee with any item line
     * that cannot be read is left out altogether, so no payslip goes out with an item missing.
     *
     * @return int  item lines read
     */
    protected function readItems(array $itemRows, array $cols, array &$lines, $employees, array &$problems): int
    {
        $byCode = [];
        foreach ($lines as $k => $l) {
            $byCode[mb_strtolower(trim((string) $l['employee']->employee_card_id))] = $k;
        }

        $elements = $this->payElements();
        $read = 0;
        $broken = [];
        $tab = 'Earnings & Deductions tab, ';

        foreach (array_slice($itemRows, 1, null, true) as $index => $row) {
            $line = $index + 1;
            $cell = fn (string $f) => isset($cols[$f]) ? ($row[$cols[$f]] ?? null) : null;
            $code = trim((string) $cell('code'));
            $type = trim((string) $cell('type'));
            $name = trim((string) $cell('item'));
            $amountCell = $cell('amount');

            if ($code === '' && $type === '' && $name === '' && trim((string) $amountCell) === '') {
                continue;   // an empty line
            }

            $fail = function (string $message) use (&$problems, $tab, $line, $code, &$broken, $byCode) {
                $problems[] = ['line' => $tab . 'line ' . $line, 'code' => $code ?: null, 'message' => $message];
                if ($code !== '' && isset($byCode[mb_strtolower($code)])) {
                    $broken[mb_strtolower($code)] = true;
                }
            };

            if ($code === '') {
                $fail('no employee code');
                continue;
            }

            $k = $byCode[mb_strtolower($code)] ?? null;

            if ($k === null) {
                $known = $employees[mb_strtolower($code)] ?? null;
                $fail($known ? $known->name . ' is not on the Payroll tab, or their line there is left out, so this item cannot be paid'
                    : 'no employee with code ' . $code . ' for this period');
                continue;
            }

            $kind = $this->itemKind($type);

            if (!$kind) {
                $fail('Type "' . mb_substr($type, 0, 20) . '" must be Earning or Deduction');
                continue;
            }

            if ($name === '') {
                $fail('no Item name (what the ' . $kind . ' is, e.g. Bonus, Canteen)');
                continue;
            }

            $amount = $this->amount($amountCell);

            if ($amount === false || $amount === null || $amount <= 0) {
                $fail('Amount "' . mb_substr(trim((string) $amountCell), 0, 20) . '" must be a number more than 0');
                continue;
            }

            $element = $elements[preg_replace('/[^a-z0-9]/', '', mb_strtolower($name))] ?? null;

            if ($element && $element['kind'] !== $kind) {
                $fail($element['name'] . ' is a ' . $element['kind'] . ' in Pay Elements, but this line says ' . $kind);
                continue;
            }

            $label = $element['name'] ?? $name;
            $twice = collect($lines[$k]['sheet_lines'])->first(fn ($x) => $x['kind'] === $kind && mb_strtolower($x['label']) === mb_strtolower($label));

            if ($twice) {
                $fail($label . ' is given twice for ' . $lines[$k]['employee']->name . ' (a column and this tab, or two lines): give it once, with the total');
                continue;
            }

            $lines[$k]['sheet_lines'][] = ['kind' => $kind, 'label' => $label, 'amount' => $amount,
                'type_id' => $element['id'] ?? null, 'note' => trim((string) $cell('remarks')) ?: $lines[$k]['remarks']];
            $read++;
        }

        // nobody is paid with an item line missing
        foreach ($broken as $code => $_) {
            $k = $byCode[$code];
            $problems[] = ['line' => $lines[$k]['line'], 'code' => (string) $lines[$k]['employee']->employee_card_id,
                'message' => $lines[$k]['employee']->name . "'s payslip is left out until their Earnings & Deductions lines above are fixed"];
            unset($lines[$k]);
        }

        $lines = array_values($lines);

        return $read;
    }

    /** Headings that are only there to read. */
    protected const READ_ONLY = ['name', 'employeename', 'designation', 'department', 'division', 'status', 'note', 'notes', 'sno', 'slno', 'srno', 'no'];

    /**
     * The company's pay elements (Payroll -> Pay Elements), by name: a column named after one
     * is that kind of line.
     *
     * @return array<string, array{id: int, name: string, kind: string}>
     */
    public function payElements(): array
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('pay_element_types')) {
            return [];
        }

        return DB::table('pay_element_types')->where('is_active', 1)->orderBy('sort_order')->get(['id', 'name', 'kind'])
            ->mapWithKeys(fn ($t) => [preg_replace('/[^a-z0-9]/', '', mb_strtolower($t->name)) => ['id' => (int) $t->id, 'name' => $t->name, 'kind' => $t->kind]])
            ->all();
    }

    /**
     * Where each heading is, which format the sheet is, the extra earning / deduction columns
     * (a pay element's name, or "Earning: ..." / "Deduction: ..."), and the headings not understood.
     *
     * @return array{0: ?array<string, int>, 1: ?string, 2: array<int, array{kind: string, label: string, type_id: ?int, heading: string}>, 3: string[]}
     */
    protected function columns(array $headings): array
    {
        $found = [];
        $extra = [];
        $ignored = [];
        $elements = $this->payElements();

        foreach ($headings as $index => $heading) {
            $text = trim((string) $heading);

            if ($text === '') {
                continue;
            }

            $key = preg_replace('/[^a-z]/', '', mb_strtolower($text));
            $matched = false;

            foreach (self::FIELDS as $field => $names) {
                if (!isset($found[$field]) && in_array($key, $names, true)) {
                    $found[$field] = $index;
                    $matched = true;
                    break;
                }
            }

            if ($matched || in_array($key, self::READ_ONLY, true)) {
                continue;
            }

            // "Earning: Night Allowance" / "+ Night Allowance", "Deduction: Canteen" / "- Canteen"
            $kind = null;
            $label = null;

            if (preg_match('/^\s*(?:earnings?|additions?)\s*[:\-–]\s*(.+)$/iu', $text, $m) || preg_match('/^\s*\+\s*(.+)$/u', $text, $m)) {
                [$kind, $label] = ['earning', trim($m[1])];
            } elseif (preg_match('/^\s*(?:deductions?|recovery)\s*[:\-–]\s*(.+)$/iu', $text, $m) || preg_match('/^\s*[\-–−]\s*(.+)$/u', $text, $m)) {
                [$kind, $label] = ['deduction', trim($m[1])];
            }

            if ($kind && $label !== '') {
                $type = $elements[preg_replace('/[^a-z0-9]/', '', mb_strtolower($label))] ?? null;
                $extra[$index] = ['kind' => $kind, 'label' => $type['name'] ?? $label,
                    'type_id' => $type && $type['kind'] === $kind ? $type['id'] : null, 'heading' => $text];
                continue;
            }

            // a pay element's own name: "Overtime", "Bonus", "Loan Recovery"...
            $type = $elements[preg_replace('/[^a-z0-9]/', '', mb_strtolower($text))] ?? null;

            if ($type) {
                $extra[$index] = ['kind' => $type['kind'] === 'deduction' ? 'deduction' : 'earning', 'label' => $type['name'], 'type_id' => $type['id'], 'heading' => $text];
                continue;
            }

            $ignored[] = $text;
        }

        if (!isset($found['code'])) {
            return [null, null, [], []];
        }

        $detailed = count(array_intersect(self::DETAILED_MARKERS, array_keys($found))) >= 2 && isset($found['basic']);

        // "Gross" in a simple sheet is the monthly gross
        if (!$detailed && isset($found['gross']) && !isset($found['monthly'])) {
            $found['monthly'] = $found['gross'];
            unset($found['gross']);
        }

        return [$found, $detailed ? 'detailed' : 'simple', $extra, $ignored];
    }

    protected function headingOf(string $field): string
    {
        return [
            'ctc' => 'Annual CTC', 'monthly' => 'Monthly Gross', 'tds' => 'TDS', 'other_earnings' => 'Other Earnings',
            'other_deductions' => 'Other Deductions', 'working_days' => 'Working Days', 'days_paid' => 'Days Paid',
            'basic' => 'Basic', 'hra' => 'HRA', 'ca' => 'Conveyance', 'lta' => 'LTA', 'cea' => 'CEA', 'special' => 'Special Allowance',
            'gross' => 'Gross', 'pf' => 'PF', 'esi' => 'ESI', 'pt' => 'PT', 'lop' => 'Attendance Deduction',
            'employer_pf' => 'Employer PF', 'employer_esi' => 'Employer ESI', 'net' => 'Net Pay',
        ][$field] ?? $field;
    }

    /** A money / number cell: null when blank, false when not a number. */
    public function amount($value): float|false|null
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $text = str_replace([',', '₹', 'Rs.', 'Rs', 'INR', ' '], '', trim((string) $value));

        return is_numeric($text) ? (float) $text : false;
    }

    public function money(float $v): string
    {
        return '₹' . number_format($v, 2);
    }

}
