<?php

namespace App\Services\Payroll;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What an employee owes in income tax for the year, and what to take this month.
 *
 * The method is the one the rules prescribe: project what the employee will
 * earn over the whole financial year, work out the tax on it, subtract what has
 * already been deducted, and spread what is left over the months that remain.
 * A rise, a bonus or a late declaration therefore corrects itself over the rest
 * of the year rather than landing in one month.
 *
 * Every rate, allowance and threshold is read from `tax_slabs` and
 * `tax_settings`, which are editable on screen. **Nothing here is an authority
 * on tax law** — the figures seeded with the migration are a starting point and
 * are flagged for review until somebody confirms them against the Finance Act.
 *
 * Everything is returned with its working shown, because a figure an employee
 * cannot follow is a figure they will dispute.
 */
class TaxCalculator
{

    /**
     * The financial year a date falls in, April to March: "2026-27".
     */
    public static function financialYear(?Carbon $on = null): string
    {
        $on = $on ?: now();
        $start = $on->month >= 4 ? $on->year : $on->year - 1;

        return $start . '-' . substr((string) ($start + 1), -2);
    }

    /** The first day of a financial year. */
    public static function yearStart(string $financialYear): Carbon
    {
        return Carbon::create((int) substr($financialYear, 0, 4), 4, 1)->startOfDay();
    }

    /**
     * One employee's tax for the year, and the slice due this month.
     *
     * @param  int    $userId
     * @param  Carbon $periodStart  the pay period being worked out
     * @return array<string, mixed>
     */
    public function forEmployee(int $userId, Carbon $periodStart): array
    {
        $year = static::financialYear($periodStart);
        $declaration = $this->declaration($userId, $year);
        $regime = $declaration->regime ?? 'new';
        $settings = $this->settings($year, $regime);

        if (!$settings) {
            return $this->nothing($year, $regime, 'No tax rates are set up for ' . $year . '.');
        }

        $employee = DB::table('users')->where('id', $userId)->first(['id', 'name', 'employee_ctc', 'monthly_tds']);

        if (!$employee || (float) $employee->employee_ctc <= 0) {
            return $this->nothing($year, $regime, 'No salary on record, so tax cannot be worked out.');
        }

        $monthlyGross = (float) $employee->employee_ctc / 12;

        // What the year looks like: what has already been paid on payslips, plus
        // what the remaining months are expected to pay.
        $paid = $this->paidSoFar($userId, $year, $periodStart);
        $monthsLeft = $this->monthsRemaining($year, $periodStart, $userId);
        $monthsDone = 12 - $monthsLeft;

        $projected = $paid['gross'] + ($monthlyGross * $monthsLeft)
            + (float) $declaration->previous_employer_income;

        $workings = [];
        $workings[] = ['Salary already paid this year', $paid['gross']];

        if ((float) $declaration->previous_employer_income > 0) {
            $workings[] = ['Pay from a previous employer', (float) $declaration->previous_employer_income];
        }

        $workings[] = [sprintf('Expected for the %d month(s) left', $monthsLeft), $monthlyGross * $monthsLeft];
        $workings[] = ['Gross for the year', $projected, 'total'];

        $taxable = $projected;
        $reliefs = [];

        // House rent, where the regime allows it.
        if ($settings->allows_hra_exemption) {
            $exemption = $this->houseRentExemption($userId, $declaration, $monthlyGross, $projected);

            if ($exemption['amount'] > 0) {
                $taxable -= $exemption['amount'];
                $reliefs[] = ['House rent exemption', $exemption['amount']];
                $workings[] = ['House rent exemption', -$exemption['amount'], 'relief', $exemption['note']];
            }
        }

        $standard = (float) $settings->standard_deduction;

        if ($standard > 0) {
            $taxable -= $standard;
            $reliefs[] = ['Standard deduction', $standard];
            $workings[] = ['Standard deduction', -$standard, 'relief'];
        }

        // Professional tax actually paid is itself deductible under the old regime.
        if ($settings->allows_professional_tax) {
            $pt = $paid['pt'] + ($this->monthlyProfessionalTax($monthlyGross) * $monthsLeft);

            if ($pt > 0) {
                $taxable -= $pt;
                $reliefs[] = ['Professional tax', $pt];
                $workings[] = ['Professional tax paid', -$pt, 'relief'];
            }
        }

        if ($settings->allows_chapter_via) {
            foreach ($this->chapterVia($declaration) as $label => $amount) {
                if ($amount > 0) {
                    $taxable -= $amount;
                    $reliefs[] = [$label, $amount];
                    $workings[] = [$label, -$amount, 'relief'];
                }
            }
        }

        $taxable = max(0.0, round($taxable, 0));
        $workings[] = ['Taxable income', $taxable, 'total'];

        $bands = $this->applySlabs($taxable, $year, $regime);
        $tax = $bands['tax'];
        $workings = array_merge($workings, $bands['workings']);

        // The small-income rebate, which removes the tax rather than reducing it.
        $rebate = 0.0;

        if ($settings->rebate_upto > 0 && $taxable <= (float) $settings->rebate_upto) {
            $rebate = min($tax, (float) $settings->rebate_max);
            $tax -= $rebate;
            $workings[] = ['Rebate under section 87A', -$rebate, 'relief'];
        }

        $surcharge = $this->surcharge($tax, $taxable, $settings);

        if ($surcharge > 0) {
            $tax += $surcharge;
            $workings[] = ['Surcharge', $surcharge];
        }

        $cess = round($tax * (float) $settings->cess_pct / 100, 2);

        if ($cess > 0) {
            $workings[] = [sprintf('Health and education cess at %s%%', rtrim(rtrim(number_format((float) $settings->cess_pct, 2), '0'), '.')), $cess];
        }

        $annualTax = round($tax + $cess, 0);
        $workings[] = ['Tax for the year', $annualTax, 'total'];

        $alreadyDeducted = $paid['tds'] + (float) $declaration->previous_employer_tax;
        $outstanding = max(0.0, $annualTax - $alreadyDeducted);
        $monthly = $monthsLeft > 0 ? round($outstanding / $monthsLeft, 0) : 0.0;

        return [
            'ok' => true,
            'financial_year' => $year,
            'regime' => $regime,
            'needs_review' => (bool) $settings->needs_review,
            'months_done' => $monthsDone,
            'months_left' => $monthsLeft,
            'monthly_gross' => round($monthlyGross, 2),
            'projected_gross' => round($projected, 2),
            'reliefs' => $reliefs,
            'taxable' => $taxable,
            'annual_tax' => $annualTax,
            'rebate' => $rebate,
            'surcharge' => $surcharge,
            'cess' => $cess,
            'already_deducted' => round($alreadyDeducted, 2),
            'outstanding' => round($outstanding, 2),
            'monthly_tds' => $monthly,
            'paid_so_far' => $paid,
            'workings' => $workings,
            'message' => null,
        ];
    }

