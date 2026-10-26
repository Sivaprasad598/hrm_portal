<?php

namespace App\Services\Iapp\Pipeline;

use App\Models\IappAttendanceSyncRun;
use App\Models\IappSyncLog;
use App\Services\Iapp\IappConfig;
use App\Services\Payroll\PayPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Where the biometric → attendance → payslip chain stands. One place, so the
 * Overview screen, the payroll guards and the automation all read the same facts.
 */
class PipelineStatus
{

    public const HEARTBEAT_KEY = 'scheduler.heartbeat';

    protected string $timezone;

    public function __construct(protected ?IappConfig $config = null)
    {
        $this->config ??= new IappConfig();
        $this->timezone = DB::table('companies')->value('timezone') ?: 'Asia/Kolkata';
    }

    public function config(): IappConfig
    {
        return $this->config;
    }

    /** Is the device feed in use at all? Without it, payroll reads web clock-ins only. */
    public function deviceInUse(): bool
    {
        return $this->config->isConfigured() && (bool) $this->config->setting()?->status;
    }

    public function autoPull(): bool
    {
        return (bool) $this->config->setting()?->sync_enabled;
    }

    public function autoPush(): bool
    {
        return (bool) $this->config->setting()?->auto_promote;
    }

    public function autoPayslips(): bool
    {
        return (bool) $this->config->setting()?->auto_generate_payslips;
    }

    /** When the scheduler last ticked, or null if it never has. */
    public function schedulerLastTick(): ?Carbon
    {
        $tick = Cache::store('file')->get(self::HEARTBEAT_KEY);

        return $tick ? Carbon::parse($tick) : null;
    }

    /** Ticking means the scheduler ran in the last ten minutes. */
    public function schedulerRunning(): bool
    {
        $tick = $this->schedulerLastTick();

        return $tick !== null && $tick->gt(now()->subMinutes(10));
    }

    /** The latest full pull (not a one-employee pull), whatever its outcome. */
    public function lastPull(): ?IappSyncLog
    {
        return IappSyncLog::withoutGlobalScopes()
            ->where('sync_type', IappSyncLog::TYPE_DEVICE_TRANS)
            ->whereNull('emp_code')
            ->latest('id')
            ->first();
    }

    public function lastPush(): ?IappAttendanceSyncRun
    {
        return IappAttendanceSyncRun::withoutGlobalScopes()->latest('id')->first();
    }

    /**
     * The last whole day the device feed has been pulled for: the end of the
     * latest successful pull, or the day before it finished if it finished on
     * that same day (the day was still running when it was read).
     * A one-employee pull counts for that employee only.
     */
    public function deviceDataThrough(?string $empCode = null): ?Carbon
    {
        $logs = IappSyncLog::withoutGlobalScopes()
            ->where('sync_type', IappSyncLog::TYPE_DEVICE_TRANS)
            ->whereIn('status', [IappSyncLog::STATUS_SUCCESS, IappSyncLog::STATUS_PARTIAL])
            ->where(fn ($q) => $q->whereNull('emp_code')->when($empCode, fn ($q2) => $q2->orWhere('emp_code', $empCode)))
            ->whereNotNull('finished_at')
            ->latest('to_date')->latest('id')
            ->limit(20)
            ->get(['to_date', 'finished_at']);

        $best = null;

        foreach ($logs as $log) {
            $to = Carbon::parse($log->to_date, $this->timezone)->startOfDay();
            $finished = Carbon::parse($log->finished_at)->timezone($this->timezone);
            $complete = $finished->gt($to->copy()->endOfDay()) ? $to : $to->copy()->subDay();

            if ($best === null || $complete->gt($best)) {
                $best = $complete;
            }
        }

        return $best;
    }

    /**
     * Device days for mapped employees that have not reached ERP attendance yet.
     *
     * @param  int[]|null  $userIds
     */
    public function pendingPushDays(Carbon $from, Carbon $to, ?array $userIds = null): int
    {
        return DB::table('iapp_attendance_records as r')
            ->whereBetween('r.attendance_date', [$from->toDateString(), $to->toDateString()])
            ->whereNotNull('r.user_id')
            ->when($userIds, fn ($q) => $q->whereIn('r.user_id', $userIds))
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('iapp_attendance_links as l')
                    ->whereColumn('l.user_id', 'r.user_id')
                    ->whereRaw('DATE(l.attendance_date) = DATE(r.attendance_date)')
                    ->where('l.link_type', 'created');
            })
            ->count();
    }

    /** Active employees with no device code linked to them. */
    public function unmappedEmployees()
    {
        return DB::table('users as u')
            ->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->whereNotIn('u.id', \App\Services\Access\SystemAccounts::ids())   // not the Super Admin login
            ->where('u.status', 'active')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('iapp_employees as ie')
                    ->whereColumn('ie.user_id', 'u.id')
                    ->where('ie.map_status', 'mapped');
            })
            ->orderBy('u.name')
            ->get(['u.id', 'u.name', 'ed.employee_id', 'ed.employee_card_id']);
    }

    /** The employee's device code, if they have one. */
    public function deviceCodeFor(int $userId): ?string
    {
        return DB::table('iapp_employees')->where('user_id', $userId)->where('map_status', 'mapped')->value('emp_code');
    }

    public function currentCycle(): PayPeriod
    {
        return PayPeriod::containing(Carbon::now($this->timezone));
    }

    public function lastEndedCycle(): PayPeriod
    {
        return $this->currentCycle()->previous();
    }

    public function today(): Carbon
    {
        return Carbon::now($this->timezone)->startOfDay();
    }

    /**
     * Why a payslip for this employee and cycle cannot be issued yet, or null.
     *
     * Two rules. The cycle must be over: payroll pays the days that have
     * happened, and a payslip is locked once paid, so issuing mid-cycle left
     * the rest of the cycle unpaid for ever (the October run was paid on 8 Oct
     * for a cycle ending 20 Oct). And for anyone on the device, the device
     * feed must have been pulled through the last day of the cycle, otherwise
     * days that were worked are counted as absence.
     */
    public function payslipBlocker(int $userId, PayPeriod $cycle): ?string
    {
        $end = $cycle->end->copy()->setTimezone($this->timezone)->startOfDay();

        if ($end->gte($this->today())) {
            return sprintf(
                '%s runs until %s, so it can be paid from %s.',
                $cycle->describe(),
                $end->format('d M Y'),
                $end->copy()->addDay()->format('d M Y')
            );
        }

        if ($this->deviceInUse() && ($code = $this->deviceCodeFor($userId))) {
            $through = $this->deviceDataThrough($code);

            if ($through === null || $through->lt($end)) {
                return sprintf(
                    'device attendance has only been pulled up to %s; it must reach %s first (Biometric → Overview → Pull now).',
                    $through ? $through->format('d M Y') : 'no date at all',
                    $end->format('d M Y')
                );
            }
        }

        return null;
    }

}
