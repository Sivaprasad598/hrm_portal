<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\PayrollDraftsReady;
use App\Services\Iapp\Pipeline\PipelineStatus;
use App\Services\Payroll\PayslipWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Once a pay cycle is over, issues a draft payslip for everybody payable who
 * does not have one yet — the last step of the biometric → payslip chain.
 *
 * Drafts only: approving and marking paid stay with a person, on Payslips
 * Issued. Somebody the writer refuses (device data not pulled through the
 * cycle yet, no CTC, no employee code, already settled) is simply tried again
 * on the next run, so a late pull fixes itself the following morning.
 */
class GenerateDuePayslips extends Command
{

    protected $signature = 'payroll:generate-due {--force : Run even if automatic payslips are switched off}';

    protected $description = 'Issue draft payslips for the pay cycle that has just ended';

    public const LAST_RUN_KEY = 'payroll.auto_generate.last';

    /** How long after a cycle ends the automatic run keeps trying (late device data, etc.). */
    public const CATCH_UP_DAYS = 15;

    public function handle(PayslipWriter $writer): int
    {
        $status = new PipelineStatus();

        if (!$this->option('force') && !$status->autoPayslips()) {
            $this->line('Automatic payslips are switched off (Biometric → Settings). Nothing done.');

            return self::SUCCESS;
        }

        $cycle = $status->lastEndedCycle();

        // The automatic run looks after the cycle that has just ended. Switching
        // automation on must not quietly back-fill a cycle that ended weeks ago
        // and was never paid through this system - that one is for a person to
        // run on Payroll Run (or with --force).
        $endedDaysAgo = (int) $cycle->end->copy()->startOfDay()->diffInDays($status->today(), false);

        if (!$this->option('force') && $endedDaysAgo > self::CATCH_UP_DAYS) {
            $this->line(sprintf('%s ended %d days ago; the automatic run only covers the last %d days. Nothing done.',
                $cycle->label, $endedDaysAgo, self::CATCH_UP_DAYS));

            return self::SUCCESS;
        }

        $due = DB::table('users as u')
            ->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->whereNotIn('u.id', \App\Services\Access\SystemAccounts::ids())   // not the Super Admin login
            ->where('u.status', 'active')
            ->where('u.employee_ctc', '>', 0)
            ->whereNotNull('ed.employee_card_id')
            ->where(fn ($q) => $q->whereNull('ed.joining_date')->orWhereDate('ed.joining_date', '<=', $cycle->end))
            ->where(fn ($q) => $q->whereNull('ed.last_date')->orWhereDate('ed.last_date', '>=', $cycle->start))
            ->whereNotExists(function ($q) use ($cycle) {
                $q->select(DB::raw(1))->from('payslips as p')
                    ->whereRaw('CAST(p.emp_id AS CHAR) = CAST(ed.employee_card_id AS CHAR)')
                    ->where('p.period', $cycle->label)
                    ->where('p.status', 'active')
                    ->whereNull('p.deleted_at');
            })
            ->orderBy('u.name')
            ->get(['u.id', 'u.name']);

        $created = [];
        $waiting = [];

        foreach ($due as $employee) {
            $result = $writer->issue((int) $employee->id, $cycle->month, $cycle->year, null);

            if ($result['ok']) {
                $created[] = $employee->name;
            }
            else {
                $waiting[] = $result['message'];
            }
        }

        Cache::store('file')->forever(self::LAST_RUN_KEY, [
            'period' => $cycle->label,
            'range' => $cycle->describe(),
            'created' => count($created),
            'waiting' => array_slice($waiting, 0, 50),
            'at' => now()->toIso8601String(),
        ]);

        $this->info(sprintf('%s: %d draft payslip(s) issued, %d waiting.', $cycle->label, count($created), count($waiting)));

        foreach ($waiting as $reason) {
            $this->line('  waiting: ' . $reason);
        }

        if ($created !== []) {
            $admins = User::allAdmins();
            Notification::send($admins, new PayrollDraftsReady($cycle->label, count($created), $waiting));
        }

        return self::SUCCESS;
    }

}
