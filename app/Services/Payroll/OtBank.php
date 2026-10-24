<?php

namespace App\Services\Payroll;

use App\Services\Attendance\WorkRules;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The OT bank (Attendance Settings -> Overtime, "Bank it"): overtime hours
 * are kept and carried forward with no expiry, to cover LOP days or to be
 * encashed when payroll is generated.
 *
 * Hours enter the bank already weighted by their rate - 4 hours on a week-off
 * at 1.5x are 6 bank hours - so one bank hour is always worth one hourly rate:
 *
 *   hourly        = monthly gross (or basic) / (days in the period x work hours)
 *   cover a day   = the work hours of a day (8h; half a day 4h)
 *   encash        = hours x hourly
 *
 * Hours in: every day's overtime, stored as it happens (ot_daily, OtDaily),
 * and what was held before go-live, entered by month (ot_past, OtPast).
 * Hours out, in the ledger (ot_ledger), one row per movement:
 *   compensated  -h  LOP days covered (days)       } decided in the payroll run,
 *   encashed     -h  paid out as "OT encashed"     } attached to the payslip
 *   adjusted     +/-h  HR / Admin, with a reason
 *
 * A ledger row is settled once its payslip is out (adjustments always are);
 * the compensated / encashed rows of a period not yet issued are pending and
 * can still be changed. Revoking a payslip makes its rows pending again.
 */
class OtBank
{

    public const EARNED = 'earned';
    public const COMPENSATED = 'compensated';
    public const ENCASHED = 'encashed';
    public const ADJUSTED = 'adjusted';

    public const ENCASH_LABEL = 'OT encashed';

    public function __construct(protected ?WorkRules $rules = null)
    {
    }

    public function rules(int $companyId): ?WorkRules
    {
        return $this->rules ?: WorkRules::forCompany($companyId);
    }

    public function enabled(int $companyId): bool
    {
        $r = $this->rules($companyId);

        return $r && $r->s->ot_enabled && ($r->s->ot_mode ?? 'bank') === 'bank';
    }

    /** Hours a day of work is worth - the default shift's work hours, or the setting. */
    public function dayHours(int $companyId): float
    {
        $rules = $this->rules($companyId);

        if (!$rules) {
            return 8.0;
        }

        // The default shift as it stands today (nobody is user 0, so this is the company default).
        $shift = \App\Services\Attendance\ShiftResolver::shared($companyId)->shiftOn(0, now()->toDateString());

        return round($rules->workSeconds($shift) / 3600, 2) ?: 8.0;
    }

    /** Before this period: past records, the days stored, what was used on issued payslips, the corrections. */
    public function broughtForward(int $userId, PayPeriod $cycle): float
    {
        $start = $cycle->start->toDateString();
        $days = (int) DB::table('ot_daily')->where('user_id', $userId)->where('date', '<', $start)->sum('bank_minutes');
        $ledger = (float) DB::table('ot_ledger')->where('user_id', $userId)->whereDate('period_start', '<', $start)
            ->where(fn ($q) => $q->where('type', self::ADJUSTED)->orWhereNotNull('payslip_id'))
            ->whereIn('type', [self::ADJUSTED, self::COMPENSATED, self::ENCASHED])->sum('hours');

        return round(OtPast::net($userId, $start) + $days / 60 + $ledger, 2);
    }

    /** The balance today: past records, every day stored, what was used on issued payslips, the corrections. */
    public function balance(int $userId): float
    {
        $days = (int) DB::table('ot_daily')->where('user_id', $userId)->sum('bank_minutes');
        $ledger = (float) DB::table('ot_ledger')->where('user_id', $userId)
            ->where(fn ($q) => $q->where('type', self::ADJUSTED)->orWhereNotNull('payslip_id'))
            ->whereIn('type', [self::ADJUSTED, self::COMPENSATED, self::ENCASHED])->sum('hours');

        return round(OtPast::net($userId) + $days / 60 + $ledger, 2);
    }

