<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\AssetRenewal;
use App\Models\User;
use App\Notifications\AssetDueReminder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bell reminders for asset upkeep: a service, a warranty or a renewal (AMC,
 * insurance, RC / PUC, licence) due within 30 days, within 7 days, or past
 * its date. Each stage is announced once (asset_reminders), as one digest per
 * person who manages assets. In-app only: no email.
 */
class AssetDueReminders extends Command
{

    protected $signature = 'assets:due-reminders {--dry-run : List what would be announced, change nothing}';

    protected $description = 'Bell reminders for asset services, warranties and renewals coming due';

    public function handle(): int
    {
        $today = now()->startOfDay();
        $found = [];

        $assets = Asset::withoutGlobalScopes()->whereNotIn('status', ['lost', 'disposed'])
            ->where(fn ($q) => $q->whereNotNull('next_service_on')->orWhereNotNull('warranty_until')
                ->orWhereExists(fn ($r) => $r->from('asset_renewals')->whereColumn('asset_renewals.asset_id', 'assets.id')))
            ->get();

        foreach ($assets as $asset) {
            $items = [];

            if ($asset->next_service_on) {
                $items[] = ['service', 'Service', $asset->next_service_on];
            }

            if ($asset->warranty_until) {
                $items[] = ['warranty', 'Warranty', $asset->warranty_until];
            }

            foreach (AssetRenewal::withoutGlobalScopes()->where('asset_id', $asset->id)->current()->get() as $renewal) {
                $items[] = ['renewal:' . $renewal->id, $renewal->kindLabel(), $renewal->expires_on];
            }

            foreach ($items as [$subject, $label, $due]) {
                $due = Carbon::parse($due)->startOfDay();
                $days = (int) $today->diffInDays($due, false);
                $stage = $days < 0 ? 'overdue' : ($days <= 7 ? '7' : ($days <= Asset::DUE_SOON_DAYS ? '30' : null));

                if (!$stage) {
                    continue;
                }

                if (!$this->option('dry-run')) {
                    $new = DB::table('asset_reminders')->insertOrIgnore([
                        'company_id' => $asset->company_id, 'asset_id' => $asset->id, 'subject' => $subject,
                        'due_on' => $due->toDateString(), 'stage' => $stage, 'created_at' => now(), 'updated_at' => now(),
                    ]);

                    if (!$new) {
                        continue;   // this stage was announced already
                    }
                }

                $found[$asset->company_id][] = [
                    'asset_id' => $asset->id, 'asset' => $asset->name, 'code' => $asset->asset_code,
                    'what' => $label, 'due_on' => $due->toDateString(), 'days' => $days,
                ];
            }
        }

        foreach ($found as $companyId => $list) {
            usort($list, fn ($a, $b) => $a['days'] <=> $b['days']);
            $recipients = $this->recipients((int) $companyId);

            $this->info(count($list) . ' item(s) due for company ' . $companyId . ', ' . $recipients->count() . ' recipient(s).');

            foreach ($list as $item) {
                $this->line(sprintf('  %s %s (%s): %s %s', $item['what'], $item['asset'], $item['code'], $item['due_on'],
                    $item['days'] < 0 ? abs($item['days']) . ' day(s) overdue' : 'in ' . $item['days'] . ' day(s)'));
            }

            if (!$this->option('dry-run')) {
                foreach ($recipients as $user) {
                    $user->notify(new AssetDueReminder($list));
                }
            }
        }

        if (!$found) {
            $this->info('Nothing new coming due.');
        }

        return self::SUCCESS;
    }

    /** Active people who manage assets (manage_assets = all). */
    protected function recipients(int $companyId)
    {
        $ids = DB::table('user_permissions as up')
            ->join('permissions as p', 'p.id', '=', 'up.permission_id')
            ->where('p.name', 'manage_assets')
            ->where('up.permission_type_id', 4)
            ->pluck('up.user_id');

        return User::withoutGlobalScopes()->whereIn('id', $ids)->where('status', 'active')
            ->where('company_id', $companyId)->get();
    }

}
