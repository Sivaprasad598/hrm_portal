<?php

namespace App\Services\Iapp\Sync;

use App\Models\IappDevice;
use App\Models\IappSyncLog;
use App\Services\Iapp\DeviceEndpoint;
use App\Services\Iapp\IappClient;
use App\Services\Iapp\IappConfig;
use Illuminate\Support\Facades\Log;

/**
 * Pulls the controller/reader list into iapp_devices.
 *
 * getdevices returns one row per *gate*, so a controller with four doors
 * arrives as four rows sharing a controllerCode. Rows are keyed on
 * controller + gate to preserve that; counting distinct controllers is what
 * gives the device total shown in iApp's own device screen.
 */
class DeviceSync
{

    protected DeviceEndpoint $endpoint;

    public function __construct(
        protected IappClient $client,
        ?DeviceEndpoint $endpoint = null
    ) {
        $this->endpoint = $endpoint ?? new DeviceEndpoint($client);
    }

    public function config(): IappConfig
    {
        return $this->client->config();
    }

    /**
     * @param  array<int, string>|null  $locationCodes  defaults to the configured
     *         location, or every location already seen in the punch feed.
     */
    public function run(?array $locationCodes = null, string $trigger = 'manual', ?int $userId = null): IappSyncLog
    {
        $timer = microtime(true);

        $log = new IappSyncLog([
            'sync_type' => IappSyncLog::TYPE_DEVICES,
            'trigger' => $trigger,
            'triggered_by' => $userId,
            'status' => IappSyncLog::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        $log->company_id = $this->config()->companyId();
        $log->save();

        $fetched = 0;
        $created = 0;
        $updated = 0;
        $failures = [];

        try {
            if (!$this->config()->isConfigured()) {
                throw new \RuntimeException('iApp connection is not configured.');
            }

            $companyCode = $this->config()->companyCode();

            if (!$companyCode) {
                throw new \RuntimeException('No iApp company code configured.');
            }

            $locations = $locationCodes ?? $this->resolveLocations();

            if ($locations === []) {
                throw new \RuntimeException('No iApp location code configured or discoverable.');
            }

            foreach ($locations as $location) {
                $response = $this->endpoint->forLocation($companyCode, $location);

                if (!$response->successful()) {
                    // A location with no devices is not a failure of the run.
                    if (!$response->isEmptyResult()) {
                        $failures[] = $location . ': ' . $response->message();
                    }

                    continue;
                }

                foreach ($response->rows() as $row) {
                    $fetched++;
                    $attributes = DeviceEndpoint::normalise($row, $location);

                    if ($attributes['controller_code'] === '') {
                        continue;
                    }

                    $device = IappDevice::updateOrCreate(
                        [
                            'company_id' => $log->company_id,
                            'location_code' => $location,
                            'controller_code' => $attributes['controller_code'],
                            'gate_key' => $attributes['gate_key'],
                        ],
                        $attributes + ['synced_at' => now()]
                    );

                    $device->wasRecentlyCreated ? $created++ : $updated++;
                }
            }

            $deviceCount = IappDevice::where('company_id', $log->company_id)
                ->distinct()
                ->count('controller_code');

            $log->fill([
                'records_fetched' => $fetched,
                'records_created' => $created,
                'records_updated' => $updated,
                'status' => $failures === [] ? IappSyncLog::STATUS_SUCCESS : IappSyncLog::STATUS_PARTIAL,
                'message' => sprintf(
                    '%d devices across %d gates in %d location(s).%s',
                    $deviceCount,
                    $fetched,
                    count($locations),
                    $failures ? ' Issues: ' . implode('; ', $failures) : ''
                ),
            ]);
        } catch (\Throwable $e) {
            Log::error('iApp device sync failed', ['message' => $e->getMessage()]);

            $log->fill([
                'records_fetched' => $fetched,
                'status' => IappSyncLog::STATUS_FAILED,
                'message' => $e->getMessage(),
            ]);
        }

        $log->finished_at = now();
        $log->duration_ms = (int) round((microtime(true) - $timer) * 1000);
        $log->save();

        return $log;
    }

    /**
     * Locations to sync.
     *
     * getdevices needs an explicit location, and iApp exposes no
     * list-locations endpoint, so fall back to the distinct locations already
     * observed in the punch feed — which is how the second site was found here.
     *
     * @return array<int, string>
     */
    protected function resolveLocations(): array
    {
        $configured = $this->config()->locationCode();

        if ($configured) {
            return [$configured];
        }

        return \DB::table('iapp_device_transactions')
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(raw_payload, '$.locationCode')) AS loc")
            ->whereNotNull('raw_payload')
            ->distinct()
            ->pluck('loc')
            ->filter(fn ($l) => is_string($l) && $l !== '' && $l !== 'null')
            ->values()
            ->all();
    }

}
