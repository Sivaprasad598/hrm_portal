<?php

namespace App\Console\Commands;

use App\Services\Leaves\LeaveApprovals;
use Illuminate\Console\Command;

/**
 * A leave the manager or department head has not decided within the days set
 * in Leave Settings moves on to the next approval step.
 */
class EscalateLeaveApprovals extends Command
{

    protected $signature = 'leaves:escalate-approvals';

    protected $description = 'Move leave approvals left undecided too long on to the next step';

    public function handle(): int
    {
        LeaveApprovals::reset();
        $this->line(LeaveApprovals::escalateDue() . ' leave application(s) moved on.');

        return self::SUCCESS;
    }

}
