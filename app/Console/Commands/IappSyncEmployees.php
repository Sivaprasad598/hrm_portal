<?php

namespace App\Console\Commands;

use App\Models\IappEmployee;
use App\Services\Iapp\IappConfig;
use App\Services\Iapp\Sync\EmployeeSync;
use Illuminate\Console\Command;

/**
 * Refreshes the biometric employee register and links it to ERP employees.
 *
 * One-way: employees are read from the devices and mapped here. Nothing is
 * ever written back to the biometric platform, and no ERP employee record is
 * modified — only this module's mapping table.
 */
class IappSyncEmployees extends Command
{

    protected $signature = 'iapp:sync-employees
                            {--no-auto-map : Register device codes without attempting to match them to ERP employees.}';

    protected $description = 'Refresh the biometric employee register and auto-map codes to ERP employees';

    public function handle(): int
    {
        $config = new IappConfig();
        $sync = new EmployeeSync($config);

        $result = $sync->sync(!$this->option('no-auto-map'));

        // Apply the register to punches and attendance already stored, so a
        // newly mapped employee's history appears immediately.
        $applied = $sync->applyMappings();

        $sync->logRun($result, 'command');

        $this->info(sprintf(
            '%d biometric employee(s): %d new, %d auto-mapped, %d awaiting mapping.',
            $result['discovered'],
            $result['created'],
            $result['matched'],
            $result['unmapped']
        ));

        if ($applied['punches'] || $applied['attendance']) {
            $this->line(sprintf(
                '  re-linked %d punch(es) and %d attendance day(s) to their employees.',
                $applied['punches'],
                $applied['attendance']
            ));
        }

        if ($result['unmapped'] > 0) {
            $this->warn('  Map the remainder under Biometric > Employee Mapping.');

            $top = IappEmployee::withoutGlobalScopes()
                ->unmapped()
                ->orderByDesc('punch_count')
                ->limit(5)
                ->get(['emp_code', 'punch_count']);

            foreach ($top as $employee) {
                $this->line(sprintf('    %-16s %d punch(es)', $employee->emp_code, $employee->punch_count));
            }
        }

        return self::SUCCESS;
    }

}
