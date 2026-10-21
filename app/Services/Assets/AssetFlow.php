<?php

namespace App\Services\Assets;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetHistory;
use App\Models\AssetService;
use App\Models\EmployeeExit;
use App\Models\User;
use App\Notifications\AssetAssigned;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * What can happen to an asset, each step written to its history:
 *
 *   assign   available -> assigned, a hand-over row opened
 *   return   assigned  -> available / repair (or lost), the hand-over closed
 *   status   available <-> repair, -> lost, -> disposed; an asset still out is
 *            returned first, except "lost", which closes the hand-over as lost
 *
 * Every change to who holds what refreshes the holder's Asset Clearance on an
 * exit in progress: pending while they still hold anything, completed once
 * they hold nothing.
 */
class AssetFlow
{

    /** Statuses that can be set by hand, from the statuses they can be set from. */
    public const MANUAL = [
        'available' => ['repair'],
        'repair' => ['available'],
        'lost' => ['available', 'repair', 'assigned'],
        'disposed' => ['available', 'repair', 'lost'],
    ];

    public function assign(Asset $asset, User $employee, array $data, bool $notify = true): AssetAssignment
    {
        if ($asset->status === 'assigned') {
            $holder = $asset->currentAssignment?->user?->name;

            throw new RuntimeException($asset->name . ' is already with ' . ($holder ?: 'an employee') . '. Take it back first.');
        }

        if ($asset->status !== 'available') {
            throw new RuntimeException($asset->name . ' is ' . strtolower($asset->statusLabel()) . ' and cannot be assigned.');
        }

        if ($employee->status !== 'active') {
            throw new RuntimeException($employee->name . ' is not an active employee.');
        }

        $assignment = DB::transaction(function () use ($asset, $employee, $data) {
            $assignment = AssetAssignment::create([
                'company_id' => $asset->company_id,
                'asset_id' => $asset->id,
                'user_id' => $employee->id,
                'assigned_on' => $data['assigned_on'],
                'expected_return_on' => $data['expected_return_on'] ?? null,
                'condition_out' => $data['condition'] ?? $asset->condition,
                'assign_notes' => $data['notes'] ?? null,
                'assigned_by' => user()?->id,
            ]);

            $this->log($asset, 'assigned', 'assigned', $employee->id, $data['notes'] ?? null);
            $asset->update(['status' => 'assigned', 'condition' => $data['condition'] ?? $asset->condition]);

            return $assignment;
        });

        $this->syncExitClearance($employee->id);

        try {
            if ($notify) {
                $employee->notify(new AssetAssigned($assignment));
            }
        } catch (\Throwable $e) {
            // A mail problem must not undo the hand-over.
            logger()->warning('Asset assigned notification failed: ' . $e->getMessage());
        }

        return $assignment;
    }

    /** $data: returned_on, condition, back_to (available|repair|lost), notes, recovery_amount */
    public function return(AssetAssignment $assignment, array $data): AssetAssignment
    {
        if ($assignment->returned_on) {
            throw new RuntimeException('This asset was already returned on ' . $assignment->returned_on->format(company()->date_format) . '.');
        }

        $backTo = $data['back_to'] ?? 'available';

        if (!in_array($backTo, ['available', 'repair', 'lost'], true)) {
            throw new RuntimeException('Choose where the asset goes back to.');
        }

        $asset = $assignment->asset;

        DB::transaction(function () use ($assignment, $asset, $data, $backTo) {
            $assignment->update([
                'returned_on' => $data['returned_on'],
                'condition_in' => $backTo === 'lost' ? 'lost' : ($data['condition'] ?? null),
                'return_notes' => $data['notes'] ?? null,
                'recovery_amount' => ($data['recovery_amount'] ?? null) ?: null,
                'returned_by' => user()?->id,
            ]);

            $notes = trim(($backTo === 'lost' ? 'Reported lost. ' : '') . ($data['notes'] ?? '')
                . (!empty($data['recovery_amount']) ? ' To recover: ' . number_format((float) $data['recovery_amount'], 2) : ''));

            $this->log($asset, $backTo === 'lost' ? 'lost' : 'returned', $backTo, $assignment->user_id, $notes ?: null);

            $asset->update([
                'status' => $backTo,
                'condition' => $backTo === 'lost' ? $asset->condition : ($data['condition'] ?? $asset->condition),
            ]);

            // Back for repair: the repair job starts now (Assets -> Service & Repairs).
            if ($backTo === 'repair') {
                $this->openRepair($asset, $data['returned_on'], null,
                    'Returned for repair by ' . ($assignment->user?->name ?? 'the employee') . ($data['notes'] ?? '' ? ': ' . $data['notes'] : ''));
            }
        });

        $this->syncExitClearance($assignment->user_id);
        app(AssetUpkeep::class)->refreshDue($asset);

        return $assignment->fresh();
    }

