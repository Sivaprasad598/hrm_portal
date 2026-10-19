<?php

namespace App\Console\Commands;

use App\Services\Attendance\ShiftAssignments;
use Illuminate\Console\Command;

/**
 * Keeps each employee's assigned shift written into the roster a rolling
 * 60 days ahead, so clock-in, late marks and leave always find it.
 */
class ExtendShiftAssignments extends Command
{
    protected $signature = 'shifts:extend';

    protected $description = 'Write employees\' assigned shifts into the roster up to 60 days ahead';

    public function handle(ShiftAssignments $assignments): int
    {
        $written = $assignments->extendAll();
        $this->info($written . ' roster day(s) written.');

        return self::SUCCESS;
    }
}
