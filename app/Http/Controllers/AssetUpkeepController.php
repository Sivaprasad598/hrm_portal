<?php

namespace App\Http\Controllers;

use App\Helper\Files;
use App\Helper\Reply;
use App\Models\Asset;
use App\Models\AssetRenewal;
use App\Models\AssetService;
use App\Services\Assets\AssetUpkeep;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Assets: servicing, repairs and renewals (AMC, insurance, extended warranty,
 * vehicle RC / PUC, licences). Same access as the rest of Assets.
 */
class AssetUpkeepController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Assets';

        $this->middleware(function ($request, $next) {
            abort_403(user()->permission('manage_assets') !== 'all');

            return $next($request);
        });
    }

    /** Log a service / repair / inspection. */
    public function serviceForm($assetId)
    {
        $this->asset = Asset::with('openService')->findOrFail($assetId);

        return $this->panel('assets.ajax.service', 'Log Service / Repair');
    }

    public function serviceStore(Request $request, $assetId, AssetUpkeep $upkeep)
    {
        $request->validate([
            'kind' => ['required', Rule::in(array_keys(AssetService::KINDS))],
            'sent_on' => 'required',
            'vendor' => 'nullable|string|max:191',
            'reference' => 'nullable|string|max:191',
            'cost' => 'nullable|numeric|min:0',
            'bill' => 'nullable|file|max:10240',
        ]);

        $asset = Asset::with('openService')->findOrFail($assetId);

        try {
            $upkeep->logService($asset, [
                'kind' => $request->kind,
                'vendor' => $request->vendor,
                'reference' => $request->reference,
                'sent_on' => $this->date($request->sent_on, 'sent date'),
                'completed_on' => $request->completed_on ? $this->date($request->completed_on, 'completed date') : null,
                'cost' => $request->cost,
                'notes' => $request->notes,
                'next_service_on' => $request->next_service_on ? $this->date($request->next_service_on, 'next service date') : null,
                'bill' => $this->upload($request, 'bill'),
            ]);
        } catch (RuntimeException $e) {
            return Reply::error($e->getMessage());
        }

        return Reply::success(AssetService::KINDS[$request->kind] . ' recorded.');
    }

    /** Close an open service / repair. */
    public function completeForm($serviceId)
    {
        $this->service = AssetService::with('asset')->whereNull('completed_on')->findOrFail($serviceId);
        $this->asset = $this->service->asset;

        return $this->panel('assets.ajax.service-complete', 'Complete ' . $this->service->kindLabel());
    }

    public function complete(Request $request, $serviceId, AssetUpkeep $upkeep)
    {
        $request->validate([
            'completed_on' => 'required',
            'cost' => 'nullable|numeric|min:0',
            'bill' => 'nullable|file|max:10240',
        ]);

        $service = AssetService::with('asset')->findOrFail($serviceId);

        try {
            $upkeep->completeService($service, [
                'completed_on' => $this->date($request->completed_on, 'completed date'),
                'cost' => $request->cost,
                'notes' => $request->notes,
                'next_service_on' => $request->next_service_on ? $this->date($request->next_service_on, 'next service date') : null,
                'bill' => $this->upload($request, 'bill'),
            ]);
        } catch (RuntimeException $e) {
            return Reply::error($e->getMessage());
        }

        return Reply::success($service->kindLabel() . ' completed.');
    }

    /** Add a renewal, or renew one (?renew=<id>: the next period, prefilled). */
    public function renewalForm(Request $request, $assetId)
    {
        $this->asset = Asset::findOrFail($assetId);
        $this->previous = $request->renew
            ? AssetRenewal::where('asset_id', $this->asset->id)->findOrFail($request->renew)
            : null;

        // The next period runs on from the last one, for as long as it did.
        if ($this->previous) {
            $start = $this->previous->expires_on->copy()->addDay();
            $months = $this->previous->starts_on
                ? max(1, (int) round($this->previous->starts_on->diffInMonths($this->previous->expires_on->copy()->addDay())))
                : 12;
            $this->suggestStart = $start;
            $this->suggestEnd = $start->copy()->addMonthsNoOverflow($months)->subDay();
        } else {
            $this->suggestStart = null;
            $this->suggestEnd = null;
        }

        return $this->panel('assets.ajax.renewal', $this->previous ? 'Renew ' . $this->previous->kindLabel() : 'Add Renewal');
    }

    public function renewalStore(Request $request, $assetId, AssetUpkeep $upkeep)
    {
        $request->validate([
            'kind' => ['required_without:previous_id', 'nullable', Rule::in(array_keys(AssetRenewal::KINDS))],
            'expires_on' => 'required',
            'provider' => 'nullable|string|max:191',
            'reference_no' => 'nullable|string|max:191',
            'cost' => 'nullable|numeric|min:0',
            'document' => 'nullable|file|max:10240',
        ], ['kind.required_without' => 'Choose what is being renewed.']);

        $asset = Asset::findOrFail($assetId);
        $previous = $request->previous_id ? AssetRenewal::where('asset_id', $asset->id)->findOrFail($request->previous_id) : null;

        try {
            $renewal = $upkeep->addRenewal($asset, [
                'kind' => $request->kind,
                'provider' => $request->provider,
                'reference_no' => $request->reference_no,
                'starts_on' => $request->starts_on ? $this->date($request->starts_on, 'start date') : null,
                'expires_on' => $this->date($request->expires_on, 'expiry date'),
                'cost' => $request->cost,
                'notes' => $request->notes,
                'document' => $this->upload($request, 'document'),
            ], $previous);
        } catch (RuntimeException $e) {
            return Reply::error($e->getMessage());
        }

        return Reply::success($renewal->kindLabel() . ($previous ? ' renewed' : ' added') . ' until '
            . $renewal->expires_on->format(company()->date_format) . '.');
    }

    public function renewalDestroy($renewalId, AssetUpkeep $upkeep)
    {
        $renewal = AssetRenewal::with('asset')->findOrFail($renewalId);

        try {
            $upkeep->deleteRenewal($renewal);
        } catch (RuntimeException $e) {
            return Reply::error($e->getMessage());
        }

        if ($renewal->document) {
            Files::deleteFile($renewal->document, AssetService::FILE_PATH);
        }

        return Reply::success('Renewal removed.');
    }

    private function upload(Request $request, string $field): ?string
    {
        return $request->hasFile($field) ? Files::uploadLocalOrS3($request->file($field), AssetService::FILE_PATH) : null;
    }

    /** A date typed in the company format, as Y-m-d. */
    private function date(string $value, string $label): string
    {
        try {
            return Carbon::createFromFormat(company()->date_format, $value)->toDateString();
        } catch (\Throwable $e) {
            abort(response()->json(['status' => 'fail', 'message' => 'The ' . $label . ' is not a valid date.', 'errors' => []], 422));
        }
    }

    private function panel(string $view, string $title)
    {
        $this->pageTitle = $title;
        $this->view = $view;

        if (request()->ajax()) {
            return Reply::dataOnly(['status' => 'success', 'html' => view($view, $this->data)->render(), 'title' => $title]);
        }

        return view('assets.page', $this->data);
    }

}
