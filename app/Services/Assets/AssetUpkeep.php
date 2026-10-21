<?php

namespace App\Services\Assets;

use App\Models\Asset;
use App\Models\AssetRenewal;
use App\Models\AssetService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Servicing, repairs and renewals of an asset, and the one date the list,
 * the dashboard and the reminders work from: next_due_on, the earliest of
 *
 *   the next service       next_service_on
 *   the warranty end       warranty_until
 *   each current renewal   asset_renewals.expires_on (not renewed yet)
 *
 * An asset that is lost or disposed has nothing due.
 */
class AssetUpkeep
{

    public function __construct(protected AssetFlow $flow)
    {
    }

    /** Work out next_due_on / next_due_what again. */
    public function refreshDue(Asset $asset): void
    {
        $asset->refresh();
        $due = [];

        if (!in_array($asset->status, ['lost', 'disposed'], true)) {
            if ($asset->next_service_on) {
                $due[] = [$asset->next_service_on->toDateString(), 'Service'];
            }

            if ($asset->warranty_until) {
                $due[] = [$asset->warranty_until->toDateString(), 'Warranty'];
            }

            foreach (AssetRenewal::where('asset_id', $asset->id)->current()->get() as $renewal) {
                $due[] = [$renewal->expires_on->toDateString(), $renewal->kindLabel()];
            }
        }

        usort($due, fn ($a, $b) => strcmp($a[0], $b[0]));

        $asset->forceFill(['next_due_on' => $due[0][0] ?? null, 'next_due_what' => $due[0][1] ?? null])->saveQuietly();
    }

    /**
     * Log a service / repair / inspection. Left without a completed date it
     * stays open; an open repair on an asset in stock puts it Under Repair.
     *
     * $data: kind, vendor, reference, sent_on, completed_on, cost, notes, next_service_on, bill (stored name)
     */
    public function logService(Asset $asset, array $data): AssetService
    {
        $open = empty($data['completed_on']);

        if ($open && $data['kind'] === 'repair' && $asset->status === 'assigned') {
            throw new RuntimeException('This asset is with an employee. Take it back first (Take Return → needs repair), or enter the date the repair was completed.');
        }

        if ($open && $asset->openService) {
            throw new RuntimeException('A ' . strtolower($asset->openService->kindLabel()) . ' sent on '
                . $asset->openService->sent_on->format(company()->date_format) . ' is still open. Complete it first.');
        }

        if (!$open && $data['completed_on'] < $data['sent_on']) {
            throw new RuntimeException('The completed date cannot be before the date it was sent.');
        }

        $service = DB::transaction(function () use ($asset, $data, $open) {
            $service = AssetService::create([
                'company_id' => $asset->company_id,
                'asset_id' => $asset->id,
                'kind' => $data['kind'],
                'vendor' => $data['vendor'] ?? null,
                'reference' => $data['reference'] ?? null,
                'sent_on' => $data['sent_on'],
                'completed_on' => $data['completed_on'] ?? null,
                'cost' => ($data['cost'] ?? null) !== null && $data['cost'] !== '' ? $data['cost'] : null,
                'notes' => $data['notes'] ?? null,
                'bill' => $data['bill'] ?? null,
                'created_by' => user()?->id,
                'completed_by' => $open ? null : user()?->id,
            ]);

            $what = $service->kindLabel() . ($service->vendor ? ' at ' . $service->vendor : '');

            if ($open && $service->kind === 'repair' && $asset->status === 'available') {
                $this->flow->log($asset, 'repair', 'repair', null, 'Sent for repair' . ($service->vendor ? ' to ' . $service->vendor : ''));
                $asset->update(['status' => 'repair']);
            } else {
                $this->flow->log($asset, 'serviced', null, null, $what . ($open ? ' (sent)' : ' done')
                    . ($service->cost !== null ? ', cost ' . number_format((float) $service->cost, 2) : ''));
            }

            if (!$open) {
                $this->afterCompleted($asset, $service, $data['next_service_on'] ?? null);
            }

            return $service;
        });

        $this->refreshDue($asset);

        return $service;
    }