    /**
     * Tax on an amount, band by band.
     *
     * @return array{tax: float, workings: array}
     */
    public function applySlabs(float $taxable, string $year, string $regime): array
    {
        $slabs = DB::table('tax_slabs')
            ->where('financial_year', $year)->where('regime', $regime)
            ->orderBy('sort_order')->get();

        $tax = 0.0;
        $floor = 0.0;
        $workings = [];

        foreach ($slabs as $slab) {
            $ceiling = $slab->upto !== null ? (float) $slab->upto : INF;
            $inBand = max(0.0, min($taxable, $ceiling) - $floor);

            if ($inBand > 0 && (float) $slab->rate > 0) {
                $amount = round($inBand * (float) $slab->rate / 100, 2);
                $tax += $amount;
                $workings[] = [
                    sprintf('%s to %s at %s%%',
                        number_format($floor),
                        $ceiling === INF ? 'above' : number_format($ceiling),
                        rtrim(rtrim(number_format((float) $slab->rate, 2), '0'), '.')),
                    $amount,
                    'band',
                ];
            }

            $floor = $ceiling;

            if ($taxable <= $ceiling) {
                break;
            }
        }

        return ['tax' => round($tax, 2), 'workings' => $workings];
    }

    /**
     * The house rent exemption: the least of the rent paid over a tenth of
     * salary, the allowance itself, and half or two fifths of salary.
     *
     * @return array{amount: float, note: string}
     */
    protected function houseRentExemption(int $userId, object $declaration, float $monthlyGross, float $annualGross): array
    {
        $rent = (float) $declaration->rent_paid_annual;

        if ($rent <= 0) {
            return ['amount' => 0.0, 'note' => ''];
        }

        $calculator = new SalaryCalculator();
        $pay = $calculator->forEmployee($monthlyGross * 12, null, 30, 30);
        $annualBasic = $pay['basic'] * 12;
        $annualHra = $pay['hra'] * 12;

        $share = $declaration->metro_city ? 0.50 : 0.40;

        $candidates = [
            'the allowance itself' => $annualHra,
            'rent over a tenth of basic' => max(0.0, $rent - ($annualBasic * 0.10)),
            ($declaration->metro_city ? 'half of basic' : 'two fifths of basic') => $annualBasic * $share,
        ];

        $amount = min($candidates);
        $reason = array_search($amount, $candidates, true);

        return [
            'amount' => round(max(0.0, $amount), 2),
            'note' => 'the least of three: ' . $reason,
        ];
    }

    /**
     * What the declaration allows under Chapter VI-A, each capped.
     *
     * @return array<string, float>
     */
    protected function chapterVia(object $declaration): array
    {
        return [
            'Section 80C' => min((float) $declaration->section_80c, 150000),
            'Section 80D (health insurance)' => min((float) $declaration->section_80d, 100000),
            'Section 80CCD(1B) (pension)' => min((float) $declaration->section_80ccd_1b, 50000),
            'Interest on a home loan' => min((float) $declaration->home_loan_interest, 200000),
            'Other deductions declared' => (float) $declaration->other_deductions,
        ];
    }