    /** Bank hours stored for the days of a period. */
    public function periodHours(int $userId, PayPeriod $cycle): float
    {
        return round((int) DB::table('ot_daily')->where('user_id', $userId)
            ->whereBetween('date', [$cycle->start->toDateString(), $cycle->end->toDateString()])->sum('bank_minutes') / 60, 2);
    }

    /**
     * Corrections not in the brought-forward figure: made during this period or
     * since - a correction made today counts even when an older period's
     * payroll is being run late.
     */
    protected function periodAdjustments(int $userId, PayPeriod $cycle): float
    {
        return round((float) DB::table('ot_ledger')->where('user_id', $userId)->where('type', self::ADJUSTED)
            ->whereDate('period_start', '>=', $cycle->start->toDateString())->sum('hours'), 2);
    }

    /** This period's overtime in bank hours (each kind of hour times its rate). */
    public function earnedHours(array $ot): float
    {
        return round(array_sum(array_map(fn ($l) => $l['hours'] * $l['rate'], $ot['lines'] ?? [])), 2);
    }

    /** This period's compensated / encashed rows. */
    public function decisions(int $userId, PayPeriod $cycle): array
    {
        $rows = DB::table('ot_ledger')->where('user_id', $userId)
            ->whereDate('period_start', $cycle->start->toDateString())
            ->whereIn('type', [self::COMPENSATED, self::ENCASHED])->get();

        return [
            'comp_days' => (float) $rows->where('type', self::COMPENSATED)->sum('days'),
            'comp_hours' => -(float) $rows->where('type', self::COMPENSATED)->sum('hours'),
            'enc_hours' => -(float) $rows->where('type', self::ENCASHED)->sum('hours'),
            'enc_amount' => (float) $rows->where('type', self::ENCASHED)->sum('amount'),
            'issued' => $rows->whereNotNull('payslip_id')->isNotEmpty(),
        ];
    }

    /** LOP days covered by the bank in this period - added to the paid days. */
    public function compensatedDays(int $userId, PayPeriod $cycle): float
    {
        return (float) DB::table('ot_ledger')->where('user_id', $userId)
            ->whereDate('period_start', $cycle->start->toDateString())
            ->where('type', self::COMPENSATED)->sum('days');
    }

    /**
     * Everything the payroll run shows for one employee and period.
     *
     * @param array $count AttendanceDays::forRange()  @param array $pay SalaryCalculator::forEmployee()
     */
    public function summary(int $userId, int $companyId, PayPeriod $cycle, array $count, array $pay, array $ot): array
    {
        $rules = $this->rules($companyId);
        $dayHours = $this->dayHours($companyId);
        $base = ($rules && $rules->s->ot_basis === 'basic') ? (float) $pay['basic'] : (float) $pay['gross'];
        $hourly = $base / max(1, $cycle->days() * ((float) ($rules->s->wh_work_hours ?? 8) ?: 8));

        // This period's days are stored from this very count first, so the
        // bank and the screen read the same figures.
        if (!empty($count['days'])) {
            (new OtDaily())->store($userId, $companyId, $count);
        }

        $bf = $this->broughtForward($userId, $cycle);
        $earned = $this->periodHours($userId, $cycle);
        $adjusted = $this->periodAdjustments($userId, $cycle);
        $d = $this->decisions($userId, $cycle);
        $lop = max(0.0, (float) $count['working_days'] - (float) $count['days_present']);   // before any compensation
        $available = round($bf + $earned + $adjusted, 2);

        return [
            'bf' => $bf, 'earned' => $earned, 'adjusted' => $adjusted, 'available' => $available,
            'lop_days' => $lop, 'day_hours' => $dayHours, 'hourly' => round($hourly, 2),
            'comp_days' => $d['comp_days'], 'comp_hours' => $d['comp_hours'],
            'enc_hours' => $d['enc_hours'], 'enc_amount' => $d['enc_amount'],
            'cf' => round($available - $d['comp_hours'] - $d['enc_hours'], 2),
            'max_comp_days' => floor(min($lop, max(0, $available) / max(0.01, $dayHours)) * 2) / 2,
            'issued' => $d['issued'],
        ];
    }