    /** Start a repair job without touching the status (the caller sets it). */
    protected function openRepair(Asset $asset, string $sentOn, ?string $vendor, ?string $notes): void
    {
        if ($asset->openService()->exists()) {
            return;
        }

        AssetService::create([
            'company_id' => $asset->company_id,
            'asset_id' => $asset->id,
            'kind' => 'repair',
            'vendor' => $vendor ?: null,
            'sent_on' => $sentOn,
            'notes' => $notes ?: null,
            'created_by' => user()?->id,
        ]);
    }

    /**
     * $extra (optional): vendor (into repair), completed_on and cost (out of repair).
     */
    public function changeStatus(Asset $asset, string $status, ?string $notes = null, array $extra = []): void
    {
        $from = self::MANUAL[$status] ?? null;

        if ($from === null || !in_array($asset->status, $from, true)) {
            throw new RuntimeException('An asset that is ' . strtolower($asset->statusLabel()) . ' cannot be marked '
                . strtolower(Asset::STATUSES[$status][0] ?? $status) . '.');
        }

        // An asset somebody holds is closed as lost against them.
        if ($asset->status === 'assigned' && $assignment = $asset->currentAssignment) {
            $this->return($assignment, ['returned_on' => now()->toDateString(), 'back_to' => 'lost', 'notes' => $notes]);

            return;
        }

        DB::transaction(function () use ($asset, $status, $notes, $extra) {
            // Into repair opens the repair job; out of it closes the job with
            // the date and the cost, so Service & Repairs keeps every repair.
            if ($status === 'repair') {
                $this->openRepair($asset, $extra['sent_on'] ?? now()->toDateString(), $extra['vendor'] ?? null, $notes);
            } elseif ($asset->status === 'repair' && $open = $asset->openService()->where('kind', 'repair')->first()) {
                $open->update([
                    'completed_on' => $extra['completed_on'] ?? now()->toDateString(),
                    'cost' => ($extra['cost'] ?? '') !== '' && ($extra['cost'] ?? null) !== null ? $extra['cost'] : $open->cost,
                    'completed_by' => user()?->id,
                ]);
            }

            $cost = ($extra['cost'] ?? '') !== '' && ($extra['cost'] ?? null) !== null ? ' Cost ' . number_format((float) $extra['cost'], 2) . '.' : '';
            $this->log($asset, $status === 'available' ? 'repaired' : $status, $status, null, trim(($notes ?? '') . $cost) ?: null);
            $asset->update(['status' => $status]);
        });

        app(AssetUpkeep::class)->refreshDue($asset);
    }

    public function log(Asset $asset, string $action, ?string $statusTo = null, ?int $userId = null, ?string $notes = null): void
    {
        AssetHistory::create([
            'company_id' => $asset->company_id,
            'asset_id' => $asset->id,
            'user_id' => $userId,
            'action' => $action,
            'status_from' => $statusTo ? $asset->status : null,
            'status_to' => $statusTo,
            'notes' => $notes,
            'done_by' => user()?->id,
        ]);
    }

    /** Assets the employee still holds. */
    public static function heldBy(int $userId)
    {
        return AssetAssignment::withoutGlobalScopes()->with('asset.type')
            ->where('user_id', $userId)->whereNull('returned_on')->orderBy('assigned_on')->get();
    }

    /**
     * Asset Clearance on the employee's exit in progress follows what they hold.
     * A clearance waived by hand is left alone.
     */
    public function syncExitClearance(int $userId): void
    {
        $exit = EmployeeExit::openFor($userId);

        if (!$exit || $exit->asset_clearance_status === 'waived') {
            return;
        }

        $held = AssetAssignment::withoutGlobalScopes()->where('user_id', $userId)->whereNull('returned_on')->count();
        $status = $held ? 'pending' : 'completed';

        if ($exit->asset_clearance_status !== $status) {
            $exit->update(['asset_clearance_status' => $status]);
        }
    }

}
