<?php

namespace App\Console\Commands;

use App\Services\Recruit\RecruitAutomation;
use Illuminate\Console\Command;

/**
 * The recruitment rounds, every 15 minutes on the server (Kernel): reminds
 * the panel of scorecards / marks still to come and tells HR when they are
 * overdue, expires offers past their "valid until" date, and moves on any
 * application waiting on the system (next round's slot, Selected + the offer
 * draft after the last round).
 */
class RecruitAutomate extends Command
{
    protected $signature = 'recruit:automate';

    protected $description = 'Run the recruitment rounds: reminders, overdue alerts, offer expiry, next steps';

    public function handle(RecruitAutomation $automation): int
    {
        $done = $automation->run();
        $this->info(collect($done)->map(fn ($n, $what) => $what . ': ' . $n)->implode(', '));

        return self::SUCCESS;
    }
}
