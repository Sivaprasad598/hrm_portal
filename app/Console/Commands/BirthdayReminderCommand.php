<?php

namespace App\Console\Commands;

use App\Events\BirthdayReminderEvent;
use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BirthdayReminderCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'birthday-notification';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send birthday notification to everyone (use -v for per-company output)';

    /**
     * Handle the command.
     *
     * Companies are processed in chunks; for each chunk a single query loads all matching
     * birthdays for those companies (avoids one query per company when there are 1000+ tenants).
     */
    public function handle()
    {
        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }

        $wallStart = microtime(true);
        $ruStart = function_exists('getrusage') ? getrusage() : null;

        $this->info('Birthday notification run started.');

        $month = (int) now()->format('n');
        $day = (int) now()->format('j');

        $companiesProcessed = 0;
        $companiesWithBirthdays = 0;

        Company::active()
            ->with('slackSetting')
            ->select(['companies.id', 'companies.header_color', 'companies.company_name'])
            ->chunkById(100, function ($companies) use ($month, $day, &$companiesProcessed, &$companiesWithBirthdays) {
                $companyIds = $companies->modelKeys();

                if ($companyIds === []) {
                    return;
                }

                $rows = DB::table('employee_details')
                    ->join('users', 'employee_details.user_id', '=', 'users.id')
                    ->whereIn('employee_details.company_id', $companyIds)
                    ->where('users.status', 'active')
                    ->whereNotNull('employee_details.date_of_birth')
                    ->whereMonth('employee_details.date_of_birth', $month)
                    ->whereDay('employee_details.date_of_birth', $day)
                    ->orderBy('employee_details.company_id')
                    ->orderBy('employee_details.date_of_birth')
                    ->select(
                        'employee_details.company_id',
                        'employee_details.date_of_birth',
                        'users.name',
                        'users.image',
                        'users.id as id'
                    )
                    ->get();

                $birthdaysByCompany = $rows->groupBy(fn ($row) => (int) $row->company_id);

                foreach ($companies as $company) {
                    $companiesProcessed++;

                    $subset = $birthdaysByCompany->get($company->id);

                    if ($subset === null || $subset->isEmpty()) {
                        continue;
                    }

                    $upcomingBirthday = $subset->map(function ($row) {
                        return [
                            'company_id' => (int) $row->company_id,
                            'date_of_birth' => $row->date_of_birth,
                            'name' => $row->name,
                            'image' => $row->image,
                            'id' => (int) $row->id,
                        ];
                    })->values()->all();

                    event(new BirthdayReminderEvent($company, $upcomingBirthday));
                    $companiesWithBirthdays++;

                    if ($this->output->isVerbose()) {
                        $count = count($upcomingBirthday);
                        $this->line("Company #{$company->id} ({$company->company_name}): {$count} birthday(s).");
                    }
                }
            });

        $this->info("Birthday notification run finished. Companies scanned: {$companiesProcessed}, companies with birthdays notified: {$companiesWithBirthdays}.");

        $this->writeResourceUsageSummary($wallStart, $ruStart);

        return Command::SUCCESS;
    }

    private function writeResourceUsageSummary(float $wallStart, ?array $ruStart): void
    {
        $wallMs = (microtime(true) - $wallStart) * 1000;
        $peakBytes = memory_get_peak_usage(true);
        $currentBytes = memory_get_usage(true);

        $this->newLine();
        $this->line('Resource usage (this run):');
        $this->line(sprintf('  Wall time:      %.2f ms', $wallMs));
        $this->line(sprintf('  Peak memory:    %s', $this->formatBinarySuffix($peakBytes)));
        $this->line(sprintf('  Memory at end:  %s', $this->formatBinarySuffix($currentBytes)));

        if ($ruStart !== null && function_exists('getrusage')) {
            $ruEnd = getrusage();
            $userMs = ($this->rusageCpuSeconds($ruEnd, 'ru_utime') - $this->rusageCpuSeconds($ruStart, 'ru_utime')) * 1000;
            $sysMs = ($this->rusageCpuSeconds($ruEnd, 'ru_stime') - $this->rusageCpuSeconds($ruStart, 'ru_stime')) * 1000;
            $cpuMs = $userMs + $sysMs;
            $this->line(sprintf('  CPU time:       %.2f ms (user %.2f ms + system %.2f ms)', $cpuMs, $userMs, $sysMs));
        } else {
            $this->line('  CPU time:       n/a (getrusage() not available on this platform)');
        }
    }

    private function rusageCpuSeconds(array $ru, string $prefix): float
    {
        $sec = $ru["{$prefix}.tv_sec"] ?? 0;
        $usec = $ru["{$prefix}.tv_usec"] ?? 0;

        return $sec + ($usec / 1_000_000);
    }

    private function formatBinarySuffix(int $bytes): string
    {
        $units = ['MiB', 'GiB'];
        $mb = $bytes / (1024 * 1024);

        if ($mb >= 1024) {
            return sprintf('%.2f %s', $mb / 1024, $units[1]);
        }

        return sprintf('%.2f %s', $mb, $units[0]);
    }

}