    /** Close an open job. A repair closed on an asset Under Repair puts it back in stock. */
    public function completeService(AssetService $service, array $data): AssetService
    {
        if ($service->completed_on) {
            throw new RuntimeException('This was already completed on ' . $service->completed_on->format(company()->date_format) . '.');
        }

        if ($data['completed_on'] < $service->sent_on->toDateString()) {
            throw new RuntimeException('The completed date cannot be before it was sent (' . $service->sent_on->format(company()->date_format) . ').');
        }

        $asset = $service->asset;

        DB::transaction(function () use ($service, $asset, $data) {
            $service->update([
                'completed_on' => $data['completed_on'],
                'cost' => ($data['cost'] ?? '') !== '' && ($data['cost'] ?? null) !== null ? $data['cost'] : $service->cost,
                'notes' => trim(implode("\n", array_filter([$service->notes, $data['notes'] ?? null]))) ?: null,
                'bill' => $data['bill'] ?? $service->bill,
                'completed_by' => user()?->id,
            ]);

            $cost = $service->cost !== null ? ', cost ' . number_format((float) $service->cost, 2) : '';

            if ($service->kind === 'repair' && $asset->status === 'repair') {
                $this->flow->log($asset, 'repaired', 'available', null, 'Repair completed' . $cost);
                $asset->update(['status' => 'available']);
            } else {
                $this->flow->log($asset, 'serviced', null, null, $service->kindLabel() . ' completed' . $cost);
            }

            $this->afterCompleted($asset, $service, $data['next_service_on'] ?? null);
        });

        $this->refreshDue($asset);

        return $service->fresh();
    }

    /** A finished service sets the next one: the date given, or completed + the asset's interval. */
    protected function afterCompleted(Asset $asset, AssetService $service, ?string $nextServiceOn): void
    {
        if ($service->kind !== 'service' && !$nextServiceOn) {
            return;
        }

        $next = $nextServiceOn
            ?: ($asset->service_interval_months
                ? Carbon::parse($service->completed_on)->addMonthsNoOverflow((int) $asset->service_interval_months)->toDateString()
                : null);

        if ($next) {
            $asset->update(['next_service_on' => $next]);
        }
    }

    /**
     * Add a renewable period, or renew one ($previous): the new period is the
     * current one from then on. An extended warranty also moves the asset's
     * warranty date forward.
     *
     * $data: kind, provider, reference_no, starts_on, expires_on, cost, notes, document (stored name)
     */
    public function addRenewal(Asset $asset, array $data, ?AssetRenewal $previous = null): AssetRenewal
    {
        if ($previous && $previous->next()->exists()) {
            throw new RuntimeException('This ' . $previous->kindLabel() . ' was already renewed.');
        }

        if (!empty($data['starts_on']) && $data['expires_on'] < $data['starts_on']) {
            throw new RuntimeException('The expiry date cannot be before the start date.');
        }

        $renewal = DB::transaction(function () use ($asset, $data, $previous) {
            $renewal = AssetRenewal::create([
                'company_id' => $asset->company_id,
                'asset_id' => $asset->id,
                'previous_id' => $previous?->id,
                'kind' => $previous?->kind ?? $data['kind'],
                'provider' => $data['provider'] ?? null,
                'reference_no' => $data['reference_no'] ?? null,
                'starts_on' => $data['starts_on'] ?? null,
                'expires_on' => $data['expires_on'],
                'cost' => ($data['cost'] ?? '') !== '' ? $data['cost'] : null,
                'document' => $data['document'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => user()?->id,
            ]);

            if ($renewal->kind === 'warranty' && (!$asset->warranty_until || $asset->warranty_until->lt($renewal->expires_on))) {
                $asset->update(['warranty_until' => $renewal->expires_on->toDateString()]);
            }

            $this->flow->log($asset, $previous ? 'renewed' : 'renewal', null, null,
                $renewal->kindLabel() . ($renewal->provider ? ' with ' . $renewal->provider : '')
                . ' until ' . $renewal->expires_on->format(company()->date_format)
                . ($renewal->cost !== null ? ', cost ' . number_format((float) $renewal->cost, 2) : ''));

            return $renewal;
        });

        $this->refreshDue($asset);

        return $renewal;
    }

    /** Only the current period can be removed; its predecessor becomes current again. */
    public function deleteRenewal(AssetRenewal $renewal): void
    {
        if ($renewal->next()->exists()) {
            throw new RuntimeException('This period has been renewed. Delete the newer period first.');
        }

        $asset = $renewal->asset;
        $this->flow->log($asset, 'updated', null, null, $renewal->kindLabel() . ' until '
            . $renewal->expires_on->format(company()->date_format) . ' removed');
        $renewal->delete();

        $this->refreshDue($asset);
    }

}