    /**
     * HR's decision for a period: LOP days to cover and hours to encash. Replaces
     * any earlier decision for the period; the encashment goes on the run as an
     * earning line that the payslip will carry.
     */
    public function decide(int $userId, int $companyId, PayPeriod $cycle, array $summary, float $compDays, float $encHours, ?int $by = null): array
    {
        $rules = $this->rules($companyId);

        if ($summary['issued'] || $this->payslipIssued($userId, $cycle)) {
            throw new RuntimeException('The payslip for ' . $cycle->describe() . ' is already issued. Revoke it to change the OT bank.');
        }

        if ($compDays > 0 && !($rules->s->ot_compensate ?? true)) {
            throw new RuntimeException('Covering LOP with OT is switched off in the settings.');
        }

        if ($encHours > 0 && !($rules->s->ot_encash ?? true)) {
            throw new RuntimeException('Encashing OT is switched off in the settings.');
        }

        if ($compDays < 0 || $encHours < 0 || fmod($compDays * 2, 1.0) != 0.0) {
            throw new RuntimeException('LOP days are covered in whole or half days.');
        }

        if ($compDays > $summary['lop_days']) {
            throw new RuntimeException('Only ' . $summary['lop_days'] . ' LOP day(s) to cover this period.');
        }

        $compHours = round($compDays * $summary['day_hours'], 2);

        if ($compHours + $encHours > $summary['available'] + 0.001) {
            throw new RuntimeException(sprintf('That needs %sh but the bank has %sh.', $compHours + $encHours, $summary['available']));
        }

        DB::transaction(function () use ($userId, $companyId, $cycle, $compDays, $compHours, $encHours, $summary, $by) {
            DB::table('ot_ledger')->where('user_id', $userId)->whereDate('period_start', $cycle->start->toDateString())
                ->whereIn('type', [self::COMPENSATED, self::ENCASHED])->whereNull('payslip_id')->delete();

            if ($compDays > 0) {
                $this->write($userId, $companyId, $cycle, self::COMPENSATED, -$compHours, $compDays, null,
                    sprintf('%s LOP day(s) covered at %sh a day', self::fmt($compDays), self::fmt($summary['day_hours'])), $by);
            }

            $amount = round($encHours * $summary['hourly'], 2);

            if ($encHours > 0) {
                $this->write($userId, $companyId, $cycle, self::ENCASHED, -$encHours, null, $amount,
                    sprintf('%sh x %s', self::fmt($encHours), number_format($summary['hourly'], 2)), $by);
            }

            // The payment itself: an earning on the run, carried by the payslip.
            $line = DB::table('payslip_components')->where('user_id', $userId)
                ->whereDate('period_start', $cycle->start->toDateString())
                ->where('label', 'like', self::ENCASH_LABEL . '%')->whereNull('payslip_id')->first(['id']);
            $label = self::ENCASH_LABEL . ' (' . self::fmt($encHours) . 'h)';

            if ($encHours > 0 && $line) {
                DB::table('payslip_components')->where('id', $line->id)
                    ->update(['label' => $label, 'amount' => $amount, 'note' => 'OT bank', 'updated_at' => now()]);
            } elseif ($encHours > 0) {
                (new PayComponents())->add($userId, $cycle->start, 'earning', $label, $amount, 'OT bank');
            } elseif ($line) {
                DB::table('payslip_components')->where('id', $line->id)->delete();
            }
        });

        return $this->decisions($userId, $cycle);
    }

