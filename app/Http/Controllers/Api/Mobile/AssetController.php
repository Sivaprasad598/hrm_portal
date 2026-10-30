<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Models\AssetAssignment;
use App\Services\Assets\AssetFlow;

/**
 * The assets the signed-in employee holds.
 *
 * The list is what they hold now; one asset can be opened if it is, or ever
 * was, assigned to them - with their own assignments of it, no one else's.
 */
class AssetController extends MobileController
{
    public function index()
    {
        $held = AssetFlow::heldBy($this->me()->id);

        return $this->ok($held->map(fn ($assignment) => $this->summary($assignment))->values());
    }

    public function show(int $asset)
    {
        $mine = AssetAssignment::withoutGlobalScopes()->with('asset.type')
            ->where('user_id', $this->me()->id)->where('asset_id', $asset)
            ->orderByDesc('assigned_on')->get();

        abort_if($mine->isEmpty() || !$mine->first()->asset, 404);

        $item = $mine->first()->asset;
        $current = $mine->firstWhere('returned_on', null);

        return $this->ok([
            'id' => $item->id,
            'name' => $item->name,
            'asset_code' => $item->asset_code,
            'type' => $item->type?->name,
            'brand' => $item->brand,
            'model' => $item->model,
            'serial_number' => $item->serial_number,
            'condition' => $item->condition,
            'status' => $item->status,
            'warranty_until' => $this->day($item->warranty_until),
            'next_service_on' => $this->day($item->next_service_on),
            'image_url' => $item->image_url,
            'notes' => $item->notes,
            'held_by_me' => $current !== null,
            'current_assignment' => $current ? $this->assignment($current) : null,
            'my_assignments' => $mine->map(fn ($a) => $this->assignment($a))->values(),
        ]);
    }

    private function summary(AssetAssignment $assignment): array
    {
        $item = $assignment->asset;

        return [
            'id' => $item?->id,
            'name' => $item?->name,
            'asset_code' => $item?->asset_code,
            'type' => $item?->type?->name,
            'brand' => $item?->brand,
            'model' => $item?->model,
            'serial_number' => $item?->serial_number,
            'image_url' => $item?->image_url,
            'assigned_on' => $this->day($assignment->assigned_on),
            'expected_return_on' => $this->day($assignment->expected_return_on),
        ];
    }

    private function assignment(AssetAssignment $a): array
    {
        return [
            'assigned_on' => $this->day($a->assigned_on),
            'expected_return_on' => $this->day($a->expected_return_on),
            'condition_at_issue' => $a->condition_out,
            'issue_notes' => $a->assign_notes,
            'returned_on' => $this->day($a->returned_on),
            'condition_at_return' => $a->condition_in,
            'return_notes' => $a->return_notes,
        ];
    }

    private function day($value): ?string
    {
        return $value ? \Carbon\Carbon::parse($value)->toDateString() : null;
    }
}
