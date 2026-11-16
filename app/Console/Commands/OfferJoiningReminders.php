<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\OfferJoiningToday;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The morning someone with an offer letter joins: a bell notification for HR,
 * the administrators and whoever issued the letter. Once per letter
 * (joining_notified_at); a withdrawn offer is left out.
 */
class OfferJoiningReminders extends Command
{

    protected $signature = 'offer-letters:joining-reminders';

    protected $description = 'Bell notification on the day an offer letter\'s candidate joins';

    public function handle(): int
    {
        if (!\Illuminate\Support\Facades\Schema::hasColumn('offer_letters', 'joining_notified_at')) {
            return self::SUCCESS;
        }

        $sent = 0;

        foreach (DB::table('offer_letters')->whereNull('cancelled_at')->whereNull('joining_notified_at')
            ->whereDate('joining_date', now('Asia/Kolkata')->toDateString())->get() as $letter) {
            $recipients = User::withoutGlobalScopes()->where('company_id', $letter->company_id)->where('status', 'active')
                ->where(fn ($q) => $q->whereHas('roles', fn ($r) => $r->whereIn('name', ['admin', 'manager_admin', 'hr']))
                    ->when($letter->generated_by, fn ($w) => $w->orWhere('id', $letter->generated_by)))
                ->get();

            foreach ($recipients as $user) {
                $user->notify(new OfferJoiningToday($letter));
            }

            DB::table('offer_letters')->where('id', $letter->id)->update(['joining_notified_at' => now()]);
            $sent++;
        }

        $this->info($sent . ' joining reminder(s) sent.');

        return self::SUCCESS;
    }

}