    /** Surcharge on a large tax bill, from the configured bands. */
    protected function surcharge(float $tax, float $taxable, object $settings): float
    {
        $bands = json_decode((string) $settings->surcharge_slabs, true);

        if (!is_array($bands)) {
            return 0.0;
        }

        foreach ($bands as $band) {
            $upto = $band['upto'] ?? null;

            if ($upto === null || $taxable <= (float) $upto) {
                return round($tax * (float) ($band['rate'] ?? 0) / 100, 2);
            }
        }

        return 0.0;
    }

    /**
     * What this employee has actually been paid and taxed this year, from the
     * payslips themselves.
     *
     * @return array{gross: float, tds: float, pt: float, payslips: int}
     */
    public function paidSoFar(int $userId, string $year, ?Carbon $before = null): array
    {
        $query = DB::table('payslips')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->whereIn('approval_status', ['approved', 'paid'])
            ->whereDate('period_start', '>=', static::yearStart($year)->toDateString())
            ->whereDate('period_start', '<=', static::yearStart($year)->copy()->addYear()->subDay()->toDateString());

        if ($before) {
            $query->whereDate('period_start', '<', $before->toDateString());
        }

        $row = $query->selectRaw('COALESCE(SUM(gross), 0) g, COALESCE(SUM(tds), 0) t, COALESCE(SUM(pt), 0) p, COUNT(*) n')->first();

        return [
            'gross' => (float) $row->g,
            'tds' => (float) $row->t,
            'pt' => (float) $row->p,
            'payslips' => (int) $row->n,
        ];
    }

    /**
     * Pay periods left in the year, this one included.
     *
     * Somebody who is leaving has only the months up to their last working day,
     * so projecting a full year would tax them on pay they will never receive.
     */
    protected function monthsRemaining(string $year, Carbon $periodStart, ?int $userId = null): int
    {
        $start = static::yearStart($year);
        $elapsed = $start->diffInMonths($periodStart);
        $left = max(1, 12 - $elapsed);

        if ($userId === null) {
            return $left;
        }

        $leaving = DB::table('settlements')
            ->where('user_id', $userId)
            ->whereIn('status', ['draft', 'agreed', 'paid'])
            ->orderByDesc('last_working_day')
            ->value('last_working_day');

        if (!$leaving) {
            $leaving = DB::table('resignations')
                ->where('user_id', $userId)
                ->orderByDesc('id')
                ->value('last_working_date');
        }

        if (!$leaving) {
            return $left;
        }

        $lastDay = Carbon::parse($leaving);

        if ($lastDay->lt($periodStart)) {
            return 1;
        }

        return max(1, min($left, $periodStart->diffInMonths($lastDay) + 1));
    }

    /** This month's professional tax, from the company's own bands. */
    protected function monthlyProfessionalTax(float $monthlyGross): float
    {
        return (new SalaryCalculator())->professionalTax($monthlyGross);
    }

    /** An employee's declaration, or an empty one. */
    public function declaration(int $userId, string $year): object
    {
        $row = DB::table('tax_declarations')->where('user_id', $userId)
            ->where('financial_year', $year)->first();

        return $row ?: (object) [
            'user_id' => $userId, 'financial_year' => $year, 'regime' => 'new',
            'section_80c' => 0, 'section_80d' => 0, 'section_80ccd_1b' => 0,
            'home_loan_interest' => 0, 'other_deductions' => 0,
            'rent_paid_annual' => 0, 'metro_city' => false,
            'previous_employer_income' => 0, 'previous_employer_tax' => 0,
            'proofs_received' => false, 'note' => null,
        ];
    }

    public function settings(string $year, string $regime)
    {
        return DB::table('tax_settings')->where('financial_year', $year)
            ->where('regime', $regime)->first();
    }

    /** @return array<string, mixed> */
    protected function nothing(string $year, string $regime, string $why): array
    {
        return [
            'ok' => false, 'financial_year' => $year, 'regime' => $regime,
            'needs_review' => false, 'months_done' => 0, 'months_left' => 0,
            'monthly_gross' => 0.0, 'projected_gross' => 0.0, 'reliefs' => [],
            'taxable' => 0.0, 'annual_tax' => 0.0, 'rebate' => 0.0, 'surcharge' => 0.0,
            'cess' => 0.0, 'already_deducted' => 0.0, 'outstanding' => 0.0,
            'monthly_tds' => 0.0, 'paid_so_far' => ['gross' => 0.0, 'tds' => 0.0, 'pt' => 0.0, 'payslips' => 0],
            'workings' => [], 'message' => $why,
        ];
    }

}