    /** HR / Admin correction of the balance, with a reason. */
    public function adjust(int $userId, int $companyId, float $hours, string $reason, ?int $by = null): void
    {
        if ($hours == 0.0) {
            throw new RuntimeException('Give the hours to add (+) or take off (-).');
        }

        if ($this->balance($userId) + $hours < -0.001) {
            throw new RuntimeException('That would leave the bank below zero (it has ' . self::fmt($this->balance($userId)) . 'h).');
        }

        $this->write($userId, $companyId, PayPeriod::containing(now()), self::ADJUSTED, round($hours, 2), null, null, $reason, $by);
    }

    /**
     * When the period's payslip is issued: the period's overtime is already in
     * the bank day by day; attach what was used to the payslip and record it.
     */
    public function onIssue(int $userId, int $companyId, PayPeriod $cycle, int $payslipId, array $summary): void
    {
        $earned = $this->periodHours($userId, $cycle);

        DB::table('ot_ledger')->where('user_id', $userId)->whereDate('period_start', $cycle->start->toDateString())
            ->whereIn('type', [self::COMPENSATED, self::ENCASHED])->whereNull('payslip_id')
            ->update(['payslip_id' => $payslipId, 'updated_at' => now()]);

        $d = $this->decisions($userId, $cycle);

        DB::table('payslips')->where('id', $payslipId)->update(['ot_summary' => json_encode([
            'brought_forward' => $summary['bf'], 'earned' => $earned, 'covered_days' => $d['comp_days'], 'covered_hours' => $d['comp_hours'],
            'encashed_hours' => $d['enc_hours'], 'encashed_amount' => $d['enc_amount'], 'balance' => $this->balance($userId),
        ])]);
    }

    /** Automatic cover (setting "automatically"): as many LOP days as the bank allows. */
    public function autoCompensate(int $userId, int $companyId, PayPeriod $cycle, array $summary): array
    {
        $rules = $this->rules($companyId);

        if (!$rules || !($rules->s->ot_compensate ?? true) || ($rules->s->ot_compensate_mode ?? 'manual') !== 'auto'
            || $summary['comp_days'] > 0 || $summary['enc_hours'] > 0 || $summary['max_comp_days'] <= 0) {
            return $summary;
        }

        $this->decide($userId, $companyId, $cycle, $summary, $summary['max_comp_days'], 0.0);

        return array_merge($summary, $this->decisions($userId, $cycle));
    }

    /** A revoked payslip: what it used from the bank is open again (the days stay - they are attendance). */
    public function onRevoke(int $payslipId): void
    {
        DB::table('ot_ledger')->where('payslip_id', $payslipId)->update(['payslip_id' => null, 'updated_at' => now()]);
    }

    public function history(int $userId)
    {
        return DB::table('ot_ledger as l')->leftJoin('payslips as p', 'p.id', '=', 'l.payslip_id')
            ->leftJoin('users as u', 'u.id', '=', 'l.created_by')
            ->where('l.user_id', $userId)->orderByDesc('l.period_start')->orderByDesc('l.id')
            ->get(['l.*', 'p.payslip_number', 'u.name as by_name']);
    }

    protected function payslipIssued(int $userId, PayPeriod $cycle): bool
    {
        $card = DB::table('employee_details')->where('user_id', $userId)->value('employee_card_id');

        return $card && DB::table('payslips')->where('emp_id', (string) $card)->where('period', $cycle->label)
            ->where('status', 'active')->whereNull('deleted_at')->exists();
    }

    protected function write(int $userId, int $companyId, PayPeriod $cycle, string $type, float $hours, ?float $days, ?float $amount,
        ?string $note, ?int $by, ?int $payslipId = null): void
    {
        DB::table('ot_ledger')->insert([
            'company_id' => $companyId, 'user_id' => $userId, 'period_start' => $cycle->start->toDateString(), 'type' => $type,
            'hours' => $hours, 'days' => $days, 'amount' => $amount, 'payslip_id' => $payslipId, 'note' => $note,
            'created_by' => $by ?? (function_exists('user') ? user()?->id : null), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public static function fmt(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2), '0'), '.') ?: '0';
    }

}
